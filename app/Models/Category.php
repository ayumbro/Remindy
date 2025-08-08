<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'description',
        'is_active',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'display_color',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the category.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscriptions that belong to this category.
     */
    public function subscriptions()
    {
        return $this->belongsToMany(Subscription::class, 'subscription_categories');
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include categories for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this category can be deleted.
     */
    public function canBeDeleted()
    {
        // Categories in use by subscriptions cannot be deleted
        return ! $this->subscriptions()->exists();
    }

    /**
     * Get the reason why this category cannot be deleted.
     */
    public function getDeletionBlockReason()
    {
        if ($this->subscriptions()->exists()) {
            $count = $this->subscriptions()->count();

            return "This category is currently used by {$count} subscription(s) and cannot be deleted.";
        }

        return null;
    }

    /**
     * Get a random color if none is set.
     */
    public function getDisplayColorAttribute()
    {
        if ($this->color) {
            return $this->color;
        }

        // Generate a consistent color based on category name
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
            '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
        ];

        $index = abs(crc32($this->name)) % count($colors);

        return $colors[$index];
    }

    /**
     * Get the subscription count for this category.
     */
    public function getSubscriptionCountAttribute()
    {
        return $this->subscriptions()->count();
    }

    /**
     * Get default category colors.
     */
    public static function getDefaultColors()
    {
        return [
            '#3B82F6', // Blue
            '#EF4444', // Red
            '#10B981', // Green
            '#F59E0B', // Yellow
            '#8B5CF6', // Purple
            '#EC4899', // Pink
            '#06B6D4', // Cyan
            '#84CC16', // Lime
            '#F97316', // Orange
            '#6366F1', // Indigo
        ];
    }
}
