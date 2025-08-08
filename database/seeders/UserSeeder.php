<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'locale' => 'en',
                'date_format' => 'Y-m-d',
                'notification_time_utc' => '14:00:00', // 9 AM EST
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [30, 15, 7, 3, 1],
                'notification_email' => 'john@example.com',
                'default_currency_id' => 3, // USD
                'enabled_currencies' => [1, 2, 3], // HKD, CNY, USD
            ],
            [
                'name' => '张三',
                'email' => 'zhang@example.com',
                'password' => Hash::make('password'),
                'locale' => 'zh-CN',
                'date_format' => 'Y-m-d',
                'notification_time_utc' => '01:00:00', // 9 AM CST
                'default_email_enabled' => true,
                'default_webhook_enabled' => false,
                'default_reminder_intervals' => [30, 15, 7, 3, 1],
                'notification_email' => 'zhang@example.com',
                'default_currency_id' => 2, // CNY
                'enabled_currencies' => [1, 2, 3], // HKD, CNY, USD
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Create categories for each user
            $this->createCategoriesForUser($user);

            // Create payment methods for each user
            $this->createPaymentMethodsForUser($user);

            // Create subscriptions for each user
            $this->createSubscriptionsForUser($user);
        }
    }

    private function createCategoriesForUser(User $user): void
    {
        $categories = [
            ['name' => 'Entertainment', 'color' => '#FF6B6B', 'description' => 'Movies, music, games'],
            ['name' => 'Productivity', 'color' => '#4ECDC4', 'description' => 'Work tools and software'],
            ['name' => 'Education', 'color' => '#45B7D1', 'description' => 'Learning platforms and courses'],
            ['name' => 'Health & Fitness', 'color' => '#96CEB4', 'description' => 'Health and fitness apps'],
            ['name' => 'News & Media', 'color' => '#FFEAA7', 'description' => 'News and media subscriptions'],
        ];

        foreach ($categories as $categoryData) {
            $user->categories()->firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }
    }

    private function createPaymentMethodsForUser(User $user): void
    {
        // Payment methods are now created by PaymentMethodSeeder
        // This method is kept for backward compatibility but does nothing
        // Suppress unused parameter warning
        unset($user);
    }

    private function createSubscriptionsForUser(User $user): void
    {
        // Subscriptions are now created by SubscriptionSeeder
        // This method is kept for backward compatibility but does nothing
        // Suppress unused parameter warning
        unset($user);
    }
}
