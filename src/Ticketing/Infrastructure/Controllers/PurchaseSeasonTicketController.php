<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PurchaseSeasonTicketController
{
    public function __invoke(Request $request, PurchaseSeasonTicketUseCase $useCase): JsonResponse
    {
        $request->validate([
            'season_id' => 'required|integer',
            'user_id' => 'required|integer', // In real app, from Auth
            'row' => 'required|string',
            'number' => 'required|integer',
            'idempotency_key' => 'required|string',
        ]);

        try {
            $dto = new PurchaseSeasonTicketRequestDTO(
                (int) $request->input('season_id'),
                (int) $request->input('user_id'),
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
                'message' => 'Season ticket reserved successfully. Please proceed to payment.'
            ], 201);

        } catch (SeatAlreadySoldException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return new JsonResponse(['error' => 'Internal Server Error', 'details' => $e->getMessage()], 500);
        }
    }
}
