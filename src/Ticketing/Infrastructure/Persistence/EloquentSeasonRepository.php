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

    private function mapToSeason(mixed $record): Season
    {
        $record = (array) $record;
        $previousSeasonIdValue = $record['previous_season_id'] ?? null;
        $previousSeasonId = is_numeric($previousSeasonIdValue) ? (int) $previousSeasonIdValue : null;

        $renewalStartDateValue = $record['renewal_start_date'] ?? null;
        $renewalStartDate = is_string($renewalStartDateValue) ? $renewalStartDateValue : null;

        $renewalEndDateValue = $record['renewal_end_date'] ?? null;
        $renewalEndDate = is_string($renewalEndDateValue) ? $renewalEndDateValue : null;

        $idValue = $record['id'] ?? null;
        $id = is_numeric($idValue) ? (int) $idValue : 0;

        $nameValue = $record['name'] ?? null;
        $name = is_string($nameValue) ? $nameValue : '';

        $startDateValue = $record['start_date'] ?? null;
        $startDate = is_string($startDateValue) ? $startDateValue : 'now';

        $endDateValue = $record['end_date'] ?? null;
        $endDate = is_string($endDateValue) ? $endDateValue : 'now';

        return new Season(
            $id,
            $name,
            new DateTimeImmutable($startDate),
            new DateTimeImmutable($endDate),
            $previousSeasonId,
            $renewalStartDate ? new DateTimeImmutable($renewalStartDate) : null,
            $renewalEndDate ? new DateTimeImmutable($renewalEndDate) : null
        );
    }
}
