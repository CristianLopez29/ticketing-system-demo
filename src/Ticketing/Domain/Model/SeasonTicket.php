<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Exceptions\InvalidStateException;
use Src\Ticketing\Domain\ValueObjects\Money;

class SeasonTicket extends AggregateRoot
{
    public function __construct(
        private readonly string $id,
        private readonly int $seasonId,
        private readonly int $userId,
        private readonly string $row,
        private readonly int $number,
        private readonly Money $price,
        private ReservationStatus $status,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function seasonId(): int
    {
        return $this->seasonId;
    }

    public function userId(): int
    {
        return $this->userId;
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

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPaid(): bool
    {
        return $this->status === ReservationStatus::PAID;
    }

    public function pay(): void
    {
        if ($this->status !== ReservationStatus::PENDING_PAYMENT) {
            throw new InvalidStateException('Cannot pay for a non-pending season ticket.');
        }
        $this->status = ReservationStatus::PAID;
    }

    public function cancel(): void
    {
        if ($this->status === ReservationStatus::PAID) {
            throw new InvalidStateException('Cannot cancel a paid season ticket.');
        }

        if ($this->status === ReservationStatus::CANCELLED) {
            throw new InvalidStateException('Season ticket is already cancelled.');
        }

        $this->status = ReservationStatus::CANCELLED;
    }
}
