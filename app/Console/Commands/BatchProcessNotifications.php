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

Duplicate prevention is controlled by NOTIFICATION_DUPLICATE_WINDOW environment variable (default: 23 hours).

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

        // Get duplicate window from environment variable
        $duplicateWindowHours = (int) env('NOTIFICATION_DUPLICATE_WINDOW', 23);

        $this->info('Starting batch notification processing...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No jobs will be queued or processed');
        }

        if ($duplicateWindowHours === 0) {
            $this->warn('TESTING MODE - Duplicate prevention disabled (NOTIFICATION_DUPLICATE_WINDOW=0)');
        } else {
            $this->info("Duplicate prevention: {$duplicateWindowHours} hours (NOTIFICATION_DUPLICATE_WINDOW)");
        }

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

            if ($totalJobs === 0) {
                $this->info('No notifications to process at this time.');
                return self::SUCCESS;
            }

            $this->info("Queued {$totalJobs} notification jobs ({$dailyJobsQueued} daily, {$reminderJobsQueued} reminders)");

            // Process all queued jobs if not in dry-run mode
            if (!$dryRun) {
                $this->info('Processing queued jobs...');
                $this->processQueuedJobs($totalJobs);
            } else {
                $this->info('[DRY RUN] Would process all queued jobs');
            }

            Log::info('Batch notification processing completed', [
                'daily_jobs_queued' => $dailyJobsQueued,
                'reminder_jobs_queued' => $reminderJobsQueued,
                'total_jobs' => $totalJobs,
                'dry_run' => $dryRun,
                'duplicate_window_hours' => $duplicateWindowHours,
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
        $currentTimeStr = $currentTime->format('H:i:00');

        // Create a tolerance window (check current minute and previous 2 minutes)
        $timeWindow = [
            $currentTime->format('H:i:00'),                    // Current minute
            $currentTime->copy()->subMinute()->format('H:i:00'), // 1 minute ago
            $currentTime->copy()->subMinutes(2)->format('H:i:00'), // 2 minutes ago
        ];

        $this->info("Checking for daily status notifications at: {$currentTimeStr} (with 2-minute tolerance)");
        $this->info("Time window: " . implode(', ', $timeWindow));

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
        // to prevent duplicate notifications due to tolerance window
        $duplicateThreshold = null;
        if ($duplicateWindowHours > 0) {
            $duplicateThreshold = Carbon::now('UTC')->subHours($duplicateWindowHours);
            $this->info("  Duplicate prevention: Checking for notifications sent after {$duplicateThreshold->format('Y-m-d H:i:s')}");
        } else {
            $this->info("  Duplicate prevention: DISABLED (testing mode)");
        }

        foreach ($users as $user) {
            if (!$user->hasSmtpConfig()) {
                $this->warn("  Skipping {$user->email} - No SMTP configuration");
                continue;
            }

            // Check if user already received a notification within the duplicate window
            if ($duplicateWindowHours > 0 &&
                $user->last_daily_notification_sent_at &&
                $user->last_daily_notification_sent_at > $duplicateThreshold) {
                $this->info("  Skipping {$user->email} - Already received notification within {$duplicateWindowHours}h ({$user->last_daily_notification_sent_at->format('H:i')})");
                continue;
            }

            if ($dryRun) {
                $this->info("  [DRY RUN] Would queue daily status notification for: {$user->email} (scheduled: {$user->notification_time_utc})");
            } else {
                SendDailyStatusNotificationJob::dispatch($user->id);
                $this->info("  Queued daily status notification for: {$user->email} (scheduled: {$user->notification_time_utc})");
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

        $this->info("Checking for subscription reminders at user notification times: " . implode(', ', $timeWindow));

        // Get users whose notification time matches the current window
        $eligibleUsers = User::where(function ($query) use ($timeWindow) {
            foreach ($timeWindow as $time) {
                $query->orWhere('notification_time_utc', 'LIKE', $time . '%');
            }
        })->pluck('id')->toArray();

        if (empty($eligibleUsers)) {
            $this->info("  No users have notification times in current window");
            return 0;
        }

        $this->info("  Found " . count($eligibleUsers) . " users with notification times in current window");

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
    private function processQueuedJobs(int $expectedJobs): void
    {
        $this->info("Processing {$expectedJobs} queued job(s)...");

        // Process jobs from the notifications queue
        $exitCode = Artisan::call('queue:work', [
            '--queue' => 'notifications',
            '--stop-when-empty' => true,
            '--timeout' => 300, // 5 minutes max
        ]);

        if ($exitCode === 0) {
            $this->info('✓ All queued jobs processed successfully');
        } else {
            $this->error('✗ Some jobs may have failed during processing');
        }
    }
}
