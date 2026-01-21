<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command("inspire", function () {
    $this->comment(Inspiring::quote());
})->purpose("Display an inspiring quote")->hourly();

// Check subscription statuses daily at 2 AM
Schedule::command('subscriptions:check-statuses')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
