<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'price',
        'currency_id',
        'payment_method_id',
        'billing_cycle',
        'billing_interval',
        'billing_cycle_day',
        'start_date',
        'first_billing_date',
        'end_date',
        'website_url',
        'notes',
        'notifications_enabled',
        'email_enabled',
        'webhook_enabled',
        'reminder_intervals',
        'use_default_notifications',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'next_billing_date',
        'computed_status',
        'is_overdue',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'first_billing_date' => 'date',
            'end_date' => 'date',
            'notifications_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'reminder_intervals' => 'array',
            'use_default_notifications' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the currency for this subscription.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the payment method for this subscription.
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Get the categories for this subscription.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'subscription_categories');
    }

    /**
     * Get the payment histories for this subscription.
     */
    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    /**
     * Get the next billing date (calculated dynamically).
     *
     * This accessor calculates the next billing date based on:
     * - The first_billing_date
     * - The number of paid payment history records
     * - The billing frequency/interval
     *
     * Returns null if the subscription has ended.
     */
    public function getNextBillingDateAttribute()
    {
        // If subscription has ended, don't show next billing date
        if ($this->end_date && Carbon::now()->isAfter($this->end_date)) {
            return null;
        }

        // One-time subscriptions don't have recurring billing dates
        if ($this->billing_cycle === 'one-time') {
            return null;
        }

        // Get count of paid payments
        $paidPaymentsCount = $this->paymentHistories()->where('status', 'paid')->count();

        // Calculate the next billing date based on payment count
        // This represents the date when the NEXT payment is due
        $nextBillingDate = $this->calculateNextBillingDateFromFirst($paidPaymentsCount);

        return $nextBillingDate;
    }

    /**
     * Get the computed status that considers end_date.
     * Returns 'ended' if the subscription has an end_date that has passed,
     * otherwise returns 'active'.
     */
    public function getComputedStatusAttribute(): string
    {
        // Check if subscription has ended (current date is past end_date)
        if ($this->end_date && Carbon::now()->isAfter($this->end_date)) {
            return 'ended';
        }

        // All non-ended subscriptions are considered active
        return 'active';
    }

    /**
     * Get whether the subscription is overdue.
     * Returns true if the subscription is active and has a past due billing date.
     */
    public function getIsOverdueAttribute(): bool
    {
        // Ended subscriptions cannot be overdue - check end_date directly
        if ($this->end_date && Carbon::now()->isAfter($this->end_date)) {
            return false;
        }

        // One-time subscriptions don't have recurring billing, so they can't be overdue
        if ($this->billing_cycle === 'one-time') {
            return false;
        }

        $nextBillingDate = $this->next_billing_date;
        if (! $nextBillingDate) {
            return false;
        }

        return Carbon::parse($nextBillingDate)->lt(Carbon::now());
    }

    /**
     * Scope a query to only include active subscriptions.
     * Active subscriptions are those that haven't ended (no end_date or end_date is in the future).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>', Carbon::now());
        });
    }

    /**
     * Scope a query to only include subscriptions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to include subscriptions due soon.
     *
     * Since next_billing_date is now a computed accessor, we need to get all active subscriptions
     * and filter them in PHP rather than in the database query.
     * Use the static getDueSoon method for actual filtering by days.
     */
    public function scopeDueSoon($query)
    {
        // Get all active subscriptions and filter in PHP since next_billing_date is computed
        return $query->active();
    }

    /**
     * Static method to get subscriptions due soon with proper filtering.
     * This replaces the scope when we need to filter by computed next_billing_date.
     */
    public static function getDueSoon($userId, $days = 7)
    {
        $subscriptions = static::with(['currency', 'paymentMethod'])
            ->forUser($userId)
            ->active()
            ->get();

        $cutoffDate = Carbon::now()->addDays($days);

        return $subscriptions->filter(function ($subscription) use ($cutoffDate) {
            $nextBillingDate = $subscription->next_billing_date;

            return $nextBillingDate && Carbon::parse($nextBillingDate)->lte($cutoffDate);
        });
    }

    /**
     * Static method to get expired bills (overdue subscriptions).
     */
    public static function getExpiredBills($userId)
    {
        $subscriptions = static::with(['currency', 'paymentMethod'])
            ->forUser($userId)
            ->active()
            ->get();

        $today = Carbon::now();

        return $subscriptions->filter(function ($subscription) use ($today) {
            $nextBillingDate = $subscription->next_billing_date;

            return $nextBillingDate && Carbon::parse($nextBillingDate)->lt($today);
        });
    }

    /**
     * Static method to get truly upcoming bills (future bills only, excluding overdue).
     */
    public static function getUpcomingBills($userId, $days = 7)
    {
        $subscriptions = static::with(['currency', 'paymentMethod'])
            ->forUser($userId)
            ->active()
            ->get();

        $today = Carbon::now();
        $cutoffDate = Carbon::now()->addDays($days);

        return $subscriptions->filter(function ($subscription) use ($today, $cutoffDate) {
            $nextBillingDate = $subscription->next_billing_date;

            return $nextBillingDate &&
                   Carbon::parse($nextBillingDate)->gt($today) &&
                   Carbon::parse($nextBillingDate)->lte($cutoffDate);
        });
    }

    /**
     * Static method to get bills for the current month.
     */
    public static function getCurrentMonthBills($userId)
    {
        $subscriptions = static::with(['currency', 'paymentMethod'])
            ->forUser($userId)
            ->active()
            ->where(function ($q) {
                // Only include subscriptions that haven't ended
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>', Carbon::now());
            })
            ->get();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return $subscriptions->filter(function ($subscription) use ($startOfMonth, $endOfMonth) {
            $nextBillingDate = $subscription->next_billing_date;

            return $nextBillingDate &&
                   Carbon::parse($nextBillingDate)->gte($startOfMonth) &&
                   Carbon::parse($nextBillingDate)->lte($endOfMonth);
        });
    }

    /**
     * Static method to get monthly forecast representing total monthly subscription budget.
     * This includes ALL billing cycles for the month, regardless of payment status.
     * Serves as a comprehensive budgeting tool showing total monthly subscription obligations.
     */
    public static function getMonthlyForecast($userId)
    {
        $subscriptions = static::with(['currency'])
            ->forUser($userId)
            ->active() // Only active subscriptions (simplified)
            ->get();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $forecastData = [];

        foreach ($subscriptions as $subscription) {
            // Calculate total monthly budget amount (all billing cycles in month)
            $forecastAmount = $subscription->calculateMonthlyForecastAmount($startOfMonth, $endOfMonth);

            if ($forecastAmount > 0) {
                $currencyCode = $subscription->currency->code;

                if (! isset($forecastData[$currencyCode])) {
                    $forecastData[$currencyCode] = [
                        'currency' => $subscription->currency,
                        'total' => 0,
                        'count' => 0,
                        'subscriptions' => [],
                    ];
                }

                $forecastData[$currencyCode]['total'] += $forecastAmount;
                $forecastData[$currencyCode]['count']++;
                $forecastData[$currencyCode]['subscriptions'][] = [
                    'subscription' => $subscription,
                    'forecast_amount' => $forecastAmount,
                ];
            }
        }

        return collect($forecastData);
    }

    /**
     * Calculate the total forecast amount for this subscription for the current month.
     * This includes ALL billing cycles that occur within the month, regardless of payment status.
     * Serves as a "total monthly subscription budget" calculation.
     */
    public function calculateMonthlyForecastAmount($startOfMonth, $endOfMonth)
    {
        // Check if subscription was active at any point during the month
        if (! $this->isActiveInMonth($startOfMonth, $endOfMonth)) {
            return 0;
        }

        switch ($this->billing_cycle) {
            case 'daily':
                return $this->calculateFullMonthDailyForecast($startOfMonth, $endOfMonth);

            case 'weekly':
                return $this->calculateFullMonthWeeklyForecast($startOfMonth, $endOfMonth);

            case 'monthly':
            case 'quarterly':
            case 'yearly':
                return $this->calculateFullMonthPeriodicForecast($startOfMonth, $endOfMonth);

            default:
                return $this->calculateFullMonthPeriodicForecast($startOfMonth, $endOfMonth);
        }
    }

    /**
     * Check if subscription is active during any part of the given month.
     */
    private function isActiveInMonth($startOfMonth, $endOfMonth)
    {
        // Subscription must have started before or during the month
        $subscriptionStart = Carbon::parse($this->start_date);
        if ($subscriptionStart->gt($endOfMonth)) {
            return false;
        }

        // If subscription has an end date, it must end after the start of the month
        if ($this->end_date) {
            $subscriptionEnd = Carbon::parse($this->end_date);
            if ($subscriptionEnd->lt($startOfMonth)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate forecast for daily subscriptions for the full month.
     * Counts ALL billing cycles that occur within the month, regardless of payment status.
     */
    private function calculateFullMonthDailyForecast($startOfMonth, $endOfMonth)
    {
        // Find the first billing date that occurs in this month
        $firstBillingInMonth = $this->findFirstBillingDateInMonth($startOfMonth, $endOfMonth);

        if (! $firstBillingInMonth) {
            return 0;
        }

        // Determine the effective end date (subscription end date or end of month, whichever is earlier)
        $effectiveEndDate = $endOfMonth;
        if ($this->end_date) {
            $subscriptionEndDate = Carbon::parse($this->end_date);
            $effectiveEndDate = $subscriptionEndDate->lt($endOfMonth) ? $subscriptionEndDate : $endOfMonth;
        }

        // Count all billing cycles from first billing date to effective end date
        $billingCount = 0;
        $currentBilling = $firstBillingInMonth->copy();
        $maxIterations = 1000; // Safety limit for daily subscriptions
        $iterations = 0;

        while ($currentBilling->lte($effectiveEndDate) && $iterations < $maxIterations) {
            $billingCount++;
            $currentBilling->addDays($this->billing_interval);
            $iterations++;
        }

        if ($iterations >= $maxIterations) {
            Log::warning("calculateFullMonthDailyForecast hit iteration limit for subscription {$this->id}");
        }

        return $billingCount * $this->price;
    }

    /**
     * Get the next billing date.
     * Public method to access the next_billing_date attribute.
     */
    public function getNextBillingDate(): ?Carbon
    {
        $date = $this->next_billing_date;
        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Calculate forecast for weekly subscriptions for the full month.
     * Counts ALL billing cycles that occur within the month, regardless of payment status.
     */
    private function calculateFullMonthWeeklyForecast($startOfMonth, $endOfMonth)
    {
        // Find the first billing date that occurs in this month
        $firstBillingInMonth = $this->findFirstBillingDateInMonth($startOfMonth, $endOfMonth);

        if (! $firstBillingInMonth) {
            return 0;
        }

        // Determine the effective end date (subscription end date or end of month, whichever is earlier)
        $effectiveEndDate = $endOfMonth;
        if ($this->end_date) {
            $subscriptionEndDate = Carbon::parse($this->end_date);
            $effectiveEndDate = $subscriptionEndDate->lt($endOfMonth) ? $subscriptionEndDate : $endOfMonth;
        }

        // Count all billing cycles from first billing date to effective end date
        $billingCount = 0;
        $currentBilling = $firstBillingInMonth->copy();
        $maxIterations = 100; // Safety limit for weekly subscriptions
        $iterations = 0;

        while ($currentBilling->lte($effectiveEndDate) && $iterations < $maxIterations) {
            $billingCount++;
            $currentBilling->addWeeks($this->billing_interval);
            $iterations++;
        }

        if ($iterations >= $maxIterations) {
            Log::warning("calculateFullMonthWeeklyForecast hit iteration limit for subscription {$this->id}");
        }

        return $billingCount * $this->price;
    }

    /**
     * Calculate forecast for monthly, quarterly, and yearly subscriptions for the full month.
     * Counts ALL billing cycles that occur within the month, regardless of payment status.
     */
    private function calculateFullMonthPeriodicForecast($startOfMonth, $endOfMonth)
    {
        // Find the first billing date that occurs in this month
        $firstBillingInMonth = $this->findFirstBillingDateInMonth($startOfMonth, $endOfMonth);

        if (! $firstBillingInMonth) {
            return 0;
        }

        // Determine the effective end date (subscription end date or end of month, whichever is earlier)
        $effectiveEndDate = $endOfMonth;
        if ($this->end_date) {
            $subscriptionEndDate = Carbon::parse($this->end_date);
            $effectiveEndDate = $subscriptionEndDate->lt($endOfMonth) ? $subscriptionEndDate : $endOfMonth;
        }

        // For periodic subscriptions, count all billing cycles within the effective period
        $billingCount = 0;
        $currentBilling = $firstBillingInMonth->copy();
        $maxIterations = 50; // Safety limit for periodic subscriptions
        $iterations = 0;

        while ($currentBilling->lte($effectiveEndDate) && $iterations < $maxIterations) {
            $billingCount++;
            $iterations++;

            // Add the appropriate interval based on billing cycle
            switch ($this->billing_cycle) {
                case 'monthly':
                    $currentBilling->addMonths($this->billing_interval);
                    break;
                case 'quarterly':
                    $currentBilling->addMonths($this->billing_interval * 3);
                    break;
                case 'yearly':
                    $currentBilling->addYear();
                    break;
                default:
                    $currentBilling->addMonths($this->billing_interval);
                    break;
            }
        }

        if ($iterations >= $maxIterations) {
            Log::warning("calculateFullMonthPeriodicForecast hit iteration limit for subscription {$this->id}");
        }

        return $billingCount * $this->price;
    }

    /**
     * Find the first billing date that occurs within the given month.
     * This method calculates billing dates from the first_billing_date and finds
     * the first one that falls within the specified month range.
     */
    private function findFirstBillingDateInMonth($startOfMonth, $endOfMonth)
    {
        $firstBillingDate = Carbon::parse($this->first_billing_date);

        // If first billing date is within the month, that's our answer
        if ($firstBillingDate->gte($startOfMonth) && $firstBillingDate->lte($endOfMonth)) {
            return $firstBillingDate;
        }

        // If first billing date is after the month, no billing in this month
        if ($firstBillingDate->gt($endOfMonth)) {
            return null;
        }

        // First billing date is before the month, calculate how many cycles to skip
        $currentBilling = $firstBillingDate->copy();
        $maxIterations = 1000; // Safety limit to prevent infinite loops
        $iterations = 0;

        // Keep adding billing intervals until we reach or pass the start of the month
        while ($currentBilling->lt($startOfMonth) && $iterations < $maxIterations) {
            $iterations++;

            // Add billing interval based on cycle type
            switch ($this->billing_cycle) {
                case 'daily':
                    $currentBilling->addDays($this->billing_interval);
                    break;
                case 'weekly':
                    $currentBilling->addWeeks($this->billing_interval);
                    break;
                case 'monthly':
                    $currentBilling->addMonths($this->billing_interval);
                    break;
                case 'quarterly':
                    $currentBilling->addMonths($this->billing_interval * 3);
                    break;
                case 'yearly':
                    $currentBilling->addYear();
                    break;
                default:
                    $currentBilling->addMonths($this->billing_interval);
                    break;
            }

            // Safety check to prevent infinite loops
            if ($currentBilling->gt($endOfMonth)) {
                return null;
            }
        }

        // If we hit the iteration limit, something is wrong
        if ($iterations >= $maxIterations) {
            Log::warning("findFirstBillingDateInMonth hit iteration limit for subscription {$this->id}");

            return null;
        }

        // If we found a billing date within the month, return it
        if ($currentBilling->lte($endOfMonth)) {
            return $currentBilling;
        }

        return null;
    }

    /**
     * Calculate the next billing date from the first billing date and payment count.
     *
     * This method calculates the next billing date based on:
     * - The first_billing_date as the baseline
     * - The number of billing cycles that have occurred (payment count)
     * - The billing frequency/interval
     *
     * @param  int  $paymentCount  Number of payments made (billing cycles completed)
     * @return Carbon The calculated next billing date
     */
    public function calculateNextBillingDateFromFirst(int $paymentCount): Carbon
    {
        $firstBillingDate = Carbon::parse($this->first_billing_date);

        switch ($this->billing_cycle) {
            case 'daily':
                return $firstBillingDate->copy()->addDays($this->billing_interval * $paymentCount);

            case 'weekly':
                return $firstBillingDate->copy()->addWeeks($this->billing_interval * $paymentCount);

            case 'monthly':
                return $this->calculateMonthlyBillingDateFromFirst($firstBillingDate, $paymentCount);

            case 'quarterly':
                // Quarterly is treated as 3-month intervals
                return $this->calculateMonthlyBillingDateFromFirst($firstBillingDate, $paymentCount, 3);

            case 'yearly':
                return $this->calculateYearlyBillingDateFromFirst($firstBillingDate, $paymentCount);

            default:
                // Default to monthly behavior for unknown cycles
                return $this->calculateMonthlyBillingDateFromFirst($firstBillingDate, $paymentCount);
        }
    }

    /**
     * Calculate the next billing date based on billing cycle and interval.
     *
     * This method implements intelligent date adjustment for end-of-month scenarios:
     * - For monthly subscriptions created on the 30th or 31st, it preserves the original billing day
     * - When transitioning to February, it adjusts to the last day of February (28th or 29th for leap years)
     * - When transitioning back to months with 30+ days, it reverts to the original day
     *
     * Examples:
     * - Subscription created on January 31st → February 28th/29th → March 31st → April 30th → May 31st
     * - Subscription created on January 30th → February 28th/29th → March 30th → April 30th → May 30th
     *
     * The billing_cycle_day field is immutable once set during subscription creation and represents
     * the preferred billing day of the month for monthly/quarterly cycles.
     *
     * @return Carbon The calculated next billing date
     */
    public function calculateNextBillingDate()
    {
        $currentDate = Carbon::parse($this->next_billing_date);

        switch ($this->billing_cycle) {
            case 'daily':
                return $currentDate->addDays($this->billing_interval);

            case 'weekly':
                return $currentDate->addWeeks($this->billing_interval);

            case 'monthly':
                return $this->calculateNextMonthlyBillingDate($currentDate, $this->billing_interval);

            case 'quarterly':
                // Quarterly is treated as 3-month intervals with the same end-of-month logic
                return $this->calculateNextMonthlyBillingDate($currentDate, $this->billing_interval * 3);

            case 'yearly':
                return $this->calculateNextYearlyBillingDate($currentDate);

            default:
                // Default to monthly behavior for unknown cycles
                return $this->calculateNextMonthlyBillingDate($currentDate, $this->billing_interval);
        }
    }

    /**
     * Calculate the next billing date for monthly and quarterly cycles with end-of-month handling.
     *
     * This method preserves the original billing cycle day while handling months with fewer days.
     * For example, if the original billing day is 31st:
     * - January 31st → February 28th/29th (adjusted to last day of February)
     * - February 28th/29th → March 31st (reverted to original day)
     * - March 31st → April 30th (adjusted to last day of April)
     * - April 30th → May 31st (reverted to original day)
     *
     * @param  Carbon  $currentDate  The current billing date
     * @param  int  $monthsToAdd  Number of months to add (1 for monthly, 3 for quarterly)
     * @return Carbon The calculated next billing date
     */
    private function calculateNextMonthlyBillingDate(Carbon $currentDate, int $monthsToAdd): Carbon
    {
        // If no billing_cycle_day is set, fall back to simple month addition
        if (! $this->billing_cycle_day) {
            return $currentDate->addMonths($monthsToAdd);
        }

        $targetDay = $this->billing_cycle_day;

        // Calculate the target year and month
        $targetYear = $currentDate->year;
        $targetMonth = $currentDate->month + $monthsToAdd;

        // Handle year overflow
        while ($targetMonth > 12) {
            $targetMonth -= 12;
            $targetYear++;
        }

        // Create a date for the first day of the target month
        $nextDate = Carbon::create($targetYear, $targetMonth, 1);

        // Get the last day of the target month
        $lastDayOfMonth = $nextDate->copy()->endOfMonth()->day;

        // If the target day exists in the target month, use it
        if ($targetDay <= $lastDayOfMonth) {
            $nextDate->day = $targetDay;
        } else {
            // If the target day doesn't exist (e.g., 31st in February), use the last day of the month
            $nextDate->day = $lastDayOfMonth;
        }

        return $nextDate;
    }

    /**
     * Calculate the next billing date for yearly cycles with end-of-month handling.
     *
     * For yearly subscriptions, this method handles the edge case of February 29th
     * on leap years by adjusting to February 28th in non-leap years.
     *
     * @param  Carbon  $currentDate  The current billing date
     * @return Carbon The calculated next billing date
     */
    private function calculateNextYearlyBillingDate(Carbon $currentDate): Carbon
    {
        $targetYear = $currentDate->year + $this->billing_interval;
        $targetMonth = $currentDate->month;
        $targetDay = $currentDate->day;

        // Handle February 29th edge case for leap years
        if ($targetMonth === 2 && $targetDay === 29 && ! Carbon::createFromDate($targetYear, 1, 1)->isLeapYear()) {
            $targetDay = 28;
        }

        return Carbon::create($targetYear, $targetMonth, $targetDay);
    }

    /**
     * Calculate monthly billing date from first billing date and payment count.
     *
     * @param  Carbon  $firstBillingDate  The first billing date
     * @param  int  $paymentCount  Number of payments made
     * @param  int  $monthMultiplier  Multiplier for months (1 for monthly, 3 for quarterly)
     * @return Carbon The calculated billing date
     */
    private function calculateMonthlyBillingDateFromFirst(Carbon $firstBillingDate, int $paymentCount, int $monthMultiplier = 1): Carbon
    {
        $monthsToAdd = $this->billing_interval * $monthMultiplier * $paymentCount;
        $targetDate = $firstBillingDate->copy();

        if ($monthsToAdd === 0) {
            return $targetDate;
        }

        // If no billing_cycle_day is set, fall back to simple month addition
        if (! $this->billing_cycle_day) {
            return $targetDate->addMonths($monthsToAdd);
        }

        $targetDay = $this->billing_cycle_day;

        // Calculate the target year and month
        $targetYear = $targetDate->year;
        $targetMonth = $targetDate->month + $monthsToAdd;

        // Handle year overflow
        while ($targetMonth > 12) {
            $targetMonth -= 12;
            $targetYear++;
        }

        // Get the last day of the target month
        $lastDayOfMonth = Carbon::create($targetYear, $targetMonth, 1)->endOfMonth()->day;

        // Create the target date
        $nextDate = Carbon::create($targetYear, $targetMonth, 1);

        // If the target day exists in the target month, use it
        if ($targetDay <= $lastDayOfMonth) {
            $nextDate->day = $targetDay;
        } else {
            // If the target day doesn't exist (e.g., 31st in February), use the last day of the month
            $nextDate->day = $lastDayOfMonth;
        }

        return $nextDate;
    }

    /**
     * Calculate yearly billing date from first billing date and payment count.
     *
     * @param  Carbon  $firstBillingDate  The first billing date
     * @param  int  $paymentCount  Number of payments made
     * @return Carbon The calculated billing date
     */
    private function calculateYearlyBillingDateFromFirst(Carbon $firstBillingDate, int $paymentCount): Carbon
    {
        $yearsToAdd = $this->billing_interval * $paymentCount;
        $targetDate = $firstBillingDate->copy();

        if ($yearsToAdd === 0) {
            return $targetDate;
        }

        $targetYear = $targetDate->year + $yearsToAdd;
        $targetMonth = $targetDate->month;
        $targetDay = $targetDate->day;

        // Handle February 29th edge case for leap years
        if ($targetMonth === 2 && $targetDay === 29 && ! Carbon::createFromDate($targetYear, 1, 1)->isLeapYear()) {
            $targetDay = 28;
        }

        return Carbon::create($targetYear, $targetMonth, $targetDay);
    }

    /**
     * Set the billing cycle day based on the start date.
     *
     * This method should be called during subscription creation to establish the immutable
     * billing cycle day for monthly and quarterly subscriptions. The billing cycle day
     * represents the preferred day of the month for billing and is preserved throughout
     * the subscription lifecycle.
     */
    public function setBillingCycleDay(): void
    {
        // Only set billing_cycle_day for monthly and quarterly cycles
        if (in_array($this->billing_cycle, ['monthly', 'quarterly'])) {
            $startDate = Carbon::parse($this->start_date);
            $this->billing_cycle_day = $startDate->day;
        } else {
            // For non-monthly cycles, billing_cycle_day should be null
            $this->billing_cycle_day = null;
        }
    }

    /**
     * Mark subscription as paid.
     *
     * Note: next_billing_date is now calculated dynamically based on payment count,
     * so we no longer need to update it manually.
     */
    public function markAsPaid($amount = null, $paymentDate = null, $paymentMethodId = null, $currencyId = null)
    {
        $amount = $amount ?? $this->price;
        $paymentDate = $paymentDate ?? Carbon::now();
        $paymentMethodId = $paymentMethodId ?? $this->payment_method_id;
        $currencyId = $currencyId ?? $this->currency_id;

        // Create payment history record
        $this->paymentHistories()->create([
            'amount' => $amount,
            'currency_id' => $currencyId,
            'payment_method_id' => $paymentMethodId,
            'payment_date' => $paymentDate,
            'status' => 'paid',
        ]);

        return $this;
    }

    /**
     * Get effective notification settings for this subscription.
     * Falls back to user defaults if use_default_notifications is true or settings are null.
     */
    public function getEffectiveNotificationSettings(): array
    {
        $user = $this->user;
        
        // If using default notifications or specific settings are null, use user defaults
        return [
            'notifications_enabled' => $this->use_default_notifications || is_null($this->notifications_enabled) 
                ? true 
                : $this->notifications_enabled,
            'email_enabled' => $this->use_default_notifications || is_null($this->email_enabled) 
                ? $user->default_email_enabled 
                : $this->email_enabled,
            'webhook_enabled' => $this->use_default_notifications || is_null($this->webhook_enabled) 
                ? $user->default_webhook_enabled 
                : $this->webhook_enabled,
            'reminder_intervals' => $this->use_default_notifications || is_null($this->reminder_intervals) 
                ? $user->getDefaultReminderIntervalsWithFallback() 
                : $this->reminder_intervals,
            'email_address' => $user->getEffectiveNotificationEmail(),
            'webhook_url' => $user->webhook_url,
            'webhook_headers' => $user->webhook_headers,
        ];
    }

    /**
     * Check if notifications are enabled for this subscription.
     */
    public function areNotificationsEnabled(): bool
    {
        $settings = $this->getEffectiveNotificationSettings();
        return $settings['notifications_enabled'] &&
               ($settings['email_enabled'] || $settings['webhook_enabled']);
    }

    /**
     * Recalculate billing cycle day when start_date or first_billing_date changes.
     * This method ensures that the billing cycle day is properly updated to maintain
     * consistent billing dates when the subscription dates are modified.
     */
    public function recalculateBillingCycleDay(): void
    {
        // Only recalculate for monthly and quarterly cycles
        if (in_array($this->billing_cycle, ['monthly', 'quarterly'])) {
            // Use first_billing_date to determine the billing cycle day
            $firstBillingDate = Carbon::parse($this->first_billing_date);
            $this->billing_cycle_day = $firstBillingDate->day;
        } else {
            // For non-monthly cycles, billing_cycle_day should be null
            $this->billing_cycle_day = null;
        }
    }

    /**
     * Update subscription dates and recalculate billing information.
     * This method should be used when updating start_date or first_billing_date
     * to ensure all related billing calculations remain consistent.
     *
     * @param array $dateUpdates Array containing 'start_date' and/or 'first_billing_date'
     * @return bool Whether the update was successful
     */
    public function updateDatesAndRecalculate(array $dateUpdates): bool
    {
        // Validate that we have at least one date to update
        if (!isset($dateUpdates['start_date']) && !isset($dateUpdates['first_billing_date'])) {
            return false;
        }

        // Update the dates
        if (isset($dateUpdates['start_date'])) {
            $this->start_date = $dateUpdates['start_date'];
        }

        if (isset($dateUpdates['first_billing_date'])) {
            $this->first_billing_date = $dateUpdates['first_billing_date'];
        }

        // No validation needed - first_billing_date can be before start_date

        // Recalculate billing cycle day based on the new first_billing_date
        $this->recalculateBillingCycleDay();

        // Save the changes
        return $this->save();
    }

    /**
     * Check if this subscription can be deleted.
     * Subscriptions cannot be deleted if they have associated payment history records
     * to preserve audit trail and transaction history.
     */
    public function canBeDeleted(): bool
    {
        return !$this->paymentHistories()->exists();
    }

    /**
     * Get the reason why this subscription cannot be deleted.
     */
    public function getDeletionBlockReason(): ?string
    {
        $paymentHistoryCount = $this->paymentHistories()->count();

        if ($paymentHistoryCount > 0) {
            return "This subscription has {$paymentHistoryCount} payment history record(s) and cannot be deleted to preserve transaction history for audit purposes.";
        }

        return null;
    }
}
