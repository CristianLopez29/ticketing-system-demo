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

    private function mapToEntity(mixed $record): SeasonTicket
    {
        $record = (array) $record;
        $expiresAtValue = $record['expires_at'] ?? null;
        $expiresAt = is_string($expiresAtValue) ? $expiresAtValue : null;

        $idValue = $record['id'] ?? null;
        $id = is_string($idValue) ? $idValue : '';

        $seasonIdValue = $record['season_id'] ?? null;
        $seasonId = is_numeric($seasonIdValue) ? (int) $seasonIdValue : 0;

        $userIdValue = $record['user_id'] ?? null;
        $userId = is_numeric($userIdValue) ? (int) $userIdValue : 0;

        $rowValue = $record['row'] ?? null;
        $row = is_string($rowValue) ? $rowValue : '';

        $numberValue = $record['number'] ?? null;
        $number = is_numeric($numberValue) ? (int) $numberValue : 0;

        $priceAmountValue = $record['price_amount'] ?? null;
        $priceAmount = is_numeric($priceAmountValue) ? (int) $priceAmountValue : 0;

        $priceCurrencyValue = $record['price_currency'] ?? null;
        $priceCurrency = is_string($priceCurrencyValue) ? $priceCurrencyValue : 'EUR';

        $statusValue = $record['status'] ?? null;
        $status = is_string($statusValue) ? $statusValue : ReservationStatus::PENDING_PAYMENT->value;

        $createdAtValue = $record['created_at'] ?? null;
        $createdAt = is_string($createdAtValue) ? $createdAtValue : 'now';

        return new SeasonTicket(
            $id,
            $seasonId,
            $userId,
            $row,
            $number,
            new Money($priceAmount, $priceCurrency),
            ReservationStatus::from($status),
            $expiresAt ? new DateTimeImmutable($expiresAt) : null,
            new DateTimeImmutable($createdAt)
        );
    }
}
