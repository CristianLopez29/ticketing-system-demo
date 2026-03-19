<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Jobs;

use Src\Ticketing\Application\Ports\AsyncDispatcher;

class LaravelAsyncDispatcher implements AsyncDispatcher
{
    public function dispatch(string $reservationId): void
    {
        ProcessTicketPayment::dispatch($reservationId);
    }
}
