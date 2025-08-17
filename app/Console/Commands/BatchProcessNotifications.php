<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyStatusNotificationJob;
use App\Jobs\SendSubscriptionReminderJob;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BatchProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:batch-process
                            {--dry-run : Show what would be queued without actually queuing}
                            {--daily-only : Only process daily status notifications}
                            {--reminders-only : Only process subscription reminders}';

    /**
     * The console command description.
     */
    protected $description = 'Queue all pending notifications and process them in batch

Optimized for cron execution every minute with 3-minute time window tolerance.
Duplicate prevention controlled by NOTIFICATION_DUPLICATE_WINDOW environment variable:
- NOTIFICATION_DUPLICATE_WINDOW=0: Allow multiple notifications per day (testing)
- NOTIFICATION_DUPLICATE_WINDOW=23: Prevent duplicates within 23 hours (production)

Examples:
  php artisan notifications:batch-process                    # Normal processing
  php artisan notifications:batch-process --dry-run          # See what would be processed
  php artisan notifications:batch-process --daily-only       # Only daily status notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $dailyOnly = $this->option('daily-only');
        $remindersOnly = $this->option('reminders-only');

        $currentTime = now()->format('H:i:s');

        // Only show output for dry runs to avoid log spam
        if ($dryRun) {
            $this->line("[$currentTime] Mode: DRY_RUN");
        }

        // Simple execution log
        Log::info('Batch process executed', [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'dry_run' => $dryRun,
        ]);

        $dailyJobsQueued = 0;
        $reminderJobsQueued = 0;

        try {
            // Queue daily status notifications
            if (!$remindersOnly) {
                $dailyJobsQueued = $this->queueDailyStatusNotifications($dryRun);
            }

            // Queue subscription reminders
            if (!$dailyOnly) {
                $reminderJobsQueued = $this->queueSubscriptionReminders($dryRun);
            }

            $totalJobs = $dailyJobsQueued + $reminderJobsQueued;

            // Only log when there's activity to reduce noise
            if ($totalJobs > 0) {
                $this->line("[$currentTime] Queued: {$dailyJobsQueued}d/{$reminderJobsQueued}r");

                if (!$dryRun) {
                    $this->processQueuedJobs();
                    $this->line("[$currentTime] Processed: $totalJobs jobs");
                }
            }
            // Silent when no jobs to reduce log noise

            Log::info('Batch notification processing completed', [
                'daily_jobs_queued' => $dailyJobsQueued,
                'reminder_jobs_queued' => $reminderJobsQueued,
                'total_jobs' => $totalJobs,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during batch processing: {$e->getMessage()}");
            Log::error('Batch notification processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Queue daily status notifications for eligible users.
     */
    private function queueDailyStatusNotifications(bool $dryRun): int
    {
        $currentTime = Carbon::now('UTC');

        // Create a tolerance window (check current minute and previous 2 minutes)
        $timeWindow = [
            $currentTime->format('H:i:00'),                    // Current minute
            $currentTime->copy()->subMinute()->format('H:i:00'), // 1 minute ago
            $currentTime->copy()->subMinutes(2)->format('H:i:00'), // 2 minutes ago
        ];

        // Find users with daily notifications enabled within the time window
        $users = User::where('daily_notification_enabled', true)
            ->where(function ($query) use ($timeWindow) {
                foreach ($timeWindow as $time) {
                    $query->orWhere('notification_time_utc', 'LIKE', $time . '%');
                }
            })
            ->get();

        $jobsQueued = 0;

        // Get duplicate window from environment variable
        $duplicateWindowHours = (int) env('NOTIFICATION_DUPLICATE_WINDOW', 23);

        // Filter out users who already received a notification within the duplicate window
        $duplicateThreshold = null;
        if ($duplicateWindowHours > 0) {
            $duplicateThreshold = Carbon::now('UTC')->subHours($duplicateWindowHours);
        }

        foreach ($users as $user) {
            if (!$user->hasSmtpConfig()) {
                continue; // Skip silently to reduce noise
            }

            // Check if user already received a notification within the duplicate window
            if ($duplicateWindowHours > 0 &&
                $user->last_daily_notification_sent_at &&
                $user->last_daily_notification_sent_at > $duplicateThreshold) {
                continue; // Skip silently to reduce noise
            }

            if (!$dryRun) {
                SendDailyStatusNotificationJob::dispatch($user->id);
                $this->line("  Daily: {$user->email}");
            }

            $jobsQueued++;
        }

        return $jobsQueued;
    }

    /**
     * Queue subscription reminders for eligible subscriptions.
     * Only queues reminders for users whose notification time matches current time window.
     */
    private function queueSubscriptionReminders(bool $dryRun): int
    {
        $currentTime = Carbon::now('UTC');

        // Create the same tolerance window as daily notifications
        $timeWindow = [
            $currentTime->format('H:i:00'),                    // Current minute
            $currentTime->copy()->subMinute()->format('H:i:00'), // 1 minute ago
            $currentTime->copy()->subMinutes(2)->format('H:i:00'), // 2 minutes ago
        ];

        // Get users whose notification time matches the current window
        $eligibleUsers = User::where(function ($query) use ($timeWindow) {
            foreach ($timeWindow as $time) {
                $query->orWhere('notification_time_utc', 'LIKE', $time . '%');
            }
        })->pluck('id')->toArray();

        if (empty($eligibleUsers)) {
            return 0; // Silent when no users to reduce noise
        }

        // Get subscriptions for eligible users only
        $subscriptions = Subscription::with('user')
            ->active()
            ->whereIn('user_id', $eligibleUsers)
            ->get();

        $jobsQueued = 0;

        foreach ($subscriptions as $subscription) {
            $reminders = $this->getRemindersForSubscription($subscription);

            foreach ($reminders as $reminder) {
                $user = $reminder['user'];

                if (!$user->hasSmtpConfig()) {
                    $this->warn("  Skipping {$user->email} - No SMTP configuration");
                    continue;
                }

                if ($dryRun) {
                    $this->info("  [DRY RUN] Would queue reminder for: {$user->email} - {$subscription->name} ({$reminder['days_before']} days) at {$user->notification_time_utc}");
                } else {
                    SendSubscriptionReminderJob::dispatch(
                        $subscription->id,
                        $user->id,
                        $reminder['days_before'],
                        $reminder['due_date']->format('Y-m-d')
                    );
                    $this->info("  Queued reminder for: {$user->email} - {$subscription->name} ({$reminder['days_before']} days) at {$user->notification_time_utc}");
                }

                $jobsQueued++;
            }
        }

        return $jobsQueued;
    }

    /**
     * Get reminders that should be sent for a subscription.
     */
    private function getRemindersForSubscription(Subscription $subscription): array
    {
        $reminders = [];
        $today = Carbon::today();
        
        // Get next billing date
        $nextBillingDate = $subscription->getNextBillingDate();
        
        if (!$nextBillingDate) {
            return $reminders;
        }

        // Calculate days until due
        $daysUntilDue = $today->diffInDays($nextBillingDate, false);
        
        // Get effective reminder intervals
        $settings = $subscription->getEffectiveNotificationSettings();
        $reminderDays = $settings['reminder_intervals'] ?? [30, 7, 3, 1];
        
        // Check if today matches any reminder day
        foreach ($reminderDays as $days) {
            if ($daysUntilDue == $days) {
                $reminders[] = [
                    'subscription' => $subscription,
                    'user' => $subscription->user,
                    'days_before' => $days,
                    'due_date' => $nextBillingDate,
                ];
                break; // Only send one reminder per subscription per run
            }
        }
        
        return $reminders;
    }

    /**
     * Process all queued jobs and wait for completion.
     */
    private function processQueuedJobs(): void
    {
        // Process jobs from the notifications queue
        $exitCode = Artisan::call('queue:work', [
            '--queue' => 'notifications',
            '--stop-when-empty' => true,
            '--timeout' => 300, // 5 minutes max
        ]);

        // Only log failures to reduce noise
        if ($exitCode !== 0) {
            $this->error('✗ Some jobs may have failed during processing');
        }
    }
}
