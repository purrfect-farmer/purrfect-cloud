<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


/** Farm Gold Eagle every 10 Minutes */
Schedule::command('farm:gold-eagle')->everyTenMinutes();

/** Farm Funatic every 30 Minutes */
Schedule::command('farm:funatic')->everyTenMinutes();
