<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company().' Subscription',
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 5, 100),
            'currency_id' => Currency::factory(),
            'payment_method_id' => null,
            'billing_cycle' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']),
            'billing_interval' => $this->faker->numberBetween(1, 3),
            'billing_cycle_day' => null,
            'start_date' => $startDate,
            'first_billing_date' => $startDate,
            'next_billing_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'end_date' => null,
            'website_url' => $this->faker->url(),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
