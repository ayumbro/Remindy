<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingBusinessLogicTest extends TestCase
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
    public function subscription_computed_status_is_active_when_no_end_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => null,
        ]);

        $this->assertEquals('active', $subscription->computed_status);
    }

    /** @test */
    public function subscription_computed_status_is_active_when_end_date_is_future()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->addDays(30),
        ]);

        $this->assertEquals('active', $subscription->computed_status);
    }

    /** @test */
    public function subscription_computed_status_is_ended_when_end_date_has_passed()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->subDays(1),
        ]);

        $this->assertEquals('ended', $subscription->computed_status);
    }

    /** @test */
    public function subscription_computed_status_is_ended_when_end_date_is_today()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'end_date' => Carbon::now()->startOfDay(),
        ]);

        $this->assertEquals('ended', $subscription->computed_status);
    }

    /** @test */
    public function mark_as_paid_creates_payment_history()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'price' => 9.99,
        ]);

        $this->assertEquals(0, $subscription->paymentHistories()->count());

        $subscription->markAsPaid();

        $this->assertEquals(1, $subscription->paymentHistories()->count());
        
        $payment = $subscription->paymentHistories()->first();
        $this->assertEquals(9.99, $payment->amount);
        $this->assertEquals($this->currency->id, $payment->currency_id);
        $this->assertEquals('paid', $payment->status);
    }

    /** @test */
    public function mark_as_paid_with_custom_amount_and_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'price' => 9.99,
        ]);

        $customDate = Carbon::now()->subDays(5);
        $customAmount = 15.50;

        $subscription->markAsPaid($customAmount, $customDate);

        $payment = $subscription->paymentHistories()->first();
        $this->assertEquals($customAmount, $payment->amount);
        $this->assertEquals($customDate->format('Y-m-d'), $payment->payment_date->format('Y-m-d'));
    }

    /** @test */
    public function next_billing_date_is_calculated_from_first_billing_date()
    {
        $firstBillingDate = Carbon::parse('2024-01-15');
        
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'first_billing_date' => $firstBillingDate,
        ]);

        // No payments yet - next billing should be first billing date
        $this->assertEquals($firstBillingDate->format('Y-m-d'), $subscription->next_billing_date->format('Y-m-d'));

        // After one payment - next billing should be one month later
        $subscription->markAsPaid(9.99, $firstBillingDate);
        $expectedNext = $firstBillingDate->copy()->addMonth();
        $this->assertEquals($expectedNext->format('Y-m-d'), $subscription->next_billing_date->format('Y-m-d'));
    }

    /** @test */
    public function subscription_is_overdue_when_next_billing_date_has_passed()
    {
        $pastDate = Carbon::now()->subDays(5);
        
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'first_billing_date' => $pastDate,
        ]);

        $this->assertTrue($subscription->is_overdue);
    }

    /** @test */
    public function subscription_is_not_overdue_when_next_billing_date_is_future()
    {
        $futureDate = Carbon::now()->addDays(5);
        
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'first_billing_date' => $futureDate,
        ]);

        $this->assertFalse($subscription->is_overdue);
    }

    /** @test */
    public function ended_subscription_is_not_overdue()
    {
        $pastDate = Carbon::now()->subDays(5);
        
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'first_billing_date' => $pastDate,
            'end_date' => Carbon::now()->subDays(1), // Ended yesterday
        ]);

        $this->assertFalse($subscription->is_overdue);
    }

    /** @test */
    public function subscription_can_be_deleted_when_no_payment_history()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
        ]);

        $this->assertTrue($subscription->canBeDeleted());
    }

    /** @test */
    public function subscription_cannot_be_deleted_when_payment_history_exists()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
        ]);

        $subscription->markAsPaid();

        $this->assertFalse($subscription->canBeDeleted());
    }
}
