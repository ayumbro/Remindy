<?php

namespace App\Http\Controllers;

use App\Models\PaymentHistory;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Get subscription statistics
        $totalSubscriptions = Subscription::forUser($user->id)->count();
        $activeSubscriptions = Subscription::forUser($user->id)->active()->count();
        $upcomingBillsCollection = Subscription::getUpcomingBills($user->id, 7);
        $upcomingBills = $upcomingBillsCollection->count();

        // Get spending by currency for current month
        $currentMonth = Carbon::now()->startOfMonth();
        $spendingByCurrency = PaymentHistory::whereHas('subscription', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('payment_date', '>=', $currentMonth)
            ->where('status', 'paid')
            ->with('currency')
            ->get()
            ->groupBy('currency.code')
            ->map(function ($payments) {
                return [
                    'currency' => $payments->first()->currency,
                    'total' => (float) $payments->sum('amount'),
                    'count' => $payments->count(),
                ];
            });

        // Get upcoming bills (next 30 days) - only future bills, not overdue
        $upcomingBillsList = Subscription::getUpcomingBills($user->id, 30)
            ->sortBy(function ($subscription) {
                return $subscription->next_billing_date;
            })
            ->take(10)
            ->values();

        // Get expired bills (overdue)
        $expiredBillsList = Subscription::getExpiredBills($user->id)
            ->sortBy(function ($subscription) {
                return $subscription->next_billing_date;
            })
            ->take(10)
            ->values();

        // Get enhanced monthly forecast with proper billing frequency calculations
        $currentMonthForecast = Subscription::getMonthlyForecast($user->id)
            ->map(function ($forecast) {
                return [
                    'currency' => $forecast['currency'],
                    'total' => (float) $forecast['total'],
                    'count' => $forecast['count'],
                    'subscriptions' => $forecast['subscriptions'],
                ];
            });

        // Get monthly spending trend (last 6 months)
        $monthlySpending = PaymentHistory::whereHas('subscription', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('payment_date', '>=', Carbon::now()->subMonths(6))
            ->where('status', 'paid')
            ->with('currency')
            ->get()
            ->groupBy(function ($payment) {
                return $payment->payment_date->format('Y-m');
            })
            ->map(function ($payments, $month) {
                return [
                    'month' => $month,
                    'total' => (float) $payments->sum('amount'),
                    'count' => $payments->count(),
                ];
            })
            ->values();

        // Get spending by category
        $spendingByCategory = PaymentHistory::whereHas('subscription', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('payment_date', '>=', $currentMonth)
            ->where('status', 'paid')
            ->with(['subscription.categories'])
            ->get()
            ->flatMap(function ($payment) {
                return $payment->subscription->categories->map(function ($category) use ($payment) {
                    return [
                        'category' => $category,
                        'amount' => (float) $payment->amount,
                    ];
                });
            })
            ->groupBy('category.name')
            ->map(function ($items) {
                return [
                    'category' => $items->first()['category'],
                    'total' => (float) $items->sum('amount'),
                    'count' => $items->count(),
                ];
            });

        return Inertia::render('dashboard', [
            'stats' => [
                'totalSubscriptions' => $totalSubscriptions,
                'activeSubscriptions' => $activeSubscriptions,
                'upcomingBills' => $upcomingBills,
                'expiredBills' => $expiredBillsList->count(),
            ],
            'spendingByCurrency' => $spendingByCurrency,
            'spendingByCategory' => $spendingByCategory,
            'monthlySpending' => $monthlySpending,
            'upcomingBills' => $upcomingBillsList,
            'expiredBills' => $expiredBillsList,
            'currentMonthForecast' => $currentMonthForecast,
        ]);
    }
}
