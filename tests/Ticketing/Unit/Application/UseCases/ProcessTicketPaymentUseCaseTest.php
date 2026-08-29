<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Application\UseCases;

use Closure;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Application\Ports\UserNotifier;
use Src\Ticketing\Application\UseCases\ProcessTicketPaymentUseCase;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Model\Seat;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Repositories\PendingRefundRepository;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;

/**
 * Branch coverage for the payment saga: the guards that stop a retried job from
 * charging twice, and the compensation that must undo every side-effect when the
 * commit fails after the money has already moved.
 */
class ProcessTicketPaymentUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const RESERVATION_ID = 'res-1';

    private const EVENT_ID = 10;

    private const USER_ID = 42;

    private const TRANSACTION_ID = 'txn-abc';

    private ReservationRepository&MockInterface $reservationRepository;

    private TicketRepository&MockInterface $ticketRepository;

    private SeatRepository&MockInterface $seatRepository;

    private PaymentGateway&MockInterface $paymentGateway;

    private StockManager&MockInterface $stockManager;

    private UserNotifier&MockInterface $userNotifier;

    private TransactionManager&MockInterface $transactionManager;

    private PendingRefundRepository&MockInterface $pendingRefundRepository;

    private UuidGenerator&MockInterface $uuidGenerator;

    private LoggerInterface&MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationRepository = Mockery::mock(ReservationRepository::class);
        $this->ticketRepository = Mockery::mock(TicketRepository::class);
        $this->seatRepository = Mockery::mock(SeatRepository::class);
        $this->paymentGateway = Mockery::mock(PaymentGateway::class);
        $this->stockManager = Mockery::mock(StockManager::class);
        $this->userNotifier = Mockery::mock(UserNotifier::class);
        $this->transactionManager = Mockery::mock(TransactionManager::class);
        $this->pendingRefundRepository = Mockery::mock(PendingRefundRepository::class);
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

        $this->uuidGenerator = Mockery::mock(UuidGenerator::class);
        $this->uuidGenerator->shouldReceive('generate')->andReturn('ticket-1');
    }

    // -------------------------------------------------------------------------
    // Guards — a retried job must never charge a second time
    // -------------------------------------------------------------------------

    public function test_it_does_nothing_when_the_reservation_no_longer_exists(): void
    {
        $this->reservationRepository->shouldReceive('find')->once()->andReturn(null);
        $this->paymentGateway->shouldNotReceive('charge');

        $this->useCase()->execute(self::RESERVATION_ID);

        $this->addToAssertionCount(1);
    }

    public function test_it_does_not_charge_a_reservation_that_is_no_longer_pending_payment(): void
    {
        $this->reservationRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($this->reservation(ReservationStatus::PAID));
        $this->paymentGateway->shouldNotReceive('charge');

        $this->useCase()->execute(self::RESERVATION_ID);

        $this->addToAssertionCount(1);
    }

    public function test_it_logs_and_rethrows_when_the_charge_fails(): void
    {
        $this->reservationRepository->shouldReceive('find')->once()->andReturn($this->reservation());
        $this->paymentGateway
            ->shouldReceive('charge')
            ->once()
            ->andThrow(new RuntimeException('Payment declined by the bank.'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Payment gateway charge failed', Mockery::type('array'));

        // No charge went through, so there is nothing to compensate.
        $this->transactionManager->shouldNotReceive('run');
        $this->paymentGateway->shouldNotReceive('refund');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment declined by the bank.');

        $this->useCase()->execute(self::RESERVATION_ID);
    }

    // -------------------------------------------------------------------------
    // Compensation — the commit failed after the money moved
    // -------------------------------------------------------------------------

    public function test_it_cancels_the_reservation_releases_the_seat_and_reverts_stock_when_the_commit_fails(): void
    {
        // The rolled-back transaction leaves the row untouched, so the compensation
        // re-reads a reservation that is still pending.
        $forCompensation = $this->reservation();
        $seat = $this->seatReservedBy(self::USER_ID);

        $this->arrangeFailedCommit($this->reservation(), $forCompensation);
        $this->seatRepository->shouldReceive('findAndLock')->once()->andReturn($seat);
        $this->seatRepository->shouldReceive('save')->once()->with($seat);
        $this->stockManager->shouldReceive('revertReservation')->once()->with(self::EVENT_ID);
        $this->paymentGateway->shouldReceive('refund')->once()->with(self::TRANSACTION_ID);
        $this->userNotifier
            ->shouldReceive('notifyPaymentFailed')
            ->once()
            ->with(self::USER_ID, self::RESERVATION_ID, 'ticket store unreachable');
        $this->pendingRefundRepository->shouldNotReceive('save');

        $this->expectException(RuntimeException::class);

        try {
            $this->useCase()->execute(self::RESERVATION_ID);
        } finally {
            $this->assertSame(ReservationStatus::CANCELLED, $forCompensation->status());
            $this->assertNull($seat->reservedByUserId(), 'The seat must go back on sale.');
        }
    }

    public function test_it_leaves_the_seat_alone_when_it_is_held_by_another_user(): void
    {
        $anotherBuyerId = self::USER_ID + 1;
        $seatHeldByAnotherBuyer = $this->seatReservedBy($anotherBuyerId);

        $this->arrangeFailedCommit($this->reservation(), $this->reservation());
        $this->seatRepository->shouldReceive('findAndLock')->once()->andReturn($seatHeldByAnotherBuyer);
        $this->seatRepository->shouldNotReceive('save');
        $this->stockManager->shouldReceive('revertReservation')->once();
        $this->paymentGateway->shouldReceive('refund')->once();
        $this->userNotifier->shouldReceive('notifyPaymentFailed')->once();

        $this->expectException(RuntimeException::class);

        try {
            $this->useCase()->execute(self::RESERVATION_ID);
        } finally {
            $this->assertSame(
                $anotherBuyerId,
                $seatHeldByAnotherBuyer->reservedByUserId(),
                'Releasing a seat another buyer already holds would oversell it.'
            );
        }
    }

    public function test_it_skips_compensation_when_the_reservation_was_already_cancelled(): void
    {
        // The cleanup command got there first: cancelling twice would throw.
        $this->arrangeFailedCommit($this->reservation(), $this->reservation(ReservationStatus::CANCELLED));
        $this->seatRepository->shouldNotReceive('findAndLock');
        $this->stockManager->shouldNotReceive('revertReservation');

        // The charge still has to go back to the buyer.
        $this->paymentGateway->shouldReceive('refund')->once()->with(self::TRANSACTION_ID);
        $this->userNotifier->shouldReceive('notifyPaymentFailed')->once();

        $this->expectException(RuntimeException::class);

        $this->useCase()->execute(self::RESERVATION_ID);
    }

    public function test_it_records_a_pending_refund_when_the_refund_itself_fails(): void
    {
        $this->arrangeFailedCommit($this->reservation(), $this->reservation());
        $this->seatRepository->shouldReceive('findAndLock')->once()->andReturn($this->seatReservedBy(self::USER_ID));
        $this->seatRepository->shouldReceive('save')->once();
        $this->stockManager->shouldReceive('revertReservation')->once();
        $this->paymentGateway
            ->shouldReceive('refund')
            ->once()
            ->andThrow(new RuntimeException('Refund declined by the bank.'));
        $this->userNotifier->shouldReceive('notifyPaymentFailed')->once();

        $this->pendingRefundRepository
            ->shouldReceive('save')
            ->once()
            ->with(
                self::TRANSACTION_ID,
                self::RESERVATION_ID,
                Mockery::pattern('/Refund declined by the bank\./')
            );

        $this->expectException(RuntimeException::class);

        $this->useCase()->execute(self::RESERVATION_ID);
    }

    public function test_it_swallows_a_notifier_failure_so_the_compensation_still_completes(): void
    {
        $this->arrangeFailedCommit($this->reservation(), $this->reservation());
        $this->seatRepository->shouldReceive('findAndLock')->once()->andReturn($this->seatReservedBy(self::USER_ID));
        $this->seatRepository->shouldReceive('save')->once();
        $this->stockManager->shouldReceive('revertReservation')->once();
        $this->paymentGateway->shouldReceive('refund')->once();
        $this->userNotifier
            ->shouldReceive('notifyPaymentFailed')
            ->once()
            ->andThrow(new RuntimeException('Mailer down.'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Failed to notify user of payment failure', Mockery::type('array'));

        // The original commit failure surfaces, not the notifier one.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ticket store unreachable');

        $this->useCase()->execute(self::RESERVATION_ID);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Charge succeeds, the ticket write blows up inside the transaction, and the
     * compensation re-reads the reservation it is given.
     */
    private function arrangeFailedCommit(Reservation $forCommit, Reservation $forCompensation): void
    {
        $this->reservationRepository->shouldReceive('find')->once()->andReturn($forCommit);
        $this->reservationRepository
            ->shouldReceive('findAndLock')
            ->twice()
            ->andReturn($forCommit, $forCompensation);
        $this->reservationRepository->shouldReceive('save')->andReturnNull();

        $this->paymentGateway->shouldReceive('charge')->once()->andReturn(self::TRANSACTION_ID);

        $this->ticketRepository
            ->shouldReceive('saveTicket')
            ->once()
            ->andThrow(new RuntimeException('ticket store unreachable'));

        $this->transactionManager
            ->shouldReceive('run')
            ->twice()
            ->andReturnUsing(fn (Closure $callback) => $callback());
    }

    private function useCase(): ProcessTicketPaymentUseCase
    {
        return new ProcessTicketPaymentUseCase(
            $this->reservationRepository,
            $this->ticketRepository,
            $this->seatRepository,
            $this->paymentGateway,
            $this->stockManager,
            $this->userNotifier,
            $this->uuidGenerator,
            $this->transactionManager,
            $this->pendingRefundRepository,
            $this->logger
        );
    }

    private function reservation(ReservationStatus $status = ReservationStatus::PENDING_PAYMENT): Reservation
    {
        return new Reservation(
            self::RESERVATION_ID,
            self::EVENT_ID,
            new SeatId(7),
            self::USER_ID,
            $status,
            new Money(5000, 'EUR'),
            (new DateTimeImmutable)->modify('+5 minutes'),
            new DateTimeImmutable
        );
    }

    private function seatReservedBy(int $userId): Seat
    {
        return new Seat(new SeatId(7), self::EVENT_ID, 'A', 1, new Money(5000, 'EUR'), $userId);
    }
}
