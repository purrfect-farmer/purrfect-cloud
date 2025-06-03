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
        config('farmer.update_webapp_data')
    )
    ->everyTenMinutes();

/** Farm enabled drops every 10 minutes */
if (config('farmer.use_single_command')) {
    Schedule::command('farm:all')->withoutOverlapping()
        ->onOneServer()
        ->everyTenMinutes();
} else {
    collect(config('farmer.drops'))
        ->filter(fn($drop) => $drop['enabled'])
        ->each(
            function ($drop, $key) {
                $event = Schedule::command('farm:' . $key)
                    ->withoutOverlapping()
                    ->onOneServer();

                if (config('farmer.run_in_background')) {
                    $event->runInBackground();
                }

                if (isset($drop['interval']) && method_exists($event, $drop['interval'])) {
                    $event->{$drop['interval']}();
                } else {
                    $event->everyTenMinutes();
                }
            }
        );
}
