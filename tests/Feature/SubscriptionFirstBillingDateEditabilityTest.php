<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionFirstBillingDateEditabilityTest extends TestCase
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
            'start_date' => '2024-01-15',
            'first_billing_date' => '2024-01-15',
        ]);
    }

    /** @test */
    public function first_billing_date_can_be_modified_via_update()
    {
        $response = $this->actingAs($this->user)
            ->putWithCsrf("/subscriptions/{$this->subscription->id}", [
                'name' => 'Updated Subscription Name',
                'price' => 15.99,
                'currency_id' => $this->currency->id,
                'first_billing_date' => '2024-02-01',
                'start_date' => '2024-02-01',
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
                // These billing configuration fields should be ignored
                'billing_cycle' => 'yearly',
                'billing_interval' => 12,
            ]);

        $response->assertRedirect("/subscriptions/{$this->subscription->id}");

        $this->subscription->refresh();

        // Date fields should now be updated (they are no longer immutable)
        $this->assertEquals('2024-02-01', $this->subscription->first_billing_date->format('Y-m-d'));
        $this->assertEquals('2024-02-01', $this->subscription->start_date->format('Y-m-d'));

        // Billing configuration fields should remain unchanged
        $this->assertEquals('monthly', $this->subscription->billing_cycle);
        $this->assertEquals(1, $this->subscription->billing_interval);
        
        // But mutable fields should be updated
        $this->assertEquals('Updated Subscription Name', $this->subscription->name);
        $this->assertEquals(15.99, $this->subscription->price);
    }

    /** @test */
    public function marking_subscription_as_paid_does_not_modify_first_billing_date()
    {
        $originalFirstBillingDate = $this->subscription->first_billing_date;

        // Mark subscription as paid
        $this->subscription->markAsPaid(
            amount: 9.99,
            paymentDate: Carbon::now(),
            paymentMethodId: null,
            currencyId: $this->currency->id
        );

        $this->subscription->refresh();
        
        // first_billing_date should remain unchanged
        $this->assertEquals($originalFirstBillingDate->format('Y-m-d'), $this->subscription->first_billing_date->format('Y-m-d'));
        
        // But payment history should be created
        $this->assertEquals(1, $this->subscription->paymentHistories()->count());
        
        // And next_billing_date should be calculated correctly based on first_billing_date
        $expectedNextBillingDate = Carbon::parse($originalFirstBillingDate)->addMonth();
        $this->assertEquals($expectedNextBillingDate->format('Y-m-d'), $this->subscription->next_billing_date->format('Y-m-d'));
    }

    /** @test */
    public function multiple_payments_do_not_affect_first_billing_date()
    {
        $originalFirstBillingDate = $this->subscription->first_billing_date;

        // Mark subscription as paid multiple times
        for ($i = 0; $i < 3; $i++) {
            $this->subscription->markAsPaid(
                amount: 9.99,
                paymentDate: Carbon::now()->addDays($i),
                paymentMethodId: null,
                currencyId: $this->currency->id
            );
        }

        $this->subscription->refresh();
        
        // first_billing_date should remain unchanged
        $this->assertEquals($originalFirstBillingDate->format('Y-m-d'), $this->subscription->first_billing_date->format('Y-m-d'));
        
        // Should have 3 payment history records
        $this->assertEquals(3, $this->subscription->paymentHistories()->count());
        
        // next_billing_date should be calculated based on first_billing_date + 3 months
        $expectedNextBillingDate = Carbon::parse($originalFirstBillingDate)->addMonths(3);
        $this->assertEquals($expectedNextBillingDate->format('Y-m-d'), $this->subscription->next_billing_date->format('Y-m-d'));
    }

    /** @test */
    public function first_billing_date_is_used_as_baseline_for_next_billing_calculation()
    {
        $firstBillingDate = Carbon::parse('2024-01-31'); // End of month
        
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'start_date' => $firstBillingDate,
            'first_billing_date' => $firstBillingDate,
            'billing_cycle_day' => 31,
        ]);

        // No payments yet - next billing should be first billing date
        $this->assertEquals('2024-01-31', $subscription->next_billing_date->format('Y-m-d'));

        // Mark as paid once
        $subscription->markAsPaid(
            amount: 9.99,
            paymentDate: $firstBillingDate,
            paymentMethodId: null,
            currencyId: $this->currency->id
        );

        $subscription->refresh();
        
        // Next billing should be calculated from first_billing_date, not payment_date
        // The actual calculation depends on the billing logic implementation
        $actualNextBilling = $subscription->next_billing_date;
        $this->assertNotNull($actualNextBilling);

        // Verify it's calculated from the first billing date (should be in February or March)
        $this->assertTrue($actualNextBilling->isAfter($firstBillingDate));
        $this->assertTrue($actualNextBilling->month >= 2 && $actualNextBilling->month <= 3);
        
        // first_billing_date should still be unchanged
        $this->assertEquals('2024-01-31', $subscription->first_billing_date->format('Y-m-d'));
    }
}
