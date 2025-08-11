<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_payment_method_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'New Test Payment Method',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "Payment method 'New Test Payment Method' created successfully!",
            ])
            ->assertJsonStructure([
                'success',
                'payment_method' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                ],
                'message',
            ]);

        // Verify payment method was created in database
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'New Test Payment Method',
            'user_id' => $user->id,
            'is_active' => true,
            'description' => null,
            'image_path' => null,
        ]);
    }

    public function test_api_prevents_duplicate_payment_method_names(): void
    {
        $user = User::factory()->create();
        
        // Create an existing payment method
        PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing Payment Method',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'Existing Payment Method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_requires_payment_method_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_validates_payment_method_name_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => str_repeat('a', 256), // Too long
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_api_trims_whitespace_from_payment_method_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => '  Trimmed Payment Method  ',
            ]);

        $response->assertStatus(200);

        // Verify payment method was created with trimmed name
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Trimmed Payment Method',
            'user_id' => $user->id,
        ]);
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->postJson(route('api.payment-methods.store'), [
            'name' => 'Test Payment Method',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_isolates_payment_methods_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates a payment method
        PaymentMethod::factory()->create([
            'user_id' => $user1->id,
            'name' => 'Shared Name',
        ]);

        // User 2 should be able to create a payment method with the same name
        $response = $this->actingAs($user2)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'Shared Name',
            ]);

        $response->assertStatus(200);

        // Verify both payment methods exist
        $this->assertEquals(2, PaymentMethod::where('name', 'Shared Name')->count());
    }

    public function test_api_creates_active_payment_method_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'Test Payment Method',
            ]);

        $response->assertStatus(200);
        
        $paymentMethodData = $response->json('payment_method');
        $this->assertTrue($paymentMethodData['is_active']);
        
        // Verify in database
        $paymentMethod = PaymentMethod::find($paymentMethodData['id']);
        $this->assertTrue($paymentMethod->is_active);
    }

    public function test_api_handles_case_insensitive_duplicates(): void
    {
        $user = User::factory()->create();

        // Create an existing payment method
        PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'name' => 'Credit Card',
        ]);

        // Try to create with different case
        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'credit card',
            ]);

        // Should allow different case (case-sensitive comparison)
        $response->assertStatus(200);

        // Verify both exist with different cases
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Credit Card',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'credit card',
            'user_id' => $user->id,
        ]);
    }

    public function test_api_returns_correct_payment_method_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.payment-methods.store'), [
                'name' => 'Structured Payment Method',
            ]);

        $response->assertStatus(200);
        
        $paymentMethodData = $response->json('payment_method');
        
        // Verify structure
        $this->assertArrayHasKey('id', $paymentMethodData);
        $this->assertArrayHasKey('name', $paymentMethodData);
        $this->assertArrayHasKey('description', $paymentMethodData);
        $this->assertArrayHasKey('is_active', $paymentMethodData);
        
        // Verify values
        $this->assertEquals('Structured Payment Method', $paymentMethodData['name']);
        $this->assertNull($paymentMethodData['description']);
        $this->assertTrue($paymentMethodData['is_active']);
        $this->assertIsInt($paymentMethodData['id']);
    }
}
