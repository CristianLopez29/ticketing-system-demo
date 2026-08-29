<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Listeners;

use Mockery;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditActor;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Ticketing\Domain\Events\ReservationPaid;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Infrastructure\Listeners\RecordAuditLogOnReservationPaid;
use Tests\TestCase;

class RecordAuditLogOnReservationPaidTest extends TestCase
{
    public function test_it_records_an_audit_log_entry_for_the_paid_reservation(): void
    {
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(
                AuditAction::ReservationPaid->value,
                'reservation',
                'res-1',
                AuditActor::System->value,
                ['event_id' => 42, 'seat_id' => 5, 'user_id' => 7]
            );

        $listener = new RecordAuditLogOnReservationPaid($auditLogger);
        $listener->handle(new ReservationPaid('res-1', 42, new SeatId(5), 7));

        $this->addToAssertionCount(1);
    }
}
