<?php

use App\Jobs\SendLifecyclePushNotificationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendLifecyclePushNotificationsJob)->daily()->at('10:00')->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --max-time=300')->everyFiveMinutes()->withoutOverlapping();
