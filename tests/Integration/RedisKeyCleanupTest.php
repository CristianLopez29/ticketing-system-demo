<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Pins the behaviour of TestCase::forgetRedisKeys against the real Redis.
 *
 * Several suites rely on it to scrub only their own key prefixes between tests
 * instead of flushing a Redis shared with the rest of CI, so it has to actually
 * delete — the loop it replaced silently did not.
 */
class RedisKeyCleanupTest extends TestCase
{
    private const KEY = 'event:999999:stock';

    public function test_it_deletes_the_keys_matching_a_pattern(): void
    {
        Redis::set(self::KEY, '7');

        $this->forgetRedisKeys('event:999999:*');

        $this->assertNull(Redis::get(self::KEY));
    }

    public function test_it_leaves_keys_outside_the_pattern_alone(): void
    {
        Redis::set(self::KEY, '7');

        $this->forgetRedisKeys('purchase:idempotency:*');

        $this->assertSame('7', Redis::get(self::KEY));

        Redis::del(self::KEY);
    }
}
