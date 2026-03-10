<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Src\Ticketing\Application\UseCases\PurchaseTicketUseCase;
use Src\Ticketing\Application\DTOs\PurchaseTicketRequestDTO;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class PurchaseTicketController
{
    public function __construct(
        private readonly PurchaseTicketUseCase $useCase
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        
        if (!$idempotencyKey) {
            return new JsonResponse([
                'error' => 'Idempotency-Key header is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'seat_id'  => 'required|integer',
        ]);

        try {
            $dto = new PurchaseTicketRequestDTO(
                (int) $validated['event_id'],
                new SeatId((int) $validated['seat_id']),
                (int) $request->user()->id,
                $idempotencyKey
            );

            $reservationId = $this->useCase->execute($dto);

            return new JsonResponse([
                'message' => 'Purchase processing started. You will receive a confirmation shortly.',
                'reservation_id' => $reservationId
            ], Response::HTTP_ACCEPTED);

        } catch (SeatAlreadySoldException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // Domain/Business rule violation
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Purchase failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return new JsonResponse([
                'error' => 'An unexpected error occurred.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
