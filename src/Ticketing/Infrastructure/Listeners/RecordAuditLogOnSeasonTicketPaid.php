<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\SeasonTicketPaid;

/**
 * Unlike the reservation/ticket events, this one runs synchronously in the request
 * that pays for the season ticket, so the paying user is a real actor, not "system".
 */
class RecordAuditLogOnSeasonTicketPaid implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(SeasonTicketPaid $event): void
    {
        $this->auditLogger->log(
            AuditAction::SeasonTicketPaid->value,
            'season_ticket',
            $event->seasonTicketId,
            (string) $event->userId,
            ['season_id' => $event->seasonId]
        );
    }
}
