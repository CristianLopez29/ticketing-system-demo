<?php
declare(strict_types=1);

namespace Src\Ticketing;

use Illuminate\Support\ServiceProvider;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\Repositories\StockManager;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Infrastructure\Console\Commands\CleanupExpiredReservations;
use Src\Ticketing\Infrastructure\Payment\FakePaymentGateway;
use Src\Ticketing\Infrastructure\Persistence\EloquentEventRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentReservationRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonTicketRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentTicketRepository;
use Src\Ticketing\Infrastructure\Persistence\RedisStockManager;
use Illuminate\Support\Facades\Route;
use Src\Ticketing\Infrastructure\Controllers\PurchaseTicketController;
use Src\Ticketing\Infrastructure\Controllers\PurchaseSeasonTicketController;
use Src\Ticketing\Infrastructure\Controllers\EventController;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepository::class, EloquentTicketRepository::class);
        $this->app->bind(SeasonRepository::class, EloquentSeasonRepository::class);
        $this->app->bind(SeasonTicketRepository::class, EloquentSeasonTicketRepository::class);
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
        $this->app->bind(ReservationRepository::class, EloquentReservationRepository::class);
        $this->app->bind(StockManager::class, RedisStockManager::class);
        $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupExpiredReservations::class,
            ]);
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(function () {
                Route::post('/tickets/purchase', PurchaseTicketController::class);
                Route::post('/season-tickets/purchase', PurchaseSeasonTicketController::class);
                
                Route::get('/events/{id}/seats', [EventController::class, 'getSeats']);
                Route::get('/events/{id}/stats', [EventController::class, 'getStats']);
            });
    }
}
