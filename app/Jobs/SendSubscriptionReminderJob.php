<?php

namespace App\Jobs;

use App\Mail\BillReminderMail;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UserMailer;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendSubscriptionReminderJob implements ShouldQueue
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
        public int $subscriptionId,
        public int $userId,
        public int $daysBefore,
        public string $dueDate
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $subscription = Subscription::find($this->subscriptionId);
            $user = User::find($this->userId);
            
            if (!$subscription || !$user) {
                Log::warning('Subscription reminder job: Subscription or user not found', [
                    'subscription_id' => $this->subscriptionId,
                    'user_id' => $this->userId,
                ]);
                return;
            }

            Log::info('Processing subscription reminder job', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'days_before' => $this->daysBefore,
                'due_date' => $this->dueDate,
            ]);

            // Check if user has SMTP configuration
            if (!$user->hasSmtpConfig()) {
                Log::warning('Subscription reminder job: User has no SMTP config', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            // Send the reminder
            $this->sendSubscriptionReminder($subscription, $user, $this->daysBefore, Carbon::parse($this->dueDate));

        } catch (\Exception $e) {
            Log::error('Subscription reminder job failed', [
                'subscription_id' => $this->subscriptionId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Send subscription reminder email.
     */
    private function sendSubscriptionReminder(Subscription $subscription, User $user, int $daysBefore, Carbon $dueDate): bool
    {
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
            
            Log::info('Subscription reminder sent', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'days_before' => $daysBefore,
                'tracking_id' => $trackingId,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send subscription reminder', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Subscription reminder job failed permanently', [
            'subscription_id' => $this->subscriptionId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
