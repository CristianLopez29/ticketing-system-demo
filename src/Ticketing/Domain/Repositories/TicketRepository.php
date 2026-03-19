<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use Src\Ticketing\Domain\Model\Ticket;

interface TicketRepository
{
    public function saveTicket(Ticket $ticket): void;
}
