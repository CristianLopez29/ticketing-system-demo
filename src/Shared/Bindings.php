<?php

declare(strict_types=1);

namespace Src\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Audit\AuditLogger;
use Src\Shared\Infrastructure\Audit\EloquentAuditLogger;
use Symfony\Component\HttpFoundation\Response;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogger::class, EloquentAuditLogger::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(function () {
                Route::get('/health', function () {
                    if (app()->environment('production')) {
                        $token = request()->header('X-Health-Check-Token');
                        if (! is_string($token) || $token !== config('ticketing.healthcheck_token')) {
                            abort(403);
                        }
                    }

                    return new JsonResponse([
                        'status' => 'ok',
                        'time' => now()->toISOString(),
                    ], Response::HTTP_OK);
                });

                Route::get('/readiness', function () {
                    if (app()->environment('production')) {
                        $token = request()->header('X-Health-Check-Token');
                        if (! is_string($token) || $token !== config('ticketing.healthcheck_token')) {
                            abort(403);
                        }
                    }

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
                        'time' => now()->toISOString(),
                    ], Response::HTTP_OK);
                });
            });
    }
}
