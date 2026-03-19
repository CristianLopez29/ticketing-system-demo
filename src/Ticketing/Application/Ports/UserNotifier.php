<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface UserNotifier
{
    public function notifyPaymentFailed(int $userId, string $reservationId, string $reason): void;
}
