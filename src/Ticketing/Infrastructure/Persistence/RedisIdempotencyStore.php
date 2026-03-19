<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Application\Ports\IdempotencyStore;

class RedisIdempotencyStore implements IdempotencyStore
{
    private const PREFIX = 'purchase:idempotency:';

    public function markAsProcessed(string $key, int $ttlMinutes = 10): bool
    {
        return Cache::add(self::PREFIX.$key, true, now()->addMinutes($ttlMinutes));
    }

    public function forget(string $key): void
    {
        Cache::forget(self::PREFIX.$key);
    }
}
