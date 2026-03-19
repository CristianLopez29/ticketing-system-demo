<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as LaravelEvent;
use Src\Ticketing\Domain\Model\Seat;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class EloquentTicketRepository implements TicketRepository
{
    public function findAndLock(SeatId $id): ?Seat
    {
        // Enforce pessimistic lock (SELECT ... FOR UPDATE)
        $record = DB::table('seats')->where('id', $id->value())->lockForUpdate()->first();

        if (! $record) {
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

        if (! $record) {
            return null;
        }

        return $this->mapToSeat($record);
    }

    private function mapToSeat(mixed $record): Seat
    {
        $record = (array) $record;
        $reservedByUserIdValue = $record['reserved_by_user_id'] ?? null;
        $reservedByUserId = is_numeric($reservedByUserIdValue) ? (int) $reservedByUserIdValue : null;

        $idValue = $record['id'] ?? null;
        $id = is_numeric($idValue) ? (int) $idValue : 0;

        $eventIdValue = $record['event_id'] ?? null;
        $eventId = is_numeric($eventIdValue) ? (int) $eventIdValue : 0;

        $rowValue = $record['row'] ?? null;
        $row = is_string($rowValue) ? $rowValue : '';

        $numberValue = $record['number'] ?? null;
        $number = is_numeric($numberValue) ? (int) $numberValue : 0;

        $priceAmountValue = $record['price_amount'] ?? null;
        $priceAmount = is_numeric($priceAmountValue) ? (int) $priceAmountValue : 0;

        $priceCurrencyValue = $record['price_currency'] ?? null;
        $priceCurrency = is_string($priceCurrencyValue) ? $priceCurrencyValue : 'EUR';

        return new Seat(
            new SeatId($id),
            $eventId,
            $row,
            $number,
            new Money($priceAmount, $priceCurrency),
            $reservedByUserId
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
        $data = [
            'id' => $ticket->id(),
            'event_id' => $ticket->eventId(),
            'seat_id' => $ticket->seatId()->value(),
            'user_id' => $ticket->userId(),
            'price_amount' => $ticket->price()->amount(),
            'price_currency' => $ticket->price()->currency(),
            'payment_reference' => $ticket->paymentReference(),
            'issued_at' => $ticket->issuedAt()->format(\DateTimeImmutable::ATOM),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('tickets')->insert($data);

        foreach ($ticket->pullDomainEvents() as $domainEvent) {
            LaravelEvent::dispatch($domainEvent);
        }
    }
}
