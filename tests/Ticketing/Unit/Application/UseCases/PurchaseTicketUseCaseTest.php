<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Application\UseCases;

use Closure;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\DTOs\PurchaseTicketRequestDTO;
use Src\Ticketing\Application\Ports\AsyncDispatcher;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Application\UseCases\PurchaseTicketUseCase;
use Src\Ticketing\Domain\Exceptions\DuplicateRequestException;
use Src\Ticketing\Domain\Exceptions\EventSoldOutException;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\ValueObjects\SeatId;

/**
 * The rejection paths of the purchase flow. Each one has to leave the buyer able
 * to retry: the Redis stock goes back and the idempotency key is forgotten.
 */
class PurchaseTicketUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const EVENT_ID = 10;

    private const USER_ID = 42;

    private const IDEMPOTENCY_KEY = 'a1b2c3d4-e5f6-4789-89ab-cdef01234567';

    private SeatRepository&MockInterface $seatRepository;

    private ReservationRepository&MockInterface $reservationRepository;

    private StockManager&MockInterface $stockManager;

    private IdempotencyStore&MockInterface $idempotencyStore;

    private TransactionManager&MockInterface $transactionManager;

    private AsyncDispatcher&MockInterface $dispatcher;

    private UuidGenerator&MockInterface $uuidGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seatRepository = Mockery::mock(SeatRepository::class);
        $this->reservationRepository = Mockery::mock(ReservationRepository::class);
        $this->stockManager = Mockery::mock(StockManager::class);
        $this->idempotencyStore = Mockery::mock(IdempotencyStore::class);
        $this->transactionManager = Mockery::mock(TransactionManager::class);
        $this->dispatcher = Mockery::mock(AsyncDispatcher::class);

        $this->uuidGenerator = Mockery::mock(UuidGenerator::class);
        $this->uuidGenerator->shouldReceive('generate')->andReturn('reservation-1');
    }

    public function test_it_returns_the_original_reservation_when_the_key_already_completed(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(false);
        $this->idempotencyStore->shouldReceive('getResult')->once()->andReturn('reservation-1');
        $this->stockManager->shouldNotReceive('attemptToReserve');

        $this->assertSame('reservation-1', $this->useCase()->execute($this->request()));
    }

    public function test_it_rejects_a_duplicate_that_is_still_in_flight(): void
    {
        // The key is taken but has no stored result yet: the first request is still running.
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(false);
        $this->idempotencyStore->shouldReceive('getResult')->once()->andReturn(null);

        // Nothing was consumed, so nothing may be compensated or forgotten —
        // forgetting here would unlock the key the in-flight request still holds.
        $this->stockManager->shouldNotReceive('revertReservation');
        $this->idempotencyStore->shouldNotReceive('forget');

        $this->expectException(DuplicateRequestException::class);

        $this->useCase()->execute($this->request());
    }

    public function test_it_rejects_the_purchase_when_the_event_is_sold_out(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->stockManager->shouldReceive('attemptToReserve')->once()->andReturn(false);

        // The Lua script never decremented, so reverting would invent a seat.
        $this->stockManager->shouldNotReceive('revertReservation');
        $this->idempotencyStore->shouldReceive('forget')->once()->with(self::IDEMPOTENCY_KEY);
        $this->transactionManager->shouldNotReceive('run');

        $this->expectException(EventSoldOutException::class);

        $this->useCase()->execute($this->request());
    }

    public function test_it_reverts_the_stock_when_the_seat_does_not_exist(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->stockManager->shouldReceive('attemptToReserve')->once()->andReturn(true);
        $this->transactionManager
            ->shouldReceive('run')
            ->once()
            ->andReturnUsing(fn (Closure $callback) => $callback());
        $this->seatRepository->shouldReceive('findAndLock')->once()->andReturn(null);

        $this->stockManager->shouldReceive('revertReservation')->once()->with(self::EVENT_ID);
        $this->idempotencyStore->shouldReceive('forget')->once()->with(self::IDEMPOTENCY_KEY);
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seat not found.');

        $this->useCase()->execute($this->request());
    }

    public function test_it_refuses_to_queue_payment_when_the_transaction_returns_no_reservation_id(): void
    {
        // TransactionManager::run() is typed `mixed`: an adapter that swallows the
        // closure's return would otherwise hand the payment saga a bogus id.
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->stockManager->shouldReceive('attemptToReserve')->once()->andReturn(true);
        $this->transactionManager->shouldReceive('run')->once()->andReturn(null);

        $this->stockManager->shouldReceive('revertReservation')->once()->with(self::EVENT_ID);
        $this->idempotencyStore->shouldReceive('forget')->once()->with(self::IDEMPOTENCY_KEY);
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected reservation id.');

        $this->useCase()->execute($this->request());
    }

    private function useCase(): PurchaseTicketUseCase
    {
        return new PurchaseTicketUseCase(
            $this->seatRepository,
            $this->reservationRepository,
            $this->stockManager,
            $this->idempotencyStore,
            $this->transactionManager,
            $this->dispatcher,
            $this->uuidGenerator
        );
    }

    private function request(): PurchaseTicketRequestDTO
    {
        return new PurchaseTicketRequestDTO(
            self::EVENT_ID,
            new SeatId(7),
            self::USER_ID,
            self::IDEMPOTENCY_KEY
        );
    }
}
