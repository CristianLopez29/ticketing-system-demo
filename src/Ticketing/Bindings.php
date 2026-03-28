<?php

declare(strict_types=1);

namespace Src\Ticketing;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Src\Shared\Domain\Services\UuidGenerator;
use Src\Shared\Infrastructure\Services\PhpUuidGenerator;
use Src\Ticketing\Application\Ports\AsyncDispatcher;
use Src\Ticketing\Application\Ports\IdempotencyStore;
use Src\Ticketing\Application\Ports\StockManager;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Application\Ports\UserNotifier;
use Src\Ticketing\Application\Ports\ReadModelCache;
use Src\Ticketing\Application\Queries\GetEventSeatsQueryHandler;
use Src\Ticketing\Application\Queries\GetEventStatsQueryHandler;
use Src\Ticketing\Infrastructure\Cache\LaravelReadModelCache;
use Src\Ticketing\Application\UseCases\ProcessTicketPaymentUseCase;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\Repositories\EventRepository;
use Src\Ticketing\Domain\Repositories\PendingRefundRepository;
use Src\Ticketing\Domain\Repositories\ReservationRepository;
use Src\Ticketing\Domain\Repositories\SeasonRepository;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Domain\Repositories\SeatRepository;
use Src\Ticketing\Domain\Repositories\TicketRepository;
use Src\Ticketing\Infrastructure\Console\Commands\CleanupExpiredReservations;
use Src\Ticketing\Infrastructure\Jobs\LaravelAsyncDispatcher;
use Src\Ticketing\Infrastructure\Listeners\InvalidateSeatsCacheOnTicketSold;
use Src\Ticketing\Infrastructure\Notifications\LogUserNotifier;
use Src\Ticketing\Infrastructure\Payment\FakePaymentGateway;
use Src\Ticketing\Infrastructure\Persistence\EloquentEventRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentPendingRefundRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentReservationRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeasonTicketRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeatRepository;
use Src\Ticketing\Infrastructure\Persistence\EloquentTicketRepository;
use Src\Ticketing\Infrastructure\Persistence\LaravelTransactionManager;
use Src\Ticketing\Infrastructure\Persistence\RedisIdempotencyStore;
use Src\Ticketing\Infrastructure\Persistence\RedisStockManager;
use Src\Ticketing\Infrastructure\Queries\LaravelGetEventSeatsQueryHandler;
use Src\Ticketing\Infrastructure\Queries\LaravelGetEventStatsQueryHandler;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepository::class, EloquentTicketRepository::class);
        $this->app->bind(SeasonRepository::class, EloquentSeasonRepository::class);
        $this->app->bind(SeasonTicketRepository::class, EloquentSeasonTicketRepository::class);
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
        $this->app->bind(ReservationRepository::class, EloquentReservationRepository::class);
        $this->app->bind(SeatRepository::class, EloquentSeatRepository::class);
        $this->app->bind(StockManager::class, RedisStockManager::class);
        $this->app->bind(IdempotencyStore::class, RedisIdempotencyStore::class);
        $this->app->bind(UuidGenerator::class, PhpUuidGenerator::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(AsyncDispatcher::class, LaravelAsyncDispatcher::class);
        $this->app->bind(UserNotifier::class, LogUserNotifier::class);
        $this->app->bind(GetEventSeatsQueryHandler::class, LaravelGetEventSeatsQueryHandler::class);
        $this->app->bind(GetEventStatsQueryHandler::class, LaravelGetEventStatsQueryHandler::class);
        $this->app->bind(ReadModelCache::class, LaravelReadModelCache::class);
        $this->app->bind(PendingRefundRepository::class, EloquentPendingRefundRepository::class);

        $this->app->bind(\Psr\Clock\ClockInterface::class, function () {
            return new class implements \Psr\Clock\ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return \Carbon\CarbonImmutable::now();
                }
            };
        });

        $this->app->bind(ProcessTicketPaymentUseCase::class, function ($app) {
            return new ProcessTicketPaymentUseCase(
                $app->make(ReservationRepository::class),
                $app->make(TicketRepository::class),
                $app->make(SeatRepository::class),
                $app->make(PaymentGateway::class),
                $app->make(StockManager::class),
                $app->make(UserNotifier::class),
                $app->make(UuidGenerator::class),
                $app->make(TransactionManager::class),
                $app->make(PendingRefundRepository::class),
                $app->make(LoggerInterface::class),
            );
        });

        $this->app->bind(PurchaseSeasonTicketUseCase::class, function ($app) {
            $discountValue = config('ticketing.season_ticket_discount', 20);
            $discount = is_int($discountValue) ? $discountValue : (is_numeric($discountValue) ? (int) $discountValue : 20);

            return new PurchaseSeasonTicketUseCase(
                $app->make(SeasonRepository::class),
                $app->make(EventRepository::class),
                $app->make(SeatRepository::class),
                $app->make(SeasonTicketRepository::class),
                $app->make(StockManager::class),
                $app->make(TransactionManager::class),
                $app->make(IdempotencyStore::class),
                $app->make(UuidGenerator::class),
                $app->make(ClockInterface::class),
                $discount,
            );
        });

        $gatewayDriver = config('ticketing.payment_gateway', 'fake');
        if ($gatewayDriver === 'stripe') {
            $this->app->bind(PaymentGateway::class, \Src\Ticketing\Infrastructure\Payment\StripePaymentGateway::class);
        } else {
            $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
        }
    }

    public function boot(): void
    {
        Event::listen(TicketSold::class, InvalidateSeatsCacheOnTicketSold::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupExpiredReservations::class,
            ]);
        }
    }
}
