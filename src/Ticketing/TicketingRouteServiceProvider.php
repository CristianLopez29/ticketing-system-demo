<?php

declare(strict_types=1);

namespace Src\Ticketing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Ticketing\Infrastructure\Controllers\EventController;
use Src\Ticketing\Infrastructure\Controllers\PurchaseSeasonTicketController;
use Src\Ticketing\Infrastructure\Controllers\PurchaseTicketController;

class TicketingRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(function () {
                Route::get('/events/{id}/seats', [EventController::class, 'getSeats']);
            });

        Route::middleware(['api', 'auth:sanctum', 'role:admin'])
            ->prefix('api')
            ->group(function () {
                Route::get('/events/{id}/stats', [EventController::class, 'getStats']);
            });

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(function () {
                Route::post('/tickets/purchase', PurchaseTicketController::class);
                Route::post('/season-tickets/purchase', PurchaseSeasonTicketController::class);
            });
    }
}
