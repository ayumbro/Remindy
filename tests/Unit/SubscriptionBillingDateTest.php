<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive tests for subscription billing date calculation logic.
 *
 * These tests cover all edge cases including end-of-month dates, leap years,
 * and various billing cycles to ensure robust billing date handling.
 */
class SubscriptionBillingDateTest extends TestCase
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

    /**
     * Test monthly billing with end-of-month dates (31st).
     *
     * Scenario: Subscription created on January 31st
     * Expected progression: Jan 31 → Feb 28/29 → Mar 31 → Apr 30 → May 31
     */
    public function test_monthly_billing_with_31st_day()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // Test with 0 payments (first billing date)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(0);
        $this->assertEquals('2024-01-31', $nextDate->format('Y-m-d'));

        // Test with 1 payment (January 31st → February 29th, 2024 is a leap year)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-29', $nextDate->format('Y-m-d'));

        // Test with 2 payments (February 29th → March 31st, revert to original day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(2);
        $this->assertEquals('2024-03-31', $nextDate->format('Y-m-d'));

        // Test with 3 payments (March 31st → April 30th, adjust to last day of April)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(3);
        $this->assertEquals('2024-04-30', $nextDate->format('Y-m-d'));

        // Test with 4 payments (April 30th → May 31st, revert to original day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(4);
        $this->assertEquals('2024-05-31', $nextDate->format('Y-m-d'));
    }

    /**
     * Test monthly billing with 30th day.
     *
     * Scenario: Subscription created on January 30th
     * Expected progression: Jan 30 → Feb 28/29 → Mar 30 → Apr 30 → May 30
     */
    public function test_monthly_billing_with_30th_day()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-30',
            'first_billing_date' => '2024-01-30',
            'billing_cycle_day' => 30,
        ]);

        // Test with 1 payment (January 30th → February 29th, 2024 is a leap year)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-29', $nextDate->format('Y-m-d'));

        // Test with 2 payments (February 29th → March 30th, revert to original day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(2);
        $this->assertEquals('2024-03-30', $nextDate->format('Y-m-d'));

        // Test with 3 payments (March 30th → April 30th, same day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(3);
        $this->assertEquals('2024-04-30', $nextDate->format('Y-m-d'));

        // Test with 4 payments (April 30th → May 30th, same day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(4);
        $this->assertEquals('2024-05-30', $nextDate->format('Y-m-d'));
    }

    /**
     * Test leap year handling for February 29th.
     */
    public function test_leap_year_february_29th()
    {
        // Test in leap year (2024)
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-29',
            'first_billing_date' => '2024-01-29',
            'billing_cycle_day' => 29,
        ]);

        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-29', $nextDate->format('Y-m-d'));

        // Test in non-leap year (2023)
        $subscription->update([
            'start_date' => '2023-01-29',
            'first_billing_date' => '2023-01-29',
        ]);

        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2023-02-28', $nextDate->format('Y-m-d'));
    }

    /**
     * Test quarterly billing with end-of-month handling.
     */
    public function test_quarterly_billing_with_end_of_month()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'quarterly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // Test with 1 payment (January 31st → April 30th, 3 months later, adjust to last day of April)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-04-30', $nextDate->format('Y-m-d'));

        // Test with 2 payments (April 30th → July 31st, revert to original day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(2);
        $this->assertEquals('2024-07-31', $nextDate->format('Y-m-d'));
    }

    /**
     * Test yearly billing with leap year edge case.
     */
    public function test_yearly_billing_leap_year_edge_case()
    {
        // Test February 29th in leap year → February 28th in non-leap year
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'yearly',
            'billing_interval' => 1,
            'start_date' => '2024-02-29',
            'first_billing_date' => '2024-02-29',
        ]);

        // Test with 1 payment (2024-02-29 → 2025-02-28, adjust for non-leap year)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2025-02-28', $nextDate->format('Y-m-d'));

        // Test with 2 payments (2025-02-28 → 2026-02-28, same day)
        $nextDate = $subscription->calculateNextBillingDateFromFirst(2);
        $this->assertEquals('2026-02-28', $nextDate->format('Y-m-d'));
    }

    /**
     * Test daily and weekly billing cycles (should not use billing_cycle_day).
     */
    public function test_daily_and_weekly_billing_cycles()
    {
        // Test daily billing
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'daily',
            'billing_interval' => 7,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => null,
        ]);

        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-07', $nextDate->format('Y-m-d'));

        // Test weekly billing
        $subscription->update([
            'billing_cycle' => 'weekly',
            'billing_interval' => 2,
        ]);

        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-14', $nextDate->format('Y-m-d'));
    }

    /**
     * Test setBillingCycleDay method.
     */
    public function test_set_billing_cycle_day()
    {
        // Test monthly subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => null,
        ]);

        $subscription->setBillingCycleDay();
        $this->assertEquals(31, $subscription->billing_cycle_day);

        // Test daily subscription (should be null)
        $subscription->update([
            'billing_cycle' => 'daily',
            'billing_cycle_day' => null,
        ]);

        $subscription->setBillingCycleDay();
        $this->assertNull($subscription->billing_cycle_day);
    }

    /**
     * Test markAsPaid method creates payment history.
     */
    public function test_mark_as_paid_creates_payment_history()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        $subscription->markAsPaid();

        // Should create payment history
        $this->assertCount(1, $subscription->paymentHistories);

        // Payment history should have correct details
        $payment = $subscription->paymentHistories->first();
        $this->assertEquals($subscription->price, $payment->amount);
        $this->assertEquals('paid', $payment->status);
    }

    /**
     * Test calculateNextBillingDateFromFirst method with no payments.
     */
    public function test_calculate_next_billing_date_from_first_no_payments()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // With 0 payments, next billing date should be the first billing date
        $nextDate = $subscription->calculateNextBillingDateFromFirst(0);
        $this->assertEquals('2024-01-31', $nextDate->format('Y-m-d'));
    }

    /**
     * Test calculateNextBillingDateFromFirst method with one payment.
     */
    public function test_calculate_next_billing_date_from_first_one_payment()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // With 1 payment, next billing date should be one month after first billing date
        $nextDate = $subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals('2024-02-29', $nextDate->format('Y-m-d')); // Leap year adjustment
    }

    /**
     * Test calculateNextBillingDateFromFirst method with multiple payments.
     */
    public function test_calculate_next_billing_date_from_first_multiple_payments()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // With 3 payments, next billing date should be 3 months after first billing date
        $nextDate = $subscription->calculateNextBillingDateFromFirst(3);
        $this->assertEquals('2024-04-30', $nextDate->format('Y-m-d')); // April has 30 days
    }

    /**
     * Test next_billing_date accessor with no payments.
     */
    public function test_next_billing_date_accessor_no_payments()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // With no payments, next billing date should be the first billing date
        $this->assertEquals('2024-01-31', $subscription->next_billing_date->format('Y-m-d'));
    }

    /**
     * Test next_billing_date accessor with payments.
     */
    public function test_next_billing_date_accessor_with_payments()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'billing_cycle_day' => 31,
        ]);

        // Create a payment
        $subscription->paymentHistories()->create([
            'amount' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_date' => '2024-01-31',
        ]);

        // With 1 payment, next billing date should be one month after first billing date
        $this->assertEquals('2024-02-29', $subscription->fresh()->next_billing_date->format('Y-m-d'));
    }

    /**
     * Test next_billing_date accessor returns null for ended subscriptions.
     */
    public function test_next_billing_date_accessor_ended_subscription()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => '2024-01-31',
            'first_billing_date' => '2024-01-31',
            'end_date' => '2024-02-15', // Subscription ended
            'billing_cycle_day' => 31,
        ]);

        // For ended subscriptions, next_billing_date should be null
        $this->assertNull($subscription->next_billing_date);
    }
}
