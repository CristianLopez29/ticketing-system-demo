<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\DTOs;

readonly class PurchaseSeasonTicketRequestDTO
{
    public function __construct(
        public int $seasonId,
        public int $userId,
        public string $row,
        public int $number,
        public string $idempotencyKey
    ) {}
}
