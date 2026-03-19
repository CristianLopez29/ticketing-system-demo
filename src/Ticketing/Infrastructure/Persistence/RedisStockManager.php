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

    public function __construct(
        private readonly SeatRepository $seatRepository
    ) {}

    public function attemptToReserve(int $eventId): bool
    {
        $key = "event:{$eventId}:stock";

        // Re-hydrate from DB if key is absent (Redis restart / key eviction)
        if (Redis::get($key) === null) {
            $lockKey = "lock:rehydrate:{$eventId}";
            // Only one worker re-hydrates; others wait for the key to appear
            if (Redis::set($lockKey, '1', ['nx', 'ex' => 5])) {
                try {
                    $this->rehydrateStockFromDatabase($eventId, $key);
                } finally {
                    Redis::del($lockKey);
                }
            } else {
                $maxAttempts = 30; // Increased attempts
                for ($i = 0; $i < $maxAttempts; $i++) {
                    usleep(100_000); // 100ms
                    if (Redis::get($key) !== null) {
                        break;
                    }
                }

                if (Redis::get($key) === null) {
                    $this->rehydrateStockFromDatabase($eventId, $key);
                }
            }
        }

        // Atomic Lua execution
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

    private function rehydrateStockFromDatabase(int $eventId, string $key): void    {
        $availableSeats = $this->seatRepository->countAvailableForEvent($eventId);

        // SET NX (only set if not exists) to avoid overwriting a concurrent re-hydration
        Redis::setnx($key, $availableSeats);
    }
}
