<?php
declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use Src\Ticketing\Application\DTOs\PurchaseTicketRequestDTO;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\StockManager;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Infrastructure\Jobs\ProcessTicketPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use RuntimeException;
use InvalidArgumentException;

class PurchaseTicketUseCase
{
    public function __construct(
        private readonly TicketRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly StockManager $stockManager
    ) {}

    public function execute(PurchaseTicketRequestDTO $request): string
    {
        $idempotencyKey = 'purchase:idempotency:' . $request->idempotencyKey;
        
        // Enforce idempotency via cache lock
        if (!Cache::add($idempotencyKey, true, now()->addMinutes(10))) {
            throw new RuntimeException('This request has already been processed.');
        }

        try {
            // Distributed stock check (Redis) to reduce DB load
            $hasStock = $this->stockManager->attemptToReserve($request->eventId);
            if (!$hasStock) {
                throw new RuntimeException('Event is completely sold out.');
            }

            // Phase 1: Sync reservation & acknowledgement
            $reservationId = DB::transaction(function () use ($request) {
                $seat = $this->repository->findAndLock($request->seatId);

                if (!$seat) {
                    throw new InvalidArgumentException('Seat not found.');
                }

                if (!$seat->isAvailable()) {
                    throw new SeatAlreadySoldException("Seat {$seat->row()}-{$seat->number()} is already sold.");
                }

                $seat->reserve($request->userId);
                $this->repository->save($seat);
                
                $reservation = Reservation::create(
                    $request->eventId,
                    $seat->id(),
                    $request->userId,
                    $seat->price()
                );
                $this->reservationRepository->save($reservation);

                return $reservation->id();
            });

            // Initiate async payment processing (Saga pattern)
            ProcessTicketPayment::dispatch($reservationId);

            return $reservationId;

        } catch (\Throwable $e) {
            if (isset($hasStock) && $hasStock) {
                $this->stockManager->revertReservation($request->eventId);
            }
            Cache::forget($idempotencyKey);
            
            throw $e;
        }
    }
}
