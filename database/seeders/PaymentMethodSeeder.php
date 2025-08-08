<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user for testing
        $user = User::first();

        if (! $user) {
            $this->command->error('No users found. Please run UserSeeder first.');

            return;
        }

        // Clear existing payment method images
        $this->clearExistingImages();

        // Create sample payment methods
        $paymentMethods = [
            [
                'name' => 'HSBC Credit Card',
                'description' => 'Primary credit card for online purchases and subscriptions',
                'is_active' => true,
                'has_image' => false,
            ],
            [
                'name' => 'Bank of China Debit Card',
                'description' => 'Main debit card for daily expenses and ATM withdrawals',
                'is_active' => true,
                'has_image' => false,
            ],
            [
                'name' => 'PayPal Account',
                'description' => 'Digital wallet for international transactions and online shopping',
                'is_active' => true,
                'has_image' => true,
            ],
            [
                'name' => 'Alipay',
                'description' => 'Mobile payment app for local purchases and transfers',
                'is_active' => true,
                'has_image' => true,
            ],
            [
                'name' => 'WeChat Pay',
                'description' => 'Integrated payment solution within WeChat ecosystem',
                'is_active' => false,
                'has_image' => false,
            ],
            [
                'name' => 'Octopus Card',
                'description' => 'Hong Kong transport and retail payment card',
                'is_active' => true,
                'has_image' => true,
            ],
            [
                'name' => 'Apple Pay',
                'description' => 'Contactless payment using iPhone and Apple Watch',
                'is_active' => true,
                'has_image' => false,
            ],
            [
                'name' => 'Google Pay',
                'description' => 'Android-based mobile payment service',
                'is_active' => false,
                'has_image' => false,
            ],
        ];

        foreach ($paymentMethods as $index => $methodData) {
            $imagePath = null;

            // Create sample image for some payment methods
            if ($methodData['has_image']) {
                $imagePath = $this->createSampleImage($methodData['name'], $index);
            }

            PaymentMethod::create([
                'user_id' => $user->id,
                'name' => $methodData['name'],
                'description' => $methodData['description'],
                'image_path' => $imagePath,
                'is_active' => $methodData['is_active'],
            ]);
        }

        $this->command->info('Created '.count($paymentMethods).' payment methods for user: '.$user->name);
        $this->command->info('Payment methods with images: '.collect($paymentMethods)->where('has_image', true)->count());
    }

    /**
     * Clear existing payment method images from storage
     */
    private function clearExistingImages(): void
    {
        $disk = Storage::disk('public');

        // Clear both old and new image directories
        if ($disk->exists('payment-methods')) {
            $disk->deleteDirectory('payment-methods');
        }

        if ($disk->exists('payment-method-images')) {
            $disk->deleteDirectory('payment-method-images');
        }

        // Recreate the new directory
        $disk->makeDirectory('payment-method-images');

        $this->command->info('Cleared existing payment method images');
    }

    /**
     * Create a sample image file for testing
     */
    private function createSampleImage(string $paymentMethodName, int $index): string
    {
        $disk = Storage::disk('public');

        // Create a simple SVG image as a placeholder
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'];
        $color = $colors[$index % count($colors)];

        $svg = $this->generateSampleSvg($paymentMethodName, $color);

        // Generate a unique filename
        $filename = 'sample_'.strtolower(str_replace(' ', '_', $paymentMethodName)).'_'.time().'_'.$index.'.svg';
        $path = 'payment-method-images/'.$filename;

        // Store the SVG file
        $disk->put($path, $svg);

        return $path;
    }

    /**
     * Generate a sample SVG image
     */
    private function generateSampleSvg(string $text, string $color): string
    {
        $initials = $this->getInitials($text);

        return <<<SVG
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
    <rect width="200" height="200" fill="{$color}" rx="10"/>
    <text x="100" y="120" font-family="Arial, sans-serif" font-size="48" font-weight="bold" 
          text-anchor="middle" fill="white">{$initials}</text>
    <text x="100" y="160" font-family="Arial, sans-serif" font-size="12" 
          text-anchor="middle" fill="white" opacity="0.8">Sample Image</text>
</svg>
SVG;
    }

    /**
     * Get initials from payment method name
     */
    private function getInitials(string $text): string
    {
        $words = explode(' ', $text);
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return $initials ?: 'PM';
    }
}
