<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentHistoryFormDataValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Currency $currency;

    private PaymentMethod $paymentMethod;

    private Subscription $subscription;

    private PaymentHistory $paymentHistory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'price' => 9.99,
        ]);

        $this->paymentHistory = PaymentHistory::factory()->create([
            'subscription_id' => $this->subscription->id,
            'amount' => 9.99,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_date' => '2024-01-15',
            'notes' => 'Original payment',
        ]);
    }

    public function test_payment_history_edit_with_only_image_upload_preserves_existing_values()
    {
        Storage::fake('private');

        // Create a fake image file
        $file = UploadedFile::fake()->image('receipt.jpg');

        // Simulate editing payment history with only image upload (no field changes)
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => '9.99', // Same as existing
                'payment_date' => '2024-01-15', // Same as existing
                'payment_method_id' => $this->paymentMethod->id, // Same as existing
                'currency_id' => $this->currency->id,
                'notes' => 'Original payment', // Same as existing
                'attachments' => [$file], // Only new thing
            ]);

        // Should succeed without validation errors
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the payment history still has the correct values
        $this->paymentHistory->refresh();
        $this->assertEquals(9.99, $this->paymentHistory->amount);
        $this->assertEquals('2024-01-15', $this->paymentHistory->payment_date->format('Y-m-d'));
        $this->assertEquals($this->paymentMethod->id, $this->paymentHistory->payment_method_id);
        $this->assertEquals('Original payment', $this->paymentHistory->notes);

        // Verify the attachment was created
        $this->assertCount(1, $this->paymentHistory->attachments);
        $attachment = $this->paymentHistory->attachments->first();
        $this->assertEquals('receipt.jpg', $attachment->original_name);
    }

    public function test_payment_history_edit_with_only_image_upload_no_field_changes()
    {
        Storage::fake('private');

        // Create a fake image file
        $file = UploadedFile::fake()->image('new_receipt.png');

        // Test the exact scenario from the issue: edit existing payment with valid data,
        // upload image without changing any other fields
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                // Send the exact same values that are already in the database
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->paymentHistory->currency_id,
                'notes' => $this->paymentHistory->notes,
                'attachments' => [$file], // Only adding an image
            ]);

        // This should NOT fail with "payment date is required" error
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Payment record updated successfully!');

        // Verify all original data is preserved
        $this->paymentHistory->refresh();
        $this->assertEquals(9.99, $this->paymentHistory->amount);
        $this->assertEquals('2024-01-15', $this->paymentHistory->payment_date->format('Y-m-d'));
        $this->assertEquals($this->paymentMethod->id, $this->paymentHistory->payment_method_id);
        $this->assertEquals('Original payment', $this->paymentHistory->notes);

        // Verify the new attachment was added
        $this->assertCount(1, $this->paymentHistory->attachments);
        $attachment = $this->paymentHistory->attachments->first();
        $this->assertEquals('new_receipt.png', $attachment->original_name);
    }

    public function test_post_route_accepts_payment_history_updates_with_method_spoofing()
    {
        Storage::fake('private');

        // Create a fake image file
        $file = UploadedFile::fake()->image('test_receipt.jpg');

        // Test that POST request with _method=PUT is accepted (no Method Not Allowed error)
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => '15.99',
                'payment_date' => '2024-01-20',
                'payment_method_id' => $this->paymentMethod->id,
                'currency_id' => $this->currency->id,
                'notes' => 'Updated with route fix',
                'attachments' => [$file],
            ]);

        // Should NOT get Method Not Allowed error (405)
        $response->assertStatus(302); // Redirect after successful update
        $response->assertSessionHasNoErrors();

        // Verify the update was processed
        $this->paymentHistory->refresh();
        $this->assertEquals(15.99, $this->paymentHistory->amount);
        $this->assertEquals('Updated with route fix', $this->paymentHistory->notes);
        $this->assertCount(1, $this->paymentHistory->attachments);
    }

    public function test_attachment_removal_through_edit_dialog()
    {
        Storage::fake('private');

        // Create initial attachments for the payment history
        $attachment1 = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'receipt1.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/receipt1.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $attachment2 = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'receipt2.pdf',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/receipt2.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        // Create fake files in storage
        Storage::disk('private')->put($attachment1->file_path, 'fake image content');
        Storage::disk('private')->put($attachment2->file_path, 'fake pdf content');

        // Verify initial state
        $this->assertCount(2, $this->paymentHistory->attachments);
        $this->assertTrue(Storage::disk('private')->exists($attachment1->file_path));
        $this->assertTrue(Storage::disk('private')->exists($attachment2->file_path));

        // Test removing one attachment through the edit dialog
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->paymentHistory->currency_id,
                'notes' => $this->paymentHistory->notes,
                'remove_attachments' => [$attachment1->id], // Remove first attachment
            ]);

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Payment record updated successfully!');

        // Verify attachment was removed from database
        $this->paymentHistory->refresh();
        $this->assertCount(1, $this->paymentHistory->attachments);
        $this->assertDatabaseMissing('payment_attachments', ['id' => $attachment1->id]);
        $this->assertDatabaseHas('payment_attachments', ['id' => $attachment2->id]);

        // Verify file was removed from storage
        $this->assertFalse(Storage::disk('private')->exists($attachment1->file_path));
        $this->assertTrue(Storage::disk('private')->exists($attachment2->file_path));
    }

    public function test_attachment_removal_with_new_file_upload()
    {
        Storage::fake('private');

        // Create initial attachment
        $existingAttachment = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'old_receipt.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/old_receipt.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($existingAttachment->file_path, 'fake content');

        // Create new file to upload
        $newFile = UploadedFile::fake()->image('new_receipt.png');

        // Test removing existing attachment AND uploading new file
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->paymentHistory->currency_id,
                'notes' => 'Updated with new attachment',
                'remove_attachments' => [$existingAttachment->id], // Remove existing
                'attachments' => [$newFile], // Add new
            ]);

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify old attachment was removed and new one was added
        $this->paymentHistory->refresh();
        $this->assertCount(1, $this->paymentHistory->attachments);
        $this->assertDatabaseMissing('payment_attachments', ['id' => $existingAttachment->id]);

        // Verify new attachment exists
        $newAttachment = $this->paymentHistory->attachments->first();
        $this->assertEquals('new_receipt.png', $newAttachment->original_name);

        // Verify file operations
        $this->assertFalse(Storage::disk('private')->exists($existingAttachment->file_path));
        $this->assertTrue(Storage::disk('private')->exists($newAttachment->file_path));
    }

    public function test_attachment_removal_response_includes_updated_data()
    {
        Storage::fake('private');

        // Create initial attachment
        $attachment = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'test_receipt.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/test_receipt.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($attachment->file_path, 'fake content');

        // Test removing attachment
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->paymentHistory->currency_id,
                'notes' => $this->paymentHistory->notes,
                'remove_attachments' => [$attachment->id],
            ]);

        // Should redirect back to the subscription page
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Payment record updated successfully!');

        // The payment history should no longer have the attachment
        $this->paymentHistory->refresh();
        $this->assertCount(0, $this->paymentHistory->attachments);
        $this->assertDatabaseMissing('payment_attachments', ['id' => $attachment->id]);
    }

    public function test_attachment_removal_without_new_files_regular_form_submission()
    {
        Storage::fake('private');

        // Create initial attachment
        $attachment = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'receipt_to_remove.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/receipt_to_remove.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($attachment->file_path, 'fake content');

        // Test removing attachment WITHOUT uploading new files (should use regular form submission)
        $response = $this->actingAs($this->user)
            ->put("/payment-histories/{$this->paymentHistory->id}", [
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->currency->id,
                'notes' => $this->paymentHistory->notes,
                'remove_attachments' => [$attachment->id], // Remove attachment via regular form
                // NO 'attachments' field - this should trigger regular form submission path
            ]);

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Payment record updated successfully!');

        // Verify attachment was removed from database
        $this->paymentHistory->refresh();
        $this->assertCount(0, $this->paymentHistory->attachments);
        $this->assertDatabaseMissing('payment_attachments', ['id' => $attachment->id]);

        // Verify file was removed from storage
        $this->assertFalse(Storage::disk('private')->exists($attachment->file_path));
    }

    public function test_real_world_attachment_removal_scenario()
    {
        Storage::fake('private');

        // Create initial attachment
        $attachment = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'real_world_test.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/real_world_test.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($attachment->file_path, 'fake content');

        // Simulate the exact frontend behavior:
        // 1. User opens edit dialog (loads existing attachments)
        // 2. User clicks "Remove" on an attachment (adds to remove list)
        // 3. User submits form without uploading new files (should use regular form path)

        // This simulates what the frontend should send when removing attachments without new files
        $response = $this->actingAs($this->user)
            ->put("/payment-histories/{$this->paymentHistory->id}", [
                'amount' => $this->paymentHistory->amount,
                'payment_date' => $this->paymentHistory->payment_date->format('Y-m-d'),
                'payment_method_id' => $this->paymentHistory->payment_method_id,
                'currency_id' => $this->currency->id,
                'notes' => $this->paymentHistory->notes,
                'remove_attachments' => [$attachment->id], // This is the key part
            ]);

        // Debug: Check what actually happened
        $this->paymentHistory->refresh();

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // The attachment should be gone
        $this->assertCount(0, $this->paymentHistory->attachments, 'Attachment should be removed from database');
        $this->assertDatabaseMissing('payment_attachments', ['id' => $attachment->id]);
        $this->assertFalse(Storage::disk('private')->exists($attachment->file_path), 'File should be removed from storage');
    }

    public function test_individual_attachment_deletion_via_delete_route()
    {
        Storage::fake('private');

        // Create initial attachment
        $attachment = \App\Models\PaymentAttachment::create([
            'payment_history_id' => $this->paymentHistory->id,
            'original_name' => 'individual_delete_test.jpg',
            'file_path' => 'payment-attachments/'.$this->paymentHistory->id.'/individual_delete_test.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($attachment->file_path, 'fake content');

        // Test individual attachment deletion (this is what the frontend actually uses)
        $response = $this->actingAs($this->user)
            ->delete("/payment-attachments/{$attachment->id}");

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Attachment deleted successfully.');

        // Verify attachment was removed from database
        $this->paymentHistory->refresh();
        $this->assertCount(0, $this->paymentHistory->attachments);
        $this->assertDatabaseMissing('payment_attachments', ['id' => $attachment->id]);

        // Verify file was removed from storage
        $this->assertFalse(Storage::disk('private')->exists($attachment->file_path));
    }

    public function test_payment_history_edit_with_zero_amount_and_image_upload()
    {
        Storage::fake('private');

        // Update payment history to have amount of 0 (edge case)
        $this->paymentHistory->update(['amount' => 0]);

        // Create a fake image file
        $file = UploadedFile::fake()->image('receipt.jpg');

        // Simulate editing payment history with zero amount and image upload
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => '0', // Zero amount (falsy value)
                'payment_date' => '2024-01-15',
                'payment_method_id' => $this->paymentMethod->id,
                'currency_id' => $this->currency->id,
                'notes' => 'Free trial payment',
                'attachments' => [$file],
            ]);

        // Should fail validation because amount must be > 0.01
        $response->assertSessionHasErrors(['amount']);
    }

    public function test_payment_history_edit_with_valid_amount_and_image_upload()
    {
        Storage::fake('private');

        // Create a fake image file
        $file = UploadedFile::fake()->image('receipt.jpg');

        // Simulate editing payment history with valid amount and image upload
        $response = $this->actingAs($this->user)
            ->post("/payment-histories/{$this->paymentHistory->id}", [
                '_method' => 'PUT',
                'amount' => '15.50', // Valid amount
                'payment_date' => '2024-01-20', // New date
                'payment_method_id' => $this->paymentMethod->id,
                'currency_id' => $this->currency->id,
                'notes' => 'Updated payment with receipt',
                'attachments' => [$file],
            ]);

        // Should succeed
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the payment history was updated
        $this->paymentHistory->refresh();
        $this->assertEquals(15.50, $this->paymentHistory->amount);
        $this->assertEquals('2024-01-20', $this->paymentHistory->payment_date->format('Y-m-d'));
        $this->assertEquals('Updated payment with receipt', $this->paymentHistory->notes);
    }
}
