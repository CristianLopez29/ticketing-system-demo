<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use Src\Shared\Domain\AggregateRoot;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use DateTimeImmutable;
use RuntimeException;

class Reservation extends AggregateRoot
{
    public function __construct(
        private readonly string $id,
        private readonly int $eventId,
        private readonly SeatId $seatId,
        private readonly int $userId,
        private ReservationStatus $status,
        private readonly Money $price,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt
    ) {}

    public static function create(
        int $eventId,
        SeatId $seatId,
        int $userId,
        Money $price,
        int $durationMinutes = 5
    ): self {
        return new self(
            uniqid('res_', true),
            $eventId,
            $seatId,
            $userId,
            ReservationStatus::PENDING_PAYMENT,
            $price,
            (new DateTimeImmutable())->modify("+{$durationMinutes} minutes"),
            new DateTimeImmutable()
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function eventId(): int
    {
        return $this->eventId;
    }

    public function seatId(): SeatId
    {
        return $this->seatId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return new DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Finalize transaction (Saga success)
     */
    public function markAsPaid(): void
    {
        if ($this->status !== ReservationStatus::PENDING_PAYMENT) {
            throw new RuntimeException("Cannot pay for a reservation that is not pending.");
        }
        if ($this->isExpired()) {
            throw new RuntimeException("Reservation has expired.");
        }

        $this->status = ReservationStatus::PAID;
    }

    /**
     * Abort reservation (Saga compensation)
     */
    public function cancel(): void
    {
        if ($this->status === ReservationStatus::PAID) {
            throw new RuntimeException("Cannot cancel a paid reservation.");
        }

        $this->status = ReservationStatus::CANCELLED;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->eventId,
            'seat_id' => $this->seatId->value(),
            'user_id' => $this->userId,
            'status' => $this->status->value,
            'price_amount' => $this->price->amount(),
            'price_currency' => $this->price->currency(),
            'expires_at' => $this->expiresAt->format(DateTimeImmutable::ATOM),
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
