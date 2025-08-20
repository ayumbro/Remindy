<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlinePaymentMethodCreationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_subscription_with_inline_payment_method_creation(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        
        // Create some existing payment methods
        $existingPaymentMethods = PaymentMethod::factory()->count(2)->create(['user_id' => $user->id]);

        // Step 1: User visits subscription create page
        $response = $this->actingAs($user)
            ->get(route('subscriptions.create'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/create')
                ->has('paymentMethods', 2)
            );

        // Step 2: User creates a new payment method via API (simulating inline creation)
        $newPaymentMethodResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'New Credit Card',
            ]);

        $newPaymentMethodResponse->assertStatus(200);
        $newPaymentMethodData = $newPaymentMethodResponse->json('payment_method');

        // Verify the payment method was created
        $this->assertDatabaseHas('payment_methods', [
            'id' => $newPaymentMethodData['id'],
            'name' => 'New Credit Card',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 3: User creates subscription with the new payment method
        $subscriptionData = [
            'name' => 'Netflix Subscription',
            'description' => 'Monthly streaming service',
            'price' => '15.99',
            'currency_id' => $currency->id,
            'payment_method_id' => $newPaymentMethodData['id'], // Use the newly created payment method
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 4: Verify subscription was created with the new payment method
        $subscription = Subscription::where('name', 'Netflix Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($newPaymentMethodData['id'], $subscription->payment_method_id);
    }

    public function test_user_can_edit_subscription_and_create_new_payment_method_inline(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        
        // Create existing payment methods and subscription
        $existingPaymentMethods = PaymentMethod::factory()->count(2)->create(['user_id' => $user->id]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'payment_method_id' => $existingPaymentMethods[0]->id,
        ]);

        // Step 1: User visits subscription edit page
        $response = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('paymentMethods', 2)
                ->where('subscription.payment_method_id', $existingPaymentMethods[0]->id)
            );

        // Step 2: User creates a new payment method via API during editing
        $newPaymentMethodResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Business Debit Card',
            ]);

        $newPaymentMethodResponse->assertStatus(200);
        $newPaymentMethodData = $newPaymentMethodResponse->json('payment_method');

        // Step 3: User updates subscription with new payment method
        $updateData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $newPaymentMethodData['id'], // Change to new payment method
            'start_date' => $subscription->start_date->format('Y-m-d'),
            'first_billing_date' => $subscription->first_billing_date->format('Y-m-d'),
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [],
            'notifications_enabled' => true,
            'use_default_notifications' => true,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->put(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 4: Verify subscription was updated with new payment method
        $subscription->refresh();
        $this->assertEquals($newPaymentMethodData['id'], $subscription->payment_method_id);
    }

    public function test_inline_payment_method_creation_prevents_duplicates(): void
    {
        $user = User::factory()->create();
        
        // Create an existing payment method
        $existingPaymentMethod = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'name' => 'Visa Card',
        ]);

        // Try to create a payment method with the same name
        $response = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Visa Card',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Verify no duplicate was created
        $this->assertEquals(1, PaymentMethod::where('name', 'Visa Card')->where('user_id', $user->id)->count());
    }

    public function test_inline_payment_method_creation_is_user_isolated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates a payment method
        $response1 = $this->actingAs($user1)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Personal Card',
            ]);

        $response1->assertStatus(200);

        // User 2 should be able to create a payment method with the same name
        $response2 = $this->actingAs($user2)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Personal Card',
            ]);

        $response2->assertStatus(200);

        // Verify both payment methods exist and belong to different users
        $this->assertEquals(1, PaymentMethod::where('name', 'Personal Card')->where('user_id', $user1->id)->count());
        $this->assertEquals(1, PaymentMethod::where('name', 'Personal Card')->where('user_id', $user2->id)->count());
    }

    public function test_newly_created_payment_method_is_active_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Test Payment Method',
            ]);

        $response->assertStatus(200);
        $paymentMethodData = $response->json('payment_method');

        // Verify the payment method is active
        $paymentMethod = PaymentMethod::find($paymentMethodData['id']);
        $this->assertTrue($paymentMethod->is_active);
        $this->assertTrue($paymentMethodData['is_active']);
    }

    public function test_subscription_can_use_none_payment_method_option(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();

        // Create subscription with no payment method
        $subscriptionData = [
            'name' => 'Free Service',
            'description' => 'Free tier subscription',
            'price' => '0.00',
            'currency_id' => $currency->id,
            'payment_method_id' => 'none', // No payment method
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Verify subscription was created with null payment method
        $subscription = Subscription::where('name', 'Free Service')->first();
        $this->assertNotNull($subscription);
        $this->assertNull($subscription->payment_method_id);
    }

    public function test_payment_method_creation_with_categories_and_payment_method(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        
        // Create existing categories
        $existingCategories = Category::factory()->count(2)->create(['user_id' => $user->id]);

        // Step 1: Create new category via API
        $newCategoryResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'Entertainment',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Step 2: Create new payment method via API
        $newPaymentMethodResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.payment-methods.store'), [
                'name' => 'Entertainment Card',
            ]);

        $newPaymentMethodResponse->assertStatus(200);
        $newPaymentMethodData = $newPaymentMethodResponse->json('payment_method');

        // Step 3: Create subscription using both newly created items
        $subscriptionData = [
            'name' => 'Spotify Premium',
            'description' => 'Music streaming service',
            'price' => '9.99',
            'currency_id' => $currency->id,
            'payment_method_id' => $newPaymentMethodData['id'],
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [
                $existingCategories[0]->id,
                $newCategoryData['id'], // Include newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 4: Verify subscription was created with both new items
        $subscription = Subscription::where('name', 'Spotify Premium')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($newPaymentMethodData['id'], $subscription->payment_method_id);
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));
    }
}
