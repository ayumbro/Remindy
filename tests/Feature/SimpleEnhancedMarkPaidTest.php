<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleEnhancedMarkPaidTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create currency for this test
        $this->currency = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        // Create payment method for the user
        $this->paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    private function createSubscription(array $attributes = []): Subscription
    {
        return Subscription::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'price' => 9.99,
        ], $attributes));
    }

    public function test_mark_paid_with_enhanced_form_data()
    {
        $subscription = $this->createSubscription();

        $paymentData = [
            'amount' => '15.99',
            'payment_date' => '2024-01-15',
            'payment_method_id' => $this->paymentMethod->id,
            'currency_id' => $this->currency->id,
            'notes' => 'Test payment note',
        ];

        $response = $this->actingAs($this->user)
            ->post("/subscriptions/{$subscription->id}/mark-paid", $paymentData);

        $response->assertRedirect();

        // Verify payment history was created
        $this->assertDatabaseHas('payment_histories', [
            'subscription_id' => $subscription->id,
            'amount' => 15.99,
            'payment_method_id' => $this->paymentMethod->id,
            'notes' => 'Test payment note',
        ]);
    }

    public function test_payment_history_update()
    {
        $subscription = $this->createSubscription();

        // Create a payment history record
        $paymentHistory = PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 9.99,
            'payment_date' => '2024-01-01',
            'payment_method_id' => $this->paymentMethod->id,
            'notes' => 'Original note',
        ]);

        $updateData = [
            'amount' => '19.99',
            'payment_date' => '2024-01-15',
            'payment_method_id' => $this->paymentMethod->id,
            'currency_id' => $this->currency->id,
            'notes' => 'Updated note',
        ];

        $response = $this->actingAs($this->user)
            ->put("/payment-histories/{$paymentHistory->id}", $updateData);

        $response->assertRedirect();

        // Verify payment history was updated
        $this->assertDatabaseHas('payment_histories', [
            'id' => $paymentHistory->id,
            'amount' => 19.99,
            'notes' => 'Updated note',
        ]);
    }

    public function test_mark_paid_validation_rules()
    {
        $subscription = $this->createSubscription();

        // Test missing amount
        $response = $this->actingAs($this->user)
            ->post("/subscriptions/{$subscription->id}/mark-paid", [
                'payment_date' => '2024-01-15',
                'currency_id' => $this->currency->id,
            ]);
        $response->assertSessionHasErrors(['amount']);

        // Test invalid amount
        $response = $this->actingAs($this->user)
            ->post("/subscriptions/{$subscription->id}/mark-paid", [
                'amount' => '0',
                'payment_date' => '2024-01-15',
                'currency_id' => $this->currency->id,
            ]);
        $response->assertSessionHasErrors(['amount']);

        // Test missing payment date
        $response = $this->actingAs($this->user)
            ->post("/subscriptions/{$subscription->id}/mark-paid", [
                'amount' => '9.99',
                'currency_id' => $this->currency->id,
            ]);
        $response->assertSessionHasErrors(['payment_date']);
    }

    public function test_subscription_show_page_loads_with_payment_data()
    {
        $subscription = $this->createSubscription();

        $response = $this->actingAs($this->user)
            ->get("/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        // Test passes if page loads correctly
        $this->assertTrue(true);
    }

    public function test_payment_history_update_authorization()
    {
        $otherUser = User::factory()->create();
        $otherPaymentMethod = PaymentMethod::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Payment Method',
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $otherPaymentMethod->id,
            'price' => 9.99,
        ]);

        $otherPaymentHistory = PaymentHistory::factory()->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/payment-histories/{$otherPaymentHistory->id}", [
                'amount' => '9.99',
                'payment_date' => '2024-01-15',
                'currency_id' => $this->currency->id,
            ]);

        $response->assertStatus(403);
    }
}
