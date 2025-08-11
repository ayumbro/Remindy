<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineCategoryCreationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_subscription_with_inline_category_creation(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        
        // Create some existing categories
        $existingCategories = Category::factory()->count(2)->create(['user_id' => $user->id]);

        // Step 1: User visits subscription create page
        $response = $this->actingAs($user)
            ->get(route('subscriptions.create'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/create')
                ->has('categories', 2)
            );

        // Step 2: User creates a new category via API (simulating inline creation)
        $newCategoryResponse = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => 'Streaming Services',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Verify the category was created
        $this->assertDatabaseHas('categories', [
            'id' => $newCategoryData['id'],
            'name' => 'Streaming Services',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 3: User creates subscription with both existing and new categories
        $subscriptionData = [
            'name' => 'Netflix Subscription',
            'description' => 'Monthly streaming service',
            'price' => '15.99',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [
                $existingCategories[0]->id,
                $newCategoryData['id'], // Include the newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->post(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 4: Verify subscription was created with categories
        $subscription = Subscription::where('name', 'Netflix Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));
    }

    public function test_user_can_edit_subscription_and_create_new_category_inline(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        
        // Create existing categories and subscription
        $existingCategories = Category::factory()->count(2)->create(['user_id' => $user->id]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        // Attach one existing category
        $subscription->categories()->attach($existingCategories[0]->id);

        // Step 1: User visits subscription edit page
        $response = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories', 2)
                ->where('subscription.categories.0.id', $existingCategories[0]->id)
            );

        // Step 2: User creates a new category via API during editing
        $newCategoryResponse = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => 'Entertainment',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Step 3: User updates subscription with new category
        $updateData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $subscription->payment_method_id,
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [
                $existingCategories[0]->id, // Keep existing
                $newCategoryData['id'], // Add new
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->put(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 4: Verify subscription was updated with new category
        $subscription->refresh();
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));
    }

    public function test_inline_category_creation_prevents_duplicates(): void
    {
        $user = User::factory()->create();
        
        // Create an existing category
        $existingCategory = Category::factory()->create([
            'user_id' => $user->id,
            'name' => 'Utilities',
        ]);

        // Try to create a category with the same name
        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => 'Utilities',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Verify no duplicate was created
        $this->assertEquals(1, Category::where('name', 'Utilities')->where('user_id', $user->id)->count());
    }

    public function test_inline_category_creation_is_user_isolated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates a category
        $response1 = $this->actingAs($user1)
            ->postJson(route('api.categories.store'), [
                'name' => 'Personal',
            ]);

        $response1->assertStatus(200);

        // User 2 should be able to create a category with the same name
        $response2 = $this->actingAs($user2)
            ->postJson(route('api.categories.store'), [
                'name' => 'Personal',
            ]);

        $response2->assertStatus(200);

        // Verify both categories exist and belong to different users
        $this->assertEquals(1, Category::where('name', 'Personal')->where('user_id', $user1->id)->count());
        $this->assertEquals(1, Category::where('name', 'Personal')->where('user_id', $user2->id)->count());
    }

    public function test_newly_created_category_gets_random_color(): void
    {
        $user = User::factory()->create();
        $defaultColors = Category::getDefaultColors();

        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => 'Test Category',
            ]);

        $response->assertStatus(200);
        $categoryData = $response->json('category');

        // Verify the category has a color from the default palette
        $category = Category::find($categoryData['id']);
        $this->assertContains($category->color, $defaultColors);
        $this->assertEquals($category->color, $categoryData['display_color']);
    }
}
