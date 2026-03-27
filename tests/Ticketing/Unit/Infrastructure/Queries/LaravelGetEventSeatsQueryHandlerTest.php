<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Queries;

use Closure;
use Mockery;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Src\Ticketing\Application\Queries\GetEventSeatsQuery;
use Src\Ticketing\Infrastructure\Queries\LaravelGetEventSeatsQueryHandler;
use Tests\TestCase;

/**
 * Unit tests for LaravelGetEventSeatsQueryHandler.
 *
 * Uses Mockery to avoid any dependency on the real cache driver
 * (the array driver in phpunit.xml does not support tags).
 *
 * DB facade is also mocked so no real database is needed.
 */
class LaravelGetEventSeatsQueryHandlerTest extends TestCase
{
    private LaravelGetEventSeatsQueryHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new LaravelGetEventSeatsQueryHandler();
    }

    public function test_it_writes_to_cache_with_correct_tags(): void
    {
        $query = new GetEventSeatsQuery(eventId: 10, afterSeatId: 0, perPage: 50);

        $expectedResult = ['data' => [], 'next_cursor' => null];

        // DB::table(...) chain mock
        $this->mockDbTableForEventSeats(10, 0, 50, []);

        // Cache mock: expect tags(['event-seats', 'event:10']) and key with pagination suffix
        $taggedCache = Mockery::mock(TaggedCache::class);
        $taggedCache->shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, Closure $cb) {
                return str_contains($key, 'after:0:per:50') && $ttl === 300;
            })
            ->andReturnUsing(fn ($key, $ttl, Closure $cb) => $cb());

        Cache::shouldReceive('tags')
            ->once()
            ->with(['event-seats', 'event:10'])
            ->andReturn($taggedCache);

        $result = $this->handler->handle($query);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('next_cursor', $result);
    }

    public function test_it_returns_cached_result_without_hitting_db(): void
    {
        $query          = new GetEventSeatsQuery(eventId: 7, afterSeatId: 0, perPage: 100);
        $cachedResult   = ['data' => [['id' => 1, 'row' => 'A']], 'next_cursor' => null];

        $taggedCache = Mockery::mock(TaggedCache::class);
        // remember() returns cached value directly, never executing the closure
        $taggedCache->shouldReceive('remember')
            ->once()
            ->andReturn($cachedResult);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['event-seats', 'event:7'])
            ->andReturn($taggedCache);

        // DB should NOT be called
        DB::shouldReceive('table')->never();

        $result = $this->handler->handle($query);

        $this->assertEquals($cachedResult, $result);
    }

    public function test_cache_key_contains_event_scoped_pagination_params(): void
    {
        $query = new GetEventSeatsQuery(eventId: 3, afterSeatId: 200, perPage: 25);

        $taggedCache = Mockery::mock(TaggedCache::class);
        $taggedCache->shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key) {
                // Key must encode afterSeatId and perPage
                return $key === 'seats_read_model:after:200:per:25';
            })
            ->andReturn(['data' => [], 'next_cursor' => null]);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['event-seats', 'event:3'])
            ->andReturn($taggedCache);

        $this->handler->handle($query);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mock DB::table('seats')->...->get() to return an empty collection.
     * Only needed when the cache closure actually executes.
     */
    private function mockDbTableForEventSeats(int $eventId, int $afterSeatId, int $perPage, array $rows): void
    {
        $collection = new Collection(array_map(fn ($r) => (object) $r, $rows));

        $queryBuilder = Mockery::mock('Illuminate\Database\Query\Builder');
        $queryBuilder->shouldReceive('where')->andReturnSelf();
        $queryBuilder->shouldReceive('when')->andReturnSelf();
        $queryBuilder->shouldReceive('select')->andReturnSelf();
        $queryBuilder->shouldReceive('orderBy')->andReturnSelf();
        $queryBuilder->shouldReceive('limit')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn($collection);

        DB::shouldReceive('table')
            ->once()
            ->with('seats')
            ->andReturn($queryBuilder);
    }
}
