<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        if (app()->environment('testing')) {
            \Laravel\Telescope\Telescope::stopRecording();

            return;
        }

        if (! (bool) config('telescope.enabled', false)) {
            \Laravel\Telescope\Telescope::stopRecording();

            return;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('telescope_entries')) {
                \Laravel\Telescope\Telescope::stopRecording();
            }
        } catch (\Throwable) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }
}
