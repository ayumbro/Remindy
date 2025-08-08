<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentMethodImageDeletionUXTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Payment Method',
            'description' => 'Test description',
            'is_active' => true,
        ]);
    }

    public function test_payment_method_edit_page_loads_successfully()
    {
        $response = $this->actingAs($this->user)
            ->get("/payment-methods/{$this->paymentMethod->id}/edit");

        $response->assertStatus(200);
    }

    public function test_payment_method_create_page_loads_successfully()
    {
        $response = $this->actingAs($this->user)
            ->get('/payment-methods/create');

        $response->assertStatus(200);
    }

    public function test_payment_method_with_image_can_be_updated_with_remove_image_flag()
    {
        Storage::fake('public');

        // First, upload an image to the payment method
        $file = UploadedFile::fake()->image('test-image.jpg');

        $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $file,
            ]);

        $this->paymentMethod->refresh();
        $this->assertNotNull($this->paymentMethod->image_path);

        // Now test the UX flow: mark image for deletion
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the image was removed
        $this->paymentMethod->refresh();
        $this->assertNull($this->paymentMethod->image_path);
    }

    public function test_payment_method_image_deletion_preserves_form_data()
    {
        Storage::fake('public');

        // Upload an image first
        $file = UploadedFile::fake()->image('test-image.png');

        $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $file,
            ]);

        $this->paymentMethod->refresh();

        // Update other fields AND remove image
        $newName = 'Updated Payment Method Name';
        $newDescription = 'Updated description';

        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $newName,
                'description' => $newDescription,
                'is_active' => 0, // Change status (use 0 instead of false)
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->paymentMethod->refresh();

        // Verify all changes were applied
        $this->assertEquals($newName, $this->paymentMethod->name);
        $this->assertEquals($newDescription, $this->paymentMethod->description);
        $this->assertFalse($this->paymentMethod->is_active);
        $this->assertNull($this->paymentMethod->image_path);
    }

    public function test_payment_method_image_deletion_with_validation_errors()
    {
        Storage::fake('public');

        // Upload an image first
        $file = UploadedFile::fake()->image('test-image.png');

        $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $file,
            ]);

        $this->paymentMethod->refresh();
        $originalImagePath = $this->paymentMethod->image_path;

        // Try to update with invalid data AND remove image
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => '', // Invalid: empty name
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'remove_image' => true,
            ]);

        $response->assertSessionHasErrors(['name']);

        $this->paymentMethod->refresh();

        // Verify image was NOT removed due to validation failure
        $this->assertEquals($originalImagePath, $this->paymentMethod->image_path);
    }

    public function test_payment_method_show_page_displays_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get("/payment-methods/{$this->paymentMethod->id}");

        $response->assertStatus(200);
    }

    public function test_payment_method_image_deletion_from_show_page()
    {
        Storage::fake('public');

        // Upload an image first
        $file = UploadedFile::fake()->image('show-page-test.jpg');

        $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $file,
            ]);

        $this->paymentMethod->refresh();
        $this->assertNotNull($this->paymentMethod->image_path);

        // Test image deletion from show page (using the same update endpoint)
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->paymentMethod->refresh();
        $this->assertNull($this->paymentMethod->image_path);
    }

    public function test_payment_method_creation_with_image_upload_and_removal()
    {
        Storage::fake('public');

        // Test creating a payment method (simulating the UX flow)
        $file = UploadedFile::fake()->image('create-test.jpg');

        $response = $this->actingAs($this->user)
            ->post('/payment-methods', [
                'name' => 'New Payment Method',
                'description' => 'New description',
                'is_active' => true,
                'image' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the payment method was created with image
        $newPaymentMethod = PaymentMethod::where('name', 'New Payment Method')->first();
        $this->assertNotNull($newPaymentMethod);
        $this->assertNotNull($newPaymentMethod->image_path);
    }
}
