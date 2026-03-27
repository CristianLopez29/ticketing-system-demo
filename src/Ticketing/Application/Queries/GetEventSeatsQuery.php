<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Queries;

readonly class GetEventSeatsQuery
{
    public function __construct(
        public int $eventId,
        public int $afterSeatId = 0,
        public int $perPage = 100,
    ) {}
}
