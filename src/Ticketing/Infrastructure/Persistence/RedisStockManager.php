<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Repositories\StockManager;

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

    public function attemptToReserve(int $eventId): bool
    {
        $key = "event:{$eventId}:stock";

        // Re-hydrate from DB if key is absent (Redis restart / key eviction)
        if ((int) Redis::exists($key) === 0) {
            $lockKey = "lock:rehydrate:{$eventId}";
            // Only one worker re-hydrates; others wait for the key to appear
            if (Redis::set($lockKey, '1', ['nx', 'ex' => 5])) {
                try {
                    $this->rehydrateStockFromDatabase($eventId, $key);
                } finally {
                    Redis::del($lockKey);
                }
            } else {
                $maxAttempts = 10;
                for ($i = 0; $i < $maxAttempts; $i++) {
                    usleep(30_000);
                }
            }
        }

        // Atomic Lua execution
        $result = Redis::connection()->command('eval', [self::SCRIPT, [$key], 1]);

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

    private function rehydrateStockFromDatabase(int $eventId, string $key): void
    {
        $availableSeats = DB::table('seats')
            ->where('event_id', $eventId)
            ->whereNull('reserved_by_user_id')
            ->count();

        // SET NX (only set if not exists) to avoid overwriting a concurrent re-hydration
        Redis::setnx($key, $availableSeats);
    }
}
