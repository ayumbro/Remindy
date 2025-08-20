<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscription $subscription,
        public User $user,
        public int $daysBefore,
        public Carbon $dueDate,
        public string $trackingId
    ) {
        // No queue configuration needed for direct sending
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->generateSubject();

        return new Envelope(
            subject: $subject,
            from: $this->getFromAddress(),
            replyTo: $this->getFromAddress(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bill-reminder',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->user,
                'daysBefore' => $this->daysBefore,
                'dueDate' => $this->dueDate,
                'trackingId' => $this->trackingId,
                'formattedDueDate' => $this->formatDueDate(),
                'urgencyLevel' => $this->getUrgencyLevel(),
                'reminderText' => $this->getReminderText(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Generate the email subject based on days before due date.
     */
    private function generateSubject(): string
    {
        $subscriptionName = $this->subscription->name;

        return match (true) {
            $this->daysBefore >= 30 => "📅 Upcoming Bill: {$subscriptionName} due in {$this->daysBefore} days",
            $this->daysBefore >= 7 => "⏰ Bill Reminder: {$subscriptionName} due in {$this->daysBefore} days",
            $this->daysBefore >= 3 => "🔔 Important: {$subscriptionName} bill due in {$this->daysBefore} days",
            $this->daysBefore == 1 => "🚨 Urgent: {$subscriptionName} bill due tomorrow!",
            default => "🚨 Critical: {$subscriptionName} bill due today!",
        };
    }

    /**
     * Get the from address for the email.
     */
    private function getFromAddress(): string
    {
        if ($this->user->hasSmtpConfig()) {
            $config = $this->user->getSmtpConfig();

            return $config['from_address'];
        }

        return config('mail.from.address');
    }

    /**
     * Format the due date according to user's preferences.
     */
    private function formatDueDate(): string
    {
        $format = $this->user->date_format ?? 'Y-m-d';

        // Use the specialized email formatting method to avoid timezone conversion
        return \App\Helpers\DateHelper::formatDateForEmail($this->dueDate, $format);
    }

    /**
     * Get urgency level for styling.
     */
    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->daysBefore >= 15 => 'low',
            $this->daysBefore >= 7 => 'medium',
            $this->daysBefore >= 3 => 'high',
            default => 'critical',
        };
    }

    /**
     * Get reminder text based on days before due date.
     */
    private function getReminderText(): string
    {
        return match (true) {
            $this->daysBefore >= 30 => 'This is an early reminder to help you plan ahead.',
            $this->daysBefore >= 7 => 'Please make sure you have sufficient funds available.',
            $this->daysBefore >= 3 => 'Your payment will be processed soon. Please ensure your payment method is up to date.',
            $this->daysBefore == 1 => 'Your payment will be processed tomorrow. Please verify your payment details immediately.',
            default => 'Your payment is being processed today. Contact support if you need assistance.',
        };
    }
}
