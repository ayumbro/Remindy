<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function it_can_delete_subscription_without_payment_history()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'name' => 'Test Subscription',
        ]);

        // Verify subscription exists
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);

        // Delete subscription
        $response = $this->actingAs($this->user)
            ->deleteWithCsrf("/subscriptions/{$subscription->id}");

        // Should redirect to subscriptions index with success message
        $response->assertRedirect('/subscriptions');
        $response->assertSessionHas('success', "Subscription 'Test Subscription' deleted successfully.");

        // Verify subscription is deleted
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    /** @test */
    public function it_cannot_delete_subscription_with_payment_history()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'name' => 'Test Subscription',
        ]);

        // Create payment history
        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 10.00,
            'status' => 'paid',
        ]);

        // Verify subscription and payment history exist
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('payment_histories', ['subscription_id' => $subscription->id]);

        // Attempt to delete subscription
        $response = $this->actingAs($this->user)
            ->deleteWithCsrf("/subscriptions/{$subscription->id}");

        // Should redirect back with error
        $response->assertRedirect();
        $response->assertSessionHasErrors(['subscription']);

        // Verify subscription still exists
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('payment_histories', ['subscription_id' => $subscription->id]);
    }

    /** @test */
    public function it_cannot_delete_subscription_with_multiple_payment_histories()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        // Create multiple payment histories
        PaymentHistory::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'paid',
        ]);

        // Attempt to delete subscription
        $response = $this->actingAs($this->user)
            ->deleteWithCsrf("/subscriptions/{$subscription->id}");

        // Should redirect back with error mentioning multiple records
        $response->assertRedirect();
        $response->assertSessionHasErrors(['subscription']);

        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('3 payment history record(s)', $errors->first('subscription'));

        // Verify subscription and all payment histories still exist
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertEquals(3, PaymentHistory::where('subscription_id', $subscription->id)->count());
    }

    /** @test */
    public function it_prevents_unauthorized_deletion()
    {
        $otherUser = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'currency_id' => $this->currency->id,
        ]);

        // Attempt to delete another user's subscription
        $response = $this->actingAs($this->user)
            ->deleteWithCsrf("/subscriptions/{$subscription->id}");

        // Should return 403 Forbidden
        $response->assertStatus(403);

        // Verify subscription still exists
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    /** @test */
    public function it_requires_authentication_for_deletion()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
        ]);

        // Attempt to delete without authentication
        $response = $this->deleteWithCsrf("/subscriptions/{$subscription->id}");

        // Should redirect to login
        $response->assertRedirect('/login');

        // Verify subscription still exists
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    /** @test */
    public function subscription_model_can_be_deleted_method_works_correctly()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
        ]);

        // Should be deletable when no payment history
        $this->assertTrue($subscription->canBeDeleted());
        $this->assertNull($subscription->getDeletionBlockReason());

        // Add payment history
        PaymentHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'status' => 'paid',
        ]);

        // Refresh the model to get updated relationships
        $subscription->refresh();

        // Should not be deletable when payment history exists
        $this->assertFalse($subscription->canBeDeleted());
        $this->assertStringContainsString('payment history record(s)', $subscription->getDeletionBlockReason());
    }

    /** @test */
    public function deletion_block_reason_includes_correct_count()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
        ]);

        // Add 5 payment histories
        PaymentHistory::factory()->count(5)->create([
            'subscription_id' => $subscription->id,
            'currency_id' => $this->currency->id,
            'status' => 'paid',
        ]);

        $subscription->refresh();

        $reason = $subscription->getDeletionBlockReason();
        $this->assertStringContainsString('5 payment history record(s)', $reason);
        $this->assertStringContainsString('audit purposes', $reason);
    }
}
