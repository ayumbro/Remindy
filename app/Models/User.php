<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'date_format',
        'notification_time_utc',
        'default_email_enabled',
        'default_webhook_enabled',
        'default_reminder_intervals',
        'notification_email',
        'webhook_url',
        'webhook_headers',
        'daily_notification_enabled',
        'last_daily_notification_sent_at',
        'default_currency_id',
        'enabled_currencies',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'smtp_password',
        'webhook_headers',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'smtp_password' => 'encrypted',
            'enabled_currencies' => 'array',
            'default_email_enabled' => 'boolean',
            'default_webhook_enabled' => 'boolean',
            'default_reminder_intervals' => 'array',
            'webhook_headers' => 'array',
            'daily_notification_enabled' => 'boolean',
            'last_daily_notification_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's categories.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the user's payment methods.
     */
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the user's default currency.
     */
    public function defaultCurrency()
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * Get the user's enabled currencies.
     * Returns all active currencies if none are specifically enabled.
     */
    public function getEnabledCurrencyIds()
    {
        $enabledCurrencies = $this->getAttributeValue('enabled_currencies');

        if (empty($enabledCurrencies)) {
            // If no enabled currencies are set, return all active currencies
            return Currency::active()->pluck('id')->toArray();
        }

        return $enabledCurrencies;
    }

    /**
     * Check if a currency is enabled for this user.
     */
    public function isCurrencyEnabled($currencyId)
    {
        return in_array($currencyId, $this->getEnabledCurrencyIds());
    }

    /**
     * Get the effective notification email address.
     */
    public function getEffectiveNotificationEmail(): string
    {
        return $this->notification_email ?? $this->email;
    }

    /**
     * Get default reminder intervals with fallback.
     */
    public function getDefaultReminderIntervalsWithFallback(): array
    {
        $intervals = $this->default_reminder_intervals;
        return empty($intervals) ? [30, 15, 7, 3, 1] : $intervals;
    }

    /**
     * Check if the user has SMTP configuration.
     */
    public function hasSmtpConfig(): bool
    {
        return ! empty($this->smtp_host) && ! empty($this->smtp_port);
    }

    /**
     * Get the SMTP configuration for this user.
     * User must configure their own SMTP settings.
     */
    public function getSmtpConfig(): array
    {
        return [
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'username' => $this->smtp_username,
            'password' => $this->smtp_password,
            'encryption' => $this->smtp_encryption,
            'from_address' => $this->smtp_from_address ?: $this->email,
            'from_name' => $this->smtp_from_name ?: $this->name,
        ];
    }
}
