<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence\Observers;

use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Infrastructure\Persistence\EventModel;

/**
 * Keeps the Redis stock counter in sync with the database.
 *
 * - On create: initialises stock from total_seats.
 * - On delete: removes the stock key to avoid stale data.
 */
class EventObserver
{
    public function created(EventModel $event): void
    {
        Redis::set("event:{$event->id}:stock", $event->total_seats);
    }

    public function deleted(EventModel $event): void
    {
        Redis::del("event:{$event->id}:stock");
    }
}
