<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSmtpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_without_smtp_config_returns_false_for_has_smtp_config()
    {
        $user = User::factory()->create([
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
        ]);

        $this->assertFalse($user->hasSmtpConfig());
    }

    /** @test */
    public function user_with_incomplete_smtp_config_returns_false()
    {
        // Missing port
        $user = User::factory()->create([
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => null,
            'smtp_username' => 'test@gmail.com',
            'smtp_password' => 'password123',
        ]);

        $this->assertFalse($user->hasSmtpConfig());

        // Missing host
        $user->update([
            'smtp_host' => null,
            'smtp_port' => 587,
        ]);

        $this->assertFalse($user->hasSmtpConfig());

        // Missing password
        $user->update([
            'smtp_host' => 'smtp.gmail.com',
            'smtp_password' => null,
        ]);

        $this->assertFalse($user->hasSmtpConfig());
    }

    /** @test */
    public function user_with_complete_smtp_config_returns_true()
    {
        $user = User::factory()->create([
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'test@gmail.com',
            'smtp_password' => 'password123',
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'test@gmail.com',
            'smtp_from_name' => 'Test User',
        ]);

        $this->assertTrue($user->hasSmtpConfig());
    }

    /** @test */
    public function get_smtp_config_returns_correct_configuration()
    {
        $user = User::factory()->create([
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'test@gmail.com',
            'smtp_password' => 'password123',
            'smtp_encryption' => 'tls',
            'smtp_from_address' => 'notifications@example.com',
            'smtp_from_name' => 'Test Notifications',
        ]);

        $config = $user->getSmtpConfig();

        $this->assertEquals('smtp.gmail.com', $config['host']);
        $this->assertEquals(587, $config['port']);
        $this->assertEquals('test@gmail.com', $config['username']);
        $this->assertEquals('password123', $config['password']);
        $this->assertEquals('tls', $config['encryption']);
        $this->assertEquals('notifications@example.com', $config['from_address']);
        $this->assertEquals('Test Notifications', $config['from_name']);
    }

    /** @test */
    public function get_smtp_config_uses_user_email_as_fallback_for_from_address()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'name' => 'John Doe',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'test@gmail.com',
            'smtp_password' => 'password123',
            'smtp_from_address' => null,
            'smtp_from_name' => null,
        ]);

        $config = $user->getSmtpConfig();

        $this->assertEquals('user@example.com', $config['from_address']);
        $this->assertEquals('John Doe', $config['from_name']);
    }

    /** @test */
    public function get_effective_notification_email_returns_notification_email_when_set()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'notification_email' => 'notifications@example.com',
        ]);

        $this->assertEquals('notifications@example.com', $user->getEffectiveNotificationEmail());
    }

    /** @test */
    public function get_effective_notification_email_returns_user_email_when_notification_email_not_set()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'notification_email' => null,
        ]);

        $this->assertEquals('user@example.com', $user->getEffectiveNotificationEmail());
    }
}
