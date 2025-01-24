<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


/** Farm Zoo every 30 Minutes */
if (env('ENABLE_ZOO_FARMER')) {
    Schedule::command('farm:zoo')->everyThirtyMinutes();
}

/** Farm Gold Eagle every 10 Minutes */
if (env('ENABLE_GOLD_EAGLE_FARMER')) {
    Schedule::command('farm:gold-eagle')->everyTenMinutes();
}

/** Farm CEX every 10 Minutes */
if (env('ENABLE_CEX_FARMER')) {
    Schedule::command('farm:cex')->everyTenMinutes();
}

/** Farm Funatic every 10 Minutes */
if (env('ENABLE_FUNATIC_FARMER')) {
    Schedule::command('farm:funatic')->everyTenMinutes();
}
