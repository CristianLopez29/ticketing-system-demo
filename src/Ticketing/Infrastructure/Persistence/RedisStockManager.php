<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Domain\Repositories\SeatRepository;

class RedisStockManager implements StockManager
{
    private const SCRIPT = <<<'LUA'
        local stock = redis.call('GET', KEYS[1])
        if (not stock or tonumber(stock) <= 0) then
            return 0
        end
        redis.call('DECR', KEYS[1])
        return 1
    LUA;

    /** Long enough to outlive a re-hydration query, short enough to free a dead holder's lock fast. */
    private const REHYDRATE_LOCK_TTL_SECONDS = 5;

    private const REHYDRATE_MAX_RETRIES = 3;

    private const REHYDRATE_RETRY_DELAY_MICROSECONDS = 50_000;

    public function __construct(
        private readonly SeatRepository $seatRepository
    ) {}

    public function attemptToReserve(int $eventId): bool
    {
        $key = "event:{$eventId}:stock";

        // Re-hydrate from DB if key is absent (Redis restart / key eviction)
        if (Redis::get($key) === null) {
            $this->ensureStockIsHydrated($eventId, $key);
        }

        // @phpstan-ignore-next-line (Laravel facade signature differs from raw phpredis stubs)
        $result = Redis::eval(self::SCRIPT, 1, $key);

        return (bool) $result;
    }

    public function revertReservation(int $eventId): void
    {
        Redis::incr("event:{$eventId}:stock");
    }

    public function setStock(int $eventId, int $stock): void
    {
        Redis::set("event:{$eventId}:stock", $stock);
    }

    /**
     * Only one worker re-hydrates the counter; the others wait for the key to appear.
     */
    private function ensureStockIsHydrated(int $eventId, string $key): void
    {
        $lockKey = "lock:rehydrate:{$eventId}";

        if ($this->rehydrateUnderLock($eventId, $key, $lockKey)) {
            return;
        }

        $this->awaitKey($key);

        // Still absent after the retry window: try to take the lock over. That
        // succeeds only once the holder's lock has expired, i.e. it died before
        // writing. While a live holder keeps it, the Lua script simply sees no
        // stock, which beats racing it with a second re-hydration.
        if (Redis::get($key) === null) {
            $this->rehydrateUnderLock($eventId, $key, $lockKey);
        }
    }

    /**
     * @return bool Whether this worker won the NX lock and re-hydrated the counter.
     */
    private function rehydrateUnderLock(int $eventId, string $key, string $lockKey): bool
    {
        // @phpstan-ignore-next-line (the facade takes EX/NX as separate arguments; the raw phpredis stubs do not declare them)
        if (! Redis::set($lockKey, '1', 'EX', self::REHYDRATE_LOCK_TTL_SECONDS, 'NX')) {
            return false;
        }

        try {
            $this->rehydrateStockFromDatabase($eventId, $key);
        } finally {
            Redis::del($lockKey);
        }

        return true;
    }

    /**
     * Give the lock-holder a bounded window to finish writing the key.
     */
    private function awaitKey(string $key): void
    {
        for ($attempt = 0; $attempt < self::REHYDRATE_MAX_RETRIES; $attempt++) {
            usleep(self::REHYDRATE_RETRY_DELAY_MICROSECONDS);

            if (Redis::get($key) !== null) {
                return;
            }
        }
    }

    private function rehydrateStockFromDatabase(int $eventId, string $key): void
    {
        $availableSeats = $this->seatRepository->countAvailableForEvent($eventId);

        // SET NX (only set if not exists) to avoid overwriting a concurrent re-hydration
        Redis::setnx($key, $availableSeats);
    }
}
