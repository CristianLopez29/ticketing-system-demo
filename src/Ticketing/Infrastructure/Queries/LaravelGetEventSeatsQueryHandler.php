<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Src\Ticketing\Application\Queries\GetEventSeatsQuery;
use Src\Ticketing\Application\Queries\GetEventSeatsQueryHandler;

class LaravelGetEventSeatsQueryHandler implements GetEventSeatsQueryHandler
{
    public function handle(GetEventSeatsQuery $query): mixed
    {
        return DB::table('seats')
            ->where('event_id', $query->eventId)
            ->select(['id', 'row', 'number', 'price_amount', 'price_currency', 'reserved_by_user_id'])
            ->orderBy('row')
            ->orderBy('number')
            ->get()
            ->map(fn ($seat) => [
                'id' => $seat->id,
                'row' => $seat->row,
                'number' => $seat->number,
                'price' => [
                    'amount' => $seat->price_amount,
                    'currency' => $seat->price_currency,
                ],
                'status' => $seat->reserved_by_user_id ? 'sold' : 'available',
            ])
            ->all();
    }
}
