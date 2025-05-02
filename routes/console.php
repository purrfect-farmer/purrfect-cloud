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
Schedule::command('app:update-proxies')
    ->when(
        config('farmer.proxy.enabled')
    )
    ->everyTenMinutes();

/** Update WebAppData */
Schedule::command('farmer:update-web-app-data')
    ->when(
        config('farmer.enable_telegram_sessions') &&
        config('farmer.update_webapp_data_periodically')
    )
    ->everyTenMinutes();

/** Farm enabled drops every 10 minutes */
collect(config('farmer.drops'))
    ->filter(fn($drop) => $drop['enabled'])
    ->keys()
    ->each(
        function ($key) {
            $event = Schedule::command('farm:' . $key)
                ->everyTenMinutes();

            if (config('farmer.run_in_background')) {
                $event->runInBackground();
            }
        }
    );
