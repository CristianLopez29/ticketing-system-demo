<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;

class EloquentSeasonTicketRepository implements SeasonTicketRepository
{
    public function save(SeasonTicket $seasonTicket): void
    {
        DB::table('season_tickets')->updateOrInsert(
            ['id' => $seasonTicket->id()],
            [
                'season_id' => $seasonTicket->seasonId(),
                'user_id' => $seasonTicket->userId(),
                'row' => $seasonTicket->row(),
                'number' => $seasonTicket->number(),
                'price_amount' => $seasonTicket->price()->amount(),
                'price_currency' => $seasonTicket->price()->currency(),
                'status' => $seasonTicket->status()->value,
                'expires_at' => $seasonTicket->expiresAt(),
                'created_at' => $seasonTicket->createdAt(),
                'updated_at' => now(),
            ]
        );
    }

    public function find(string $id): ?SeasonTicket
    {
        $record = DB::table('season_tickets')->find($id);
        if (! $record) {
            return null;
        }

        return $this->mapToEntity($record);
    }

    public function findAllByUserAndSeason(int $userId, int $seasonId): array
    {
        $records = DB::table('season_tickets')
            ->where('user_id', $userId)
            ->where('season_id', $seasonId)
            ->get();

        $tickets = [];
        foreach ($records as $record) {
            $tickets[] = $this->mapToEntity($record);
        }

        return $tickets;
    }

    public function findOneBySeasonAndSeat(int $seasonId, string $row, int $number): ?SeasonTicket
    {
        $record = DB::table('season_tickets')
            ->where('season_id', $seasonId)
            ->where('row', $row)
            ->where('number', $number)
            ->where('status', '!=', ReservationStatus::CANCELLED->value)
            ->first();

        if (! $record) {
            return null;
        }

        return $this->mapToEntity($record);
    }

    private function mapToEntity($record): SeasonTicket
    {
        return new SeasonTicket(
            $record->id,
            $record->season_id,
            $record->user_id,
            $record->row,
            $record->number,
            new Money($record->price_amount, $record->price_currency),
            ReservationStatus::from($record->status),
            $record->expires_at ? new DateTimeImmutable($record->expires_at) : null,
            new DateTimeImmutable($record->created_at)
        );
    }
}
