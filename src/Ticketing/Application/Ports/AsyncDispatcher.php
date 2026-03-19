<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface AsyncDispatcher
{
    public function dispatch(string $reservationId): void;
}
