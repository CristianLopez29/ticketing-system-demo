<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ticketing:cleanup-expired-reservations')
    ->everyMinute();
