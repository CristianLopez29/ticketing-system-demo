<?php

declare(strict_types=1);

namespace Src\Reports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Reports\Infrastructure\Controllers\ReportsController;

class Bindings extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->prefix('api')
            ->group(function () {
                Route::get('/reports/download', [ReportsController::class, 'download'])->middleware('role:admin');
            });
    }
}
