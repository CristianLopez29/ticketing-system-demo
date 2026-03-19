<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Notifications;

use Illuminate\Support\Facades\Log;
use Src\Ticketing\Application\Ports\UserNotifier;

class LogUserNotifier implements UserNotifier
{
    public function notifyPaymentFailed(int $userId, string $reservationId, string $reason): void
    {
        Log::info("Notification sent to user {$userId}: Payment failed for reservation {$reservationId}. Reason: {$reason}");
    }
}
