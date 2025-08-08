<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_smtp_settings(): void
    {
        $user = User::factory()->create();

        $data = [
            'email_enabled' => true,
            'webhook_enabled' => false,
            'email_address' => 'test@example.com',
            'reminder_intervals' => [7, 3, 1],
            'use_custom_smtp' => true,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'user@gmail.com',
            'smtp_password' => 'app-password',
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'notifications@example.com',
            'smtp_from_name' => 'Test Notifications',
        ];

        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify SMTP settings are saved to user
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'use_custom_smtp' => true,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'user@gmail.com',
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'notifications@example.com',
            'smtp_from_name' => 'Test Notifications',
        ]);
    }

    public function test_user_can_disable_custom_smtp(): void
    {
        $user = User::factory()->create([
            'use_custom_smtp' => true,
            'smtp_host' => 'smtp.gmail.com',
        ]);

        $data = [
            'email_enabled' => false,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
            'use_custom_smtp' => false,
        ];

        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify custom SMTP is disabled when email is disabled
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'use_custom_smtp' => false,
        ]);
    }

    public function test_smtp_settings_validation(): void
    {
        $user = User::factory()->create();

        // Test invalid email
        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), [
                'email_enabled' => true,
                'reminder_intervals' => [7, 3, 1],
                'smtp_from_address' => 'invalid-email',
            ]);

        $response->assertSessionHasErrors('smtp_from_address');

        // Test invalid port
        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), [
                'email_enabled' => true,
                'reminder_intervals' => [7, 3, 1],
                'smtp_port' => 99999,
            ]);

        $response->assertSessionHasErrors('smtp_port');

        // Test invalid encryption
        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), [
                'email_enabled' => true,
                'reminder_intervals' => [7, 3, 1],
                'smtp_encryption' => 'invalid',
            ]);

        $response->assertSessionHasErrors('smtp_encryption');
    }

    public function test_user_can_set_smtp_encryption_to_none(): void
    {
        $user = User::factory()->create();

        $data = [
            'email_enabled' => true,
            'webhook_enabled' => false,
            'email_address' => 'test@example.com',
            'reminder_intervals' => [7, 3, 1],
            'use_custom_smtp' => true,
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'user@example.com',
            'smtp_password' => 'password',
            'smtp_encryption' => 'none',
            'smtp_from_address' => 'notifications@example.com',
            'smtp_from_name' => 'Test Notifications',
        ];

        $response = $this->actingAs($user)
            ->patch(route('settings.notifications.update-default'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify that 'none' encryption is stored as null in the database
        $user->refresh();
        $this->assertNull($user->smtp_encryption);
        $this->assertEquals('smtp.example.com', $user->smtp_host);
    }
}
