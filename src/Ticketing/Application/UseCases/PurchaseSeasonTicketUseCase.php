<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\ValueObjects\Money;

class PurchaseSeasonTicketUseCase
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository,
        private readonly EventRepository $eventRepository,
        private readonly SeatRepository $ticketRepository,
        private readonly SeasonTicketRepository $seasonTicketRepository,
        private readonly StockManager $stockManager,
        private readonly TransactionManager $transactionManager,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly UuidGenerator $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly int $seasonTicketDiscountPercent = 20
    ) {}

    public function execute(PurchaseSeasonTicketRequestDTO $request): SeasonTicket
    {
        if (! $this->idempotencyStore->markAsProcessed($request->idempotencyKey)) {
            // If completed before, the caller is just retrying — return the stored result
            $previousId = $this->idempotencyStore->getResult($request->idempotencyKey);
            if ($previousId !== null) {
                // Re-fetch and return the existing season ticket object
                $existing = $this->seasonTicketRepository->find($previousId);
                if ($existing) {
                    return $existing;
                }
            }
            throw new \Src\Ticketing\Domain\Exceptions\DuplicateRequestException('This request has already been processed.');
        }

        $reservedStockEventIds = [];

        // Validate season existence
        try {
            $season = $this->seasonRepository->find($request->seasonId);
            if (! $season) {
                throw new InvalidArgumentException("Season not found: {$request->seasonId}");
            }

            $now = $this->clock->now();
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
                $totalPrice = null;
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

                    if ($totalPrice === null) {
                        $totalPrice = $seat->price();
                    } else {
                        if ($totalPrice->currency() !== $seat->price()->currency()) {
                            throw new InvalidArgumentException("Currency mismatch across events in season.");
                        }
                        $totalPrice = $totalPrice->add($seat->price());
                    }

                    $seatsToReserve[] = $seat;
                }

                $discountPercent = max(0, min(100, $this->seasonTicketDiscountPercent));
                $discountedPrice = $totalPrice ? $totalPrice->applyDiscountPercent($discountPercent) : new Money(0, 'EUR');

                $seasonTicket = new SeasonTicket(
                    $this->uuidGenerator->generate(),
                    $request->seasonId,
                    $request->userId,
                    $request->row,
                    $request->number,
                    $discountedPrice,
                    ReservationStatus::PENDING_PAYMENT,
                    $this->clock->now()->modify('+15 minutes'),
                    $this->clock->now()
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

            // Store the result so retrying callers get the original outcome
            $this->idempotencyStore->markAsCompleted($request->idempotencyKey, $result->id());

            return $result;
        } catch (\Throwable $e) {
            $this->compensate($request->idempotencyKey, $reservedStockEventIds);

            throw $e;
        }
    }

    /**
     * @param int[] $reservedStockEventIds
     */
    private function compensate(string $idempotencyKey, array $reservedStockEventIds): void
    {
        $this->idempotencyStore->forget($idempotencyKey);

        foreach ($reservedStockEventIds as $eventId) {
            $this->stockManager->revertReservation($eventId);
        }
    }


}
