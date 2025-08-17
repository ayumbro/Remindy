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
                            {--user= : Send notification only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily status notifications synchronously to users to confirm the notification service is working.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        $this->info('Processing daily status notifications...');

        try {
            // Get current time in UTC
            $currentTimeUtc = Carbon::now('UTC');
            $currentTime = $currentTimeUtc->format('H:i:00'); // Match exact hour:minute



            // Find users with daily notifications enabled
            $usersQuery = User::where('daily_notification_enabled', true);

            // Check notification time only (no time window restriction)

            $usersQuery->where('notification_time_utc', 'LIKE', $currentTime . '%');

            if ($userId) {
                $usersQuery->where('id', $userId);
            }

            $users = $usersQuery->get();

            if ($users->isEmpty()) {
                return self::SUCCESS;
            }

            $successCount = 0;
            $failureCount = 0;

            // Process each user
            foreach ($users as $user) {
                // Check if user has SMTP configuration
                if (!$user->hasSmtpConfig()) {
                    continue;
                }

                if ($this->sendDailyNotification($user)) {
                    $successCount++;

                    // Update last sent timestamp
                    $user->update(['last_daily_notification_sent_at' => Carbon::now()]);
                } else {
                    $failureCount++;
                }
            }

            Log::info('Daily status notifications processed', [
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

            Log::info('Daily status notification sent', [
                'user_id' => $user->id,
                'email' => $user->getEffectiveNotificationEmail(),
                'tracking_id' => $trackingId,
            ]);

            return true;

        } catch (\Exception $e) {
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