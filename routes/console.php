<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


/** Expire Subscriptions */
Schedule::command('app:expire-subscriptions')->daily();

/** Update Proxies */
Schedule::command('app:update-proxies')->everyThirtyMinutes();

/** Update WebAppData */
Schedule::command('farmer:update-web-app-data')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground()
    ->cron('5-59/10 * * * *');


/** Farm enabled drops every 10 minutes */
collect(config('farmer.drops'))
    ->filter(fn($drop) => $drop['enabled'])
    ->keys()
    ->each(
        fn($key) => Schedule::command('farm:' . $key)
            ->withoutOverlapping(10)
            ->onOneServer()
            ->runInBackground()
            ->everyTenMinutes()
    );
