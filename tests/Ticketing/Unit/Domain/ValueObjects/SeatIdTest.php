<?php

namespace Tests\Ticketing\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class SeatIdTest extends TestCase
{
    public function test_it_creates_valid_seat_id(): void
    {
        $id = new SeatId(123);
        $this->assertEquals(123, $id->value());
    }

    public function test_it_throws_exception_on_zero_or_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seat ID must be a positive integer.');
        new SeatId(0);
    }

    public function test_it_compares_equality_correctly(): void
    {
        $id1 = new SeatId(1);
        $id2 = new SeatId(1);
        $id3 = new SeatId(2);

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
