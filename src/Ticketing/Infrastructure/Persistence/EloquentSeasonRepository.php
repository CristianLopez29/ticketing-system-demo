<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Model\Season;
use Src\Ticketing\Domain\Repositories\SeasonRepository;

class EloquentSeasonRepository implements SeasonRepository
{
    public function find(int $id): ?Season
    {
        $record = DB::table('seasons')->find($id);
        if (! $record) {
            return null;
        }

        return $this->mapToSeason($record);
    }

    public function findAll(): array
    {
        $records = DB::table('seasons')->get();
        $seasons = [];
        foreach ($records as $record) {
            $seasons[] = $this->mapToSeason($record);
        }

        return $seasons;
    }

    private function mapToSeason($record): Season
    {
        return new Season(
            $record->id,
            $record->name,
            new DateTimeImmutable($record->start_date),
            new DateTimeImmutable($record->end_date),
            $record->previous_season_id,
            $record->renewal_start_date ? new DateTimeImmutable($record->renewal_start_date) : null,
            $record->renewal_end_date ? new DateTimeImmutable($record->renewal_end_date) : null
        );
    }
}
