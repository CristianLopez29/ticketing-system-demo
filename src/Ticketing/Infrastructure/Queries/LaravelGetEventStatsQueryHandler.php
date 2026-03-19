<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Application\Queries\GetEventStatsQuery;
use Src\Ticketing\Application\Queries\GetEventStatsQueryHandler;

class LaravelGetEventStatsQueryHandler implements GetEventStatsQueryHandler
{
    public function handle(GetEventStatsQuery $query): mixed
    {
        $dbSold = DB::table('seats')
            ->where('event_id', $query->eventId)
            ->whereNotNull('reserved_by_user_id')
            ->count();

        $totalSeats = DB::table('seats')
            ->where('event_id', $query->eventId)
            ->count();

        $redisStockValue = Redis::get("event:{$query->eventId}:stock");
        $redisStock = is_numeric($redisStockValue) ? (int) $redisStockValue : 0;

        $ticketsIssued = DB::table('tickets')
            ->where('event_id', $query->eventId)
            ->count();

        $reservationsPending = DB::table('reservations')
            ->where('event_id', $query->eventId)
            ->where('status', 'pending_payment')
            ->count();

        return [
            'total_seats' => $totalSeats,
            'sold_seats_db' => $dbSold,
            'available_stock_redis' => $redisStock,
            'tickets_issued' => $ticketsIssued,
            'reservations_pending' => $reservationsPending,
            'integrity_check' => ($dbSold + $redisStock) === $totalSeats ? 'OK' : 'MISMATCH',
        ];
    }
}
