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

// Run daily backups at 2:30 AM (30 minutes after subscription check)
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled backup failed');
    });

// Attempt to auto-configure wallet connections (Blink/Aqua) via BTCPay API every 15 minutes
Schedule::command('wallet-connections:attempt-config', ['--limit' => 10])
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Automatic monthly CSV exports for Pro users (1st of month at 03:00 for previous month)
Schedule::job(new \App\Jobs\ProcessMonthlyExports())->monthlyOn(1, '03:00');
