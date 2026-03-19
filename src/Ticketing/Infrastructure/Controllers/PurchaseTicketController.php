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
