<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Listeners;

use Mockery;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditActor;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Infrastructure\Listeners\RecordAuditLogOnTicketSold;
use Tests\TestCase;

class RecordAuditLogOnTicketSoldTest extends TestCase
{
    public function test_it_records_an_audit_log_entry_for_the_issued_ticket(): void
    {
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(
                AuditAction::TicketIssued->value,
                'ticket',
                '5',
                AuditActor::System->value,
                ['event_id' => 42, 'user_id' => 7]
            );

        $listener = new RecordAuditLogOnTicketSold($auditLogger);
        $listener->handle(new TicketSold(42, new SeatId(5), 7));

        $this->addToAssertionCount(1);
    }
}
