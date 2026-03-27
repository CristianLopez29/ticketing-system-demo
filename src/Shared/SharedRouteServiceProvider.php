<?php

declare(strict_types=1);

namespace Src\Shared;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SharedRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/shared.php'));
    }
}
