<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Queries;

interface GetEventSeatsQueryHandler
{
    public function handle(GetEventSeatsQuery $query): mixed;
}
