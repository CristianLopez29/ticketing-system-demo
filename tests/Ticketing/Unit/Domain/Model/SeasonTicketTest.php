<?php

namespace Tests\Ticketing\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use DateTimeImmutable;
use RuntimeException;

class SeasonTicketTest extends TestCase
{
    private function createSeasonTicket(): SeasonTicket
    {
        return new SeasonTicket(
            'st_123',
            1, // seasonId
            123, // userId
            'A',
            1,
            new Money(1000, 'USD'),
            ReservationStatus::PENDING_PAYMENT,
            (new DateTimeImmutable())->modify('+1 year'),
            new DateTimeImmutable()
        );
    }

    public function test_it_can_be_paid(): void
    {
        $ticket = $this->createSeasonTicket();
        
        $this->assertFalse($ticket->isPaid());
        
        $ticket->pay();
        
        $this->assertTrue($ticket->isPaid());
        $this->assertEquals(ReservationStatus::PAID, $ticket->status());
    }

    public function test_it_cannot_pay_twice(): void
    {
        $ticket = $this->createSeasonTicket();
        $ticket->pay();
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot pay for a non-pending season ticket.');
        
        $ticket->pay();
    }

    public function test_it_can_be_cancelled(): void
    {
        $ticket = $this->createSeasonTicket();
        
        $ticket->cancel();
        
        $this->assertEquals(ReservationStatus::CANCELLED, $ticket->status());
    }
}
