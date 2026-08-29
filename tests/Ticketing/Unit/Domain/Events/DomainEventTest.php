<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Domain\Events;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\DomainEvent;
use Src\Ticketing\Domain\Events\ReservationCancelled;
use Src\Ticketing\Domain\Events\ReservationPaid;
use Src\Ticketing\Domain\Events\SeasonTicketPaid;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\ValueObjects\SeatId;

/**
 * Every domain event has to carry the moment it happened: listeners and the audit
 * trail order events by it, not by the time they are handled.
 */
class DomainEventTest extends TestCase
{
    /**
     * @return array<string, array{DomainEvent}>
     */
    public static function events(): array
    {
        return [
            'ticket sold' => [new TicketSold(1, new SeatId(7), 42)],
            'reservation paid' => [new ReservationPaid('res-1', 1, new SeatId(7), 42)],
            'reservation cancelled' => [new ReservationCancelled('res-1', 1, new SeatId(7))],
            'season ticket paid' => [new SeasonTicketPaid('st-1', 1, 42)],
        ];
    }

    #[DataProvider('events')]
    public function test_it_records_when_it_occurred(DomainEvent $event): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredOn());
        $this->assertLessThanOrEqual(new DateTimeImmutable, $event->occurredOn());
    }
}
