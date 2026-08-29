<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Listeners;

use Mockery;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\SeasonTicketPaid;
use Src\Ticketing\Infrastructure\Listeners\RecordAuditLogOnSeasonTicketPaid;
use Tests\TestCase;

class RecordAuditLogOnSeasonTicketPaidTest extends TestCase
{
    public function test_it_records_an_audit_log_entry_attributed_to_the_paying_user(): void
    {
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(
                AuditAction::SeasonTicketPaid->value,
                'season_ticket',
                'st-1',
                '7',
                ['season_id' => 3]
            );

        $listener = new RecordAuditLogOnSeasonTicketPaid($auditLogger);
        $listener->handle(new SeasonTicketPaid('st-1', 3, 7));

        $this->addToAssertionCount(1);
    }
}
