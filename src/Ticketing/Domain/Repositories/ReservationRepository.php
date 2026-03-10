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
     * @return Reservation[]
     */
    public function findExpired(DateTimeImmutable $now): array;
}
