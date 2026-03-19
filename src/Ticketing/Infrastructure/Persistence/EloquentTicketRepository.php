<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as LaravelEvent;
use Src\Ticketing\Domain\Model\Ticket;
use Src\Ticketing\Domain\Repositories\TicketRepository;

class EloquentTicketRepository implements TicketRepository
{
    public function saveTicket(Ticket $ticket): void
    {
        $data = [
            'id' => $ticket->id(),
            'event_id' => $ticket->eventId(),
            'seat_id' => $ticket->seatId()->value(),
            'user_id' => $ticket->userId(),
            'price_amount' => $ticket->price()->amount(),
            'price_currency' => $ticket->price()->currency(),
            'payment_reference' => $ticket->paymentReference(),
            'issued_at' => $ticket->issuedAt()->format(\DateTimeImmutable::ATOM),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('tickets')->insert($data);

        foreach ($ticket->pullDomainEvents() as $domainEvent) {
            LaravelEvent::dispatch($domainEvent);
        }
    }
}
