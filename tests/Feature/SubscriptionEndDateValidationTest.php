<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEndDateValidationTest extends TestCase
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
    public function it_allows_creating_subscription_with_valid_end_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Test Subscription',
            'end_date' => '2024-12-31 00:00:00',
        ]);
    }

    /** @test */
    public function it_allows_creating_subscription_with_same_day_end_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-01',
                'end_date' => '2024-01-01',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Test Subscription',
            'end_date' => '2024-01-01 00:00:00',
        ]);
    }

    /** @test */
    public function it_rejects_creating_subscription_with_end_date_before_start_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-01',
                'end_date' => '2023-12-31',
            ]);

        $response->assertSessionHasErrors(['end_date']);
        $this->assertDatabaseMissing('subscriptions', [
            'name' => 'Test Subscription',
        ]);
    }

    /** @test */
    public function it_allows_creating_subscription_with_null_end_date()
    {
        $response = $this->actingAs($this->user)
            ->postWithCsrf('/subscriptions', [
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-01',
                // end_date not provided
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Test Subscription',
            'end_date' => null,
        ]);
    }

    /** @test */
    public function it_allows_updating_subscription_with_valid_end_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'start_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($this->user)
            ->putWithCsrf("/subscriptions/{$subscription->id}", [
                'name' => 'Updated Subscription',
                'price' => 19.99,
                'currency_id' => $this->currency->id,
                'end_date' => '2024-12-31',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'end_date' => '2024-12-31 00:00:00',
        ]);
    }

    /** @test */
    public function it_rejects_updating_subscription_with_end_date_before_start_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'start_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($this->user)
            ->putWithCsrf("/subscriptions/{$subscription->id}", [
                'name' => 'Updated Subscription',
                'price' => 19.99,
                'currency_id' => $this->currency->id,
                'end_date' => '2023-12-31',
            ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    /** @test */
    public function it_shows_helpful_error_message_for_invalid_end_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'start_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($this->user)
            ->putWithCsrf("/subscriptions/{$subscription->id}", [
                'name' => 'Updated Subscription',
                'price' => 19.99,
                'currency_id' => $this->currency->id,
                'end_date' => '2023-12-31',
            ]);

        $response->assertSessionHasErrors(['end_date']);

        $errors = session('errors');
        $endDateError = $errors->get('end_date')[0];

        $this->assertStringContainsString('must be on or after the start date', $endDateError);
        $this->assertStringContainsString('2024-01-01', $endDateError);
    }
}
