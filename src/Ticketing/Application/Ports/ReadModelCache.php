<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\Ports;

interface ReadModelCache
{
    /**
     * Retrieve an item from the cache or execute the callback to store and return it.
     */
    public function remember(string $key, int $ttlSeconds, \Closure $callback): mixed;
}
