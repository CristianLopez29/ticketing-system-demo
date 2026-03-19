<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use InvalidArgumentException;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\DTOs\PurchaseTicketRequestDTO;
use Src\Ticketing\Application\Ports\AsyncDispatcher;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class PurchaseTicketUseCase
{
    public function __construct(
        private readonly SeatRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly StockManager $stockManager,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly TransactionManager $transactionManager,
        private readonly AsyncDispatcher $dispatcher,
        private readonly UuidGenerator $uuidGenerator
    ) {}

    public function execute(PurchaseTicketRequestDTO $request): string
    {
        if (! $this->idempotencyStore->markAsProcessed($request->idempotencyKey)) {
            // If completed before, return the original result instead of throwing
            $previousResult = $this->idempotencyStore->getResult($request->idempotencyKey);
            if ($previousResult !== null) {
                return $previousResult;
            }
            throw new \Src\Ticketing\Domain\Exceptions\DuplicateRequestException('This request has already been processed.');
        }

        try {
            // Distributed stock check (Redis) to reduce DB load
            $hasStock = $this->stockManager->attemptToReserve($request->eventId);
            if (! $hasStock) {
                throw new RuntimeException('Event is completely sold out.');
            }

            // Phase 1: Sync reservation & acknowledgement
            $reservationId = $this->transactionManager->run(function () use ($request) {
                $seat = $this->repository->findAndLock($request->seatId);

                if (! $seat) {
                    throw new InvalidArgumentException('Seat not found.');
                }

                if (! $seat->isAvailable()) {
                    throw new SeatAlreadySoldException("Seat {$seat->row()}-{$seat->number()} is already sold.");
                }

                $seat->reserve($request->userId);
                $this->repository->save($seat);

                $reservation = Reservation::create(
                    $request->eventId,
                    $seat->id(),
                    $request->userId,
                    $seat->price(),
                    $this->uuidGenerator->generate()
                );
                $this->reservationRepository->save($reservation);

                return $reservation->id();
            });

            // Initiate async payment processing (Saga pattern)
            if (! is_string($reservationId)) {
                throw new RuntimeException('Unexpected reservation id.');
            }

            $this->dispatcher->dispatch($reservationId);

            // Store the result — allows duplicate requests to retrieve it instead of being blocked
            $this->idempotencyStore->markAsCompleted($request->idempotencyKey, $reservationId);

            return $reservationId;

        } catch (\Throwable $e) {
            if (isset($hasStock) && $hasStock) {
                $this->stockManager->revertReservation($request->eventId);
            }
            $this->idempotencyStore->forget($request->idempotencyKey);

            throw $e;
        }
    }
}
