<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;

class PurchaseSeasonTicketController
{
    public function __construct(
        private readonly PurchaseSeasonTicketUseCase $useCase
    ) {}

    /**
     * @OA\Post(
     *     path="/api/season-tickets/purchase",
     *     summary="Reserve a season ticket",
     *     description="Reserves a seat across all events in a season and applies the season discount. Returns a season ticket in `pending_payment` status. Idempotent via `idempotency_key`.",
     *     tags={"Season Tickets"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"season_id","row","number","idempotency_key"},
     *             @OA\Property(property="season_id",       type="integer", example=1,            description="ID of the season"),
     *             @OA\Property(property="row",             type="string",  example="A",          description="Seat row identifier"),
     *             @OA\Property(property="number",          type="integer", example=12,           description="Seat number within the row"),
     *             @OA\Property(property="idempotency_key", type="string",  example="uuid-12345", description="Unique key to prevent duplicate reservations (UUID recommended)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Season ticket reserved — awaiting payment",
     *         @OA\JsonContent(
     *             @OA\Property(property="id",         type="string",  example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="status",     type="string",  example="pending_payment"),
     *             @OA\Property(property="price",      type="object",
     *                 @OA\Property(property="amount",   type="integer", example=8800),
     *                 @OA\Property(property="currency", type="string",  example="EUR")
     *             ),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2026-01-01T12:15:00+00:00"),
     *             @OA\Property(property="message",    type="string",  example="Season ticket reserved successfully. Please proceed to payment.")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request parameters including currency mismatch",
     *         @OA\JsonContent(@OA\Property(property="error", type="string"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Seat already sold, renewal window violation, or duplicate request",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Seat A-1 is already sold for event 3."))
     *     )
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'season_id'       => 'required|integer',
            'row'             => 'required|string',
            'number'          => 'required|integer',
            'idempotency_key' => 'required|string',
        ]);

        $user = $request->user();
        if (! $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $dto = new PurchaseSeasonTicketRequestDTO(
            (int) $validated['season_id'],
            (int) $user->id,
            (string) $validated['row'],
            (int) $validated['number'],
            (string) $validated['idempotency_key']
        );

        $seasonTicket = $this->useCase->execute($dto);

        return new JsonResponse([
            'id'         => $seasonTicket->id(),
            'status'     => $seasonTicket->status()->value,
            'price'      => [
                'amount'   => $seasonTicket->price()->amount(),
                'currency' => $seasonTicket->price()->currency(),
            ],
            'expires_at' => $seasonTicket->expiresAt()?->format(DATE_ATOM),
            'message'    => 'Season ticket reserved successfully. Please proceed to payment.',
        ], 201);
    }
}
