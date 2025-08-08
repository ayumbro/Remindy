<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHistoryDeletionTest extends TestCase
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

    public function test_user_can_delete_most_recent_payment()
    {
        // Create two payment records
        $olderPayment = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-01-31',
            'amount' => 10.00,
        ]);

        $newerPayment = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-02-29',
            'amount' => 10.00,
        ]);

        // User should be able to delete the most recent payment
        $response = $this->actingAs($this->user)
            ->delete("/payment-histories/{$newerPayment->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Payment record deleted successfully!');

        // Newer payment should be deleted
        $this->assertDatabaseMissing('payment_histories', ['id' => $newerPayment->id]);

        // Older payment should still exist
        $this->assertDatabaseHas('payment_histories', ['id' => $olderPayment->id]);
    }

    public function test_user_cannot_delete_older_payment()
    {
        // Create two payment records
        $olderPayment = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-01-31',
            'amount' => 10.00,
        ]);

        $newerPayment = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-02-29',
            'amount' => 10.00,
        ]);

        // User should NOT be able to delete the older payment
        $response = $this->actingAs($this->user)
            ->delete("/payment-histories/{$olderPayment->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors(['payment_history' => 'Only the most recent payment can be deleted.']);

        // Both payments should still exist
        $this->assertDatabaseHas('payment_histories', ['id' => $olderPayment->id]);
        $this->assertDatabaseHas('payment_histories', ['id' => $newerPayment->id]);
    }

    public function test_user_cannot_delete_payment_from_other_users_subscription()
    {
        $otherUser = User::factory()->create();
        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'currency_id' => $this->currency->id,
        ]);

        $payment = PaymentHistory::factory()->create([
            'subscription_id' => $otherSubscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-01-31',
            'amount' => 10.00,
        ]);

        // User should NOT be able to delete payment from other user's subscription
        $response = $this->actingAs($this->user)
            ->delete("/payment-histories/{$payment->id}");

        $response->assertStatus(403);

        // Payment should still exist
        $this->assertDatabaseHas('payment_histories', ['id' => $payment->id]);
    }

    public function test_guest_cannot_delete_payment()
    {
        $payment = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-01-31',
            'amount' => 10.00,
        ]);

        // Guest should be redirected to login
        $response = $this->delete("/payment-histories/{$payment->id}");

        $response->assertRedirect('/login');

        // Payment should still exist
        $this->assertDatabaseHas('payment_histories', ['id' => $payment->id]);
    }

    public function test_next_billing_date_recalculates_after_payment_deletion()
    {
        // Create two payment records
        PaymentHistory::factory()->paid()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-01-31',
            'amount' => 10.00,
        ]);

        $newerPayment = PaymentHistory::factory()->paid()->create([
            'subscription_id' => $this->subscription->id,
            'currency_id' => $this->currency->id,
            'payment_date' => '2024-02-29',
            'amount' => 10.00,
        ]);

        // Before deletion: should have 2 payments, next billing date should be 3rd cycle
        $this->subscription->refresh();
        $this->assertEquals(2, $this->subscription->paymentHistories()->where('status', 'paid')->count());
        $expectedNextDate = $this->subscription->calculateNextBillingDateFromFirst(2);
        $this->assertEquals($expectedNextDate->format('Y-m-d'), $this->subscription->next_billing_date->format('Y-m-d'));

        // Delete the most recent payment
        $this->actingAs($this->user)
            ->delete("/payment-histories/{$newerPayment->id}");

        // After deletion: should have 1 payment, next billing date should be 2nd cycle
        $this->subscription->refresh();
        $this->assertEquals(1, $this->subscription->paymentHistories()->where('status', 'paid')->count());
        $expectedNextDate = $this->subscription->calculateNextBillingDateFromFirst(1);
        $this->assertEquals($expectedNextDate->format('Y-m-d'), $this->subscription->next_billing_date->format('Y-m-d'));
    }
}
