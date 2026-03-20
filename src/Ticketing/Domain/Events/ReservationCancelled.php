<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Events;

use Src\Shared\Domain\DomainEvent;
use Src\Ticketing\Domain\ValueObjects\SeatId;

readonly class ReservationCancelled implements DomainEvent
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        public string $reservationId,
        public int $eventId,
        public SeatId $seatId
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
