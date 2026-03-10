<?php

namespace Tests\Ticketing\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\ValueObjects\Money;

class MoneyTest extends TestCase
{
    public function test_it_creates_valid_money(): void
    {
        $money = new Money(1500, 'usd');
        $this->assertEquals(1500, $money->amount());
        $this->assertEquals('USD', $money->currency());
    }

    public function test_it_throws_exception_on_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money amount cannot be negative.');
        new Money(-10);
    }

    public function test_it_throws_exception_on_invalid_currency_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO code.');
        new Money(1500, 'US'); // Only 2 chars
    }
}
