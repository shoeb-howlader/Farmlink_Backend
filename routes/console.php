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
Schedule::command('payments:reconcile')->everyTenMinutes();

// Automated Database & Storage Backups with Retention Cleanup & Health Monitoring
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command('backup:run')->weeklyOn(0, '03:00');
Schedule::command('backup:clean')->dailyAt('04:00');
Schedule::command('backup:monitor')->dailyAt('05:00');
