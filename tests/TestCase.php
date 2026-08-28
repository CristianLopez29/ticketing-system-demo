<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    /**
     * Delete every Redis key matching the given patterns.
     *
     * Redis::keys() returns names with the connection prefix already applied and
     * Redis::del() applies it again, so feeding one straight into the other
     * deletes nothing. The prefix has to come off in between.
     */
    protected function forgetRedisKeys(string ...$patterns): void
    {
        $configuredPrefix = config('database.redis.options.prefix');
        $prefix = is_string($configuredPrefix) ? $configuredPrefix : '';

        foreach ($patterns as $pattern) {
            $this->deleteKeysMatching($pattern, $prefix);
        }
    }

    private function deleteKeysMatching(string $pattern, string $prefix): void
    {
        foreach (Redis::keys($pattern) as $prefixedKey) {
            Redis::del(str_starts_with($prefixedKey, $prefix) ? substr($prefixedKey, strlen($prefix)) : $prefixedKey);
        }
    }
}
