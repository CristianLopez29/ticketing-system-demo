<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReservationController
{
    public function show(string $id): JsonResponse
    {
        $reservation = DB::table('reservations')->where('id', $id)->first();

        if (! $reservation) {
            return new JsonResponse(['error' => 'Reservation not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $reservation->id,
            'event_id' => $reservation->event_id,
            'seat_id' => $reservation->seat_id,
            'user_id' => $reservation->user_id,
            'status' => $reservation->status,
            'price' => [
                'amount' => $reservation->price_amount,
                'currency' => $reservation->price_currency,
            ],
            'expires_at' => $reservation->expires_at,
            'created_at' => $reservation->created_at,
        ]);
    }
}
