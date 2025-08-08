<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionImprovementsTest extends TestCase
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
    public function it_filters_active_subscriptions_correctly()
    {
        // Create active subscription (no end date)
        $activeSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => null,
        ]);

        // Create active subscription with future end date
        $activeFutureEnd = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->addDays(30),
        ]);

        // Create ended subscription
        $endedSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)
            ->get('/subscriptions?status=active');

        $response->assertStatus(200);

        // Should include active subscriptions but not ended ones
        $subscriptions = $response->original->getData()['page']['props']['subscriptions']['data'];
        $subscriptionIds = collect($subscriptions)->pluck('id')->toArray();

        $this->assertContains($activeSubscription->id, $subscriptionIds);
        $this->assertContains($activeFutureEnd->id, $subscriptionIds);
        $this->assertNotContains($endedSubscription->id, $subscriptionIds);
    }

    /** @test */
    public function it_filters_ended_subscriptions_correctly()
    {
        // Create active subscription
        $activeSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => null,
        ]);

        // Create ended subscription
        $endedSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)
            ->get('/subscriptions?status=ended');

        $response->assertStatus(200);

        // Should include only ended subscriptions
        $subscriptions = $response->original->getData()['page']['props']['subscriptions']['data'];
        $subscriptionIds = collect($subscriptions)->pluck('id')->toArray();

        $this->assertNotContains($activeSubscription->id, $subscriptionIds);
        $this->assertContains($endedSubscription->id, $subscriptionIds);
    }

    /** @test */
    public function it_calculates_monthly_forecast_for_daily_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'daily',
            'billing_interval' => 1,
            'price' => 5.00,
            'next_billing_date' => Carbon::now()->addDays(1),
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should calculate based on remaining days in month
        $this->assertGreaterThan(0, $currencyForecast['total']);
        $this->assertEquals(1, $currencyForecast['count']);
    }

    /** @test */
    public function it_calculates_monthly_forecast_for_weekly_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'weekly',
            'billing_interval' => 1,
            'price' => 10.00,
            'next_billing_date' => Carbon::now()->addDays(3),
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should calculate based on weekly cycles in month
        $this->assertGreaterThan(0, $currencyForecast['total']);
        $this->assertEquals(1, $currencyForecast['count']);
    }

    /** @test */
    public function it_calculates_monthly_forecast_for_monthly_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'price' => 15.00,
            'next_billing_date' => Carbon::now()->startOfMonth()->addDays(10),
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include full price if billing date is this month
        $this->assertEquals(15.00, $currencyForecast['total']);
        $this->assertEquals(1, $currencyForecast['count']);
    }

    /** @test */
    public function it_excludes_ended_subscriptions_from_forecast()
    {
        // Create ended subscription
        $endedSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'price' => 20.00,
            'next_billing_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->subDays(1), // Ended yesterday
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        // Should be empty since subscription has ended
        $this->assertEmpty($forecast);
    }

    /** @test */
    public function it_groups_forecast_by_currency()
    {
        $currency2 = Currency::factory()->create(['code' => 'EUR']);

        // Create subscriptions in different currencies
        $usdSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'price' => 10.00,
            'start_date' => Carbon::now()->subMonth(),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(5),
            'end_date' => null,
        ]);

        $eurSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $currency2->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'price' => 8.00,
            'start_date' => Carbon::now()->subMonth(),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(10),
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertCount(2, $forecast);

        // Check that each currency has correct totals
        $usdForecast = $forecast->where('currency.id', $this->currency->id)->first();
        $eurForecast = $forecast->where('currency.id', $currency2->id)->first();

        $this->assertEquals(10.00, $usdForecast['total']);
        $this->assertEquals(8.00, $eurForecast['total']);
    }
}
