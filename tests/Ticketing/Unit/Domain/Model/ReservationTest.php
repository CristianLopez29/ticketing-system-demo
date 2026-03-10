<?php

namespace Tests\Ticketing\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\Model\Reservation;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use DateTimeImmutable;
use RuntimeException;

class ReservationTest extends TestCase
{
    private function createReservation(int $durationMinutes = 5): Reservation
    {
        return Reservation::create(
            1, // eventId
            new SeatId(1),
            123, // userId
            new Money(100, 'USD'),
            $durationMinutes
        );
    }

    public function test_it_creates_pending_reservation(): void
    {
        $reservation = $this->createReservation();

        $this->assertEquals(ReservationStatus::PENDING_PAYMENT, $reservation->status());
        $this->assertFalse($reservation->isExpired());
    }

    public function test_it_can_be_marked_as_paid(): void
    {
        $reservation = $this->createReservation();
        
        $reservation->markAsPaid();
        
        $this->assertEquals(ReservationStatus::PAID, $reservation->status());
    }

    public function test_it_cannot_pay_expired_reservation(): void
    {
        $reservation = $this->createReservation(-5); // Expired 5 mins ago

        $this->assertTrue($reservation->isExpired());
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reservation has expired.');
        
        $reservation->markAsPaid();
    }

    public function test_it_cannot_pay_already_paid_reservation(): void
    {
        $reservation = $this->createReservation();
        $reservation->markAsPaid();
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot pay for a reservation that is not pending.');
        
        $reservation->markAsPaid();
    }

    public function test_it_can_be_cancelled(): void
    {
        $reservation = $this->createReservation();
        
        $reservation->cancel();
        
        $this->assertEquals(ReservationStatus::CANCELLED, $reservation->status());
    }

    public function test_it_cannot_cancel_paid_reservation(): void
    {
        $reservation = $this->createReservation();
        $reservation->markAsPaid();
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot cancel a paid reservation.');
        
        $reservation->cancel();
    }
}
