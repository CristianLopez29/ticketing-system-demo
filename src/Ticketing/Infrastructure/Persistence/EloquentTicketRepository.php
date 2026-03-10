<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Src\Ticketing\Domain\Model\Seat;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;

class EloquentTicketRepository implements TicketRepository
{
    public function findAndLock(SeatId $id): ?Seat
    {
        // Enforce pessimistic lock (SELECT ... FOR UPDATE)
        $record = DB::table('seats')->where('id', $id->value())->lockForUpdate()->first();

        if (!$record) {
            return null;
        }

        return $this->mapToSeat($record);
    }

    public function findAndLockByLocation(int $eventId, string $row, int $number): ?Seat
    {
        $record = DB::table('seats')
            ->where('event_id', $eventId)
            ->where('row', $row)
            ->where('number', $number)
            ->lockForUpdate()
            ->first();

        if (!$record) {
            return null;
        }

        return $this->mapToSeat($record);
    }

    private function mapToSeat($record): Seat
    {
        return new Seat(
            new SeatId($record->id),
            $record->event_id,
            $record->row,
            $record->number,
            new Money($record->price_amount, $record->price_currency),
            $record->reserved_by_user_id
        );
    }

    public function save(Seat $seat): void
    {
        DB::table('seats')->where('id', $seat->id()->value())->update([
            'reserved_by_user_id' => $seat->reservedByUserId(),
            'updated_at' => now(),
        ]);
    }

    public function saveTicket(Ticket $ticket): void
    {
        $data = $ticket->toArray();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('tickets')->insert($data);
    }
}
