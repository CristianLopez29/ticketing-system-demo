<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule: process overdue candidate assignments every 15 minutes
Schedule::job(\Src\Evaluators\Infrastructure\Jobs\ProcessOverdueAssignmentsJob::class)
    ->everyFifteenMinutes();

// Schedule: cleanup expired ticket reservations
Schedule::command('ticketing:cleanup-expired-reservations')
    ->everyMinute();
