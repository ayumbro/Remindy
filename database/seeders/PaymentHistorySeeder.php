<?php

namespace Database\Seeders;

use App\Models\PaymentHistory;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subscriptions = Subscription::with(['currency', 'paymentMethod'])->get();

        if ($subscriptions->isEmpty()) {
            $this->command->error('No subscriptions found. Please run SubscriptionSeeder first.');
            return;
        }

        $totalPayments = 0;

        foreach ($subscriptions as $subscription) {
            // Skip one-time and ended subscriptions for payment history
            if ($subscription->billing_cycle === 'one-time' || $subscription->end_date && Carbon::parse($subscription->end_date)->isPast()) {
                continue;
            }

            // Create payment history based on billing cycle
            $paymentsToCreate = $this->calculatePaymentsToCreate($subscription);

            foreach ($paymentsToCreate as $paymentData) {
                PaymentHistory::create([
                    'subscription_id' => $subscription->id,
                    'amount' => $paymentData['amount'],
                    'currency_id' => $subscription->currency_id,
                    'payment_method_id' => $subscription->payment_method_id,
                    'payment_date' => $paymentData['payment_date'],
                    'status' => $paymentData['status'],
                    'notes' => $paymentData['notes'],
                ]);
                $totalPayments++;
            }
        }

        $this->command->info("Created {$totalPayments} payment history records");
    }

    /**
     * Calculate payments to create based on subscription billing cycle
     */
    private function calculatePaymentsToCreate(Subscription $subscription): array
    {
        $payments = [];
        $startDate = Carbon::parse($subscription->first_billing_date);
        $today = Carbon::today();

        // Calculate how many payments should have been made
        switch ($subscription->billing_cycle) {
            case 'monthly':
                $monthsPassed = $startDate->diffInMonths($today);
                for ($i = 0; $i < min($monthsPassed, 6); $i++) { // Max 6 payments for demo
                    $paymentDate = $startDate->copy()->addMonths($i * $subscription->billing_interval);
                    if ($paymentDate->lte($today)) {
                        $payments[] = [
                            'amount' => $subscription->price,
                            'payment_date' => $paymentDate,
                            'status' => 'paid',
                            'notes' => "Payment for {$paymentDate->format('F Y')}",
                        ];
                    }
                }
                break;

            case 'yearly':
                $yearsPassed = $startDate->diffInYears($today);
                for ($i = 0; $i < min($yearsPassed, 2); $i++) { // Max 2 payments for demo
                    $paymentDate = $startDate->copy()->addYears($i * $subscription->billing_interval);
                    if ($paymentDate->lte($today)) {
                        $payments[] = [
                            'amount' => $subscription->price,
                            'payment_date' => $paymentDate,
                            'status' => 'paid',
                            'notes' => "Annual payment for {$paymentDate->format('Y')}",
                        ];
                    }
                }
                break;

            case 'weekly':
                $weeksPassed = $startDate->diffInWeeks($today);
                for ($i = 0; $i < min($weeksPassed, 8); $i++) { // Max 8 payments for demo
                    $paymentDate = $startDate->copy()->addWeeks($i * $subscription->billing_interval);
                    if ($paymentDate->lte($today)) {
                        $payments[] = [
                            'amount' => $subscription->price,
                            'payment_date' => $paymentDate,
                            'status' => 'paid',
                            'notes' => "Weekly payment - Week {$paymentDate->weekOfYear}",
                        ];
                    }
                }
                break;

            case 'quarterly':
                $quartersPassed = $startDate->diffInMonths($today) / 3;
                for ($i = 0; $i < min($quartersPassed, 4); $i++) { // Max 4 payments for demo
                    $paymentDate = $startDate->copy()->addMonths($i * 3 * $subscription->billing_interval);
                    if ($paymentDate->lte($today)) {
                        $payments[] = [
                            'amount' => $subscription->price,
                            'payment_date' => $paymentDate,
                            'status' => 'paid',
                            'notes' => "Quarterly payment - Q{$paymentDate->quarter} {$paymentDate->year}",
                        ];
                    }
                }
                break;
        }

        // Add a pending payment for the most recent one if applicable
        if (!empty($payments) && rand(1, 3) === 1) { // 33% chance of having a pending payment
            $lastPayment = end($payments);
            $lastPayment['status'] = 'pending';
            $lastPayment['notes'] = 'Payment pending - awaiting confirmation';
            $payments[] = $lastPayment;
        }

        return $payments;
    }
}