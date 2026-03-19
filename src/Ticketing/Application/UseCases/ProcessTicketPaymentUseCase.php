<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Application\Ports\UserNotifier;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Repositories\PendingRefundRepository;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Throwable;

class ProcessTicketPaymentUseCase
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly SeatRepository $seatRepository,
        private readonly PaymentGateway $paymentGateway,
        private readonly StockManager $stockManager,
        private readonly UserNotifier $userNotifier,
        private readonly UuidGenerator $uuidGenerator,
        private readonly TransactionManager $transactionManager,
        private readonly PendingRefundRepository $pendingRefundRepository
    ) {}

    public function execute(string $reservationId): void
    {
        $reservation = $this->reservationRepository->find($reservationId);

        if (! $reservation) {
            return;
        }

        // Ensure job idempotency
        if ($reservation->status() !== ReservationStatus::PENDING_PAYMENT) {
            return;
        }

        try {
            $transactionId = $this->paymentGateway->charge($reservation->userId(), $reservation->price());
        } catch (Throwable $e) {
            Log::warning('Payment gateway charge failed', [
                'reservation_id' => $reservationId,
                'user_id' => $reservation->userId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            $this->transactionManager->run(function () use ($reservation, $transactionId) {
                $lockedReservation = $this->reservationRepository->findAndLock($reservation->id());

                if (! $lockedReservation || $lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                    throw new Exception('Reservation expired or cancelled during payment processing.');
                }

                $lockedReservation->markAsPaid();
                $this->reservationRepository->save($lockedReservation);

                $ticket = Ticket::issue(
                    $lockedReservation->eventId(),
                    $lockedReservation->seatId(),
                    $lockedReservation->userId(),
                    $lockedReservation->price(),
                    $transactionId,
                    $this->uuidGenerator->generate()
                );
                $this->ticketRepository->saveTicket($ticket);
            });
        } catch (Throwable $e) {
            Log::error('Payment processing failed after charge', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
            ]);

            $this->transactionManager->run(function () use ($reservationId) {
                $lockedReservation = $this->reservationRepository->findAndLock($reservationId);

                if (! $lockedReservation) {
                    return;
                }

                if ($lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                    return;
                }

                $lockedReservation->cancel();
                $this->reservationRepository->save($lockedReservation);

                $seat = $this->seatRepository->findAndLock($lockedReservation->seatId());
                if ($seat && $seat->reservedByUserId() === $lockedReservation->userId()) {
                    $seat->release();
                    $this->seatRepository->save($seat);
                }

                $this->stockManager->revertReservation($lockedReservation->eventId());
            });

            if (isset($transactionId)) {
                try {
                    $this->paymentGateway->refund($transactionId);
                } catch (Throwable $refundException) {
                    Log::error('Failed to refund after DB failure', [
                        'transaction_id' => $transactionId,
                        'reservation_id' => $reservationId,
                        'error' => $refundException->getMessage(),
                    ]);

                    $this->pendingRefundRepository->save(
                        $transactionId,
                        $reservationId,
                        'Failed to refund during saga compensation: ' . $refundException->getMessage()
                    );
                }
            }

            try {
                $this->userNotifier->notifyPaymentFailed(
                    $reservation->userId(),
                    $reservationId,
                    $e->getMessage()
                );
            } catch (Throwable $notifyError) {
                Log::warning('Failed to notify user of payment failure', [
                    'reservation_id' => $reservationId,
                    'error' => $notifyError->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
