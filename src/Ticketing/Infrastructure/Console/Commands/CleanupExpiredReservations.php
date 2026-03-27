<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Throwable;

class CleanupExpiredReservations extends Command
{
    protected $signature = 'ticketing:cleanup-expired-reservations';

    protected $description = 'Release seats for expired pending reservations';

    public function handle(
        ReservationRepository $reservationRepository,
        SeatRepository $ticketRepository,
        StockManager $stockManager,
        TransactionManager $transactionManager
    ): int {
        $now           = new DateTimeImmutable();
        $limit         = 100;
        $total         = 0;
        $afterCreatedAt = null;
        $afterId        = null;

        while (true) {
            $batch = $reservationRepository->findExpiredChunked($now, $limit, $afterCreatedAt, $afterId);

            if (empty($batch)) {
                break;
            }

            foreach ($batch as $reservation) {
                try {
                    $didCleanup = $transactionManager->run(function () use ($reservation, $reservationRepository, $ticketRepository, $stockManager): bool {
                        // Pessimistic lock to prevent race conditions with payment processing
                        $lockedReservation = $reservationRepository->findAndLock($reservation->id());

                        if (! $lockedReservation) {
                            return false;
                        }

                        if ($lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                            return false;
                        }

                        $lockedReservation->cancel();
                        $reservationRepository->save($lockedReservation);

                        // Release seat allocation — verify ownership before release
                        $seat = $ticketRepository->findAndLock($lockedReservation->seatId());

                        if ($seat && $seat->reservedByUserId() === $lockedReservation->userId()) {
                            $seat->release();
                            $ticketRepository->save($seat);
                        }

                        // Revert distributed stock counter
                        $stockManager->revertReservation($lockedReservation->eventId());

                        return true;
                    });

                    if ($didCleanup) {
                        $total++;
                        $this->info("Cleaned up reservation: {$reservation->id()}");
                    }
                } catch (Throwable $e) {
                    $this->error("Failed to cleanup reservation {$reservation->id()}: {$e->getMessage()}");
                    Log::error("Cleanup failed for reservation {$reservation->id()}", ['exception' => $e]);
                }
            }

            // Advance cursor to the last record of this batch
            /** @var Reservation $last */
            $last           = end($batch);
            $afterCreatedAt = $last->createdAt()->format(DateTimeImmutable::ATOM);
            $afterId        = $last->id();
        }

        if ($total === 0) {
            $this->info('No expired reservations found.');
        }

        return 0;
    }
}