<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Ticketing\Application\Queries\GetEventSeatsQuery;
use Src\Ticketing\Application\Queries\GetEventSeatsQueryHandler;
use Src\Ticketing\Application\Queries\GetEventStatsQuery;
use Src\Ticketing\Application\Queries\GetEventStatsQueryHandler;

class EventController
{
    public function __construct(
        private readonly GetEventSeatsQueryHandler $seatsHandler,
        private readonly GetEventStatsQueryHandler $statsHandler
    ) {}

    public function getSeats(int $eventId): JsonResponse
    {
        $seats = $this->seatsHandler->handle(new GetEventSeatsQuery($eventId));

        return new JsonResponse($seats);
    }

    public function getStats(int $eventId): JsonResponse
    {
        $stats = $this->statsHandler->handle(new GetEventStatsQuery($eventId));

        return new JsonResponse($stats);
    }
}
