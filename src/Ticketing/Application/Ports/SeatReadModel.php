<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface SeatReadModel
{
    public function countAvailableForEvent(int $eventId): int;
}
