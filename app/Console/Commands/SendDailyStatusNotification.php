<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendDailyStatusNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-daily-status
                            {--dry-run : Show what would be sent without actually sending}
                            {--user= : Send notification only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily status notifications to users to confirm the notification service is working';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');
        
        $this->info('Processing daily status notifications...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No emails will actually be sent');
        }

        try {
            // Get current time in UTC
            $currentTimeUtc = Carbon::now('UTC');
            $currentHour = $currentTimeUtc->format('H:00:00');
            
            $this->info("Current UTC time: {$currentTimeUtc->format('Y-m-d H:i:s')}");
            $this->info("Checking for users with daily notifications enabled at: {$currentHour}");
            
            // Find users with daily notifications enabled whose notification time matches current hour
            $usersQuery = User::where('daily_notification_enabled', true)
                ->where('notification_time_utc', 'LIKE', $currentHour . '%')
                ->where(function ($query) {
                    // Either never sent or sent more than 20 hours ago
                    $query->whereNull('last_daily_notification_sent_at')
                        ->orWhere('last_daily_notification_sent_at', '<', Carbon::now()->subHours(20));
                });
            
            if ($userId) {
                $usersQuery->where('id', $userId);
            }
            
            $users = $usersQuery->get();
            
            if ($users->isEmpty()) {
                $this->info('No users scheduled for daily status notifications at this time.');
                return self::SUCCESS;
            }
            
            $this->info(sprintf('Found %d user(s) scheduled for daily status notifications', $users->count()));
            
            $successCount = 0;
            $failureCount = 0;
            
            // Process each user
            foreach ($users as $user) {
                $this->info("Processing daily notification for user: {$user->email}");
                
                // Check if user has SMTP configuration
                if (!$user->hasSmtpConfig() && !$dryRun) {
                    $this->warn("  Skipping - User does not have SMTP configuration");
                    continue;
                }
                
                if ($this->sendDailyNotification($user, $dryRun)) {
                    $successCount++;
                    
                    // Update last sent timestamp
                    if (!$dryRun) {
                        $user->update(['last_daily_notification_sent_at' => Carbon::now()]);
                    }
                } else {
                    $failureCount++;
                }
            }
            
            $this->info("Daily status notifications processed:");
            $this->info("  Successfully sent: $successCount");
            if ($failureCount > 0) {
                $this->warn("  Failed to send: $failureCount");
            }
            
            Log::info('Daily status notifications processed', [
                'dry_run' => $dryRun,
                'current_hour_utc' => $currentHour,
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
    private function sendDailyNotification(User $user, bool $dryRun): bool
    {
        $this->info(sprintf(
            '  - %s daily notification to %s',
            $dryRun ? '[DRY RUN]' : 'Sending',
            $user->getEffectiveNotificationEmail()
        ));
        
        if ($dryRun) {
            return true;
        }
        
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