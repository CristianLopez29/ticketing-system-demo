<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Console;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use RuntimeException;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Tests\TestCase;

/**
 * The cleanup command races the payment saga for the same reservation. These are
 * the outcomes of losing that race: the row is gone, it was paid in the meantime,
 * or the transaction itself blows up. None of them may abort the whole sweep.
 */
class CleanupExpiredReservationsGuardsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const RESERVATION_ID = 'res-expired';

    private ReservationRepository&MockInterface $reservationRepository;

    private SeatRepository&MockInterface $seatRepository;

    private StockManager&MockInterface $stockManager;

    private TransactionManager&MockInterface $transactionManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationRepository = Mockery::mock(ReservationRepository::class);
        $this->seatRepository = Mockery::mock(SeatRepository::class);
        $this->stockManager = Mockery::mock(StockManager::class);
        $this->transactionManager = Mockery::mock(TransactionManager::class);

        $this->app->instance(ReservationRepository::class, $this->reservationRepository);
        $this->app->instance(SeatRepository::class, $this->seatRepository);
        $this->app->instance(StockManager::class, $this->stockManager);
        $this->app->instance(TransactionManager::class, $this->transactionManager);

        $this->reservationRepository
            ->shouldReceive('findExpiredChunked')
            ->andReturn([$this->expiredReservation()], []);
    }

    public function test_it_skips_a_reservation_that_disappeared_before_the_lock(): void
    {
        $this->runsTheTransaction();
        $this->reservationRepository->shouldReceive('findAndLock')->once()->andReturn(null);

        $this->stockManager->shouldNotReceive('revertReservation');
        $this->seatRepository->shouldNotReceive('findAndLock');

        $this->artisanCleanup()
            ->expectsOutput('No expired reservations found.')
            ->assertExitCode(0)
            ->run();
    }

    public function test_it_does_not_release_a_reservation_that_was_paid_in_the_meantime(): void
    {
        $this->runsTheTransaction();
        $this->reservationRepository
            ->shouldReceive('findAndLock')
            ->once()
            ->andReturn($this->expiredReservation(ReservationStatus::PAID));

        // Cancelling a paid reservation would refund a buyer who already has a ticket.
        $this->stockManager->shouldNotReceive('revertReservation');
        $this->seatRepository->shouldNotReceive('findAndLock');
        $this->reservationRepository->shouldNotReceive('save');

        $this->artisanCleanup()
            ->expectsOutput('No expired reservations found.')
            ->assertExitCode(0)
            ->run();
    }

    public function test_it_reports_a_failed_reservation_and_keeps_sweeping(): void
    {
        Log::shouldReceive('error')->once();

        $this->transactionManager
            ->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('deadlock found when trying to get lock'));

        $this->artisanCleanup()
            ->expectsOutput(
                'Failed to cleanup reservation '.self::RESERVATION_ID.': deadlock found when trying to get lock'
            )
            ->assertExitCode(0)
            ->run();
    }

    private function runsTheTransaction(): void
    {
        $this->transactionManager
            ->shouldReceive('run')
            ->once()
            ->andReturnUsing(fn (Closure $callback) => $callback());
    }

    private function artisanCleanup(): \Illuminate\Testing\PendingCommand
    {
        $pending = $this->artisan('ticketing:cleanup-expired-reservations');

        if (is_int($pending)) {
            $this->fail('Expected a PendingCommand instance.');
        }

        return $pending;
    }

    private function expiredReservation(
        ReservationStatus $status = ReservationStatus::PENDING_PAYMENT,
    ): Reservation {
        return new Reservation(
            self::RESERVATION_ID,
            10,
            new SeatId(7),
            42,
            $status,
            new Money(5000, 'EUR'),
            (new DateTimeImmutable)->modify('-10 minutes'),
            (new DateTimeImmutable)->modify('-20 minutes')
        );
    }
}
