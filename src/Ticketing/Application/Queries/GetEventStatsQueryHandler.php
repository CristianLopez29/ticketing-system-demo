<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Queries;

interface GetEventStatsQueryHandler
{
    public function handle(GetEventStatsQuery $query): mixed;
}
