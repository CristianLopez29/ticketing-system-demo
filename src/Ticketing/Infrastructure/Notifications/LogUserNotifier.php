<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Notifications;

use Illuminate\Support\Facades\Log;
use Src\Ticketing\Domain\Ports\UserNotifier;

class LogUserNotifier implements UserNotifier
{
    public function notifyPaymentFailed(int $userId, string $reservationId, string $reason): void
    {
        Log::warning('Payment failed', [
            'user_id' => $userId,
            'reservation_id' => $reservationId,
            'reason' => $reason,
        ]);
    }
}
