<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonTicketRepository;
use Tests\TestCase;

/**
 * Integration tests for the season-tickets adapter. The seat lookups are the
 * barrier against selling the same seat of a season twice, so they have to run
 * against the real database — including the cancelled-row exclusion.
 */
class EloquentSeasonTicketRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentSeasonTicketRepository $repository;

    private int $seasonId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentSeasonTicketRepository;
        $this->seasonId = $this->createSeason();
        $this->userId = User::factory()->create()->id;
    }

    public function test_it_returns_an_empty_list_when_the_user_has_no_season_tickets(): void
    {
        $this->assertSame([], $this->repository->findAllByUserAndSeason($this->userId, $this->seasonId));
    }

    public function test_it_finds_every_season_ticket_of_a_user_for_one_season(): void
    {
        $otherSeasonId = $this->createSeason();
        $otherUserId = User::factory()->create()->id;

        $this->insertSeasonTicket('st-1', $this->seasonId, $this->userId, 'A', 1);
        $this->insertSeasonTicket('st-2', $this->seasonId, $this->userId, 'A', 2);
        $this->insertSeasonTicket('st-3', $otherSeasonId, $this->userId, 'A', 1);
        $this->insertSeasonTicket('st-4', $this->seasonId, $otherUserId, 'B', 1);

        $tickets = $this->repository->findAllByUserAndSeason($this->userId, $this->seasonId);

        $this->assertCount(2, $tickets);
        $this->assertSame(['st-1', 'st-2'], array_map(
            fn (SeasonTicket $ticket) => $ticket->id(),
            $tickets
        ));
    }

    public function test_it_maps_every_field_of_a_stored_season_ticket(): void
    {
        $this->insertSeasonTicket('st-1', $this->seasonId, $this->userId, 'C', 12);

        $ticket = $this->repository->find('st-1');

        $this->assertNotNull($ticket);
        $this->assertSame('st-1', $ticket->id());
        $this->assertSame($this->seasonId, $ticket->seasonId());
        $this->assertSame($this->userId, $ticket->userId());
        $this->assertSame('C', $ticket->row());
        $this->assertSame(12, $ticket->number());
        $this->assertSame(15000, $ticket->price()->amount());
        $this->assertSame('EUR', $ticket->price()->currency());
        $this->assertSame(ReservationStatus::PENDING_PAYMENT, $ticket->status());
        $this->assertNotNull($ticket->expiresAt());
    }

    public function test_it_finds_the_season_ticket_holding_a_seat(): void
    {
        $this->insertSeasonTicket('st-1', $this->seasonId, $this->userId, 'A', 1);

        $ticket = $this->repository->findOneBySeasonAndSeat($this->seasonId, 'A', 1);

        $this->assertNotNull($ticket);
        $this->assertSame('st-1', $ticket->id());
    }

    public function test_it_reports_a_free_seat_when_nothing_holds_it(): void
    {
        $this->assertNull($this->repository->findOneBySeasonAndSeat($this->seasonId, 'A', 1));
    }

    public function test_a_cancelled_season_ticket_leaves_the_seat_free(): void
    {
        $this->insertSeasonTicket('st-1', $this->seasonId, $this->userId, 'A', 1, ReservationStatus::CANCELLED);

        $this->assertNull($this->repository->findOneBySeasonAndSeat($this->seasonId, 'A', 1));
        $this->assertNull($this->repository->findAndLockBySeasonAndSeat($this->seasonId, 'A', 1));
    }

    public function test_it_locks_the_season_ticket_holding_a_seat(): void
    {
        $this->insertSeasonTicket('st-1', $this->seasonId, $this->userId, 'A', 1, ReservationStatus::PAID);

        // lockForUpdate() requires an open transaction to have any meaning.
        $ticket = DB::transaction(
            fn () => $this->repository->findAndLockBySeasonAndSeat($this->seasonId, 'A', 1)
        );

        $this->assertNotNull($ticket);
        $this->assertSame('st-1', $ticket->id());
        $this->assertSame(ReservationStatus::PAID, $ticket->status());
    }

    public function test_it_locks_nothing_when_the_seat_is_free(): void
    {
        $ticket = DB::transaction(
            fn () => $this->repository->findAndLockBySeasonAndSeat($this->seasonId, 'Z', 99)
        );

        $this->assertNull($ticket);
    }

    private function insertSeasonTicket(
        string $id,
        int $seasonId,
        int $userId,
        string $row,
        int $number,
        ReservationStatus $status = ReservationStatus::PENDING_PAYMENT,
    ): void {
        DB::table('season_tickets')->insert([
            'id' => $id,
            'season_id' => $seasonId,
            'user_id' => $userId,
            'row' => $row,
            'number' => $number,
            'price_amount' => 15000,
            'price_currency' => 'EUR',
            'status' => $status->value,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSeason(): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => '2026/27',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
