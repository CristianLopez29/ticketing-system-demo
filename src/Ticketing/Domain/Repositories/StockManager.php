<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

interface StockManager
{
    /**
     * Atomically checks and decrements stock. Returns true if successful (seat available), false if Sold Out.
     */
    public function attemptToReserve(int $eventId): bool;

    /**
     * Reverts a stock decrement operation.
     */
    public function revertReservation(int $eventId): void;

    /**
     * Initializes the stock for an event. Used during seeding or event creation.
     */
    public function setStock(int $eventId, int $stock): void;
}
