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

class SendSubscriptionReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send
                            {--dry-run : Show what would be sent without actually sending}
                            {--user= : Send reminders only for a specific user ID}
                            {--days= : Check for subscriptions due in X days (default: check all reminder days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders for upcoming subscription payments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');
        $specificDays = $this->option('days');

        $this->info('Processing subscription reminders...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No emails will actually be sent');
        }

        try {
            // Get subscriptions that need reminders
            $query = Subscription::with('user')
                ->active(); // Use the active scope from the model

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $subscriptions = $query->get();
            $remindersToSend = [];

            foreach ($subscriptions as $subscription) {
                $reminders = $this->getRemindersForSubscription($subscription, $specificDays);
                $remindersToSend = array_merge($remindersToSend, $reminders);
            }

            if (empty($remindersToSend)) {
                $this->info('No reminders to send today.');
                return self::SUCCESS;
            }

            $this->info(sprintf('Found %d reminder(s) to send', count($remindersToSend)));

            // Send each reminder
            $successCount = 0;
            $failureCount = 0;

            foreach ($remindersToSend as $reminder) {
                if ($this->sendReminder($reminder, $dryRun)) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            $this->info("Reminders sent successfully: $successCount");
            if ($failureCount > 0) {
                $this->warn("Reminders failed: $failureCount");
            }

            Log::info('Send subscription reminders command completed', [
                'dry_run' => $dryRun,
                'total_reminders' => count($remindersToSend),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error processing subscription reminders: {$e->getMessage()}");

            Log::error('Error in send subscription reminders command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get reminders that should be sent for a subscription.
     */
    private function getRemindersForSubscription(Subscription $subscription, ?string $specificDays): array
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
        
        // If specific days requested, only check that
        if ($specificDays !== null) {
            if ($daysUntilDue == $specificDays) {
                $reminders[] = [
                    'subscription' => $subscription,
                    'user' => $subscription->user,
                    'days_before' => $daysUntilDue,
                    'due_date' => $nextBillingDate,
                ];
            }
            return $reminders;
        }

        // Otherwise check all reminder days from user preferences
        $reminderDays = $subscription->user->default_reminder_intervals ?? [30, 7, 3, 1];
        
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

        $this->info(sprintf(
            '  - %s: %s for %s (due in %d days)',
            $dryRun ? '[DRY RUN]' : 'Sending',
            $subscription->name,
            $user->email,
            $daysBefore
        ));

        if ($dryRun) {
            return true;
        }

        try {
            // Check if user has SMTP configuration
            if (!$user->hasSmtpConfig()) {
                $this->warn("    User {$user->email} does not have SMTP configuration");
                return false;
            }

            // Generate tracking ID for this notification
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

            $this->info("    ✓ Email sent successfully");

            // Log the successful send
            Log::info('Subscription reminder sent', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'days_before' => $daysBefore,
                'tracking_id' => $trackingId,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("    ✗ Failed to send: {$e->getMessage()}");

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