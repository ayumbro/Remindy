<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMultiSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_create_form_loads_with_categories(): void
    {
        $user = User::factory()->create();
        $categories = Category::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('subscriptions.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('subscriptions/create')
            ->has('categories', 3)
            ->where('categories.0.id', $categories[0]->id)
            ->where('categories.0.name', $categories[0]->name)
            ->where('categories.0.display_color', $categories[0]->display_color)
        );
    }

    public function test_subscription_edit_form_loads_with_categories(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $categories = Category::factory()->count(3)->create(['user_id' => $user->id]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        // Attach categories to subscription
        $subscription->categories()->attach([$categories[0]->id, $categories[1]->id]);

        $response = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('subscriptions/edit')
            ->has('subscription')
            ->has('categories', 3)
            ->where('subscription.categories.0.id', $categories[0]->id)
            ->where('subscription.categories.1.id', $categories[1]->id)
        );
    }

    public function test_subscription_can_be_created_with_categories(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $categories = Category::factory()->count(3)->create(['user_id' => $user->id]);

        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test Description',
            'price' => '9.99',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [$categories[0]->id, $categories[2]->id],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $response = $this->actingAs($user)
            ->post(route('subscriptions.store'), $subscriptionData);

        $response->assertRedirect();

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($categories[0]));
        $this->assertTrue($subscription->categories->contains($categories[2]));
        $this->assertFalse($subscription->categories->contains($categories[1]));
    }

    public function test_subscription_categories_can_be_updated(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $categories = Category::factory()->count(4)->create(['user_id' => $user->id]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        // Initially attach first two categories
        $subscription->categories()->attach([$categories[0]->id, $categories[1]->id]);

        $updateData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $subscription->payment_method_id,
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [$categories[1]->id, $categories[2]->id, $categories[3]->id], // Change categories
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $response = $this->actingAs($user)
            ->put(route('subscriptions.update', $subscription), $updateData);

        $response->assertRedirect();

        $subscription->refresh();
        $this->assertCount(3, $subscription->categories);
        $this->assertFalse($subscription->categories->contains($categories[0])); // Removed
        $this->assertTrue($subscription->categories->contains($categories[1])); // Kept
        $this->assertTrue($subscription->categories->contains($categories[2])); // Added
        $this->assertTrue($subscription->categories->contains($categories[3])); // Added
    }

    public function test_subscription_can_be_created_without_categories(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);

        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test Description',
            'price' => '9.99',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [], // No categories
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $response = $this->actingAs($user)
            ->post(route('subscriptions.store'), $subscriptionData);

        $response->assertRedirect();

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(0, $subscription->categories);
    }
}
