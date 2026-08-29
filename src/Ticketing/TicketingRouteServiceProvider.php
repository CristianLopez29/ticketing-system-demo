<?php

declare(strict_types=1);

namespace Src\Ticketing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Ticketing\Infrastructure\Controllers\EventController;
use Src\Ticketing\Infrastructure\Controllers\PaySeasonTicketController;
use Src\Ticketing\Infrastructure\Controllers\PurchaseSeasonTicketController;
use Src\Ticketing\Infrastructure\Controllers\PurchaseTicketController;

class TicketingRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Laravel 11+ dropped throttle from the default "api" group: an unauthenticated
        // endpoint has no limit at all unless one is declared here.
        Route::middleware(['api', 'throttle:60,1'])
            ->prefix('api')
            ->group(function () {
                Route::get('/events/{id}/seats', [EventController::class, 'getSeats']);
            });

        Route::middleware(['api', 'auth:sanctum', 'role:admin', 'throttle:10,1'])
            ->prefix('api')
            ->group(function () {
                Route::get('/events/{id}/stats', [EventController::class, 'getStats']);
            });

        Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->prefix('api')
            ->group(function () {
                Route::post('/tickets/purchase', PurchaseTicketController::class);
                Route::post('/season-tickets/purchase', PurchaseSeasonTicketController::class);
                Route::post('/season-tickets/{id}/pay', PaySeasonTicketController::class);
            });
    }
}
