<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineCreationStatePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_created_category_persists_in_dropdown_after_deselection(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->active()->create(['user_id' => $user->id]);

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
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'Newly Created Category',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Verify the category was created
        $this->assertDatabaseHas('categories', [
            'id' => $newCategoryData['id'],
            'name' => 'Newly Created Category',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 3: Create subscription with the new category selected
        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test subscription with new category',
            'price' => '10.00',
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
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 4: Verify subscription was created with categories
        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));

        // Step 5: Edit the subscription and remove the newly created category
        $editResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories') // Should include all categories including the newly created one
                ->where('subscription.categories.0.id', $existingCategories[0]->id)
                ->where('subscription.categories.1.id', $newCategoryData['id'])
            );

        // Step 6: Update subscription to remove the newly created category
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
                $existingCategories[0]->id, // Keep only the existing category
                // Remove the newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->putWithCsrf(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 7: Verify the category was removed from the subscription
        $subscription->refresh();
        $this->assertCount(1, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertFalse($subscription->categories->contains('id', $newCategoryData['id']));

        // Step 8: Visit the edit page again - the newly created category should still be available
        $editAgainResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editAgainResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories', 3) // Should have 2 original + 1 newly created = 3 total
                ->where('subscription.categories.0.id', $existingCategories[0]->id)
            );

        // Step 9: Verify the newly created category is in the categories list
        $categoriesInResponse = $editAgainResponse->viewData('page')['props']['categories'];
        $categoryIds = collect($categoriesInResponse)->pluck('id')->toArray();
        
        $this->assertContains($newCategoryData['id'], $categoryIds, 
            'Newly created category should still be available in the dropdown after being deselected');
        $this->assertContains($existingCategories[0]->id, $categoryIds);
        $this->assertContains($existingCategories[1]->id, $categoryIds);
    }

    public function test_newly_created_payment_method_persists_in_dropdown_after_deselection(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        
        // Create some existing payment methods (explicitly active)
        $existingPaymentMethods = PaymentMethod::factory()->active()->count(2)->create(['user_id' => $user->id]);

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
                'name' => 'Newly Created Payment Method',
            ]);

        $newPaymentMethodResponse->assertStatus(200);
        $newPaymentMethodData = $newPaymentMethodResponse->json('payment_method');

        // Verify the payment method was created
        $this->assertDatabaseHas('payment_methods', [
            'id' => $newPaymentMethodData['id'],
            'name' => 'Newly Created Payment Method',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 3: Create subscription with the new payment method
        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test subscription with new payment method',
            'price' => '10.00',
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
        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals($newPaymentMethodData['id'], $subscription->payment_method_id);

        // Step 5: Edit the subscription and change to a different payment method
        $editResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('paymentMethods') // Should include all payment methods including the newly created one
                ->where('subscription.payment_method_id', $newPaymentMethodData['id'])
            );

        // Step 6: Update subscription to use a different payment method
        $updateData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $existingPaymentMethods[0]->id, // Change to existing payment method
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->putWithCsrf(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 7: Verify the payment method was changed
        $subscription->refresh();
        $this->assertEquals($existingPaymentMethods[0]->id, $subscription->payment_method_id);

        // Step 8: Visit the edit page again - the newly created payment method should still be available
        $editAgainResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editAgainResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('paymentMethods', 3) // Should have 2 original + 1 newly created = 3 total
                ->where('subscription.payment_method_id', $existingPaymentMethods[0]->id)
            );

        // Step 9: Verify the newly created payment method is in the payment methods list
        $paymentMethodsInResponse = $editAgainResponse->viewData('page')['props']['paymentMethods'];
        $paymentMethodIds = collect($paymentMethodsInResponse)->pluck('id')->toArray();
        
        $this->assertContains($newPaymentMethodData['id'], $paymentMethodIds, 
            'Newly created payment method should still be available in the dropdown after being deselected');
        $this->assertContains($existingPaymentMethods[0]->id, $paymentMethodIds);
        $this->assertContains($existingPaymentMethods[1]->id, $paymentMethodIds);
    }

    public function test_category_creation_error_does_not_corrupt_existing_selections(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->active()->create(['user_id' => $user->id]);

        // Create some existing categories
        $existingCategories = Category::factory()->count(2)->create(['user_id' => $user->id])->all();

        // Step 1: Create a category via API (simulating successful inline creation)
        $firstCategoryResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'test1',
            ]);

        $firstCategoryResponse->assertStatus(200);
        $firstCategoryData = $firstCategoryResponse->json('category');

        // Verify the category was created
        $this->assertDatabaseHas('categories', [
            'id' => $firstCategoryData['id'],
            'name' => 'test1',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 2: Create subscription with the new category and an existing category
        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test subscription with categories',
            'price' => '10.00',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [
                $existingCategories[0]->id,
                $firstCategoryData['id'], // Include the newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 3: Verify subscription was created with both categories
        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(2, $subscription->categories);
        $this->assertTrue($subscription->categories->contains($existingCategories[0]));
        $this->assertTrue($subscription->categories->contains('id', $firstCategoryData['id']));

        // Step 4: Try to create a duplicate category (this should fail)
        $duplicateCategoryResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'test1', // Same name as before
            ]);

        // Verify the duplicate creation fails
        $duplicateCategoryResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJson([
                'errors' => [
                    'name' => ['A category with this name already exists.']
                ]
            ]);

        // Step 5: Verify the original category still exists and is accessible
        $editResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories') // Should include all categories including the original "test1"
                ->where('subscription.categories.0.id', $existingCategories[0]->id)
                ->where('subscription.categories.1.id', $firstCategoryData['id'])
            );

        // Step 6: Verify the original "test1" category is still in the categories list
        $categoriesInResponse = $editResponse->viewData('page')['props']['categories'];
        $categoryIds = collect($categoriesInResponse)->pluck('id')->toArray();
        $categoryNames = collect($categoriesInResponse)->pluck('name')->toArray();

        $this->assertContains($firstCategoryData['id'], $categoryIds,
            'Original "test1" category should still be available after failed duplicate creation');
        $this->assertContains('test1', $categoryNames,
            'Original "test1" category name should still be available after failed duplicate creation');
        $this->assertContains($existingCategories[0]->id, $categoryIds);
        $this->assertContains($existingCategories[1]->id, $categoryIds);

        // Step 7: Verify we can still select the original "test1" category
        // Try to update subscription to include only the "test1" category
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
                $firstCategoryData['id'], // Only the "test1" category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->putWithCsrf(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 8: Verify the update was successful
        $subscription->refresh();
        $this->assertCount(1, $subscription->categories);
        $this->assertTrue($subscription->categories->contains('id', $firstCategoryData['id']));
        $this->assertEquals('test1', $subscription->categories->first()->name);
    }

    public function test_deselected_category_remains_available_for_reselection(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->active()->create(['user_id' => $user->id]);

        // Create some existing categories for context
        Category::factory()->count(2)->create(['user_id' => $user->id]);

        // Step 1: Create a category via API (simulating successful inline creation)
        $newCategoryResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'test2',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Verify the category was created
        $this->assertDatabaseHas('categories', [
            'id' => $newCategoryData['id'],
            'name' => 'test2',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 2: Create subscription with the new category selected
        $subscriptionData = [
            'name' => 'Test Subscription',
            'description' => 'Test subscription with new category',
            'price' => '10.00',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [
                $newCategoryData['id'], // Include the newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 3: Verify subscription was created with the new category
        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(1, $subscription->categories);
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));

        // Step 4: Edit the subscription and remove the newly created category (deselect it)
        $editResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editResponse->assertStatus(200);

        // Step 5: Update subscription to remove the newly created category
        $updateData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $subscription->payment_method_id,
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [], // Remove all categories (deselect)
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $updateResponse = $this->actingAs($user)
            ->putWithCsrf(route('subscriptions.update', $subscription), $updateData);

        $updateResponse->assertRedirect();

        // Step 6: Verify the category was removed from the subscription
        $subscription->refresh();
        $this->assertCount(0, $subscription->categories);

        // Step 7: Visit the edit page again - the newly created category should still be available
        $editAgainResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editAgainResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories', 3) // Should have 2 original + 1 newly created = 3 total
            );

        // Step 8: Verify the newly created category is in the categories list
        $categoriesInResponse = $editAgainResponse->viewData('page')['props']['categories'];
        $categoryIds = collect($categoriesInResponse)->pluck('id')->toArray();
        $categoryNames = collect($categoriesInResponse)->pluck('name')->toArray();

        $this->assertContains($newCategoryData['id'], $categoryIds,
            'Newly created category should still be available in the dropdown after being deselected');
        $this->assertContains('test2', $categoryNames,
            'Newly created category name should still be available after being deselected');

        // Step 9: Try to select the existing category again (this should work without creating a duplicate)
        $reselectData = [
            'name' => $subscription->name,
            'description' => $subscription->description,
            'price' => $subscription->price,
            'currency_id' => $subscription->currency_id,
            'payment_method_id' => $subscription->payment_method_id,
            'end_date' => '',
            'website_url' => '',
            'notes' => '',
            'category_ids' => [
                $newCategoryData['id'], // Reselect the existing category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $reselectResponse = $this->actingAs($user)
            ->putWithCsrf(route('subscriptions.update', $subscription), $reselectData);

        $reselectResponse->assertRedirect();

        // Step 10: Verify the category was successfully reselected
        $subscription->refresh();
        $this->assertCount(1, $subscription->categories);
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));
        $this->assertEquals('test2', $subscription->categories->first()->name);

        // Step 11: Verify no duplicate categories were created
        $totalTest2Categories = Category::where('user_id', $user->id)
            ->where('name', 'test2')
            ->count();
        $this->assertEquals(1, $totalTest2Categories,
            'Only one "test2" category should exist - no duplicates should be created');
    }

    public function test_newly_created_category_appears_in_dropdown_immediately(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->active()->create(['user_id' => $user->id]);

        // Create some existing categories for context
        $existingCategories = Category::factory()->count(2)->create(['user_id' => $user->id]);

        // Step 1: Visit subscription create page to get initial categories
        $initialResponse = $this->actingAs($user)
            ->get(route('subscriptions.create'));

        $initialResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/create')
                ->has('categories', 2) // Should have 2 existing categories
            );

        $initialCategories = $initialResponse->viewData('page')['props']['categories'];
        $initialCategoryNames = collect($initialCategories)->pluck('name')->toArray();

        // Verify initial state
        $this->assertCount(2, $initialCategories);
        $this->assertNotContains('new cat name', $initialCategoryNames);

        // Step 2: Create a new category via API (simulating inline creation)
        $newCategoryResponse = $this->actingAs($user)
            ->postJsonWithCsrf(route('api.categories.store'), [
                'name' => 'new cat name',
            ]);

        $newCategoryResponse->assertStatus(200);
        $newCategoryData = $newCategoryResponse->json('category');

        // Verify the category was created in database
        $this->assertDatabaseHas('categories', [
            'id' => $newCategoryData['id'],
            'name' => 'new cat name',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Step 3: Visit subscription create page again - new category should be available
        $updatedResponse = $this->actingAs($user)
            ->get(route('subscriptions.create'));

        $updatedResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/create')
                ->has('categories', 3) // Should now have 3 categories (2 existing + 1 new)
            );

        $updatedCategories = $updatedResponse->viewData('page')['props']['categories'];
        $updatedCategoryNames = collect($updatedCategories)->pluck('name')->toArray();
        $updatedCategoryIds = collect($updatedCategories)->pluck('id')->toArray();

        // Step 4: Verify the new category is in the categories list
        $this->assertCount(3, $updatedCategories);
        $this->assertContains('new cat name', $updatedCategoryNames,
            'Newly created category should appear in the categories list');
        $this->assertContains($newCategoryData['id'], $updatedCategoryIds,
            'Newly created category ID should be in the categories list');

        // Step 5: Verify all original categories are still there
        foreach ($existingCategories as $existingCategory) {
            $this->assertContains($existingCategory->name, $updatedCategoryNames,
                "Existing category '{$existingCategory->name}' should still be in the list");
            $this->assertContains($existingCategory->id, $updatedCategoryIds,
                "Existing category ID {$existingCategory->id} should still be in the list");
        }

        // Step 6: Create a subscription using the newly created category
        $subscriptionData = [
            'name' => 'Test Subscription with New Category',
            'description' => 'Testing newly created category selection',
            'price' => '15.00',
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
            'billing_cycle' => 'monthly',
            'billing_interval' => '1',
            'start_date' => now()->format('Y-m-d'),
            'first_billing_date' => now()->format('Y-m-d'),
            'category_ids' => [
                $newCategoryData['id'], // Use the newly created category
            ],
            'notifications_enabled' => true,
            'email_enabled' => true,
            'reminder_intervals' => [7, 3, 1],
        ];

        $createResponse = $this->actingAs($user)
            ->postWithCsrf(route('subscriptions.store'), $subscriptionData);

        $createResponse->assertRedirect();

        // Step 7: Verify subscription was created successfully with the new category
        $subscription = Subscription::where('name', 'Test Subscription with New Category')->first();
        $this->assertNotNull($subscription);
        $this->assertCount(1, $subscription->categories);
        $this->assertTrue($subscription->categories->contains('id', $newCategoryData['id']));
        $this->assertEquals('new cat name', $subscription->categories->first()->name);

        // Step 8: Verify the category is available for editing
        $editResponse = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $editResponse->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('subscriptions/edit')
                ->has('subscription')
                ->has('categories', 3) // Should have all 3 categories available
                ->where('subscription.categories.0.id', $newCategoryData['id'])
                ->where('subscription.categories.0.name', 'new cat name')
            );

        $editCategories = $editResponse->viewData('page')['props']['categories'];
        $editCategoryNames = collect($editCategories)->pluck('name')->toArray();

        $this->assertContains('new cat name', $editCategoryNames,
            'Newly created category should be available in edit form');
    }
}
