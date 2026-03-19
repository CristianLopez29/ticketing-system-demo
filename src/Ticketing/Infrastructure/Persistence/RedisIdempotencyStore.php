<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Persistence;

use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Application\Ports\IdempotencyStore;

class RedisIdempotencyStore implements IdempotencyStore
{
    private const PREFIX        = 'purchase:idempotency:';
    private const RESULT_SUFFIX = ':result';

    public function markAsProcessed(string $key, int $ttlMinutes = 10): bool
    {
        return Cache::add(self::PREFIX.$key, 'processing', now()->addMinutes($ttlMinutes));
    }

    public function markAsCompleted(string $key, string $result, int $ttlMinutes = 10): void
    {
        // Store the actual result so duplicate requests can return it immediately
        Cache::put(
            self::PREFIX.$key.self::RESULT_SUFFIX,
            $result,
            now()->addMinutes($ttlMinutes)
        );
    }

    public function getResult(string $key): ?string
    {
        $value = Cache::get(self::PREFIX.$key.self::RESULT_SUFFIX);

        return is_string($value) ? $value : null;
    }

    public function forget(string $key): void
    {
        Cache::forget(self::PREFIX.$key);
        Cache::forget(self::PREFIX.$key.self::RESULT_SUFFIX);
    }
}
