<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

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
     */
    public function attemptToReserve(int $eventId): bool
    {
        $key = "event:{$eventId}:stock";
        
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
}
