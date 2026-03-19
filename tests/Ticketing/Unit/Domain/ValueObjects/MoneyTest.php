<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Ticketing\Domain\ValueObjects\Money;

class MoneyTest extends TestCase
{
    public function test_it_can_be_created(): void
    {
        $money = new Money(1000, 'USD');

        $this->assertEquals(1000, $money->amount());
        $this->assertEquals('USD', $money->currency());
    }

    public function test_it_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-100, 'USD');
    }

    public function test_it_can_add_money(): void
    {
        $money = new Money(1000, 'USD');
        $other = new Money(500, 'USD');

        $result = $money->add($other);

        $this->assertEquals(1500, $result->amount());
        $this->assertEquals('USD', $result->currency());
    }

    public function test_it_cannot_add_different_currencies(): void
    {
        $money = new Money(1000, 'USD');
        $other = new Money(500, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $money->add($other);
    }

    public function test_it_can_subtract_money(): void
    {
        $money = new Money(1000, 'USD');
        $other = new Money(500, 'USD');

        $result = $money->subtract($other);

        $this->assertEquals(500, $result->amount());
        $this->assertEquals('USD', $result->currency());
    }

    public function test_it_can_check_equality(): void
    {
        $money = new Money(1000, 'USD');
        $same = new Money(1000, 'USD');
        $differentAmount = new Money(500, 'USD');
        $differentCurrency = new Money(1000, 'EUR');

        $this->assertTrue($money->equals($same));
        $this->assertFalse($money->equals($differentAmount));
        $this->assertFalse($money->equals($differentCurrency));
    }

    public function test_it_can_check_if_zero(): void
    {
        $money = new Money(0, 'USD');
        $notZero = new Money(100, 'USD');

        $this->assertTrue($money->isZero());
        $this->assertFalse($notZero->isZero());
    }
}
