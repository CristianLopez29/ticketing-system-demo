<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ticketing:cleanup-expired-reservations')
    ->everyMinute();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
