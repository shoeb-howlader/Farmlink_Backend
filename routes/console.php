<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('farmlink:process-follow-ups')->daily();
Schedule::command('farmlink:send-admin-digest --frequency=daily')->dailyAt('08:00');
Schedule::command('farmlink:send-admin-digest --frequency=weekly')->weeklyOn(1, '08:00');
Schedule::command('payments:cancel-expired')->everyThirtyMinutes();
