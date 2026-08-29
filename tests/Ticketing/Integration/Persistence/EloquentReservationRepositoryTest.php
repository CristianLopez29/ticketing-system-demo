<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Events\ReservationCancelled;
use Src\Ticketing\Domain\Events\ReservationPaid;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Infrastructure\Persistence\EloquentReservationRepository;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

/**
 * Integration tests for EloquentReservationRepository::findExpiredChunked.
 *
 * These tests verify the cursor-based pagination logic with a real database
 * (MySQL inside Docker via the testing environment).
 */
class EloquentReservationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentReservationRepository $repository;

    private int $eventId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentReservationRepository;

        $event = EventModel::create(['name' => 'Test Event', 'total_seats' => 50]);
        $this->eventId = $event->id;
        $this->userId = User::factory()->create()->id;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function insertReservation(
        string $id,
        string $status,
        string $expiresAt,
        string $createdAt,
    ): void {
        $seat = SeatModel::create([
            'event_id' => $this->eventId,
            'row' => 'A',
            'number' => rand(1, 9999),
            'price_amount' => 1000,
            'price_currency' => 'EUR',
        ]);

        DB::table('reservations')->insert([
            'id' => $id,
            'event_id' => $this->eventId,
            'seat_id' => $seat->id,
            'user_id' => $this->userId,
            'status' => $status,
            'price_amount' => 1000,
            'price_currency' => 'EUR',
            'expires_at' => $expiresAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_returns_empty_array_when_no_expired_reservations(): void
    {
        $now = new DateTimeImmutable;
        $result = $this->repository->findExpiredChunked($now, 100);

        $this->assertSame([], $result);
    }

    public function test_returns_only_pending_payment_expired_reservations(): void
    {
        $past = now()->subHour()->toIso8601String();
        $now = new DateTimeImmutable;

        $this->insertReservation('res-pending', ReservationStatus::PENDING_PAYMENT->value, $past, $past);
        $this->insertReservation('res-paid', ReservationStatus::PAID->value, $past, $past);
        $this->insertReservation('res-cancelled', ReservationStatus::CANCELLED->value, $past, $past);

        $batch = $this->repository->findExpiredChunked($now, 100);

        $this->assertCount(1, $batch);
        $this->assertSame('res-pending', $batch[0]->id());
    }

    public function test_respects_limit_per_batch(): void
    {
        $past = now()->subHour()->toIso8601String();
        $now = new DateTimeImmutable;

        for ($i = 1; $i <= 5; $i++) {
            $this->insertReservation("res-{$i}", ReservationStatus::PENDING_PAYMENT->value, $past, $past);
        }

        $batch = $this->repository->findExpiredChunked($now, 2);

        $this->assertCount(2, $batch);
    }

    public function test_cursor_returns_next_page_without_overlap(): void
    {
        $now = new DateTimeImmutable;

        // Insert 4 reservations with distinct created_at timestamps
        $base = now()->subHours(4);
        for ($i = 1; $i <= 4; $i++) {
            $createdAt = $base->addMinutes($i)->toIso8601String();
            $expiresAt = now()->subMinutes(1)->toIso8601String();
            $this->insertReservation("res-cursor-{$i}", ReservationStatus::PENDING_PAYMENT->value, $expiresAt, $createdAt);
        }

        // First page: 2 records
        $page1 = $this->repository->findExpiredChunked($now, 2);
        $this->assertCount(2, $page1);

        $last = end($page1);
        assert($last instanceof \Src\Ticketing\Domain\Model\Reservation);
        $afterCreatedAt = $last->createdAt()->format(DateTimeImmutable::ATOM);
        $afterId = $last->id();

        // Second page: next 2 records — no overlap with first page
        $page2 = $this->repository->findExpiredChunked($now, 2, $afterCreatedAt, $afterId);
        $this->assertCount(2, $page2);

        $page1Ids = array_map(fn ($r) => $r->id(), $page1);
        $page2Ids = array_map(fn ($r) => $r->id(), $page2);

        $this->assertEmpty(array_intersect($page1Ids, $page2Ids), 'Pages must not overlap');
        $this->assertCount(4, array_unique(array_merge($page1Ids, $page2Ids)), 'All 4 reservations must be covered');
    }

    public function test_third_page_is_empty_when_all_records_consumed(): void
    {
        $now = new DateTimeImmutable;
        $expiresAt = now()->subMinutes(1)->toIso8601String();
        $base = now()->subHours(2);

        for ($i = 1; $i <= 4; $i++) {
            $createdAt = $base->addMinutes($i)->toIso8601String();
            $this->insertReservation("res-end-{$i}", ReservationStatus::PENDING_PAYMENT->value, $expiresAt, $createdAt);
        }

        $page1 = $this->repository->findExpiredChunked($now, 2);
        $last1 = end($page1);
        assert($last1 instanceof \Src\Ticketing\Domain\Model\Reservation);

        $page2 = $this->repository->findExpiredChunked(
            $now, 2,
            $last1->createdAt()->format(DateTimeImmutable::ATOM),
            $last1->id()
        );
        $last2 = end($page2);
        assert($last2 instanceof \Src\Ticketing\Domain\Model\Reservation);

        // Third call: no more records
        $page3 = $this->repository->findExpiredChunked(
            $now, 2,
            $last2->createdAt()->format(DateTimeImmutable::ATOM),
            $last2->id()
        );

        $this->assertSame([], $page3);
    }

    public function test_does_not_return_non_expired_reservations(): void
    {
        $future = now()->addHour()->toIso8601String();
        $past = now()->subHour()->toIso8601String();
        $now = new DateTimeImmutable;

        $this->insertReservation('res-future', ReservationStatus::PENDING_PAYMENT->value, $future, $past);

        $batch = $this->repository->findExpiredChunked($now, 100);

        $this->assertSame([], $batch);
    }

    public function test_it_returns_null_when_the_reservation_does_not_exist(): void
    {
        $this->assertNull($this->repository->find('res-unknown'));
    }

    public function test_it_maps_a_stored_reservation(): void
    {
        $this->insertReservation(
            'res-1',
            ReservationStatus::PENDING_PAYMENT->value,
            now()->addHour()->toIso8601String(),
            now()->toIso8601String()
        );

        $reservation = $this->repository->find('res-1');

        $this->assertNotNull($reservation);
        $this->assertSame('res-1', $reservation->id());
        $this->assertSame($this->eventId, $reservation->eventId());
        $this->assertSame($this->userId, $reservation->userId());
        $this->assertSame(ReservationStatus::PENDING_PAYMENT, $reservation->status());
        $this->assertSame(1000, $reservation->price()->amount());
        $this->assertSame('EUR', $reservation->price()->currency());
    }

    public function test_it_dispatches_the_reservation_paid_event_on_save(): void
    {
        Event::fake([ReservationPaid::class]);

        $seat = SeatModel::create([
            'event_id' => $this->eventId,
            'row' => 'A',
            'number' => rand(1, 9999),
            'price_amount' => 1000,
            'price_currency' => 'EUR',
        ]);

        $reservation = Reservation::create(
            $this->eventId,
            new SeatId($seat->id),
            $this->userId,
            new Money(1000, 'EUR'),
            'res-paid'
        );
        $reservation->markAsPaid();

        $this->repository->save($reservation);

        Event::assertDispatched(ReservationPaid::class, fn ($event) => $event->reservationId === 'res-paid');
    }

    public function test_it_dispatches_the_reservation_cancelled_event_on_save(): void
    {
        Event::fake([ReservationCancelled::class]);

        $seat = SeatModel::create([
            'event_id' => $this->eventId,
            'row' => 'A',
            'number' => rand(1, 9999),
            'price_amount' => 1000,
            'price_currency' => 'EUR',
        ]);

        $reservation = Reservation::create(
            $this->eventId,
            new SeatId($seat->id),
            $this->userId,
            new Money(1000, 'EUR'),
            'res-cancelled'
        );
        $reservation->cancel();

        $this->repository->save($reservation);

        Event::assertDispatched(ReservationCancelled::class, fn ($event) => $event->reservationId === 'res-cancelled');
    }
}
