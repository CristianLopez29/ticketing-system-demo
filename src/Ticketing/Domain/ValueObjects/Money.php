<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Money
{
    public function __construct(
        private int $amount, // internal storage in cents to avoid float precision issues
        private string $currency = 'USD'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return strtoupper($this->currency);
    }

    public function add(Money $other): self
    {
        if ($this->currency() !== $other->currency()) {
            throw new InvalidArgumentException('Cannot add money with different currencies.');
        }

        return new self($this->amount + $other->amount(), $this->currency);
    }

    public function subtract(Money $other): self
    {
        if ($this->currency() !== $other->currency()) {
            throw new InvalidArgumentException('Cannot subtract money with different currencies.');
        }

        if ($this->amount < $other->amount()) {
            throw new InvalidArgumentException('Cannot subtract: result would be negative.');
        }

        return new self($this->amount - $other->amount(), $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount() && $this->currency() === $other->currency();
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function multiply(float $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier cannot be negative.');
        }

        $newAmount = (int) round($this->amount * $multiplier);

        return new self($newAmount, $this->currency);
    }

    public function applyDiscountPercent(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Discount percent must be between 0 and 100.');
        }

        $multiplier = 1 - ($percent / 100);

        return $this->multiply($multiplier);
    }
}
