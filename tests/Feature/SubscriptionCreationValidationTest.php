<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
    }

    /** @test */
    public function subscription_creation_requires_all_mandatory_fields()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', []);

        $response->assertSessionHasErrors([
            'name',
            'price',
            'currency_id',
            'billing_cycle',
            'billing_interval',
            'start_date'
        ]);
    }

    /** @test */
    public function subscription_creation_validates_price_is_positive()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => -5.00, // Invalid negative price
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
            ]);

        $response->assertSessionHasErrors(['price']);
    }

    /** @test */
    public function subscription_creation_validates_billing_cycle_values()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'invalid-cycle', // Invalid billing cycle
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
            ]);

        $response->assertSessionHasErrors(['billing_cycle']);
    }

    /** @test */
    public function subscription_creation_validates_billing_interval_range()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 15, // Invalid - max is 12
                'start_date' => '2024-01-01',
            ]);

        $response->assertSessionHasErrors(['billing_interval']);
    }

    /** @test */
    public function subscription_creation_validates_end_date_after_start_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
                'end_date' => '2024-01-10', // Before start date
            ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    /** @test */
    public function subscription_creation_allows_first_billing_date_before_start_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
                'first_billing_date' => '2024-01-10', // Before start date - should be allowed
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect('/subscriptions');
        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Test Subscription',
            'start_date' => '2024-01-15 00:00:00',
            'first_billing_date' => '2024-01-10 00:00:00',
        ]);
    }

    /** @test */
    public function subscription_creation_sets_first_billing_date_to_start_date_when_not_provided()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
                // first_billing_date not provided
            ]);

        $response->assertRedirect('/subscriptions');

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('2024-01-15', $subscription->first_billing_date->format('Y-m-d'));
        $this->assertEquals('2024-01-15', $subscription->start_date->format('Y-m-d'));
    }

    /** @test */
    public function subscription_creation_validates_payment_method_belongs_to_user()
    {
        $otherUser = User::factory()->create();
        $otherUserPaymentMethod = PaymentMethod::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
                'payment_method_id' => $otherUserPaymentMethod->id, // Belongs to other user
            ]);

        $response->assertSessionHasErrors(['payment_method_id']);
    }

    /** @test */
    public function subscription_creation_accepts_valid_payment_method()
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
                'payment_method_id' => $paymentMethod->id,
            ]);

        $response->assertRedirect('/subscriptions');

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($paymentMethod->id, $subscription->payment_method_id);
    }

    /** @test */
    public function subscription_creation_sets_billing_cycle_day_for_monthly_subscriptions()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
            ]);

        $response->assertRedirect('/subscriptions');

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals(15, $subscription->billing_cycle_day); // Should be set to day of start_date
    }

    /** @test */
    public function subscription_creation_validates_currency_exists()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => 99999, // Non-existent currency
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-15',
            ]);

        $response->assertSessionHasErrors(['currency_id']);
    }
}
