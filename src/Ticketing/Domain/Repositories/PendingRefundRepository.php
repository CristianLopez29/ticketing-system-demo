<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

interface PendingRefundRepository
{
    public function save(string $transactionId, string $reservationId, string $reason): void;
}
