<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\ValueObjects;

use InvalidArgumentException;

readonly class SeatId
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Seat ID must be a positive integer.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(SeatId $other): bool
    {
        return $this->value === $other->value();
    }
}
