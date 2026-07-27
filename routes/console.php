<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seasons:expire-applications')->dailyAt('00:05');
Schedule::command('notifications:exam-reminder --days=1')->dailyAt('06:00');
Schedule::command('notifications:exam-reminder --days=3')->dailyAt('06:00');
Schedule::command('sessions:auto-close')->everyFifteenMinutes()->withoutOverlapping();
