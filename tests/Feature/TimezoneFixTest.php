<?php

namespace Tests\Feature;

use App\Mail\BillReminderMail;
use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneFixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'date_format' => 'Y-m-d',
        ]);
        $this->currency = Currency::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function payment_date_is_stored_and_retrieved_correctly_without_timezone_shift()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        // Create a payment with a specific date
        $paymentDate = '2025-08-10';
        $payment = PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_date' => $paymentDate,
            'amount' => 10.00,
        ]);

        // Verify the date is stored correctly
        $this->assertEquals($paymentDate, $payment->payment_date->format('Y-m-d'));

        // Refresh from database to ensure no timezone conversion issues
        $payment->refresh();
        $this->assertEquals($paymentDate, $payment->payment_date->format('Y-m-d'));
    }

    /** @test */
    public function email_notification_formats_due_date_correctly_without_timezone_conversion()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        // Create a due date
        $dueDate = Carbon::parse('2025-08-15');
        $daysBefore = 3;
        $trackingId = 'test-tracking-id';

        // Create the email notification
        $mail = new BillReminderMail(
            $subscription,
            $this->user,
            $daysBefore,
            $dueDate,
            $trackingId
        );

        // Get the email content
        $content = $mail->content();
        $data = $content->with;

        // Verify the formatted due date is correct (no timezone shift)
        $this->assertEquals('2025-08-15', $data['formattedDueDate']);
    }

    /** @test */
    public function payment_edit_form_preserves_date_without_timezone_conversion()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        // Create a payment with a specific date
        $originalDate = '2025-08-10';
        $payment = PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_date' => $originalDate,
            'amount' => 10.00,
        ]);

        // Simulate editing the payment (update with same date)
        $response = $this->actingAs($this->user)
            ->putWithCsrf("/payment-histories/{$payment->id}", [
                'amount' => '10.00',
                'payment_date' => $originalDate,
                'currency_id' => $this->currency->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]);

        $response->assertRedirect();

        // Verify the date is still correct after update
        $payment->refresh();
        $this->assertEquals($originalDate, $payment->payment_date->format('Y-m-d'));
    }

    /** @test */
    public function subscription_creation_preserves_dates_without_timezone_conversion()
    {
        $startDate = '2025-08-01';
        $firstBillingDate = '2025-08-15';

        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => '10.00',
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => '1',
                'start_date' => $startDate,
                'first_billing_date' => $firstBillingDate,
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect();

        // Find the created subscription
        $subscription = Subscription::where('user_id', $this->user->id)
            ->where('name', 'Test Subscription')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertEquals($startDate, $subscription->start_date->format('Y-m-d'));
        $this->assertEquals($firstBillingDate, $subscription->first_billing_date->format('Y-m-d'));
    }

    /** @test */
    public function mark_as_paid_preserves_payment_date_without_timezone_conversion()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $paymentDate = '2025-08-10';

        $response = $this->actingAs($this->user)
            ->postWithCsrf("/subscriptions/{$subscription->id}/mark-paid", [
                'amount' => '10.00',
                'payment_date' => $paymentDate,
                'currency_id' => $this->currency->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]);

        $response->assertRedirect();

        // Verify the payment was created with the correct date
        $payment = PaymentHistory::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($paymentDate, $payment->payment_date->format('Y-m-d'));
    }
}
