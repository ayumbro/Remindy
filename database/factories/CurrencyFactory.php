<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->currencyCode(),
            'name' => $this->faker->words(2, true).' Currency',
            'symbol' => $this->faker->randomElement(['$', '€', '£', '¥', '₹', '₽', '₩', '₪', '₦', '₨']),
            'is_active' => true,
            'is_system_default' => false,
        ];
    }
}
