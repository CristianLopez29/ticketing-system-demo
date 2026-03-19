<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Ports;

interface UserNotifier
{
    public function notifyPaymentFailed(int $userId, string $reservationId, string $reason): void;
}
