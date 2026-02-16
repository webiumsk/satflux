<?php

use Illuminate\Support\Facades\Schedule;

// Check subscription statuses daily at 2 AM
Schedule::command('subscriptions:check-statuses')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Backup: handled via cron on host, not by Laravel schedule.
// Schedule::command('backup:run')->dailyAt('02:30')->withoutOverlapping()->runInBackground()...

// Wallet connection auto-config disabled: BTCPay does not support custom Lightning connection strings via API; configure via cron/manual if needed.
// Schedule::command('wallet-connections:attempt-config', ['--limit' => 10])
//     ->everyFifteenMinutes()
//     ->withoutOverlapping(10)
//     ->runInBackground();

// Automatic monthly CSV exports for Pro users (1st of month at 03:00 for previous month)
Schedule::job(new \App\Jobs\ProcessMonthlyExports())->monthlyOn(1, '03:00');
