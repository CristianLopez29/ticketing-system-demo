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
                'event_id' => $reservation->eventId(),
                'seat_id' => $reservation->seatId()->value(),
                'user_id' => $reservation->userId(),
                'status' => $reservation->status()->value,
                'price_amount' => $reservation->price()->amount(),
                'price_currency' => $reservation->price()->currency(),
                'expires_at' => $reservation->expiresAt()->format(\DateTimeImmutable::ATOM),
                'created_at' => $reservation->createdAt()->format(\DateTimeImmutable::ATOM),
                'updated_at' => now(),
            ]
        );
    }

    public function find(string $id): ?Reservation
    {
        $record = DB::table('reservations')->find($id);

        if (! $record) {
            return null;
        }

        $data = (array) $record;

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

    public function findAndLock(string $id): ?Reservation
    {
        $record = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();

        if (! $record) {
            return null;
        }

        $data = (array) $record;

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

    /**
     * @return Reservation[]
     */
    public function findExpired(DateTimeImmutable $now): array
    {
        $records = DB::table('reservations')
            ->where('status', ReservationStatus::PENDING_PAYMENT->value)
            ->where('expires_at', '<=', $now)
            ->get();

        $reservations = [];
        foreach ($records as $record) {
            $data = (array) $record;
            $reservations[] = new Reservation(
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

        return $reservations;
    }

    /**
     * @return Reservation[]
     */
    public function findExpiredChunked(DateTimeImmutable $now, int $limit, string $afterId = ''): array
    {
        $query = DB::table('reservations')
            ->where('status', ReservationStatus::PENDING_PAYMENT->value)
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->limit($limit);

        if ($afterId !== '') {
            $query->where('id', '>', $afterId);
        }

        $records = $query->get();

        $reservations = [];
        foreach ($records as $record) {
            $data = (array) $record;
            $reservations[] = new Reservation(
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

        return $reservations;
    }
}
