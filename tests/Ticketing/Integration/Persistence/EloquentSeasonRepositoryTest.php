<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Model\Season;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonRepository;
use Tests\TestCase;

class EloquentSeasonRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentSeasonRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentSeasonRepository;
    }

    public function test_it_returns_null_when_the_season_does_not_exist(): void
    {
        $this->assertNull($this->repository->find(404));
    }

    public function test_it_maps_the_renewal_window_of_a_stored_season(): void
    {
        $previousSeasonId = $this->insertSeason('2025/26');
        $seasonId = $this->insertSeason('2026/27', $previousSeasonId, '2026-07-01 00:00:00', '2026-07-31 23:59:59');

        $season = $this->repository->find($seasonId);

        $this->assertNotNull($season);
        $this->assertSame($seasonId, $season->id());
        $this->assertSame('2026/27', $season->name());
        $this->assertSame($previousSeasonId, $season->previousSeasonId());
        $this->assertNotNull($season->renewalStartDate());
        $this->assertNotNull($season->renewalEndDate());
        $this->assertSame('2026-07-01', $season->renewalStartDate()->format('Y-m-d'));
    }

    public function test_it_maps_a_season_without_a_renewal_window(): void
    {
        $season = $this->repository->find($this->insertSeason('2026/27'));

        $this->assertNotNull($season);
        $this->assertNull($season->previousSeasonId());
        $this->assertNull($season->renewalStartDate());
        $this->assertNull($season->renewalEndDate());
    }

    public function test_it_returns_an_empty_list_when_there_are_no_seasons(): void
    {
        $this->assertSame([], $this->repository->findAll());
    }

    public function test_it_returns_every_stored_season(): void
    {
        $this->insertSeason('2025/26');
        $this->insertSeason('2026/27');

        $seasons = $this->repository->findAll();

        $this->assertCount(2, $seasons);
        $this->assertSame(['2025/26', '2026/27'], array_map(
            fn (Season $season) => $season->name(),
            $seasons
        ));
    }

    private function insertSeason(
        string $name,
        ?int $previousSeasonId = null,
        ?string $renewalStartDate = null,
        ?string $renewalEndDate = null,
    ): int {
        return DB::table('seasons')->insertGetId([
            'name' => $name,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'previous_season_id' => $previousSeasonId,
            'renewal_start_date' => $renewalStartDate,
            'renewal_end_date' => $renewalEndDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
