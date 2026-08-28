<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies the Redis SET NX atomicity that RedisIdempotencyStore is built on:
 * two Cache::add() calls with the same key must succeed exactly once, which is
 * the whole idempotency guarantee of the purchase flow.
 *
 * It addresses the redis store explicitly rather than the default one. In
 * production and in .env.testing the default *is* redis, but phpunit.xml pins
 * the suite to the array store, and array semantics would prove nothing here.
 */
class RedisIdempotencyTest extends TestCase
{
    private Repository $redisCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisCache = Cache::store('redis');
    }

    public function test_it_only_lets_the_first_add_of_a_key_through(): void
    {
        $key = $this->uniqueKey();

        $first = $this->redisCache->add($key, 'processed', 60);
        $second = $this->redisCache->add($key, 'processed', 60);

        $this->assertTrue($first, 'First add() must return true (key did not exist)');
        $this->assertFalse($second, 'Second add() must return false (key already exists — SET NX semantics)');

        $this->redisCache->forget($key);
    }

    public function test_it_lets_a_key_through_again_once_its_ttl_expires(): void
    {
        $key = $this->uniqueKey();

        $this->assertTrue($this->redisCache->add($key, 'processed', 1));

        sleep(2); // Outlive the 1-second TTL

        $this->assertTrue(
            $this->redisCache->add($key, 'processed', 60),
            'After TTL expiry, add() must succeed again'
        );

        $this->redisCache->forget($key);
    }

    private function uniqueKey(): string
    {
        return 'idempotency:test:'.uniqid('', true);
    }
}
