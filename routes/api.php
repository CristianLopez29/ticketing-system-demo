<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportsController;

Route::get('/health', function () {
    if (app()->environment('production')) {
        $token = request()->header('X-Health-Check-Token');
        if (!is_string($token) || $token !== env('HEALTHCHECK_TOKEN')) {
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
        if (!is_string($token) || $token !== env('HEALTHCHECK_TOKEN')) {
            abort(403);
        }
    }
    $status = 'ok';
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'up';
    } catch (\Throwable $e) {
        $checks['database'] = 'down';
        $status = 'degraded';
    }

    try {
        Cache::store()->get('health_check');
        $checks['cache'] = 'up';
    } catch (\Throwable $e) {
        $checks['cache'] = 'down';
        $status = 'degraded';
    }

    return new JsonResponse([
        'status' => $status,
        'checks' => $checks,
        'time' => now()->toISOString(),
    ], Response::HTTP_OK);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh-token', [AuthController::class, 'refresh']);
    Route::post('/users/{id}/tokens/revoke-all', [AuthController::class, 'revokeAllTokens'])->middleware('role:admin');
    Route::get('/reports/download', [ReportsController::class, 'download'])->middleware('role:admin');
});
