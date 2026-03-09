<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;

class EloquentReservationRepository implements ReservationRepository
{
    public function save(Reservation $reservation): void
    {
        $data = $reservation->toArray();
        
        DB::table('reservations')->updateOrInsert(
            ['id' => $reservation->id()],
            [
                'event_id' => $data['event_id'],
                'seat_id' => $data['seat_id'],
                'user_id' => $data['user_id'],
                'status' => $data['status'],
                'price_amount' => $data['price_amount'],
                'price_currency' => $data['price_currency'],
                'expires_at' => $data['expires_at'],
                'created_at' => $data['created_at'],
                'updated_at' => now(),
            ]
        );
    }

    public function find(string $id): ?Reservation
    {
        $record = DB::table('reservations')->find($id);

        if (!$record) {
            return null;
        }

        return new Reservation(
            $record->id,
            $record->event_id,
            new SeatId($record->seat_id),
            $record->user_id,
            ReservationStatus::from($record->status),
            new Money($record->price_amount, $record->price_currency),
            new DateTimeImmutable($record->expires_at),
            new DateTimeImmutable($record->created_at)
        );
    }

    public function findAndLock(string $id): ?Reservation
    {
        $record = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();

        if (!$record) {
            return null;
        }

        return new Reservation(
            $record->id,
            $record->event_id,
            new SeatId($record->seat_id),
            $record->user_id,
            ReservationStatus::from($record->status),
            new Money($record->price_amount, $record->price_currency),
            new DateTimeImmutable($record->expires_at),
            new DateTimeImmutable($record->created_at)
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
            $reservations[] = new Reservation(
                $record->id,
                $record->event_id,
                new SeatId($record->seat_id),
                $record->user_id,
                ReservationStatus::from($record->status),
                new Money($record->price_amount, $record->price_currency),
                new DateTimeImmutable($record->expires_at),
                new DateTimeImmutable($record->created_at)
            );
        }

        return $reservations;
    }
}
