<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use Src\Ticketing\Domain\Model\Seat;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\ValueObjects\SeatId;

interface TicketRepository
{
    public function findAndLock(SeatId $id): ?Seat;

    public function findAndLockByLocation(int $eventId, string $row, int $number): ?Seat;

    public function save(Seat $seat): void;

    public function saveTicket(Ticket $ticket): void;
}
