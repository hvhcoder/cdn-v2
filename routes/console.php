<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire')->daily();
Schedule::command('videos:cleanup-r2')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('videos:enforce-expiry')->daily();
Schedule::command('videos:check-dead-urls')->dailyAt('03:00');
