<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class EventController
{
    public function getSeats(int $eventId): JsonResponse
    {
        // Projection (Read Model): Optimized query bypassing Domain Entities for performance
        $seats = DB::table('seats')
            ->where('event_id', $eventId)
            ->select(['id', 'row', 'number', 'price_amount', 'price_currency', 'reserved_by_user_id'])
            ->orderBy('row')
            ->orderBy('number')
            ->get()
            ->map(function ($seat) {
                return [
                    'id' => $seat->id,
                    'row' => $seat->row,
                    'number' => $seat->number,
                    'price' => [
                        'amount' => $seat->price_amount,
                        'currency' => $seat->price_currency,
                    ],
                    'status' => $seat->reserved_by_user_id ? 'sold' : 'available',
                ];
            });

        return new JsonResponse($seats);
    }

    public function getStats(int $eventId): JsonResponse
    {
        $dbSold = DB::table('seats')
            ->where('event_id', $eventId)
            ->whereNotNull('reserved_by_user_id')
            ->count();

        $totalSeats = DB::table('seats')
            ->where('event_id', $eventId)
            ->count();

        $redisStock = Redis::get("event:{$eventId}:stock");

        $ticketsIssued = DB::table('tickets')
            ->where('event_id', $eventId)
            ->count();

        $reservationsPending = DB::table('reservations')
            ->where('event_id', $eventId)
            ->where('status', 'pending_payment')
            ->count();

        return new JsonResponse([
            'total_seats' => $totalSeats,
            'sold_seats_db' => $dbSold,
            'available_stock_redis' => (int) $redisStock,
            'tickets_issued' => $ticketsIssued,
            'reservations_pending' => $reservationsPending,
            'integrity_check' => ($dbSold + (int)$redisStock) === $totalSeats ? 'OK' : 'MISMATCH',
        ]);
    }
}
