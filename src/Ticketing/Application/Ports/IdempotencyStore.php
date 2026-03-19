<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface IdempotencyStore
{
    /**
     * Mark a key as in-progress. Returns false if the key already exists (duplicate request).
     */
    public function markAsProcessed(string $key, int $ttlMinutes = 10): bool;

    /**
     * Store the successful result for a completed operation.
     * Must be called after the operation succeeds so returning callers get the original result.
     */
    public function markAsCompleted(string $key, string $result, int $ttlMinutes = 10): void;

    /**
     * Retrieve a previously stored result, or null if not yet completed.
     */
    public function getResult(string $key): ?string;

    public function forget(string $key): void;
}
