<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


/** Farm enabled drops every 10 minutes */
collect(config('farmer.drops'))
    ->filter(fn($drop) => $drop['enabled'])
    ->keys()
    ->each(fn($key) => Schedule::command('farm:' . $key)->everyTenMinutes());
