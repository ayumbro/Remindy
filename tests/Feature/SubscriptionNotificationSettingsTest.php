<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'default_email_enabled' => true,
            'default_webhook_enabled' => false,
            'default_reminder_intervals' => [7, 3, 1],
        ]);
        $this->currency = Currency::factory()->create();
    }

    /** @test */
    public function new_subscription_inherits_user_default_notification_settings()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['_token' => 'test-token'])
            ->post('/subscriptions', [
                '_token' => 'test-token',
                'name' => 'Test Subscription',
                'price' => 9.99,
                'currency_id' => $this->currency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => '2024-01-01',
                'first_billing_date' => '2024-01-01',
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => true,
                'webhook_enabled' => false,
                'reminder_intervals' => [7, 3, 1],
            ]);

        $response->assertRedirect('/subscriptions');

        $subscription = Subscription::where('name', 'Test Subscription')->first();
        $this->assertNotNull($subscription);
        
        // Should inherit user's default settings
        $this->assertTrue($subscription->use_default_notifications);
        $this->assertTrue($subscription->notifications_enabled);
        $this->assertTrue($subscription->email_enabled);
        $this->assertFalse($subscription->webhook_enabled);
        $this->assertEquals([7, 3, 1], $subscription->reminder_intervals);
    }

    /** @test */
    public function subscription_notification_settings_can_be_updated()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'use_default_notifications' => true,
            'notifications_enabled' => true,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['_token' => 'test-token'])
            ->put("/subscriptions/{$subscription->id}", [
                '_token' => 'test-token',
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'notifications_enabled' => true,
                'use_default_notifications' => false,
                'email_enabled' => false,
                'webhook_enabled' => true,
                'reminder_intervals' => [15, 7],
            ]);

        $response->assertRedirect("/subscriptions/{$subscription->id}");

        $subscription->refresh();
        
        // Should have custom settings
        $this->assertFalse($subscription->use_default_notifications);
        $this->assertTrue($subscription->notifications_enabled);
        $this->assertFalse($subscription->email_enabled);
        $this->assertTrue($subscription->webhook_enabled);
        $this->assertEquals([15, 7], $subscription->reminder_intervals);
    }

    /** @test */
    public function subscription_can_be_reset_to_default_notifications()
    {
        // Update user's default settings
        $this->user->update([
            'default_email_enabled' => false,
            'default_webhook_enabled' => true,
            'default_reminder_intervals' => [30, 15, 7],
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'use_default_notifications' => false,
            'notifications_enabled' => true,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['_token' => 'test-token'])
            ->put("/subscriptions/{$subscription->id}", [
                '_token' => 'test-token',
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'notifications_enabled' => true,
                'use_default_notifications' => true,
                'email_enabled' => false,
                'webhook_enabled' => true,
                'reminder_intervals' => [30, 15, 7],
            ]);

        $response->assertRedirect("/subscriptions/{$subscription->id}");

        $subscription->refresh();
        
        // Should inherit current user defaults
        $this->assertTrue($subscription->use_default_notifications);
        $this->assertTrue($subscription->notifications_enabled);
        $this->assertFalse($subscription->email_enabled); // User's current default
        $this->assertTrue($subscription->webhook_enabled); // User's current default
        $this->assertEquals([30, 15, 7], $subscription->reminder_intervals); // User's current default
    }

    /** @test */
    public function subscription_notifications_can_be_disabled()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'use_default_notifications' => true,
            'notifications_enabled' => true,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['_token' => 'test-token'])
            ->put("/subscriptions/{$subscription->id}", [
                '_token' => 'test-token',
                'name' => $subscription->name,
                'price' => $subscription->price,
                'currency_id' => $subscription->currency_id,
                'notifications_enabled' => false,
                'use_default_notifications' => false,
                'email_enabled' => false,
                'webhook_enabled' => false,
                'reminder_intervals' => [],
            ]);

        $response->assertRedirect("/subscriptions/{$subscription->id}");

        $subscription->refresh();

        // Should have notifications disabled
        $this->assertFalse($subscription->use_default_notifications);
        $this->assertFalse($subscription->notifications_enabled);
        $this->assertFalse($subscription->email_enabled);
        $this->assertFalse($subscription->webhook_enabled);
        $this->assertNull($subscription->reminder_intervals);
    }
}
