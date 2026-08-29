<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Model\Event;
use Src\Ticketing\Infrastructure\Persistence\EloquentEventRepository;
use Tests\TestCase;

/**
 * Integration tests for the events adapter against the real MySQL testing database.
 * The mapping is what the purchase flow reads its seat count from, so a silent
 * column rename has to fail here rather than at checkout.
 */
class EloquentEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentEventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentEventRepository;
    }

    public function test_it_returns_null_when_the_event_does_not_exist(): void
    {
        $this->assertNull($this->repository->find(404));
    }

    public function test_it_maps_a_stored_event(): void
    {
        $id = DB::table('events')->insertGetId([
            'name' => 'Concert',
            'total_seats' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = $this->repository->find($id);

        $this->assertNotNull($event);
        $this->assertSame($id, $event->id());
        $this->assertSame('Concert', $event->name());
        $this->assertSame(120, $event->totalSeats());
    }

    public function test_it_returns_an_empty_list_when_the_season_has_no_events(): void
    {
        $this->assertSame([], $this->repository->findBySeasonId($this->createSeason()));
    }

    public function test_it_finds_only_the_events_of_the_given_season(): void
    {
        $seasonId = $this->createSeason();
        $otherSeasonId = $this->createSeason();

        $this->insertEvent('Matchday 1', 100, $seasonId);
        $this->insertEvent('Matchday 2', 200, $seasonId);
        $this->insertEvent('Friendly', 50, $otherSeasonId);

        $events = $this->repository->findBySeasonId($seasonId);

        $this->assertCount(2, $events);
        $this->assertSame(['Matchday 1', 'Matchday 2'], array_map(
            fn (Event $event) => $event->name(),
            $events
        ));
        $this->assertSame([100, 200], array_map(
            fn (Event $event) => $event->totalSeats(),
            $events
        ));
    }

    public function test_it_inserts_an_event_that_is_not_stored_yet(): void
    {
        $this->repository->save(new Event(77, 'Premiere', 300));

        $this->assertDatabaseHas('events', [
            'id' => 77,
            'name' => 'Premiere',
            'total_seats' => 300,
        ]);
    }

    public function test_it_updates_an_event_that_already_exists(): void
    {
        $id = $this->insertEvent('Old name', 100, null);

        $this->repository->save(new Event($id, 'New name', 250));

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseHas('events', [
            'id' => $id,
            'name' => 'New name',
            'total_seats' => 250,
        ]);
    }

    private function insertEvent(string $name, int $totalSeats, ?int $seasonId): int
    {
        return DB::table('events')->insertGetId([
            'name' => $name,
            'total_seats' => $totalSeats,
            'season_id' => $seasonId,
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
