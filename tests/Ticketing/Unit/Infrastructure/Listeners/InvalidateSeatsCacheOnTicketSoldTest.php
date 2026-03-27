<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Listeners;

use Mockery;
use Mockery\MockInterface;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Infrastructure\Listeners\InvalidateSeatsCacheOnTicketSold;
use Tests\TestCase;

class InvalidateSeatsCacheOnTicketSoldTest extends TestCase
{
    public function test_it_flushes_tagged_cache_for_the_sold_event(): void
    {
        // Arrange: mock the TaggedCache that Cache::tags() returns
        $taggedCache = Mockery::mock(TaggedCache::class);
        $taggedCache->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['event:42'])
            ->andReturn($taggedCache);

        // Act
        $event    = new TicketSold(eventId: 42, seatId: new SeatId(1), userId: 7);
        $listener = new InvalidateSeatsCacheOnTicketSold();
        $listener->handle($event);

        // Assert is handled by Mockery expectations above
        $this->addToAssertionCount(1);
    }

    public function test_it_uses_event_id_tag_to_scope_the_flush(): void
    {
        // Ensure the tag matches the event id from the domain event
        $eventId     = 99;
        $taggedCache = Mockery::mock(TaggedCache::class);
        $taggedCache->shouldReceive('flush')->once()->andReturn(true);

        Cache::shouldReceive('tags')
            ->once()
            ->with(["event:{$eventId}"])
            ->andReturn($taggedCache);

        $event    = new TicketSold(eventId: $eventId, seatId: new SeatId(5), userId: 3);
        $listener = new InvalidateSeatsCacheOnTicketSold();
        $listener->handle($event);

        $this->addToAssertionCount(1);
    }
}
