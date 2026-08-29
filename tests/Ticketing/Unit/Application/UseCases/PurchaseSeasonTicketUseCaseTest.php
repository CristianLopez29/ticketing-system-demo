<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Application\UseCases;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Ticketing\Application\DTOs\PurchaseSeasonTicketRequestDTO;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;
use Src\Ticketing\Domain\Exceptions\DuplicateRequestException;
use Src\Ticketing\Domain\Exceptions\EventSoldOutException;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\Model\Event;
use Src\Ticketing\Domain\Model\Season;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;

/**
 * The rejection paths of a season-ticket purchase. Every one of them has to hand
 * back the Redis stock it took for the events it had already walked through.
 */
class PurchaseSeasonTicketUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SEASON_ID = 1;

    private const PREVIOUS_SEASON_ID = 99;

    private const USER_ID = 42;

    private const IDEMPOTENCY_KEY = 'a1b2c3d4-e5f6-4789-89ab-cdef01234567';

    private SeasonRepository&MockInterface $seasonRepository;

    private EventRepository&MockInterface $eventRepository;

    private SeatRepository&MockInterface $seatRepository;

    private SeasonTicketRepository&MockInterface $seasonTicketRepository;

    private StockManager&MockInterface $stockManager;

    private TransactionManager&MockInterface $transactionManager;

    private IdempotencyStore&MockInterface $idempotencyStore;

    private UuidGenerator&MockInterface $uuidGenerator;

    private ClockInterface&MockInterface $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seasonRepository = Mockery::mock(SeasonRepository::class);
        $this->eventRepository = Mockery::mock(EventRepository::class);
        $this->seatRepository = Mockery::mock(SeatRepository::class);
        $this->seasonTicketRepository = Mockery::mock(SeasonTicketRepository::class);
        $this->stockManager = Mockery::mock(StockManager::class);
        $this->transactionManager = Mockery::mock(TransactionManager::class);
        $this->idempotencyStore = Mockery::mock(IdempotencyStore::class);

        $this->uuidGenerator = Mockery::mock(UuidGenerator::class);
        $this->uuidGenerator->shouldReceive('generate')->andReturn('season-ticket-1');

        $this->clock = Mockery::mock(ClockInterface::class);
        $this->clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-07-15 10:00:00'));
    }

    public function test_it_rejects_a_duplicate_whose_season_ticket_cannot_be_found(): void
    {
        // The key completed but the row is gone: replaying the purchase would
        // hand out a second season ticket for the same seat.
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(false);
        $this->idempotencyStore->shouldReceive('getResult')->once()->andReturn('season-ticket-1');
        $this->seasonTicketRepository->shouldReceive('find')->once()->andReturn(null);

        $this->expectException(DuplicateRequestException::class);

        $this->useCase()->execute($this->request());
    }

    public function test_it_rejects_a_duplicate_that_is_still_in_flight(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(false);
        $this->idempotencyStore->shouldReceive('getResult')->once()->andReturn(null);
        $this->seasonTicketRepository->shouldNotReceive('find');

        $this->expectException(DuplicateRequestException::class);

        $this->useCase()->execute($this->request());
    }

    public function test_it_rejects_a_purchase_for_a_season_that_does_not_exist(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->seasonRepository->shouldReceive('find')->once()->andReturn(null);

        $this->stockManager->shouldNotReceive('revertReservation');
        $this->idempotencyStore->shouldReceive('forget')->once()->with(self::IDEMPOTENCY_KEY);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Season not found: '.self::SEASON_ID);

        $this->useCase()->execute($this->request());
    }

    public function test_it_rejects_a_purchase_for_a_season_with_no_events(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->seasonRepository->shouldReceive('find')->once()->andReturn($this->season());
        $this->eventRepository->shouldReceive('findBySeasonId')->once()->andReturn([]);

        $this->stockManager->shouldNotReceive('revertReservation');
        $this->idempotencyStore->shouldReceive('forget')->once();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No events found for season: '.self::SEASON_ID);

        $this->useCase()->execute($this->request());
    }

    public function test_it_returns_the_stock_it_already_took_when_a_later_event_is_sold_out(): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->seasonRepository->shouldReceive('find')->once()->andReturn($this->season());
        $this->eventRepository
            ->shouldReceive('findBySeasonId')
            ->once()
            ->andReturn([new Event(1, 'Matchday 1', 100), new Event(2, 'Matchday 2', 100)]);

        $this->stockManager->shouldReceive('attemptToReserve')->once()->with(1)->andReturn(true);
        $this->stockManager->shouldReceive('attemptToReserve')->once()->with(2)->andReturn(false);

        // Only the first event was decremented, so only that one goes back.
        $this->stockManager->shouldReceive('revertReservation')->once()->with(1);
        $this->stockManager->shouldNotReceive('revertReservation')->with(2);
        $this->idempotencyStore->shouldReceive('forget')->once();
        $this->transactionManager->shouldNotReceive('run');

        $this->expectException(EventSoldOutException::class);

        $this->useCase()->execute($this->request());
    }

    public function test_it_refuses_a_renewal_for_a_seat_the_buyer_did_not_hold_last_season(): void
    {
        $this->arrangeReachingTheTransaction($this->seasonInRenewalWindow());
        $this->seasonTicketRepository
            ->shouldReceive('findAndLockBySeasonAndSeat')
            ->once()
            ->with(self::PREVIOUS_SEASON_ID, 'A', 1)
            ->andReturn(null);

        $this->stockManager->shouldReceive('revertReservation')->once()->with(1);
        $this->idempotencyStore->shouldReceive('forget')->once();

        $this->expectException(SeatAlreadySoldException::class);
        $this->expectExceptionMessage('was not reserved in the previous season');

        $this->useCase()->execute($this->request());
    }

    public function test_it_rejects_a_seat_that_does_not_exist_for_one_of_the_events(): void
    {
        $this->arrangeReachingTheTransaction($this->season());
        $this->seatRepository
            ->shouldReceive('findAndLockByLocation')
            ->once()
            ->with(1, 'A', 1)
            ->andReturn(null);

        $this->stockManager->shouldReceive('revertReservation')->once()->with(1);
        $this->idempotencyStore->shouldReceive('forget')->once();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Seat A-1 does not exist for event 1');

        $this->useCase()->execute($this->request());
    }

    /**
     * Walks the flow up to the body of the transaction with a single event whose
     * stock was successfully taken.
     */
    private function arrangeReachingTheTransaction(Season $season): void
    {
        $this->idempotencyStore->shouldReceive('markAsProcessed')->once()->andReturn(true);
        $this->seasonRepository->shouldReceive('find')->once()->andReturn($season);
        $this->eventRepository
            ->shouldReceive('findBySeasonId')
            ->once()
            ->andReturn([new Event(1, 'Matchday 1', 100)]);
        $this->stockManager->shouldReceive('attemptToReserve')->once()->with(1)->andReturn(true);
        $this->transactionManager
            ->shouldReceive('run')
            ->once()
            ->andReturnUsing(fn (Closure $callback) => $callback());
    }

    private function useCase(): PurchaseSeasonTicketUseCase
    {
        return new PurchaseSeasonTicketUseCase(
            $this->seasonRepository,
            $this->eventRepository,
            $this->seatRepository,
            $this->seasonTicketRepository,
            $this->stockManager,
            $this->transactionManager,
            $this->idempotencyStore,
            $this->uuidGenerator,
            $this->clock
        );
    }

    private function request(): PurchaseSeasonTicketRequestDTO
    {
        return new PurchaseSeasonTicketRequestDTO(
            self::SEASON_ID,
            self::USER_ID,
            'A',
            1,
            self::IDEMPOTENCY_KEY
        );
    }

    private function season(): Season
    {
        return new Season(
            self::SEASON_ID,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30')
        );
    }

    private function seasonInRenewalWindow(): Season
    {
        return new Season(
            self::SEASON_ID,
            '2026/27',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30'),
            previousSeasonId: self::PREVIOUS_SEASON_ID,
            renewalStartDate: new DateTimeImmutable('2026-07-01'),
            renewalEndDate: new DateTimeImmutable('2026-07-31')
        );
    }
}
