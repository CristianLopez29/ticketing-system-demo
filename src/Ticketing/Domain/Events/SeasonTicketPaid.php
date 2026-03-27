<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Events;

use Src\Shared\Domain\DomainEvent;

class SeasonTicketPaid implements DomainEvent
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $seasonTicketId,
        public readonly int $seasonId,
        public readonly int $userId
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
