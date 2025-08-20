<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyForecastBudgetTest extends TestCase
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
    public function it_includes_all_daily_billing_cycles_for_the_month()
    {
        // Create a daily subscription that started before this month
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'daily',
            'billing_interval' => 1,
            'price' => 5.00,
            'start_date' => Carbon::now()->subMonth(),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(2), // 3rd of month
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include all daily charges from the 3rd to the end of month
        $daysInMonth = Carbon::now()->endOfMonth()->day;
        $billingDays = $daysInMonth - 2; // Starting from 3rd day
        $expectedTotal = $billingDays * 5.00;

        $this->assertEquals($expectedTotal, $currencyForecast['total']);
    }

    /** @test */
    public function it_includes_paid_bills_in_forecast_total()
    {
        // Create a weekly subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'weekly',
            'billing_interval' => 1,
            'price' => 10.00,
            'start_date' => Carbon::now()->subMonth(),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(1), // 2nd of month
            'end_date' => null,
        ]);

        // Mark some bills as paid (this should NOT reduce the forecast)
        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 10.00,
            'currency_id' => $this->currency->id,
            'payment_date' => Carbon::now()->startOfMonth()->addDays(1),
        ]);

        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 10.00,
            'currency_id' => $this->currency->id,
            'payment_date' => Carbon::now()->startOfMonth()->addDays(8),
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include ALL weekly cycles in the month, regardless of payment status
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $firstBilling = $startOfMonth->copy()->addDays(1);

        $weeklyCount = 0;
        $currentBilling = $firstBilling->copy();
        while ($currentBilling->lte($endOfMonth)) {
            $weeklyCount++;
            $currentBilling->addWeek();
        }

        $expectedTotal = $weeklyCount * 10.00;
        $this->assertEquals($expectedTotal, $currencyForecast['total']);
    }

    /** @test */
    public function it_includes_overdue_bills_in_forecast()
    {
        // Create a monthly subscription with billing date early in the month
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'price' => 25.00,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(5), // 6th of month
            'end_date' => null,
        ]);

        // Don't create any payment history - bill is overdue

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include the monthly bill even though it's overdue
        $this->assertEquals(25.00, $currencyForecast['total']);
    }

    /** @test */
    public function it_excludes_ended_subscriptions_from_forecast()
    {
        // Create a subscription that ended before this month
        $endedSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'price' => 20.00,
            'start_date' => Carbon::now()->subMonths(3),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(10),
            'end_date' => Carbon::now()->subDays(5), // Ended 5 days ago
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        // Should be empty since subscription has ended
        $this->assertEmpty($forecast);
    }

    /** @test */
    public function it_includes_subscriptions_that_end_during_the_month()
    {
        // Create a subscription that ends mid-month but in the future
        $endDate = Carbon::now()->endOfMonth()->subDays(5); // Ends 5 days before month end
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'daily',
            'billing_interval' => 1,
            'price' => 3.00,
            'start_date' => Carbon::now()->startOfMonth()->subDays(10), // Started before this month
            'first_billing_date' => Carbon::now()->startOfMonth(),
            'end_date' => $endDate,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Calculate expected total: daily charges from start of month to end date
        $startOfMonth = Carbon::now()->startOfMonth();
        $daysInForecast = (int) $startOfMonth->diffInDays($endDate) + 1; // +1 to include both start and end days, cast to int
        $expectedTotal = $daysInForecast * 3.00;

        // The actual calculation should match our expectation
        $this->assertEquals($expectedTotal, $currencyForecast['total']);
    }

    /** @test */
    public function it_calculates_quarterly_subscriptions_correctly()
    {
        // Create a quarterly subscription with billing date in this month
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'quarterly',
            'billing_interval' => 1,
            'price' => 150.00,
            'start_date' => Carbon::now()->subYear(),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(20),
            'end_date' => null,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include the quarterly bill if it falls in this month
        $this->assertEquals(150.00, $currencyForecast['total']);
    }

    /** @test */
    public function it_represents_total_monthly_budget_not_remaining_payments()
    {
        // Create multiple subscriptions with different payment statuses
        $dailySub = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'daily',
            'billing_interval' => 1,
            'price' => 2.00,
            'start_date' => Carbon::now()->subMonth(),
            'first_billing_date' => Carbon::now()->startOfMonth(),
        ]);

        $monthlySub = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'price' => 50.00,
            'start_date' => Carbon::now()->subMonths(2),
            'first_billing_date' => Carbon::now()->startOfMonth()->addDays(15),
        ]);

        // Pay some bills for the daily subscription
        PaymentHistory::factory()->count(10)->create([
            'subscription_id' => $dailySub->id,
            'amount' => 2.00,
            'currency_id' => $this->currency->id,
        ]);

        // Pay the monthly bill
        PaymentHistory::factory()->create([
            'subscription_id' => $monthlySub->id,
            'amount' => 50.00,
            'currency_id' => $this->currency->id,
        ]);

        $forecast = Subscription::getMonthlyForecast($this->user->id);

        $this->assertNotEmpty($forecast);
        $currencyForecast = $forecast->first();

        // Should include ALL billing cycles for the month, not just unpaid ones
        $daysInMonth = Carbon::now()->endOfMonth()->day;
        $expectedDailyTotal = $daysInMonth * 2.00;
        $expectedMonthlyTotal = 50.00;
        $expectedTotal = $expectedDailyTotal + $expectedMonthlyTotal;

        $this->assertEquals($expectedTotal, $currencyForecast['total']);
        $this->assertEquals(2, $currencyForecast['count']); // 2 subscriptions contributing
    }
}
