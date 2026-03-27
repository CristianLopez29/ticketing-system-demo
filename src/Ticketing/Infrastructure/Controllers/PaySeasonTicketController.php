<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Ticketing\Application\UseCases\PaySeasonTicketUseCase;
use Symfony\Component\HttpFoundation\Response;

class PaySeasonTicketController
{
    public function __construct(
        private readonly PaySeasonTicketUseCase $useCase
    ) {}

    /**
     * @OA\Post(
     *     path="/api/season-tickets/{id}/pay",
     *     summary="Confirm payment for a season ticket",
     *     description="Transitions a `pending_payment` season ticket to `paid` status and emits a `SeasonTicketPaid` domain event. Must be called by the authenticated user who owns the ticket.",
     *     tags={"Season Tickets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Season ticket UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment confirmed — season ticket is now paid",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",          type="string", example="Season ticket payment confirmed."),
     *             @OA\Property(property="season_ticket_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="status",           type="string", example="paid")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Season ticket not found",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Season ticket not found: xxx"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=409, description="Season ticket is not in pending_payment status",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Cannot pay for a non-pending season ticket."))
     *     )
     * )
     */
    public function __invoke(string $id): JsonResponse
    {
        $seasonTicket = $this->useCase->execute($id);

        return new JsonResponse([
            'message'          => 'Season ticket payment confirmed.',
            'season_ticket_id' => $seasonTicket->id(),
            'status'           => $seasonTicket->status()->value,
        ], Response::HTTP_OK);
    }
}
