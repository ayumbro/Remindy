<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bill Reminder Scheduling - Respects user notification time preferences
// Runs hourly to check for users scheduled at the current UTC hour
Schedule::command('reminders:send-scheduled')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-reminders.log'));

// BATCH PROCESSING APPROACH - Queue all notifications and process them in batch
// Runs every minute with 3-minute time window tolerance for user notification times
// Duplicate prevention: NOTIFICATION_DUPLICATE_WINDOW=0 (testing) or 23 (production)
Schedule::command('notifications:batch-process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/batch-notifications.log'));

// LEGACY COMMANDS (commented out - replaced by batch processing)
// Schedule::command('reminders:send-daily-status')
//     ->everyMinute()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/daily-notifications.log'));

// Schedule::command('reminders:process-pending')
//     ->everyMinute()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/reminders.log'));

// Schedule::command('reminders:create-schedules')
//     ->hourly()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/reminder-schedules.log'));

Schedule::command('reminders:process-failed')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/failed-notifications.log'));

// Queue maintenance
Schedule::command('queue:prune-batches --hours=48')
    ->daily()
    ->at('02:00');

Schedule::command('queue:prune-failed --hours=168') // 1 week
    ->daily()
    ->at('02:30');
