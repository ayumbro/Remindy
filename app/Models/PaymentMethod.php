<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'image_path',
        'is_active',
    ];

    protected $appends = [
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the payment method.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscriptions using this payment method.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the payment histories using this payment method.
     */
    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    /**
     * Scope a query to only include active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include payment methods for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the full URL for the payment method image.
     */
    public function getImageUrlAttribute()
    {
        if (! $this->image_path) {
            return null;
        }

        return asset('storage/'.$this->image_path);
    }

    /**
     * Check if the payment method has an image.
     */
    public function hasImage()
    {
        return ! empty($this->image_path);
    }

    /**
     * Check if this payment method can be deleted.
     */
    public function canBeDeleted()
    {
        // Payment methods cannot be deleted if they are used by:
        // 1. Any subscription that has this payment method set as default
        // 2. Any payment history records that reference this payment method
        return ! $this->subscriptions()->exists() && ! $this->paymentHistories()->exists();
    }

    /**
     * Get the reason why this payment method cannot be deleted.
     */
    public function getDeletionBlockReason()
    {
        $subscriptionCount = $this->subscriptions()->count();
        $paymentHistoryCount = $this->paymentHistories()->count();

        if ($subscriptionCount > 0 && $paymentHistoryCount > 0) {
            return "This payment method is currently used by {$subscriptionCount} subscription(s) and has {$paymentHistoryCount} payment history record(s). It cannot be deleted.";
        } elseif ($subscriptionCount > 0) {
            return "This payment method is currently used by {$subscriptionCount} subscription(s) and cannot be deleted.";
        } elseif ($paymentHistoryCount > 0) {
            return "This payment method has {$paymentHistoryCount} payment history record(s) and cannot be deleted to preserve transaction history.";
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
     * Check if this payment method can be disabled.
     */
    public function canBeDisabled()
    {
        // Payment methods can always be disabled, but warn if they're in use
        return true;
    }

    /**
     * Get the reason/warning when disabling this payment method.
     */
    public function getDisableWarning()
    {
        $subscriptionCount = $this->subscriptions()->count();

        if ($subscriptionCount > 0) {
            return "This payment method is currently used by {$subscriptionCount} subscription(s). Disabling it will prevent it from being used for new payments, but existing subscriptions will keep their reference.";
        }

        return null;
    }

    /**
     * Get a formatted display name for the payment method.
     */
    public function getDisplayNameAttribute()
    {
        return $this->name;
    }

    /**
     * Get available payment method types.
     */
    public static function getTypes()
    {
        return [
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'digital_wallet' => 'Digital Wallet',
            'cash' => 'Cash',
            'other' => 'Other',
        ];
    }

    /**
     * Delete the payment method image file.
     */
    public function deleteImage()
    {
        if ($this->image_path && Storage::disk('public')->exists($this->image_path)) {
            Storage::disk('public')->delete($this->image_path);
        }
    }
}
