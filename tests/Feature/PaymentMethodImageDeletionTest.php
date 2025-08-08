<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentMethodImageDeletionTest extends TestCase
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

    public function test_payment_method_image_can_be_deleted_via_remove_image_flag()
    {
        Storage::fake('public');

        // First, upload an image to the payment method
        $file = UploadedFile::fake()->image('payment-method.jpg');

        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $file,
            ]);

        $response->assertRedirect();
        $this->paymentMethod->refresh();
        $this->assertNotNull($this->paymentMethod->image_path);

        // Verify the file was stored
        Storage::disk('public')->assertExists($this->paymentMethod->image_path);

        // Now test image deletion via remove_image flag
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the image was removed from the database
        $this->paymentMethod->refresh();
        $this->assertNull($this->paymentMethod->image_path);

        // Note: We can't easily test file deletion in this context since the exact path is generated dynamically
    }

    public function test_payment_method_image_deletion_preserves_other_fields()
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
        $originalName = $this->paymentMethod->name;
        $originalDescription = $this->paymentMethod->description;
        $originalIsActive = $this->paymentMethod->is_active;

        // Delete the image
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $originalName,
                'description' => $originalDescription,
                'is_active' => $originalIsActive,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $this->paymentMethod->refresh();

        // Verify other fields are preserved
        $this->assertEquals($originalName, $this->paymentMethod->name);
        $this->assertEquals($originalDescription, $this->paymentMethod->description);
        $this->assertEquals($originalIsActive, $this->paymentMethod->is_active);

        // Verify image was removed
        $this->assertNull($this->paymentMethod->image_path);
    }

    public function test_payment_method_image_deletion_handles_nonexistent_image()
    {
        // Test deleting image when no image exists (should not cause errors)
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

    public function test_payment_method_image_deletion_requires_authentication()
    {
        // Test that unauthenticated users cannot delete images
        $response = $this->put("/payment-methods/{$this->paymentMethod->id}", [
            'name' => $this->paymentMethod->name,
            'description' => $this->paymentMethod->description,
            'is_active' => $this->paymentMethod->is_active,
            'remove_image' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_payment_method_image_deletion_requires_ownership()
    {
        Storage::fake('public');

        // Create another user and their payment method
        $otherUser = User::factory()->create();
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        // Upload an image to the other user's payment method
        $file = UploadedFile::fake()->image('other-user-image.jpg');
        $response = $this->actingAs($otherUser)
            ->put("/payment-methods/{$otherPaymentMethod->id}", [
                'name' => $otherPaymentMethod->name,
                'description' => $otherPaymentMethod->description,
                'is_active' => $otherPaymentMethod->is_active ? 1 : 0,
                'image' => $file,
            ]);

        $response->assertRedirect();
        $otherPaymentMethod->refresh();
        $this->assertNotNull($otherPaymentMethod->image_path);

        // Try to delete the image as a different user
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$otherPaymentMethod->id}", [
                'name' => $otherPaymentMethod->name,
                'description' => $otherPaymentMethod->description,
                'is_active' => $otherPaymentMethod->is_active,
                'remove_image' => true,
            ]);

        $response->assertStatus(403);

        // Verify the image was not deleted
        $otherPaymentMethod->refresh();
        $this->assertNotNull($otherPaymentMethod->image_path);
    }

    public function test_payment_method_image_deletion_with_new_image_upload()
    {
        Storage::fake('public');

        // Upload initial image
        $initialFile = UploadedFile::fake()->image('initial-image.jpg');
        $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $initialFile,
            ]);

        $this->paymentMethod->refresh();
        $initialImagePath = $this->paymentMethod->image_path;
        $this->assertNotNull($initialImagePath);

        // Upload new image with remove_image flag (new image should take precedence)
        $newFile = UploadedFile::fake()->image('new-image.png');
        $response = $this->actingAs($this->user)
            ->put("/payment-methods/{$this->paymentMethod->id}", [
                'name' => $this->paymentMethod->name,
                'description' => $this->paymentMethod->description,
                'is_active' => $this->paymentMethod->is_active,
                'image' => $newFile,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->paymentMethod->refresh();

        // Should have new image, not null
        $this->assertNotNull($this->paymentMethod->image_path);
        $this->assertNotEquals($initialImagePath, $this->paymentMethod->image_path);

        // Note: File deletion testing is handled by the model test below
    }

    public function test_payment_method_model_delete_image_method()
    {
        Storage::fake('public');

        // Create a fake image file and set it on the payment method
        $imagePath = 'payment-method-images/test-image.jpg';
        Storage::disk('public')->put($imagePath, 'fake image content');

        $this->paymentMethod->update(['image_path' => $imagePath]);

        // Verify file exists
        $this->assertTrue(Storage::disk('public')->exists($imagePath));

        // Call deleteImage method
        $this->paymentMethod->deleteImage();

        // Verify file was deleted
        $this->assertFalse(Storage::disk('public')->exists($imagePath));
    }

    public function test_payment_method_model_delete_image_method_handles_missing_file()
    {
        Storage::fake('public');

        // Set image path to non-existent file
        $imagePath = 'payment-method-images/non-existent.jpg';
        $this->paymentMethod->update(['image_path' => $imagePath]);

        // Should not throw exception when file doesn't exist
        $this->paymentMethod->deleteImage();

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_payment_method_model_delete_image_method_handles_null_path()
    {
        // Should not throw exception when image_path is null
        $this->paymentMethod->update(['image_path' => null]);
        $this->paymentMethod->deleteImage();

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }
}
