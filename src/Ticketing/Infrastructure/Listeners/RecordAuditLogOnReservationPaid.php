<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditActor;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\ReservationPaid;

class RecordAuditLogOnReservationPaid implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(ReservationPaid $event): void
    {
        $this->auditLogger->log(
            AuditAction::ReservationPaid->value,
            'reservation',
            $event->reservationId,
            AuditActor::System->value,
            ['event_id' => $event->eventId, 'seat_id' => $event->seatId->value(), 'user_id' => $event->userId]
        );
    }
}
