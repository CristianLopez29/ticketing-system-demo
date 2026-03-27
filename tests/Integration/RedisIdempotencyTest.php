<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies that RedisIdempotencyStore relies on Redis SET NX atomicity.
 *
 * Run with CACHE_STORE=redis (Redis must be available — run inside Docker):
 *   php artisan test --testsuite=Integration
 *
 * This test is intentionally simple: it verifies that two sequential Cache::add()
 * calls with the same key only succeed once, which is the foundation of the
 * idempotency guarantee in production.
 */
class RedisIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('This test requires CACHE_STORE=redis. Run with: CACHE_STORE=redis php artisan test --testsuite=Integration');
        }
    }

    public function test_cache_add_is_atomic_only_first_call_succeeds(): void
    {
        $key = 'idempotency:test:' . uniqid('', true);

        $first  = Cache::add($key, 'processed', 60);
        $second = Cache::add($key, 'processed', 60);

        $this->assertTrue($first, 'First add() must return true (key did not exist)');
        $this->assertFalse($second, 'Second add() must return false (key already exists — SET NX semantics)');

        Cache::forget($key);
    }

    public function test_cache_add_returns_true_after_expiry(): void
    {
        $key = 'idempotency:test:' . uniqid('', true);

        $first = Cache::add($key, 'processed', 1); // 1-second TTL
        $this->assertTrue($first);

        sleep(2); // Let key expire

        $second = Cache::add($key, 'processed', 60);
        $this->assertTrue($second, 'After TTL expiry, add() must succeed again');

        Cache::forget($key);
    }
}
