<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingDateEndDateTest extends TestCase
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

    public function test_subscription_with_past_end_date_returns_null_next_billing_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(6),
            'first_billing_date' => Carbon::now()->subMonths(6),
            'end_date' => Carbon::now()->subDays(10), // Ended 10 days ago
        ]);

        // The next_billing_date should be null for ended subscriptions
        $this->assertNull($subscription->next_billing_date);
    }

    public function test_subscription_with_future_end_date_returns_next_billing_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::now()->addDays(30), // Ends in 30 days
        ]);

        // Should have a next billing date since it hasn't ended yet
        $this->assertNotNull($subscription->next_billing_date);
    }

    public function test_subscription_with_no_end_date_returns_next_billing_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => null, // No end date
        ]);

        // Should have a next billing date since it's ongoing
        $this->assertNotNull($subscription->next_billing_date);
    }

    public function test_subscription_ending_today_edge_case()
    {
        // Test subscription that ends today
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::now()->startOfDay(), // Ends today at start of day
        ]);

        // Since current time is after start of day, this should return null
        $this->assertNull($subscription->next_billing_date);
    }

    public function test_subscription_ending_tomorrow_edge_case()
    {
        // Test subscription that ends tomorrow
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::now()->addDay()->startOfDay(), // Ends tomorrow at start of day
        ]);

        // Should still have a next billing date since it hasn't ended yet
        $this->assertNotNull($subscription->next_billing_date);
    }

    public function test_frontend_display_logic_for_ended_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(6),
            'first_billing_date' => Carbon::now()->subMonths(6),
            'end_date' => Carbon::now()->subDays(5), // Ended 5 days ago
        ]);

        $response = $this->actingAs($this->user)
            ->get("/subscriptions/{$subscription->id}");

        $response->assertStatus(200);

        // Check that next_billing_date is null in the response
        $response->assertInertia(fn ($page) => $page->where('subscription.next_billing_date', null)
        );
    }

    public function test_timezone_handling_for_end_date_comparison()
    {
        // Test with a subscription that ended in a different timezone
        $endDate = Carbon::now('UTC')->subHours(2); // 2 hours ago in UTC

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => $endDate,
        ]);

        // Should return null since the end date has passed
        $this->assertNull($subscription->next_billing_date);
    }

    public function test_subscription_serialization_with_ended_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(6),
            'first_billing_date' => Carbon::now()->subMonths(6),
            'end_date' => Carbon::now()->subDays(10), // Ended 10 days ago
        ]);

        // Test how the subscription serializes to array (like Inertia would do)
        $subscriptionArray = $subscription->toArray();

        // The next_billing_date should be null in the serialized array
        $this->assertNull($subscriptionArray['next_billing_date']);

        // Also test direct accessor
        $this->assertNull($subscription->next_billing_date);

        // Verify the end date is actually in the past
        $this->assertTrue(Carbon::now()->isAfter($subscription->end_date), 'End date should be in the past');
    }

    public function test_subscription_with_end_date_exactly_at_midnight()
    {
        // Test subscription that ended exactly at midnight today
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::today(), // Today at 00:00:00
        ]);

        // Since we're past midnight, this should return null
        $this->assertNull($subscription->next_billing_date);
    }

    public function test_subscription_status_does_not_affect_end_date_logic()
    {
        // Test that even active subscriptions with past end dates return null
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::now()->subDays(1), // Ended yesterday
        ]);

        // Should return null regardless of status if end date has passed
        $this->assertNull($subscription->next_billing_date);
    }
}
