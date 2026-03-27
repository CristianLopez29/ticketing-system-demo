<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Ticketing\Application\DTOs\PurchaseTicketRequestDTO;
use Src\Ticketing\Application\UseCases\PurchaseTicketUseCase;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Symfony\Component\HttpFoundation\Response;

class PurchaseTicketController
{
    public function __construct(
        private readonly PurchaseTicketUseCase $useCase
    ) {}

    /**
     * @OA\Post(
     *     path="/api/tickets/purchase",
     *     summary="Purchase a ticket for a single event",
     *     description="Reserves a seat and queues an async payment job. Idempotent: repeated calls with the same Idempotency-Key return the original reservation.",
     *     tags={"Tickets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         required=true,
     *         description="Unique client-generated key to prevent duplicate purchases (UUID recommended).",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id","seat_id"},
     *             @OA\Property(property="event_id", type="integer", example=1,  description="ID of the event"),
     *             @OA\Property(property="seat_id",  type="integer", example=42, description="ID of the seat to purchase")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Purchase accepted — payment processing queued",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",        type="string", example="Purchase processing started. You will receive a confirmation shortly."),
     *             @OA\Property(property="reservation_id", type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Missing or invalid parameters",
     *         @OA\JsonContent(@OA\Property(property="error", type="string"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Seat already sold or duplicate request",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Seat already sold."))
     *     )
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return new JsonResponse([
                'error' => 'Idempotency-Key header is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'seat_id' => 'required|integer',
        ]);

        $user = $request->user();
        if (! $user) {
            return new JsonResponse([
                'error' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $dto = new PurchaseTicketRequestDTO(
            (int) $validated['event_id'],
            new SeatId((int) $validated['seat_id']),
            (int) $user->id,
            $idempotencyKey
        );

        $reservationId = $this->useCase->execute($dto);

        return new JsonResponse([
            'message' => 'Purchase processing started. You will receive a confirmation shortly.',
            'reservation_id' => $reservationId,
        ], Response::HTTP_ACCEPTED);
    }
}
