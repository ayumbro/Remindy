<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function smtp_settings_are_required_when_email_notifications_enabled()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [7, 3, 1],
                'notification_email' => 'test@example.com',
                // Missing SMTP settings
                'smtp_host' => '',
                'smtp_port' => '',
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_from_address' => '',
                'smtp_from_name' => '',
            ]);

        $response->assertSessionHasErrors([
            'smtp_host',
            'smtp_port', 
            'smtp_username',
            'smtp_from_address',
            'smtp_from_name'
        ]);
    }

    /** @test */
    public function smtp_settings_are_not_required_when_email_notifications_disabled()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => false,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [7, 3, 1],
                // No SMTP settings provided
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    /** @test */
    public function valid_smtp_settings_are_accepted()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [7, 3, 1],
                'notification_email' => 'test@example.com',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'test@gmail.com',
                'smtp_password' => 'password123',
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'notifications@example.com',
                'smtp_from_name' => 'Test Notifications',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->user->refresh();
        $this->assertTrue($this->user->default_email_enabled);
        $this->assertEquals('smtp.gmail.com', $this->user->smtp_host);
        $this->assertEquals(587, $this->user->smtp_port);
        $this->assertEquals('test@gmail.com', $this->user->smtp_username);
        $this->assertEquals('tls', $this->user->smtp_encryption);
        $this->assertEquals('notifications@example.com', $this->user->smtp_from_address);
        $this->assertEquals('Test Notifications', $this->user->smtp_from_name);
    }

    /** @test */
    public function invalid_smtp_port_is_rejected()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [7, 3, 1],
                'notification_email' => 'test@example.com',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 99999, // Invalid port
                'smtp_username' => 'test@gmail.com',
                'smtp_password' => 'password123',
                'smtp_from_address' => 'notifications@example.com',
                'smtp_from_name' => 'Test Notifications',
            ]);

        $response->assertSessionHasErrors(['smtp_port']);
    }

    /** @test */
    public function invalid_email_addresses_are_rejected()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [7, 3, 1],
                'notification_email' => 'invalid-email', // Invalid email
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'test@gmail.com',
                'smtp_password' => 'password123',
                'smtp_from_address' => 'invalid-from-email', // Invalid email
                'smtp_from_name' => 'Test Notifications',
            ]);

        $response->assertSessionHasErrors(['notification_email', 'smtp_from_address']);
    }

    /** @test */
    public function invalid_reminder_intervals_are_rejected()
    {
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => false,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [99], // Invalid interval
            ]);

        $response->assertSessionHasErrors(['default_reminder_intervals.0']);
    }

    /** @test */
    public function valid_reminder_intervals_are_accepted()
    {
        $validIntervals = [1, 2, 3, 7, 15, 30];
        
        $response = $this->actingAs($this->user)
            ->patchWithCsrf(route('settings.notifications.update'), [
                'notification_time_utc' => '09:00:00',
                'default_email_enabled' => false,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => $validIntervals,
            ]);

        $response->assertSessionHasNoErrors();
        
        $this->user->refresh();
        $this->assertEquals($validIntervals, $this->user->default_reminder_intervals);
    }
}
