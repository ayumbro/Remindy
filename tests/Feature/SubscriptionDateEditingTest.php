<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionDateEditingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Currency $currency;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create(['user_id' => $this->user->id]);
    }

    #[Test]
    public function user_can_edit_start_date_and_first_billing_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'start_date' => '2024-01-01',
            'first_billing_date' => '2024-01-01',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-01-15',
                'first_billing_date' => '2024-01-15',
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect(route('subscriptions.show', $subscription));
        
        $subscription->refresh();
        $this->assertEquals('2024-01-15', $subscription->start_date->format('Y-m-d'));
        $this->assertEquals('2024-01-15', $subscription->first_billing_date->format('Y-m-d'));
        $this->assertEquals(15, $subscription->billing_cycle_day);
    }



    #[Test]
    public function billing_cycle_day_is_recalculated_when_first_billing_date_changes()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'start_date' => '2024-01-01',
            'first_billing_date' => '2024-01-01',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'billing_cycle_day' => 1,
        ]);

        $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-31',
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $subscription->refresh();
        $this->assertEquals(31, $subscription->billing_cycle_day);
    }

    #[Test]
    public function next_billing_date_is_recalculated_after_date_changes()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'start_date' => '2024-01-01',
            'first_billing_date' => '2024-01-01',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
        ]);

        // Add a payment to test calculation with payment history
        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'paid',
            'payment_date' => '2024-01-15', // Payment after the original first billing date
        ]);

        $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-10', // Move first billing date earlier (before payment)
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $subscription->refresh();

        // With 1 payment made, next billing should be first_billing_date + 1 month
        $expectedNextBilling = Carbon::parse('2024-01-10')->addMonth();
        $actualNextBilling = Carbon::parse($subscription->next_billing_date);

        $this->assertEquals($expectedNextBilling->format('Y-m-d'), $actualNextBilling->format('Y-m-d'));
    }

    #[Test]
    public function cannot_change_first_billing_date_after_earliest_payment()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'start_date' => '2024-01-01',
            'first_billing_date' => '2024-01-01',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
        ]);

        // Add a payment
        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'paid',
            'payment_date' => '2024-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-20', // After earliest payment
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertSessionHasErrors(['first_billing_date']);
    }

    #[Test]
    public function can_edit_dates_when_no_payments_exist()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'start_date' => '2024-01-01',
            'first_billing_date' => '2024-01-01',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-02-01',
                'first_billing_date' => '2024-02-15',
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect(route('subscriptions.show', $subscription));
        
        $subscription->refresh();
        $this->assertEquals('2024-02-01', $subscription->start_date->format('Y-m-d'));
        $this->assertEquals('2024-02-15', $subscription->first_billing_date->format('Y-m-d'));
    }

    #[Test]
    public function can_set_first_billing_date_before_start_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'start_date' => '2024-01-15',
            'first_billing_date' => '2024-01-15',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('subscriptions.update', $subscription), [
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'start_date' => '2024-01-15',
                'first_billing_date' => '2024-01-10', // Before start date
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect(route('subscriptions.show', $subscription));

        $subscription->refresh();
        $this->assertEquals('2024-01-15', $subscription->start_date->format('Y-m-d'));
        $this->assertEquals('2024-01-10', $subscription->first_billing_date->format('Y-m-d'));

        // Verify billing cycle day is recalculated based on first billing date
        $this->assertEquals(10, $subscription->billing_cycle_day);
    }
}
