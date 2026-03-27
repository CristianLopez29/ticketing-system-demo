<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class EloquentReservationRepository implements ReservationRepository
{
    public function save(Reservation $reservation): void
    {
        DB::table('reservations')->updateOrInsert(
            ['id' => $reservation->id()],
            [
                'event_id'       => $reservation->eventId(),
                'seat_id'        => $reservation->seatId()->value(),
                'user_id'        => $reservation->userId(),
                'status'         => $reservation->status()->value,
                'price_amount'   => $reservation->price()->amount(),
                'price_currency' => $reservation->price()->currency(),
                'expires_at'     => $reservation->expiresAt()->format(\DateTimeImmutable::ATOM),
                'created_at'     => $reservation->createdAt()->format(\DateTimeImmutable::ATOM),
                'updated_at'     => now(),
            ]
        );
    }

    public function find(string $id): ?Reservation
    {
        $record = DB::table('reservations')->find($id);

        if (! $record) {
            return null;
        }

        return $this->hydrate((array) $record);
    }

    public function findAndLock(string $id): ?Reservation
    {
        $record = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();

        if (! $record) {
            return null;
        }

        return $this->hydrate((array) $record);
    }

    /**
     * Cursor-based pagination over expired PENDING_PAYMENT reservations.
     *
     * Uses (created_at, id) as a composite cursor, which is stable and
     * chronologically meaningful — unlike UUID v4 which has no ordering semantics.
     *
     * Query:
     *   WHERE status = 'pending_payment'
     *     AND expires_at <= :now
     *     AND (created_at > :afterCreatedAt OR (created_at = :afterCreatedAt AND id > :afterId))
     *   ORDER BY created_at ASC, id ASC
     *   LIMIT :limit
     *
     * The composite index (status, expires_at, id) on the reservations table
     * ensures this query is efficient.
     *
     * @return Reservation[]
     */
    public function findExpiredChunked(
        DateTimeImmutable $now,
        int $limit,
        ?string $afterCreatedAt = null,
        ?string $afterId = null,
    ): array {
        $query = DB::table('reservations')
            ->where('status', ReservationStatus::PENDING_PAYMENT->value)
            ->where('expires_at', '<=', $now->format(\DateTimeImmutable::ATOM))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit);

        // Apply cursor when this is not the first page
        if ($afterCreatedAt !== null && $afterId !== null) {
            $query->where(function ($q) use ($afterCreatedAt, $afterId) {
                $q->where('created_at', '>', $afterCreatedAt)
                  ->orWhere(function ($q2) use ($afterCreatedAt, $afterId) {
                      $q2->where('created_at', '=', $afterCreatedAt)
                         ->where('id', '>', $afterId);
                  });
            });
        }

        $records = $query->get();

        $reservations = [];
        foreach ($records as $record) {
            $reservations[] = $this->hydrate((array) $record);
        }

        return $reservations;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function hydrate(array $data): Reservation
    {
        return new Reservation(
            (string) ($data['id'] ?? ''),
            (int) ($data['event_id'] ?? 0),
            new SeatId((int) ($data['seat_id'] ?? 0)),
            (int) ($data['user_id'] ?? 0),
            ReservationStatus::from((string) ($data['status'] ?? ReservationStatus::PENDING_PAYMENT->value)),
            new Money((int) ($data['price_amount'] ?? 0), (string) ($data['price_currency'] ?? 'EUR')),
            new DateTimeImmutable((string) ($data['expires_at'] ?? 'now')),
            new DateTimeImmutable((string) ($data['created_at'] ?? 'now'))
        );
    }
}
