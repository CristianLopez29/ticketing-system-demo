<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Listeners;

use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Domain\Events\TicketSold;

class InvalidateSeatsCacheOnTicketSold
{
    public function handle(TicketSold $event): void
    {
        Cache::tags(["event:{$event->eventId}"])->flush();
    }
}
