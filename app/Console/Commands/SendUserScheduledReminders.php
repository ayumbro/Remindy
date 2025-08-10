<?php

namespace App\Console\Commands;

use App\Mail\BillReminderMail;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UserMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendUserScheduledReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-scheduled
                            {--dry-run : Show what would be sent without actually sending}
                            {--user= : Send reminders only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders based on user notification time preferences';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');
        
        $this->info('Processing user-scheduled subscription reminders...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No emails will actually be sent');
        }

        try {
            // Get current time in UTC
            $currentTimeUtc = Carbon::now('UTC');
            $currentHour = $currentTimeUtc->format('H:00:00');
            
            // Find users whose notification time matches current hour
            $usersQuery = User::where('notification_time_utc', 'LIKE', $currentHour . '%');

            if ($userId) {
                $usersQuery->where('id', $userId);
            }

            $users = $usersQuery->get();

            if ($users->isEmpty()) {
                return self::SUCCESS; // Silent when no users
            }
            
            $totalReminders = 0;
            $successCount = 0;
            $failureCount = 0;
            
            // Process each user
            foreach ($users as $user) {
                // Check if user has SMTP configuration
                if (!$user->hasSmtpConfig() && !$dryRun) {
                    continue; // Skip silently
                }
                
                // Get active subscriptions with notifications enabled
                $subscriptions = Subscription::where('user_id', $user->id)
                    ->active()
                    ->where(function ($query) {
                        $query->where('notifications_enabled', true)
                            ->orWhere('use_default_notifications', true);
                    })
                    ->get();
                
                foreach ($subscriptions as $subscription) {
                    $reminders = $this->getRemindersForSubscription($subscription);
                    
                    foreach ($reminders as $reminder) {
                        $totalReminders++;
                        
                        if ($this->sendReminder($reminder, $dryRun)) {
                            $successCount++;
                        } else {
                            $failureCount++;
                        }
                    }
                }
            }
            
            // Only log when there's activity or failures
            if ($totalReminders > 0) {
                $this->line("[{$currentTimeUtc->format('H:i')}] Reminders: {$successCount} sent, {$failureCount} failed");
            }
            
            Log::info('User-scheduled reminders processed', [
                'dry_run' => $dryRun,
                'current_hour_utc' => $currentHour,
                'users_processed' => $users->count(),
                'total_reminders' => $totalReminders,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error processing scheduled reminders: {$e->getMessage()}");
            
            Log::error('Error in scheduled reminders command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::FAILURE;
        }
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
     * Send a single reminder.
     */
    private function sendReminder(array $reminder, bool $dryRun): bool
    {
        $subscription = $reminder['subscription'];
        $user = $reminder['user'];
        $daysBefore = $reminder['days_before'];
        $dueDate = $reminder['due_date'];
        
        // Compact logging - only show essential info
        $this->line("  {$subscription->name} → {$user->email} ({$daysBefore}d)");
        
        if ($dryRun) {
            return true;
        }
        
        try {
            // Generate tracking ID
            $trackingId = Str::uuid()->toString();
            
            // Create the mailable
            $mailable = new BillReminderMail(
                $subscription,
                $user,
                $daysBefore,
                $dueDate,
                $trackingId
            );
            
            // Send using user's SMTP settings
            UserMailer::send($user, $mailable);
            
            // Success logged silently to reduce noise
            
            Log::info('Subscription reminder sent', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'days_before' => $daysBefore,
                'tracking_id' => $trackingId,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("    ✗ {$e->getMessage()}");
            
            Log::error('Failed to send subscription reminder', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'days_before' => $daysBefore,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}