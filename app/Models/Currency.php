<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_active',
        'user_id',
        'is_system_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system_default' => 'boolean',
        ];
    }

    /**
     * Get the subscriptions for this currency.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the payment histories for this currency.
     */
    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    /**
     * Scope a query to only include active currencies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include currencies for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the user who created this currency.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this currency can be deleted.
     */
    public function canBeDeleted()
    {
        // Currencies cannot be deleted if they are used by:
        // 1. Any subscription that has this currency set
        // 2. Any payment history records that reference this currency
        return ! $this->subscriptions()->exists() && ! $this->paymentHistories()->exists();
    }

    /**
     * Get the reason why this currency cannot be deleted.
     */
    public function getDeletionBlockReason()
    {
        $subscriptionCount = $this->subscriptions()->count();
        $paymentHistoryCount = $this->paymentHistories()->count();

        if ($subscriptionCount > 0 && $paymentHistoryCount > 0) {
            return "This currency is currently used by {$subscriptionCount} subscription(s) and has {$paymentHistoryCount} payment history record(s). It cannot be deleted.";
        } elseif ($subscriptionCount > 0) {
            return "This currency is currently used by {$subscriptionCount} subscription(s) and cannot be deleted.";
        } elseif ($paymentHistoryCount > 0) {
            return "This currency has {$paymentHistoryCount} payment history record(s) and cannot be deleted to preserve transaction history.";
        }

        return null;
    }

    /**
     * Get the total usage count (subscriptions + payment history).
     */
    public function getTotalUsageCount()
    {
        return $this->subscriptions()->count() + $this->paymentHistories()->count();
    }

    /**
     * Get detailed usage information for this currency.
     */
    public function getUsageDetails()
    {
        return [
            'subscriptions_count' => $this->subscriptions()->count(),
            'payment_histories_count' => $this->paymentHistories()->count(),
            'total_usage_count' => $this->getTotalUsageCount(),
        ];
    }
}
