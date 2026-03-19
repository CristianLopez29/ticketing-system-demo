<?php

declare(strict_types=1);

namespace Src\Reports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Reports\Application\UseCases\DownloadReportUseCase;
use Src\Reports\Domain\Ports\ReportStorage;
use Src\Reports\Infrastructure\Controllers\ReportsController;
use Src\Reports\Infrastructure\Storage\LaravelReportStorage;

class Bindings extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportStorage::class, LaravelReportStorage::class);
        $this->app->bind(DownloadReportUseCase::class, function ($app) {
            return new DownloadReportUseCase($app->make(ReportStorage::class));
        });
    }

    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:admin'])
            ->prefix('api/reports')
            ->group(function () {
                Route::get('/download', [ReportsController::class, 'download']);
            });
    }
}
