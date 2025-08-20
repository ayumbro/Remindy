<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized date formatting utilities for the Remindi application.
 *
 * This class provides consistent date/time formatting across the application
 * with user-configurable date format preferences.
 */
class DateHelper
{
    /**
     * ISO date format: YYYY-MM-DD (default)
     */
    public const DATE_FORMAT_ISO = 'Y-m-d';

    /**
     * US date format: MM/DD/YYYY
     */
    public const DATE_FORMAT_US = 'm/d/Y';

    /**
     * European date format: DD/MM/YYYY
     */
    public const DATE_FORMAT_EU = 'd/m/Y';

    /**
     * Standard datetime format: YYYY-MM-DD HH:MM
     */
    public const DATETIME_FORMAT = 'Y-m-d H:i';

    /**
     * Standard time format: HH:MM
     */
    public const TIME_FORMAT = 'H:i';

    /**
     * Format a date using the user's preferred date format.
     *
     * @param  Carbon|string|null  $date  The date to format
     * @param  string|null  $format  Optional format override (defaults to user's date format)
     * @return string|null Formatted date string or null if input is null
     */
    public static function formatDate($date, ?string $format = null): ?string
    {
        if (! $date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $dateFormat = $format ?? (Auth::check() && Auth::user()->date_format ? Auth::user()->date_format : self::DATE_FORMAT_ISO);

        return $carbon->format($dateFormat);
    }

    /**
     * Format a datetime using the standard YYYY-MM-DD HH:MM format.
     *
     * @param  Carbon|string|null  $datetime  The datetime to format
     * @return string|null Formatted datetime string or null if input is null
     */
    public static function formatDateTime($datetime): ?string
    {
        if (! $datetime) {
            return null;
        }

        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);

        return $carbon->format(self::DATETIME_FORMAT);
    }

    /**
     * Format a time using the standard HH:MM format.
     *
     * @param  Carbon|string|null  $time  The time to format
     * @return string|null Formatted time string or null if input is null
     */
    public static function formatTime($time): ?string
    {
        if (! $time) {
            return null;
        }

        $carbon = $time instanceof Carbon ? $time : Carbon::parse($time);

        return $carbon->format(self::TIME_FORMAT);
    }

    /**
     * Format a date for database storage (always in ISO format).
     *
     * @param  Carbon|string  $date  The date to format
     * @return string Formatted date string in ISO format (Y-m-d)
     */
    public static function formatForDatabase($date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $carbon->format(self::DATE_FORMAT_ISO);
    }

    /**
     * Format a datetime for database storage (always in UTC).
     *
     * @param  Carbon|string  $datetime  The datetime to format
     * @return string Formatted datetime string in UTC
     */
    public static function formatDateTimeForDatabase($datetime): string
    {
        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);

        return $carbon->utc()->format(self::DATETIME_FORMAT);
    }

    /**
     * Check if a year is a leap year.
     *
     * @param  int  $year  The year to check
     * @return bool True if the year is a leap year, false otherwise
     */
    public static function isLeapYear(int $year): bool
    {
        return Carbon::createFromDate($year, 1, 1)->isLeapYear();
    }

    /**
     * Get today's date in ISO format (Y-m-d) without timezone conversion.
     * This is safer than using Carbon::now()->format() which can be affected by timezone.
     *
     * @return string Today's date as YYYY-MM-DD string
     */
    public static function getTodayString(): string
    {
        return Carbon::now()->format(self::DATE_FORMAT_ISO);
    }

    /**
     * Convert a date input to YYYY-MM-DD format safely without timezone conversion.
     * Handles cases where the input might be in different formats.
     *
     * @param  Carbon|string|null  $dateInput  The date to format
     * @return string|null Formatted date string in YYYY-MM-DD format or null if invalid
     */
    public static function toDateString($dateInput): ?string
    {
        if (! $dateInput) {
            return null;
        }

        try {
            $carbon = $dateInput instanceof Carbon ? $dateInput : Carbon::parse($dateInput);
            return $carbon->format(self::DATE_FORMAT_ISO);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Format a date for email notifications without timezone conversion.
     * This method is specifically designed for email templates where we want
     * to display the date exactly as stored without any timezone shifts.
     *
     * @param  Carbon|string|null  $date  The date to format
     * @param  string|null  $format  Optional format override (defaults to user's date format)
     * @return string|null Formatted date string or null if input is null
     */
    public static function formatDateForEmail($date, ?string $format = null): ?string
    {
        if (! $date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $dateFormat = $format ?? self::DATE_FORMAT_ISO;

        // For email formatting, avoid timezone conversion which can shift the date
        return $carbon->format($dateFormat);
    }

    /**
     * Get the last day of a specific month and year.
     *
     * @param  int  $year  The year
     * @param  int  $month  The month (1-12)
     * @return int The last day of the month (28-31)
     */
    public static function getLastDayOfMonth(int $year, int $month): int
    {
        return Carbon::createFromDate($year, $month, 1)->endOfMonth()->day;
    }
}
