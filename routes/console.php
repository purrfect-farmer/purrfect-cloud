<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


/** Expire Subscriptions */
Schedule::command('app:expire-subscriptions')->daily();

/** Cleanup Sessions */
Schedule::command('telegram:cleanup-sessions')->daily();

/** Update Proxies */
Schedule::command('app:update-proxies')->hourly();

/** Farm all enabled drops every 10 minutes */
Schedule::command('farm:all')
    ->withoutOverlapping()
    ->everyTenMinutes()
    ->runInBackground();
