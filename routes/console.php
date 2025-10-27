<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Social Media Management Scheduler
Schedule::command('social-media:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('social-media:sync-analytics')->daily();
