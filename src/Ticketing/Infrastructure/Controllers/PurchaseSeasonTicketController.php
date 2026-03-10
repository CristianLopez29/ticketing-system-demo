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
        $request->validate([
            'season_id' => 'required|integer',
            'row' => 'required|string',
            'number' => 'required|integer',
            'idempotency_key' => 'required|string',
        ]);

        try {
            $dto = new PurchaseSeasonTicketRequestDTO(
                (int) $request->input('season_id'),
                (int) $request->user()->id,
                (string) $request->input('row'),
                (int) $request->input('number'),
                (string) $request->input('idempotency_key')
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
