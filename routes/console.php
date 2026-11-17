<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recover missed webhooks and stranded submit jobs once a minute.
Schedule::command('pixelforge:poll')->everyMinute()->withoutOverlapping();
