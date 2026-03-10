<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\Repositories\StockManager;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;

class PurchaseSeasonTicketUseCase
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository,
        private readonly EventRepository $eventRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly SeasonTicketRepository $seasonTicketRepository,
        private readonly StockManager $stockManager
    ) {}

    public function execute(PurchaseSeasonTicketRequestDTO $request): SeasonTicket
    {
        // Validate season existence
        $season = $this->seasonRepository->find($request->seasonId);
        if (! $season) {
            throw new InvalidArgumentException("Season not found: {$request->seasonId}");
        }

        // Enforce renewal constraints
        $now = new DateTimeImmutable;
        if ($season->isRenewalWindow($now)) {
            $previousSeasonId = $season->previousSeasonId();
            if ($previousSeasonId) {
                // Check if this specific seat was owned by the user in the previous season
                $previousTicket = $this->seasonTicketRepository->findOneBySeasonAndSeat(
                    $previousSeasonId,
                    $request->row,
                    $request->number
                );

                if (! $previousTicket) {
                    // Seat was not sold in previous season, or logic dictates it's locked.
                    throw new SeatAlreadySoldException('Renewal Period: This seat was not reserved in the previous season, so it cannot be renewed. Wait for general sale.');
                }

                if ($previousTicket->userId() !== $request->userId) {
                    throw new SeatAlreadySoldException('Renewal Period: This seat is reserved for the previous owner.');
                }
            }
        }

        // Atomic transaction for multi-event reservation
        return DB::transaction(function () use ($request, $season) {
            // Retrieve season events
            $events = $this->eventRepository->findBySeasonId($season->id());
            if (empty($events)) {
                throw new RuntimeException("No events found for season: {$season->id()}");
            }

            // Aggregate price & verify availability across all events
            $totalAmount = 0;
            $currency = 'EUR'; // Default or derived from first event
            $seatsToReserve = [];

            foreach ($events as $event) {
                // Lock the seat for this event
                $seat = $this->ticketRepository->findAndLockByLocation(
                    $event->id(),
                    $request->row,
                    $request->number
                );

                if (! $seat) {
                    throw new RuntimeException(
                        "Seat {$request->row}-{$request->number} does not exist for event {$event->id()}"
                    );
                }

                if (! $seat->isAvailable()) {
                    throw new SeatAlreadySoldException(
                        "Seat {$request->row}-{$request->number} is already sold for event {$event->id()}"
                    );
                }

                $totalAmount += $seat->price()->amount();
                $currency = $seat->price()->currency(); // Assume consistent currency
                $seatsToReserve[] = $seat;
            }

            $discountPercent = config('ticketing.season_ticket_discount', 20);
            $discountedAmount = (int) ($totalAmount * (1 - $discountPercent / 100));

            $seasonTicket = new SeasonTicket(
                (string) Str::uuid(),
                $request->seasonId,
                $request->userId,
                $request->row,
                $request->number,
                new Money($discountedAmount, $currency),
                ReservationStatus::PENDING_PAYMENT,
                new DateTimeImmutable('+15 minutes'), // Payment window
                new DateTimeImmutable
            );

            $this->seasonTicketRepository->save($seasonTicket);

            foreach ($seatsToReserve as $seat) {
                $seat->reserve($request->userId);
                $this->ticketRepository->save($seat);
                $this->stockManager->attemptToReserve($seat->eventId());
            }

            return $seasonTicket;
        });
    }
}
