<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditActor;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\TicketSold;

/**
 * Deferred to after commit: TicketSold is dispatched from inside the payment saga's
 * DB::transaction() closure, and an audit entry for a sale that then rolls back would
 * be a false record.
 */
class RecordAuditLogOnTicketSold implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(TicketSold $event): void
    {
        $this->auditLogger->log(
            AuditAction::TicketIssued->value,
            'ticket',
            (string) $event->seatId->value(),
            AuditActor::System->value,
            ['event_id' => $event->eventId, 'user_id' => $event->userId]
        );
    }
}
