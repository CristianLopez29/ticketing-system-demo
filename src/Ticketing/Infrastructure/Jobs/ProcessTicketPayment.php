<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Ticketing\Application\Ports\TransactionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Ports\UserNotifier;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Application\Ports\EventDispatcher;
use Throwable;

class ProcessTicketPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Exponential backoff: 10s, 20s, 40s, 80s...
     */
    public function backoff(): array
    {
        return [10, 20, 40, 80];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function __construct(
        public readonly string $reservationId
    ) {}

    public function handle(
        ReservationRepository $reservationRepository,
        TicketRepository $ticketRepository,
        PaymentGateway $paymentGateway,
        StockManager $stockManager,
        UserNotifier $userNotifier,
        UuidGenerator $uuidGenerator,
        TransactionManager $transactionManager
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
            $transactionId = $paymentGateway->charge($reservation->userId(), $reservation->price());
        } catch (Throwable $e) {
            Log::warning('Payment gateway charge failed', [
                'reservation_id' => $this->reservationId,
                'user_id' => $reservation->userId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            $transactionManager->run(function () use ($reservation, $ticketRepository, $reservationRepository, $transactionId, $uuidGenerator) {
                $lockedReservation = $reservationRepository->findAndLock($reservation->id());

                if (! $lockedReservation || $lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                    throw new Exception('Reservation expired or cancelled during payment processing.');
                }

                $lockedReservation->markAsPaid();
                $reservationRepository->save($lockedReservation);

                $ticket = Ticket::issue(
                    $lockedReservation->eventId(),
                    $lockedReservation->seatId(),
                    $lockedReservation->userId(),
                    $lockedReservation->price(),
                    $transactionId,
                    $uuidGenerator->generate()
                );
                $ticketRepository->saveTicket($ticket);
                
                // Note: The event TicketSold is recorded in Ticket::issue and should be dispatched.
                // We let saveTicket dispatch it if using EloquentTicketRepository,
                // or we could explicitly pull and dispatch it here if the repo doesn't.
                // In EloquentTicketRepository::saveTicket, it pulls and dispatches events.
            });
        } catch (Throwable $e) {
            Log::error('Payment processing failed after charge', [
                'reservation_id' => $this->reservationId,
                'error' => $e->getMessage(),
            ]);

            $transactionManager->run(function () use ($reservationRepository, $ticketRepository, $stockManager) {
                $lockedReservation = $reservationRepository->findAndLock($this->reservationId);

                if (! $lockedReservation) {
                    return;
                }

                if ($lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                    return;
                }

                $lockedReservation->cancel();
                $reservationRepository->save($lockedReservation);

                $seat = $ticketRepository->findAndLock($lockedReservation->seatId());
                if ($seat && $seat->reservedByUserId() === $lockedReservation->userId()) {
                    $seat->release();
                    $ticketRepository->save($seat);
                }

                $stockManager->revertReservation($lockedReservation->eventId());
            });

            if (isset($transactionId)) {
                try {
                    $paymentGateway->refund($transactionId);
                } catch (Throwable $refundException) {
                    Log::error('Failed to refund after DB failure', [
                        'transaction_id' => $transactionId,
                        'reservation_id' => $this->reservationId,
                        'error' => $refundException->getMessage(),
                    ]);
                }
            }

            try {
                $userNotifier->notifyPaymentFailed(
                    $reservation->userId(),
                    $this->reservationId,
                    $e->getMessage()
                );
            } catch (Throwable $notifyError) {
                Log::warning('Failed to notify user of payment failure', [
                    'reservation_id' => $this->reservationId,
                    'error' => $notifyError->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
