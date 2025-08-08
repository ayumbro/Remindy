<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Currency $currency;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
        ]);
    }

    public function test_edit_page_displays_first_billing_date()
    {
        $response = $this->actingAs($this->user)
            ->get("/subscriptions/{$this->subscription->id}/edit");

        $response->assertStatus(200);

        // Check that the response contains the subscription data with first_billing_date
        $response->assertInertia(fn ($page) => $page->has('subscription.first_billing_date')
            ->where('subscription.first_billing_date', '2024-01-31')
        );
    }

    public function test_billing_cycle_and_interval_are_not_updated()
    {
        $originalBillingCycle = $this->subscription->billing_cycle;
        $originalBillingInterval = $this->subscription->billing_interval;

        $response = $this->actingAs($this->user)
            ->put("/subscriptions/{$this->subscription->id}", [
                'name' => 'Updated Subscription Name',
                'price' => 15.99,
                'currency_id' => $this->currency->id,
                // These should be ignored even if sent
                'billing_cycle' => 'yearly',
                'billing_interval' => 12,
            ]);

        $response->assertRedirect("/subscriptions/{$this->subscription->id}");

        // Refresh the subscription from database
        $this->subscription->refresh();

        // Billing cycle and interval should remain unchanged
        $this->assertEquals($originalBillingCycle, $this->subscription->billing_cycle);
        $this->assertEquals($originalBillingInterval, $this->subscription->billing_interval);

        // Other fields should be updated
        $this->assertEquals('Updated Subscription Name', $this->subscription->name);
        $this->assertEquals(15.99, $this->subscription->price);
    }

    public function test_immutable_fields_are_not_updated()
    {
        $originalStartDate = $this->subscription->start_date;
        $originalFirstBillingDate = $this->subscription->first_billing_date;
        $originalBillingCycle = $this->subscription->billing_cycle;
        $originalBillingInterval = $this->subscription->billing_interval;

        $response = $this->actingAs($this->user)
            ->put("/subscriptions/{$this->subscription->id}", [
                'name' => 'Updated Subscription Name',
                'price' => 15.99,
                'currency_id' => $this->currency->id,
                // These should all be ignored
                'start_date' => '2024-06-01',
                'first_billing_date' => '2024-06-01',
                'billing_cycle' => 'yearly',
                'billing_interval' => 12,
                'billing_cycle_day' => 15,
            ]);

        $response->assertRedirect("/subscriptions/{$this->subscription->id}");

        // Refresh the subscription from database
        $this->subscription->refresh();

        // All immutable fields should remain unchanged
        $this->assertEquals($originalStartDate->format('Y-m-d'), $this->subscription->start_date->format('Y-m-d'));
        $this->assertEquals($originalFirstBillingDate->format('Y-m-d'), $this->subscription->first_billing_date->format('Y-m-d'));
        $this->assertEquals($originalBillingCycle, $this->subscription->billing_cycle);
        $this->assertEquals($originalBillingInterval, $this->subscription->billing_interval);

        // Mutable fields should be updated
        $this->assertEquals('Updated Subscription Name', $this->subscription->name);
        $this->assertEquals(15.99, $this->subscription->price);
    }

    public function test_user_cannot_edit_other_users_subscription()
    {
        $otherUser = User::factory()->create();
        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/subscriptions/{$otherSubscription->id}", [
                'name' => 'Hacked Subscription',
                'price' => 999.99,
                'currency_id' => $this->currency->id,
            ]);

        $response->assertStatus(403);

        // Subscription should remain unchanged
        $otherSubscription->refresh();
        $this->assertNotEquals('Hacked Subscription', $otherSubscription->name);
    }

    public function test_guest_cannot_edit_subscription()
    {
        $response = $this->put("/subscriptions/{$this->subscription->id}", [
            'name' => 'Hacked Subscription',
            'price' => 999.99,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertRedirect('/login');

        // Subscription should remain unchanged
        $this->subscription->refresh();
        $this->assertNotEquals('Hacked Subscription', $this->subscription->name);
    }
}
