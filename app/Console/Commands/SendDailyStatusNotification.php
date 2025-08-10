<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendDailyStatusNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-daily-status
                            {--user= : Send notification only for a specific user ID}
                            {--testing : TEMPORARY: Send hourly for testing instead of daily}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily status notifications synchronously to users to confirm the notification service is working. Use --testing for hourly testing mode.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $testingMode = $this->option('testing'); // TEMPORARY: For testing purposes

        $this->info($testingMode ? 'Processing status notifications (TESTING MODE - Hourly)...' : 'Processing daily status notifications...');

        if ($testingMode) {
            $this->warn('⚠️  TESTING MODE ENABLED - Sending real emails hourly for testing!');
            $this->warn('⚠️  Remember to remove --testing flag for production daily mode.');
        }

        try {
            // Get current time in UTC
            $currentTimeUtc = Carbon::now('UTC');
            $currentTime = $currentTimeUtc->format('H:i:00'); // Match exact hour:minute

            $this->info("Current UTC time: {$currentTimeUtc->format('Y-m-d H:i:s')}");
            Log::info('Daily status notification check started', [
                'current_utc_time' => $currentTimeUtc->format('Y-m-d H:i:s'),
                'current_time' => $currentTime,
                'testing_mode' => $testingMode,
                'user_id_filter' => $userId,
            ]);

            // First, let's see all users with daily notifications enabled
            $allDailyUsers = User::where('daily_notification_enabled', true)->get();
            $this->info("Total users with daily notifications enabled: {$allDailyUsers->count()}");
            Log::info('Users with daily notifications enabled', [
                'total_count' => $allDailyUsers->count(),
                'users' => $allDailyUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'notification_time_utc' => $user->notification_time_utc,
                        'last_daily_notification_sent_at' => $user->last_daily_notification_sent_at?->format('Y-m-d H:i:s'),
                        'has_smtp_config' => $user->hasSmtpConfig(),
                    ];
                })->toArray(),
            ]);

            // Find users with daily notifications enabled
            $usersQuery = User::where('daily_notification_enabled', true);

            if ($testingMode) {
                // TESTING MODE: Send to all users with daily notifications enabled
                // but only if they haven't received one in the last hour
                $this->info("TESTING MODE: Checking for users with daily notifications enabled (sent more than 1 hour ago)");
                $oneHourAgo = Carbon::now()->subHour();
                $this->info("One hour ago threshold: {$oneHourAgo->format('Y-m-d H:i:s')}");
                Log::info('Testing mode filters', [
                    'one_hour_ago_threshold' => $oneHourAgo->format('Y-m-d H:i:s'),
                ]);

                $usersQuery->where(function ($query) use ($oneHourAgo) {
                    $query->whereNull('last_daily_notification_sent_at')
                        ->orWhere('last_daily_notification_sent_at', '<', $oneHourAgo);
                });
            } else {
                // Normal daily mode - check notification time only (no time window restriction)
                $this->info("Checking for users with daily notifications enabled at: {$currentTime}");
                Log::info('Normal mode filters', [
                    'target_time' => $currentTime,
                    'note' => 'No time window restriction - users can receive notifications whenever their time matches',
                ]);

                $usersQuery->where('notification_time_utc', 'LIKE', $currentTime . '%');
            }

            if ($userId) {
                $usersQuery->where('id', $userId);
                $this->info("Filtering for specific user ID: {$userId}");
                Log::info('User ID filter applied', ['user_id' => $userId]);
            }

            // Log the SQL query for debugging
            $sql = $usersQuery->toSql();
            $bindings = $usersQuery->getBindings();
            $this->info("SQL Query: {$sql}");
            $this->info("Bindings: " . json_encode($bindings));
            Log::info('Query details', [
                'sql' => $sql,
                'bindings' => $bindings,
            ]);

            $users = $usersQuery->get();

            $this->info("Query returned {$users->count()} user(s)");
            Log::info('Query results', [
                'matching_users_count' => $users->count(),
                'matching_users' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'email' => $user->email,
                        'notification_time_utc' => $user->notification_time_utc,
                        'last_daily_notification_sent_at' => $user->last_daily_notification_sent_at?->format('Y-m-d H:i:s'),
                        'has_smtp_config' => $user->hasSmtpConfig(),
                    ];
                })->toArray(),
            ]);

            if ($users->isEmpty()) {
                $this->info('No users scheduled for daily status notifications at this time.');
                Log::info('No users found for daily status notifications');
                return self::SUCCESS;
            }
            
            $this->info(sprintf('Found %d user(s) scheduled for daily status notifications', $users->count()));

            $successCount = 0;
            $failureCount = 0;

            // Process each user
            foreach ($users as $user) {
                $this->info("Processing daily notification for user: {$user->email}");
                Log::info('Processing user for daily notification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'notification_time_utc' => $user->notification_time_utc,
                    'last_daily_notification_sent_at' => $user->last_daily_notification_sent_at?->format('Y-m-d H:i:s'),
                    'has_smtp_config' => $user->hasSmtpConfig(),
                ]);

                // Check if user has SMTP configuration
                if (!$user->hasSmtpConfig()) {
                    $this->warn("  Skipping - User does not have SMTP configuration");
                    Log::warning('User skipped - no SMTP configuration', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                    continue;
                }

                if ($this->sendDailyNotification($user)) {
                    $successCount++;

                    // Update last sent timestamp
                    $newTimestamp = Carbon::now();
                    $user->update(['last_daily_notification_sent_at' => $newTimestamp]);
                    $this->info("  Updated last_daily_notification_sent_at to: {$newTimestamp->format('Y-m-d H:i:s')}");
                    Log::info('Daily notification sent successfully', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'last_daily_notification_sent_at' => $newTimestamp->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    $failureCount++;
                    Log::error('Failed to send daily notification', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                }
            }
            
            $this->info("Daily status notifications processed:");
            $this->info("  Successfully sent: $successCount");
            if ($failureCount > 0) {
                $this->warn("  Failed to send: $failureCount");
            }
            
            Log::info('Daily status notifications processed', [
                'testing_mode' => $testingMode,
                'current_time_utc' => $currentTime,
                'users_processed' => $users->count(),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error processing daily notifications: {$e->getMessage()}");
            
            Log::error('Error in daily status notifications command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::FAILURE;
        }
    }
    
    /**
     * Send daily notification to a user.
     */
    private function sendDailyNotification(User $user): bool
    {
        $this->info(sprintf(
            '  - Sending daily notification to %s',
            $user->getEffectiveNotificationEmail()
        ));
        
        try {
            // Get subscription statistics
            $activeSubscriptions = $user->subscriptions()->active()->count();
            $upcomingReminders = $this->getUpcomingReminders($user);
            
            // Generate tracking ID
            $trackingId = Str::uuid()->toString();
            
            // Create the mailable
            $mailable = new \App\Mail\DailyStatusNotification(
                $user,
                $activeSubscriptions,
                $upcomingReminders,
                $trackingId
            );
            
            // Send using user's SMTP settings
            UserMailer::send($user, $mailable);
            
            $this->info("    ✓ Daily notification sent successfully");
            
            Log::info('Daily status notification sent', [
                'user_id' => $user->id,
                'email' => $user->getEffectiveNotificationEmail(),
                'active_subscriptions' => $activeSubscriptions,
                'upcoming_reminders' => count($upcomingReminders),
                'tracking_id' => $trackingId,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("    ✗ Failed to send: {$e->getMessage()}");
            
            Log::error('Failed to send daily status notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Get upcoming reminders for the user in the next 7 days.
     */
    private function getUpcomingReminders(User $user): array
    {
        $reminders = [];
        $today = Carbon::today();
        $weekFromNow = $today->copy()->addDays(7);
        
        // Get active subscriptions with notifications enabled
        $subscriptions = $user->subscriptions()
            ->active()
            ->where(function ($query) {
                $query->where('notifications_enabled', true)
                    ->orWhere('use_default_notifications', true);
            })
            ->get();
        
        foreach ($subscriptions as $subscription) {
            $nextBillingDate = $subscription->getNextBillingDate();
            
            if ($nextBillingDate && $nextBillingDate->between($today, $weekFromNow)) {
                $reminders[] = [
                    'name' => $subscription->name,
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency->code ?? 'USD',
                    'due_date' => $nextBillingDate->format('M d, Y'),
                    'days_until' => $today->diffInDays($nextBillingDate),
                ];
            }
        }
        
        // Sort by due date
        usort($reminders, function ($a, $b) {
            return $a['days_until'] <=> $b['days_until'];
        });
        
        return $reminders;
    }
}