<?php

namespace Tests\Ticketing\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\Model\Seat;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use InvalidArgumentException;

class SeatTest extends TestCase
{
    private function createValidSeat(): Seat
    {
        return new Seat(
            new SeatId(1),
            100, // event ID
            'A', // row
            10,  // number
            new Money(5000, 'USD'), // 50 dollars
            null // reservedByUserId
        );
    }

    public function test_it_creates_valid_seat(): void
    {
        $seat = $this->createValidSeat();
        
        $this->assertEquals(1, $seat->id()->value());
        $this->assertEquals(100, $seat->eventId());
        $this->assertEquals('A', $seat->row());
        $this->assertEquals(10, $seat->number());
        $this->assertEquals(5000, $seat->price()->amount());
        $this->assertTrue($seat->isAvailable());
        $this->assertNull($seat->reservedByUserId());
    }

    public function test_it_throws_exception_on_empty_row(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seat row cannot be empty.');
        
        new Seat(new SeatId(1), 100, '  ', 10, new Money(5000), null);
    }

    public function test_it_throws_exception_on_zero_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seat number must be a positive integer.');
        
        new Seat(new SeatId(1), 100, 'A', 0, new Money(5000), null);
    }

    public function test_it_can_be_reserved(): void
    {
        $seat = $this->createValidSeat();
        
        $seat->reserve(999);
        
        $this->assertFalse($seat->isAvailable());
        $this->assertEquals(999, $seat->reservedByUserId());
    }

    public function test_it_throws_exception_when_reserved_twice(): void
    {
        $seat = $this->createValidSeat();
        
        $seat->reserve(999);
        
        $this->expectException(SeatAlreadySoldException::class);
        $this->expectExceptionMessage('Seat A-10 is already sold.');
        
        $seat->reserve(1000); // Try to reserve again
    }
}
