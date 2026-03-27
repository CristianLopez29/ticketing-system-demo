<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Application\Queries\GetEventSeatsQuery;
use Src\Ticketing\Application\Queries\GetEventSeatsQueryHandler;

class LaravelGetEventSeatsQueryHandler implements GetEventSeatsQueryHandler
{
    private const CACHE_TTL_SECONDS = 300;

    public function handle(GetEventSeatsQuery $query): mixed
    {
        $cacheKey = "seats_read_model:after:{$query->afterSeatId}:per:{$query->perPage}";

        return Cache::tags(['event-seats', "event:{$query->eventId}"])
            ->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query) {
                $seats = DB::table('seats')
                    ->where('event_id', $query->eventId)
                    ->when($query->afterSeatId > 0, fn ($q) => $q->where('id', '>', $query->afterSeatId))
                    ->select(['id', 'row', 'number', 'price_amount', 'price_currency', 'reserved_by_user_id'])
                    ->orderBy('row')
                    ->orderBy('number')
                    ->limit($query->perPage)
                    ->get()
                    ->map(fn ($seat) => [
                        'id'     => $seat->id,
                        'row'    => $seat->row,
                        'number' => $seat->number,
                        'price'  => [
                            'amount'   => $seat->price_amount,
                            'currency' => $seat->price_currency,
                        ],
                        'status' => $seat->reserved_by_user_id ? 'sold' : 'available',
                    ])
                    ->all();

                $nextCursor = count($seats) === $query->perPage
                    ? end($seats)['id'] ?? null
                    : null;

                return [
                    'data'        => $seats,
                    'next_cursor' => $nextCursor,
                ];
            });
    }
}
