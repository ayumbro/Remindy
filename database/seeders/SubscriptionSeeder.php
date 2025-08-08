<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $usdCurrency = Currency::where('code', 'USD')->first();
        $hkdCurrency = Currency::where('code', 'HKD')->first();

        if (! $user || ! $usdCurrency || ! $hkdCurrency) {
            $this->command->error('Required data not found. Please run UserSeeder and CurrencySeeder first.');

            return;
        }

        // Get some payment methods
        $paymentMethods = PaymentMethod::where('user_id', $user->id)->get();

        if ($paymentMethods->isEmpty()) {
            $this->command->error('No payment methods found. Please run PaymentMethodSeeder first.');

            return;
        }

        $subscriptions = [
            [
                'name' => 'Netflix Premium',
                'description' => 'Streaming service for movies and TV shows',
                'price' => 15.99,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => now()->subMonths(6),
                'first_billing_date' => now()->subMonths(6)->startOfMonth()->addDays(14),
            ],
            [
                'name' => 'Spotify Premium',
                'description' => 'Music streaming service',
                'price' => 9.99,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => now()->subMonths(12),
                'first_billing_date' => now()->subMonths(12)->startOfMonth()->addDays(4),
            ],
            [
                'name' => 'Adobe Creative Cloud',
                'description' => 'Design and creative software suite',
                'price' => 52.99,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => now()->subMonths(3),
                'first_billing_date' => now()->subMonths(3)->startOfMonth()->addDays(14),
            ],
            [
                'name' => 'Microsoft 365',
                'description' => 'Office productivity suite',
                'price' => 99.99,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'yearly',
                'billing_interval' => 1,
                'start_date' => now()->subYear(),
                'first_billing_date' => now()->subYear(),
            ],
            [
                'name' => 'Hong Kong Broadband',
                'description' => 'Internet service provider',
                'price' => 299.00,
                'currency_id' => $hkdCurrency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => now()->subMonths(18),
                'first_billing_date' => now()->subMonths(18)->startOfMonth()->addDays(9),
            ],
            [
                'name' => 'GitHub Pro',
                'description' => 'Code repository and collaboration platform',
                'price' => 4.00,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'monthly',
                'billing_interval' => 1,
                'start_date' => now()->subMonths(8),
                'first_billing_date' => now()->subMonths(8)->startOfMonth()->addDays(19),
                'end_date' => now()->subDays(30), // Ended subscription example
            ],
            [
                'name' => 'Domain Registration',
                'description' => 'Annual domain registration for example.com',
                'price' => 12.99,
                'currency_id' => $usdCurrency->id,
                'billing_cycle' => 'one-time',
                'billing_interval' => 1,
                'start_date' => now(),
                'first_billing_date' => now(),
                'end_date' => now()->addYear(),
            ],
        ];

        foreach ($subscriptions as $index => $subscriptionData) {
            // Assign payment methods in a round-robin fashion
            $paymentMethod = $paymentMethods[$index % $paymentMethods->count()];

            $subscriptionCreateData = [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
                'name' => $subscriptionData['name'],
                'description' => $subscriptionData['description'],
                'price' => $subscriptionData['price'],
                'currency_id' => $subscriptionData['currency_id'],
                'billing_cycle' => $subscriptionData['billing_cycle'],
                'billing_interval' => $subscriptionData['billing_interval'] ?? 1,
                'start_date' => $subscriptionData['start_date'],
                'first_billing_date' => $subscriptionData['first_billing_date'] ?? $subscriptionData['start_date'],
                'use_default_notifications' => true, // Use user's default notification settings
            ];

            // Add optional fields if they exist
            if (isset($subscriptionData['end_date'])) {
                $subscriptionCreateData['end_date'] = $subscriptionData['end_date'];
            }

            $subscription = Subscription::create($subscriptionCreateData);
            
            // For some subscriptions, override with custom notification settings
            if ($index === 0) { // Netflix - custom settings example
                $subscription->update([
                    'use_default_notifications' => false,
                    'notifications_enabled' => true,
                    'email_enabled' => true,
                    'webhook_enabled' => false,
                    'reminder_intervals' => [7, 3, 1], // Only 7, 3, and 1 day reminders
                ]);
            }
        }

        $this->command->info('Created '.count($subscriptions).' subscriptions for user: '.$user->name);
    }
}
