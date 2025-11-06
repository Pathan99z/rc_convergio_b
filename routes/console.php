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

// Campaign Scheduler - Process scheduled campaigns that are due
// This ensures campaigns are sent automatically at their scheduled time
Schedule::command('campaigns:process-scheduled')->everyMinute()->withoutOverlapping();
