<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use InvalidArgumentException;
use Src\Shared\Domain\AggregateRoot;
use Src\Ticketing\Domain\Exceptions\InvalidStateException;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\ValueObjects\SeatId;

class Seat extends AggregateRoot
{
    private SeatId $id;

    private int $eventId;

    private string $row;

    private int $number;

    private Money $price;

    private ?int $reservedByUserId;

    public function __construct(
        SeatId $id,
        int $eventId,
        string $row,
        int $number,
        Money $price,
        ?int $reservedByUserId
    ) {
        if (trim($row) === '') {
            throw new InvalidArgumentException('Seat row cannot be empty.');
        }

        if ($number <= 0) {
            throw new InvalidArgumentException('Seat number must be a positive integer.');
        }

        $this->id = $id;
        $this->eventId = $eventId;
        $this->row = $row;
        $this->number = $number;
        $this->price = $price;
        $this->reservedByUserId = $reservedByUserId;
    }

    public function id(): SeatId
    {
        return $this->id;
    }

    public function eventId(): int
    {
        return $this->eventId;
    }

    public function row(): string
    {
        return $this->row;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function reservedByUserId(): ?int
    {
        return $this->reservedByUserId;
    }

    public function isAvailable(): bool
    {
        return $this->reservedByUserId === null;
    }

    public function reserve(int $userId): void
    {
        if (! $this->isAvailable()) {
            throw new SeatAlreadySoldException("Seat {$this->row}-{$this->number} is already sold.");
        }

        $this->reservedByUserId = $userId;
        // Event dispatch deferred to payment confirmation (Saga completion)
    }

    public function release(): void
    {
        if ($this->reservedByUserId === null) {
            throw new InvalidStateException("Seat {$this->row}-{$this->number} is not reserved.");
        }
        $this->reservedByUserId = null;
    }
}
