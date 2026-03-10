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
}
