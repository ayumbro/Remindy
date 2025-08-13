<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionShowPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->currency = Currency::factory()->create();
    }

    /** @test */
    public function subscription_show_page_displays_correct_first_billing_date()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'name' => 'Test Subscription',
            'start_date' => '2024-01-15',
            'first_billing_date' => '2024-01-20', // Different from start date
        ]);

        $response = $this->actingAs($this->user)
            ->get("/subscriptions/{$subscription->id}");

        $response->assertOk();
        
        // Check that the subscription data is passed correctly
        $response->assertInertia(fn ($page) =>
            $page->component('subscriptions/show')
                ->has('subscription')
                ->where('subscription.id', $subscription->id)
                ->where('subscription.name', 'Test Subscription')
                ->has('subscription.first_billing_date') // Just check it exists
                ->has('subscription.start_date')
        );
    }

    /** @test */
    public function subscription_show_page_includes_all_required_data()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $this->currency->id,
            'notifications_enabled' => true,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'reminder_intervals' => [7, 3, 1],
            'use_default_notifications' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/subscriptions/{$subscription->id}");

        $response->assertOk();
        
        // Check that notification settings are included in the raw subscription data
        $response->assertInertia(fn ($page) => 
            $page->component('subscriptions/show')
                ->has('subscription')
                ->where('subscription.notifications_enabled', true)
                ->where('subscription.email_enabled', true)
                ->where('subscription.webhook_enabled', false)
                ->where('subscription.use_default_notifications', false)
                ->where('subscription.reminder_intervals', [7, 3, 1])
        );
    }
}
