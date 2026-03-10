<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Throwable;

class PurchaseSeasonTicketController
{
    public function __invoke(Request $request, PurchaseSeasonTicketUseCase $useCase): JsonResponse
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

        $seasonIdValue = $validated['season_id'] ?? null;
        $seasonId = filter_var($seasonIdValue, FILTER_VALIDATE_INT);
        if ($seasonId === false) {
            return new JsonResponse(['error' => 'Invalid season_id'], 422);
        }

        $numberValue = $validated['number'] ?? null;
        $number = filter_var($numberValue, FILTER_VALIDATE_INT);
        if ($number === false) {
            return new JsonResponse(['error' => 'Invalid number'], 422);
        }

        $rowValue = $validated['row'] ?? null;
        if (! is_string($rowValue)) {
            return new JsonResponse(['error' => 'Invalid row'], 422);
        }

        $idempotencyKeyValue = $validated['idempotency_key'] ?? null;
        if (! is_string($idempotencyKeyValue)) {
            return new JsonResponse(['error' => 'Invalid idempotency_key'], 422);
        }

        try {
            $dto = new PurchaseSeasonTicketRequestDTO(
                $seasonId,
                (int) $user->id,
                $rowValue,
                $number,
                $idempotencyKeyValue
            );

            $seasonTicket = $useCase->execute($dto);

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

        } catch (SeatAlreadySoldException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Season ticket purchase failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return new JsonResponse(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
