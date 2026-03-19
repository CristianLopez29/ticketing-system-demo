<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Queries;

readonly class GetEventStatsQuery
{
    public function __construct(
        public int $eventId
    ) {}
}
