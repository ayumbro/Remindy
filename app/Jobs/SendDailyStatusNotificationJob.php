<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\UserMailer;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendDailyStatusNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);

            if (!$user) {
                Log::warning('Daily status notification job: User not found', [
                    'user_id' => $this->userId,
                ]);
                return;
            }

            Log::info('Processing daily status notification job', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Check if user has SMTP configuration
            if (!$user->hasSmtpConfig()) {
                Log::warning('Daily status notification job: User has no SMTP config', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            // Send the notification
            if ($this->sendDailyNotification($user)) {
                // Update last sent timestamp
                $user->update(['last_daily_notification_sent_at' => Carbon::now()]);

                Log::info('Daily status notification sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Daily status notification job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Send daily status notification to a user.
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
                'active_subscriptions' => $activeSubscriptions,
                'upcoming_reminders' => count($upcomingReminders),
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

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Daily status notification job failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
