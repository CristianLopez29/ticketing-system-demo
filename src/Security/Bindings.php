<?php

declare(strict_types=1);

namespace Src\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Security\Infrastructure\Controllers\AuthController;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $emailInput = $request->input('email');
            $email = is_string($emailInput) ? $emailInput : '';

            return [
                Limit::perMinute(5)->by($email.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(function () {
                Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

                Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
                    Route::post('/logout', [AuthController::class, 'logout']);
                    Route::post('/refresh-token', [AuthController::class, 'refresh']);
                    Route::post('/users/{id}/tokens/revoke-all', [AuthController::class, 'revokeAllTokens'])->middleware('role:admin');
                });
            });
    }
}
