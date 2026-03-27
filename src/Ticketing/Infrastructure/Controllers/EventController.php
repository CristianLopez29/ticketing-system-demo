<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * @OA\Get(
     *     path="/api/events/{id}/seats",
     *     summary="List seat availability for an event",
     *     description="Returns a paginated cursor-based list of seats with their availability status. Use `next_cursor` as the `after_seat_id` parameter on the next request to load more. Results are cached for 5 minutes.",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="after_seat_id",
     *         in="query",
     *         required=false,
     *         description="Cursor: fetch seats with ID greater than this value (for pagination)",
     *         @OA\Schema(type="integer", example=0)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of seats per page (1–500, default 100)",
     *         @OA\Schema(type="integer", example=100, minimum=1, maximum=500)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of seats",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",     type="integer", example=42),
     *                     @OA\Property(property="row",    type="string",  example="A"),
     *                     @OA\Property(property="number", type="integer", example=1),
     *                     @OA\Property(property="price",  type="object",
     *                         @OA\Property(property="amount",   type="integer", example=5000),
     *                         @OA\Property(property="currency", type="string",  example="EUR")
     *                     ),
     *                     @OA\Property(property="status", type="string", enum={"available","sold"}, example="available")
     *                 )
     *             ),
     *             @OA\Property(property="next_cursor", type="integer", nullable=true, example=142,
     *                 description="Pass this as after_seat_id to fetch the next page; null means no more pages")
     *         )
     *     )
     * )
     */
    public function getSeats(int $eventId, Request $request): JsonResponse
    {
        $afterSeatId = max(0, (int) $request->query('after_seat_id', 0));
        $perPage     = min(500, max(1, (int) $request->query('per_page', 100)));

        $result = $this->seatsHandler->handle(
            new GetEventSeatsQuery($eventId, $afterSeatId, $perPage)
        );

        return new JsonResponse($result);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{id}/stats",
     *     summary="Event statistics (admin)",
     *     description="Returns real-time stock counters from Redis alongside sold-seat counts from the database. Includes an integrity check flag. Rate limited: 10 requests/minute per admin.",
     *     tags={"Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_seats",          type="integer", example=100),
     *             @OA\Property(property="sold_seats_db",        type="integer", example=42),
     *             @OA\Property(property="available_stock_redis",type="integer", example=58),
     *             @OA\Property(property="integrity_check",      type="string", enum={"OK","DRIFT"}, example="OK")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden — requires admin role"),
     *     @OA\Response(response=429, description="Rate limit exceeded (10 req/min)")
     * )
     */
    public function getStats(int $eventId): JsonResponse
    {
        $stats = $this->statsHandler->handle(new GetEventStatsQuery($eventId));

        return new JsonResponse($stats);
    }
}
