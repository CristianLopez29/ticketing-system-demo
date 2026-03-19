<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

interface IdempotencyStore
{
    public function markAsProcessed(string $key, int $ttlMinutes = 10): bool;

    public function forget(string $key): void;
}
