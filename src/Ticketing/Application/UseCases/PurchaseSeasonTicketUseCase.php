<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;

class PurchaseSeasonTicketUseCase
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository,
        private readonly EventRepository $eventRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly SeasonTicketRepository $seasonTicketRepository,
        private readonly StockManager $stockManager,
        private readonly TransactionManager $transactionManager,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly UuidGenerator $uuidGenerator,
        private readonly int $seasonTicketDiscountPercent = 20
    ) {}

    public function execute(PurchaseSeasonTicketRequestDTO $request): SeasonTicket
    {
        if (! $this->idempotencyStore->markAsProcessed($request->idempotencyKey)) {
            throw new \Src\Ticketing\Domain\Exceptions\DuplicateRequestException('This request has already been processed.');
        }

        $reservedStockEventIds = [];

        // Validate season existence
        try {
            $season = $this->seasonRepository->find($request->seasonId);
            if (! $season) {
                throw new InvalidArgumentException("Season not found: {$request->seasonId}");
            }

            $now = new DateTimeImmutable;
            if ($season->isRenewalWindow($now)) {
                $previousSeasonId = $season->previousSeasonId();
                if ($previousSeasonId) {
                    $previousTicket = $this->seasonTicketRepository->findOneBySeasonAndSeat(
                        $previousSeasonId,
                        $request->row,
                        $request->number
                    );

                    if (! $previousTicket) {
                        throw new SeatAlreadySoldException('Renewal Period: This seat was not reserved in the previous season, so it cannot be renewed. Wait for general sale.');
                    }

                    if ($previousTicket->userId() !== $request->userId) {
                        throw new SeatAlreadySoldException('Renewal Period: This seat is reserved for the previous owner.');
                    }
                }
            }

            $events = $this->eventRepository->findBySeasonId($season->id());
            if (empty($events)) {
                throw new RuntimeException("No events found for season: {$season->id()}");
            }

            // Phase 1: Distributed stock check (Redis) before opening DB transaction
            foreach ($events as $event) {
                if ($this->stockManager->attemptToReserve($event->id())) {
                    $reservedStockEventIds[] = $event->id();
                } else {
                    throw new RuntimeException("Event {$event->id()} is completely sold out.");
                }
            }

            $result = $this->transactionManager->run(function () use ($request, $season, $events) {
                $totalAmount = 0;
                $currency = null;
                $seatsToReserve = [];

                foreach ($events as $event) {
                    $seat = $this->ticketRepository->findAndLockByLocation(
                        $event->id(),
                        $request->row,
                        $request->number
                    );

                    if (! $seat) {
                        throw new RuntimeException("Seat {$request->row}-{$request->number} does not exist for event {$event->id()}");
                    }

                    if (! $seat->isAvailable()) {
                        throw new SeatAlreadySoldException("Seat {$request->row}-{$request->number} is already sold for event {$event->id()}");
                    }

                    if ($currency === null) {
                        $currency = $seat->price()->currency();
                    } elseif ($currency !== $seat->price()->currency()) {
                        throw new RuntimeException("Currency mismatch across events in season.");
                    }

                    $totalAmount += $seat->price()->amount();
                    $seatsToReserve[] = $seat;
                }

                $discountPercent = max(0, min(100, $this->seasonTicketDiscountPercent));
                $discountedAmount = (int) ($totalAmount * (1 - ($discountPercent / 100)));

                $seasonTicket = new SeasonTicket(
                    $this->uuidGenerator->generate(),
                    $request->seasonId,
                    $request->userId,
                    $request->row,
                    $request->number,
                    new Money($discountedAmount, $currency ?? 'EUR'),
                    ReservationStatus::PENDING_PAYMENT,
                    new DateTimeImmutable('+15 minutes'),
                    new DateTimeImmutable
                );

                $this->seasonTicketRepository->save($seasonTicket);

                foreach ($seatsToReserve as $seat) {
                    $seat->reserve($request->userId);
                    $this->ticketRepository->save($seat);
                }

                return $seasonTicket;
            });

            if (! $result instanceof SeasonTicket) {
                throw new RuntimeException('Unexpected result while creating season ticket.');
            }

            return $result;
        } catch (\Throwable $e) {
            $this->idempotencyStore->forget($request->idempotencyKey);

            if ($reservedStockEventIds !== []) {
                foreach ($reservedStockEventIds as $eventId) {
                    $this->stockManager->revertReservation($eventId);
                }
            }

            throw $e;
        }
    }


}
