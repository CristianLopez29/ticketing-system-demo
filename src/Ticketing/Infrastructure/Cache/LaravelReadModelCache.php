<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;
use Src\Ticketing\Application\Ports\ReadModelCache;

class LaravelReadModelCache implements ReadModelCache
{
    public function remember(string $key, int $ttlSeconds, \Closure $callback): mixed
    {
        return Cache::remember($key, $ttlSeconds, $callback);
    }
}
