<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEditFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_edit_form_loads_without_checkbox_errors(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('subscriptions.edit', $subscription));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('subscriptions/edit')
            ->has('subscription')
            ->has('categories')
            ->has('currencies')
            ->has('paymentMethods')
        );
    }
}
