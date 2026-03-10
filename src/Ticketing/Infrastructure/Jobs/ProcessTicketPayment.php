<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\StockManager;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Throwable;

class ProcessTicketPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reservationId
    ) {}

    public function handle(
        ReservationRepository $reservationRepository,
        TicketRepository $ticketRepository,
        PaymentGateway $paymentGateway,
        StockManager $stockManager
    ): void {
        $reservation = $reservationRepository->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        // Ensure job idempotency
        if ($reservation->status() !== ReservationStatus::PENDING_PAYMENT) {
            return;
        }

        try {
            // External payment gateway interaction
            $transactionId = $paymentGateway->charge($reservation->userId(), $reservation->price());

            // Phase 2: Payment confirmation & Ticket issuance
            DB::transaction(function () use ($reservation, $ticketRepository, $reservationRepository, $transactionId) {
                // Re-acquire lock to prevent race conditions with expiry jobs
                $lockedReservation = $reservationRepository->findAndLock($reservation->id());

                if (! $lockedReservation || $lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                    throw new Exception('Reservation expired or cancelled during payment processing.');
                }

                // Confirm Reservation
                $lockedReservation->markAsPaid();
                $reservationRepository->save($lockedReservation);

                // Issue Ticket
                $ticket = Ticket::issue(
                    $lockedReservation->eventId(),
                    $lockedReservation->seatId(),
                    $lockedReservation->userId(),
                    $lockedReservation->price(),
                    $transactionId
                );
                $ticketRepository->saveTicket($ticket);

                // Publish domain event
                Event::dispatch(new TicketSold($lockedReservation->seatId(), $lockedReservation->userId()));
            });

        } catch (Throwable $e) {
            // Compensation logic (Saga rollback)

            DB::transaction(function () use ($reservation, $reservationRepository, $ticketRepository, $stockManager) {
                $reservation->cancel();
                $reservationRepository->save($reservation);

                // Release the seat lock
                $seat = $ticketRepository->findAndLock($reservation->seatId());
                if ($seat && $seat->reservedByUserId() === $reservation->userId()) {
                    $seat->release();
                    $ticketRepository->save($seat);
                }

                // Revert Redis Stock
                $stockManager->revertReservation($reservation->eventId());
            });

            // In a real system, you might want to log this or notify the user via email
            // throw $e; // Don't throw if you handled the compensation, unless you want retry.
        }
    }
}
