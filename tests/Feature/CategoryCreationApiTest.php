<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_category_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('api.categories.store'), [
                'name' => 'New Test Category',
            ]);

        $response->assertStatus(200);

        // Verify category was created in database
        $this->assertDatabaseHas('categories', [
            'name' => 'New Test Category',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Verify the category has a color assigned
        $category = Category::where('name', 'New Test Category')->first();
        $this->assertNotNull($category->color);
        $this->assertContains($category->color, Category::getDefaultColors());
    }

    public function test_api_prevents_duplicate_category_names(): void
    {
        $user = User::factory()->create();
        
        // Create an existing category
        Category::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing Category',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => 'Existing Category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_requires_category_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_validates_category_name_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => str_repeat('a', 256), // Too long
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_trims_whitespace_from_category_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.categories.store'), [
                'name' => '  Trimmed Category  ',
            ]);

        $response->assertStatus(200);

        // Verify category was created with trimmed name
        $this->assertDatabaseHas('categories', [
            'name' => 'Trimmed Category',
            'user_id' => $user->id,
        ]);
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->postJson(route('api.categories.store'), [
            'name' => 'Test Category',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_isolates_categories_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates a category
        Category::factory()->create([
            'user_id' => $user1->id,
            'name' => 'Shared Name',
        ]);

        // User 2 should be able to create a category with the same name
        $response = $this->actingAs($user2)
            ->postJson(route('api.categories.store'), [
                'name' => 'Shared Name',
            ]);

        $response->assertStatus(200);

        // Verify both categories exist
        $this->assertEquals(2, Category::where('name', 'Shared Name')->count());
    }

    public function test_api_assigns_random_color_from_default_palette(): void
    {
        $user = User::factory()->create();
        $defaultColors = Category::getDefaultColors();

        // Create multiple categories to test randomness
        $createdColors = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)
                ->postJson(route('api.categories.store'), [
                    'name' => "Test Category {$i}",
                ]);

            $response->assertStatus(200);
            $categoryData = $response->json('category');
            $createdColors[] = $categoryData['display_color'];
            
            // Verify the color is from the default palette
            $category = Category::find($categoryData['id']);
            $this->assertContains($category->color, $defaultColors);
        }

        // Verify we got some variety in colors (not all the same)
        $uniqueColors = array_unique($createdColors);
        $this->assertGreaterThan(1, count($uniqueColors), 'Should have some variety in assigned colors');
    }
}
