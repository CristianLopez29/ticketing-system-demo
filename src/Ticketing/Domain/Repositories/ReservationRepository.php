<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use DateTimeImmutable;
use Src\Ticketing\Domain\Model\Reservation;

interface ReservationRepository
{
    public function save(Reservation $reservation): void;

    public function find(string $id): ?Reservation;

    public function findAndLock(string $id): ?Reservation;

    /**
     * Cursor-based pagination over expired reservations.
     *
     * Returns at most $limit reservations with status PENDING_PAYMENT
     * whose expires_at is at or before $now, ordered by (created_at ASC, id ASC).
     *
     * Pass the $afterCreatedAt and $afterId from the last record of the previous
     * batch to fetch the next page. Pass null for both on the first call.
     *
     * @return Reservation[]
     */
    public function findExpiredChunked(
        DateTimeImmutable $now,
        int $limit,
        ?string $afterCreatedAt = null,
        ?string $afterId = null,
    ): array;
}
