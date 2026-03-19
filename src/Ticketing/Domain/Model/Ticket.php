<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class Ticket extends AggregateRoot
{
    public function __construct(
        private readonly string $id,
        private readonly int $eventId,
        private readonly SeatId $seatId,
        private readonly int $userId,
        private readonly Money $price,
        private readonly string $paymentReference,
        private readonly DateTimeImmutable $issuedAt
    ) {}

    public static function issue(
        int $eventId,
        SeatId $seatId,
        int $userId,
        Money $price,
        string $paymentReference,
        string $id
    ): self {
        $ticket = new self(
            $id,
            $eventId,
            $seatId,
            $userId,
            $price,
            $paymentReference,
            new DateTimeImmutable
        );

        $ticket->record(new \Src\Ticketing\Domain\Events\TicketSold($seatId, $userId));

        return $ticket;
    }



    public function id(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->eventId,
            'seat_id' => $this->seatId->value(),
            'user_id' => $this->userId,
            'price_amount' => $this->price->amount(),
            'price_currency' => $this->price->currency(),
            'payment_reference' => $this->paymentReference,
            'issued_at' => $this->issuedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
