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

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'season_id' => 'required|integer',
            'row' => 'required|string',
            'number' => 'required|integer',
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
            'id' => $seasonTicket->id(),
            'status' => $seasonTicket->status()->value,
            'price' => [
                'amount' => $seasonTicket->price()->amount(),
                'currency' => $seasonTicket->price()->currency(),
            ],
            'expires_at' => $seasonTicket->expiresAt()?->format(DATE_ATOM),
            'message' => 'Season ticket reserved successfully. Please proceed to payment.',
        ], 201);
    }
}
