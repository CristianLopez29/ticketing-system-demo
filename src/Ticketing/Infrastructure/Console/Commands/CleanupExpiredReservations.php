<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\Repositories\StockManager;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use DateTimeImmutable;
use Throwable;

class CleanupExpiredReservations extends Command
{
    protected $signature = 'ticketing:cleanup-expired-reservations';
    protected $description = 'Release seats for expired pending reservations';

    public function handle(
        ReservationRepository $reservationRepository,
        TicketRepository $ticketRepository,
        StockManager $stockManager
    ): int {
        $now = new DateTimeImmutable();
        $expiredReservations = $reservationRepository->findExpired($now);

        $count = count($expiredReservations);
        if ($count === 0) {
            $this->info('No expired reservations found.');
            return 0;
        }

        $this->info("Found {$count} expired reservations. Processing...");

        foreach ($expiredReservations as $reservation) {
            try {
                DB::transaction(function () use ($reservation, $reservationRepository, $ticketRepository, $stockManager) {
                    // Atomic state verification
                    // Pessimistic lock to prevent race conditions with payment processing
                    $lockedReservation = $reservationRepository->findAndLock($reservation->id());

                    if (!$lockedReservation) {
                        return;
                    }

                    if ($lockedReservation->status() !== ReservationStatus::PENDING_PAYMENT) {
                        // Skip if state mutated concurrently
                        return;
                    }

                    // Mark reservation as cancelled
                    $lockedReservation->cancel();
                    $reservationRepository->save($lockedReservation);

                    // Release seat allocation
                    // Verify ownership before release
                    $seat = $ticketRepository->findAndLock($lockedReservation->seatId());
                    
                    if ($seat && $seat->reservedByUserId() === $lockedReservation->userId()) {
                        $seat->release();
                        $ticketRepository->save($seat);
                    }

                    // Revert distributed stock counter
                    $stockManager->revertReservation($lockedReservation->eventId());
                });

                $this->info("Cleaned up reservation: {$reservation->id()}");

            } catch (Throwable $e) {
                $this->error("Failed to cleanup reservation {$reservation->id()}: {$e->getMessage()}");
                Log::error("Cleanup failed for reservation {$reservation->id()}", ['exception' => $e]);
            }
        }

        return 0;
    }
}
