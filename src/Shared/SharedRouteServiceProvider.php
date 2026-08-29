<?php

declare(strict_types=1);

namespace Src\Shared;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SharedRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The probes stay unauthenticated for orchestrators, so the throttle is the only
        // thing standing between them and an anonymous flood.
        Route::middleware(['api', 'throttle:60,1'])
            ->prefix('api')
            ->group(base_path('routes/shared.php'));
    }
}
