<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\DTOs;

use Src\Ticketing\Domain\ValueObjects\SeatId;

readonly class PurchaseTicketRequestDTO
{
    public function __construct(
        public int $eventId,
        public SeatId $seatId,
        public int $userId,
        public string $idempotencyKey
    ) {}
}
