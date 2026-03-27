<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Enums\ReservationStatus;
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
        $this->repository = new EloquentReservationRepository();

        $event         = EventModel::create(['name' => 'Test Event', 'total_seats' => 50]);
        $this->eventId = $event->id;
        $this->userId  = User::factory()->create()->id;
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
            'event_id'       => $this->eventId,
            'row'            => 'A',
            'number'         => rand(1, 9999),
            'price_amount'   => 1000,
            'price_currency' => 'EUR',
        ]);

        DB::table('reservations')->insert([
            'id'             => $id,
            'event_id'       => $this->eventId,
            'seat_id'        => $seat->id,
            'user_id'        => $this->userId,
            'status'         => $status,
            'price_amount'   => 1000,
            'price_currency' => 'EUR',
            'expires_at'     => $expiresAt,
            'created_at'     => $createdAt,
            'updated_at'     => $createdAt,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_returns_empty_array_when_no_expired_reservations(): void
    {
        $now    = new DateTimeImmutable();
        $result = $this->repository->findExpiredChunked($now, 100);

        $this->assertSame([], $result);
    }

    public function test_returns_only_pending_payment_expired_reservations(): void
    {
        $past = now()->subHour()->toIso8601String();
        $now  = new DateTimeImmutable();

        $this->insertReservation('res-pending', ReservationStatus::PENDING_PAYMENT->value, $past, $past);
        $this->insertReservation('res-paid',    ReservationStatus::PAID->value,            $past, $past);
        $this->insertReservation('res-cancelled', ReservationStatus::CANCELLED->value,     $past, $past);

        $batch = $this->repository->findExpiredChunked($now, 100);

        $this->assertCount(1, $batch);
        $this->assertSame('res-pending', $batch[0]->id());
    }

    public function test_respects_limit_per_batch(): void
    {
        $past = now()->subHour()->toIso8601String();
        $now  = new DateTimeImmutable();

        for ($i = 1; $i <= 5; $i++) {
            $this->insertReservation("res-{$i}", ReservationStatus::PENDING_PAYMENT->value, $past, $past);
        }

        $batch = $this->repository->findExpiredChunked($now, 2);

        $this->assertCount(2, $batch);
    }

    public function test_cursor_returns_next_page_without_overlap(): void
    {
        $now = new DateTimeImmutable();

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

        $last           = end($page1);
        $afterCreatedAt = $last->createdAt()->format(DateTimeImmutable::ATOM);
        $afterId        = $last->id();

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
        $now       = new DateTimeImmutable();
        $expiresAt = now()->subMinutes(1)->toIso8601String();
        $base      = now()->subHours(2);

        for ($i = 1; $i <= 4; $i++) {
            $createdAt = $base->addMinutes($i)->toIso8601String();
            $this->insertReservation("res-end-{$i}", ReservationStatus::PENDING_PAYMENT->value, $expiresAt, $createdAt);
        }

        $page1 = $this->repository->findExpiredChunked($now, 2);
        $last1 = end($page1);

        $page2 = $this->repository->findExpiredChunked(
            $now, 2,
            $last1->createdAt()->format(DateTimeImmutable::ATOM),
            $last1->id()
        );
        $last2 = end($page2);

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
        $past   = now()->subHour()->toIso8601String();
        $now    = new DateTimeImmutable();

        $this->insertReservation('res-future', ReservationStatus::PENDING_PAYMENT->value, $future, $past);

        $batch = $this->repository->findExpiredChunked($now, 100);

        $this->assertSame([], $batch);
    }
}
