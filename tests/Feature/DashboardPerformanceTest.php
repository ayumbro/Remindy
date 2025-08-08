<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
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
    public function dashboard_loads_without_timeout_with_multiple_subscriptions()
    {
        // Create multiple subscriptions with different billing cycles
        $subscriptions = [];

        // Daily subscriptions
        for ($i = 0; $i < 5; $i++) {
            $subscriptions[] = Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'daily',
                'billing_interval' => 1,
                'price' => rand(100, 500) / 100, // $1.00 to $5.00
                'start_date' => Carbon::now()->subDays(rand(30, 90)),
                'first_billing_date' => Carbon::now()->startOfMonth()->addDays(rand(0, 10)),
                'end_date' => null,
            ]);
        }

        // Weekly subscriptions
        for ($i = 0; $i < 3; $i++) {
            $subscriptions[] = Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'weekly',
                'billing_interval' => 1,
                'price' => rand(500, 2000) / 100, // $5.00 to $20.00
                'start_date' => Carbon::now()->subWeeks(rand(4, 12)),
                'first_billing_date' => Carbon::now()->startOfMonth()->addDays(rand(0, 6)),
                'end_date' => null,
            ]);
        }

        // Monthly subscriptions
        for ($i = 0; $i < 5; $i++) {
            $subscriptions[] = Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'price' => rand(1000, 10000) / 100, // $10.00 to $100.00
                'start_date' => Carbon::now()->subMonths(rand(1, 6)),
                'first_billing_date' => Carbon::now()->startOfMonth()->addDays(rand(1, 28)),
                'end_date' => null,
            ]);
        }

        // Add some payment histories to simulate real usage
        foreach ($subscriptions as $subscription) {
            $paymentCount = rand(0, 10);
            for ($j = 0; $j < $paymentCount; $j++) {
                PaymentHistory::factory()->create([
                    'subscription_id' => $subscription->id,
                    'amount' => $subscription->price,
                    'currency_id' => $this->currency->id,
                    'payment_date' => Carbon::now()->subDays(rand(1, 60)),
                ]);
            }
        }

        // Measure the time it takes to load the dashboard
        $startTime = microtime(true);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert that the dashboard loads successfully
        $response->assertStatus(200);

        // Assert that it loads within a reasonable time (5 seconds)
        $this->assertLessThan(5.0, $executionTime, "Dashboard took {$executionTime} seconds to load, which exceeds the 5-second limit");

        // Log the execution time for monitoring
        $this->addToAssertionCount(1); // Count this as an assertion
        echo "\nDashboard loaded in ".round($executionTime, 3).' seconds with '.count($subscriptions)." subscriptions\n";
    }

    /** @test */
    public function forecast_calculation_completes_within_time_limit()
    {
        // Create subscriptions that could potentially cause infinite loops
        $problematicSubscriptions = [
            // Daily subscription with very small interval
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'daily',
                'billing_interval' => 1,
                'price' => 1.00,
                'start_date' => Carbon::now()->subYear(),
                'first_billing_date' => Carbon::now()->startOfMonth(),
                'end_date' => null,
            ]),

            // Weekly subscription with edge case dates
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'weekly',
                'billing_interval' => 1,
                'price' => 5.00,
                'start_date' => Carbon::now()->subMonths(6),
                'first_billing_date' => Carbon::now()->startOfMonth()->subDays(1),
                'end_date' => null,
            ]),

            // Monthly subscription with complex interval
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 2,
                'price' => 25.00,
                'start_date' => Carbon::now()->subYear(),
                'first_billing_date' => Carbon::now()->startOfMonth()->addDays(15),
                'end_date' => null,
            ]),
        ];

        // Measure the time it takes to calculate the forecast
        $startTime = microtime(true);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert that the forecast calculation completes
        $this->assertNotNull($forecast);

        // Assert that it completes within a reasonable time (2 seconds)
        $this->assertLessThan(2.0, $executionTime, "Forecast calculation took {$executionTime} seconds, which exceeds the 2-second limit");

        // Verify that the forecast contains expected data
        $this->assertGreaterThan(0, $forecast->count());

        echo "\nForecast calculated in ".round($executionTime, 3).' seconds for '.count($problematicSubscriptions)." subscriptions\n";
    }

    /** @test */
    public function forecast_handles_edge_case_dates_without_infinite_loops()
    {
        // Create subscriptions with edge case dates that could cause infinite loops
        $edgeCaseSubscriptions = [
            // Subscription starting exactly on month boundary
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'daily',
                'billing_interval' => 1,
                'price' => 2.00,
                'start_date' => Carbon::now()->startOfMonth(),
                'first_billing_date' => Carbon::now()->startOfMonth(),
                'end_date' => null,
            ]),

            // Subscription ending exactly on month boundary
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'weekly',
                'billing_interval' => 1,
                'price' => 10.00,
                'start_date' => Carbon::now()->subMonth(),
                'first_billing_date' => Carbon::now()->startOfMonth()->addDays(3),
                'end_date' => Carbon::now()->endOfMonth(),
            ]),

            // Subscription with first billing date in the future
            Subscription::factory()->create([
                'user_id' => $this->user->id,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'price' => 50.00,
                'start_date' => Carbon::now(),
                'first_billing_date' => Carbon::now()->addDays(10),
                'end_date' => null,
            ]),
        ];

        // Test that each subscription can be processed without infinite loops
        foreach ($edgeCaseSubscriptions as $subscription) {
            $startTime = microtime(true);

            $forecastAmount = $subscription->calculateMonthlyForecastAmount(
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            );

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Assert that calculation completes quickly (under 1 second per subscription)
            $this->assertLessThan(1.0, $executionTime, "Individual forecast calculation took {$executionTime} seconds for subscription {$subscription->id}");

            // Assert that we get a valid result (number, not null)
            $this->assertIsNumeric($forecastAmount);
            $this->assertGreaterThanOrEqual(0, $forecastAmount);
        }

        echo "\nAll edge case subscriptions processed successfully\n";
    }
}
