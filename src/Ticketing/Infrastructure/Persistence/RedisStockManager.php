<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Repositories\StockManager;

class RedisStockManager implements StockManager
{
    private const SCRIPT = <<<LUA
        local stock = redis.call('GET', KEYS[1])
        if (not stock or tonumber(stock) <= 0) then
            return 0
        end
        redis.call('DECR', KEYS[1])
        return 1
    LUA;

    /**
     * Atomically check and decrement stock.
     * Returns true if stock was available and decremented, false otherwise.
     *
     * If the Redis key is absent (e.g. after a Redis restart), the stock is
     * re-hydrated from MySQL before the atomic decrement is attempted.
     * A distributed lock prevents concurrent re-hydration stampedes.
     */
    public function attemptToReserve(int $eventId): bool
    {
        $key = "event:{$eventId}:stock";

        // Re-hydrate from DB if key is absent (Redis restart / key eviction)
        if (!Redis::exists($key)) {
            $lockKey = "lock:rehydrate:{$eventId}";
            // Only one worker re-hydrates; others wait for the key to appear
            if (Redis::set($lockKey, 1, 'EX', 5, 'NX')) {
                try {
                    $this->rehydrateStockFromDatabase($eventId, $key);
                } finally {
                    Redis::del($lockKey);
                }
            } else {
                // Another worker is re-hydrating; wait briefly for the key
                usleep(50000); // 50ms
            }
        }

        // Atomic Lua execution
        $result = Redis::eval(self::SCRIPT, 1, $key);

        return (bool) $result;
    }

    /**
     * Reverts a stock decrement operation.
     */
    public function revertReservation(int $eventId): void
    {
        Redis::incr("event:{$eventId}:stock");
    }

    /**
     * Initializes the stock for an event. Used during seeding or event creation.
     */
    public function setStock(int $eventId, int $stock): void
    {
        Redis::set("event:{$eventId}:stock", $stock);
    }

    /**
     * Queries MySQL for the number of available (unreserved) seats and writes
     * the result back to Redis, restoring a consistent state after a cache miss.
     */
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
