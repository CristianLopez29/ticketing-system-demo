<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

// Health probes are intentionally unauthenticated so that Kubernetes,
// ECS and other orchestrators can call them without credentials.
// Network-level protection (firewall / CIDR) should be used instead.

Route::get('/health', function (): JsonResponse {
    return new JsonResponse([
        'status' => 'ok',
        'time'   => now()->toISOString(),
    ], Response::HTTP_OK);
});

Route::get('/readiness', function (): JsonResponse {
    $status = 'ok';
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'up';
    } catch (\Throwable) {
        $checks['database'] = 'down';
        $status = 'degraded';
    }

    try {
        Cache::store()->get('health_check');
        $checks['cache'] = 'up';
    } catch (\Throwable) {
        $checks['cache'] = 'down';
        $status = 'degraded';
    }

    return new JsonResponse([
        'status' => $status,
        'checks' => $checks,
        'time'   => now()->toISOString(),
    ], Response::HTTP_OK);
});
