import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Centralized date formatting utilities for the Remindi application frontend.
 *
 * These utilities provide consistent date/time formatting across the application
 * with support for different date format preferences.
 */

/**
 * Date format constants matching backend DateHelper
 */
export const DATE_FORMAT_ISO = 'YYYY-MM-DD';
export const DATE_FORMAT_US = 'MM/DD/YYYY';
export const DATE_FORMAT_EU = 'DD/MM/YYYY';

/**
 * Standard datetime format: YYYY-MM-DD HH:MM
 */
export const DATETIME_FORMAT = 'YYYY-MM-DD HH:mm';

/**
 * Format a date using the specified format or default ISO format.
 *
 * @param date - The date to format (Date object, string, or null)
 * @param format - The format to use ('Y-m-d', 'm/d/Y', 'd/m/Y') or null for ISO
 * @returns Formatted date string or null if input is null
 */
export function formatDate(date: Date | string | null, format: string | null = null): string | null {
    if (!date) {
        return null;
    }

    const dateObj = typeof date === 'string' ? new Date(date) : date;

    if (isNaN(dateObj.getTime())) {
        return null;
    }

    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');

    // Use specified format or default to ISO
    const dateFormat = format || 'Y-m-d';

    switch (dateFormat) {
        case 'm/d/Y':
            return `${month}/${day}/${year}`;
        case 'd/m/Y':
            return `${day}/${month}/${year}`;
        case 'Y-m-d':
        default:
            return `${year}-${month}-${day}`;
    }
}

/**
 * Format a datetime using the standard YYYY-MM-DD HH:MM format.
 *
 * @param datetime - The datetime to format (Date object, string, or null)
 * @returns Formatted datetime string or null if input is null
 */
export function formatDateTime(datetime: Date | string | null): string | null {
    if (!datetime) {
        return null;
    }

    const dateObj = typeof datetime === 'string' ? new Date(datetime) : datetime;

    if (isNaN(dateObj.getTime())) {
        return null;
    }

    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    const hours = String(dateObj.getHours()).padStart(2, '0');
    const minutes = String(dateObj.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

/**
 * Format a time using the standard HH:MM format.
 *
 * @param time - The time to format (Date object, string, or null)
 * @returns Formatted time string or null if input is null
 */
export function formatTime(time: Date | string | null): string | null {
    if (!time) {
        return null;
    }

    const dateObj = typeof time === 'string' ? new Date(time) : time;

    if (isNaN(dateObj.getTime())) {
        return null;
    }

    const hours = String(dateObj.getHours()).padStart(2, '0');
    const minutes = String(dateObj.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
}

/**
 * Check if a year is a leap year.
 *
 * @param year - The year to check
 * @returns True if the year is a leap year, false otherwise
 */
export function isLeapYear(year: number): boolean {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

/**
 * Get the last day of a specific month and year.
 *
 * @param year - The year
 * @param month - The month (1-12)
 * @returns The last day of the month (28-31)
 */
export function getLastDayOfMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

/**
 * Calculate the next billing date with end-of-month handling.
 *
 * This function implements the same logic as the backend PHP implementation
 * to ensure consistency between frontend preview and actual billing calculations.
 *
 * @param startDate - The subscription start date (YYYY-MM-DD format)
 * @param cycle - The billing cycle ('daily', 'weekly', 'monthly', 'quarterly', 'yearly')
 * @param interval - The billing interval (e.g., 1 for every month, 2 for every 2 months)
 * @returns The calculated next billing date in YYYY-MM-DD format
 */
export function calculateNextBillingDate(startDate: string, cycle: string, interval: number): string {
    const date = new Date(startDate);
    const originalDay = date.getDate();

    switch (cycle) {
        case 'daily':
            date.setDate(date.getDate() + interval);
            break;

        case 'weekly':
            date.setDate(date.getDate() + interval * 7);
            break;

        case 'monthly':
            return calculateNextMonthlyBillingDate(date, interval, originalDay);

        case 'quarterly':
            // Quarterly is treated as 3-month intervals with the same end-of-month logic
            return calculateNextMonthlyBillingDate(date, interval * 3, originalDay);

        case 'yearly':
            return calculateNextYearlyBillingDate(date, interval);

        default:
            // Default to monthly behavior for unknown cycles
            return calculateNextMonthlyBillingDate(date, interval, originalDay);
    }

    return formatDate(date) || '';
}

/**
 * Calculate the next billing date for monthly and quarterly cycles with end-of-month handling.
 *
 * This function preserves the original billing cycle day while handling months with fewer days.
 * For example, if the original billing day is 31st:
 * - January 31st → February 28th/29th (adjusted to last day of February)
 * - February 28th/29th → March 31st (reverted to original day)
 * - March 31st → April 30th (adjusted to last day of April)
 * - April 30th → May 31st (reverted to original day)
 *
 * @param currentDate - The current billing date
 * @param monthsToAdd - Number of months to add (1 for monthly, 3 for quarterly)
 * @param originalDay - The original billing cycle day (1-31)
 * @returns The calculated next billing date in YYYY-MM-DD format
 */
function calculateNextMonthlyBillingDate(currentDate: Date, monthsToAdd: number, originalDay: number): string {
    const nextDate = new Date(currentDate);
    nextDate.setMonth(nextDate.getMonth() + monthsToAdd);

    // Get the last day of the target month
    const lastDayOfMonth = getLastDayOfMonth(nextDate.getFullYear(), nextDate.getMonth() + 1);

    // If the original day exists in the target month, use it
    if (originalDay <= lastDayOfMonth) {
        nextDate.setDate(originalDay);
    } else {
        // If the original day doesn't exist (e.g., 31st in February), use the last day of the month
        nextDate.setDate(lastDayOfMonth);
    }

    return formatDate(nextDate) || '';
}

/**
 * Calculate the next billing date for yearly cycles with end-of-month handling.
 *
 * For yearly subscriptions, this function handles the edge case of February 29th
 * on leap years by adjusting to February 28th in non-leap years.
 *
 * @param currentDate - The current billing date
 * @param yearsToAdd - Number of years to add
 * @returns The calculated next billing date in YYYY-MM-DD format
 */
function calculateNextYearlyBillingDate(currentDate: Date, yearsToAdd: number): string {
    const nextDate = new Date(currentDate);
    nextDate.setFullYear(nextDate.getFullYear() + yearsToAdd);

    // Handle February 29th edge case for leap years
    if (currentDate.getMonth() === 1 && currentDate.getDate() === 29 && !isLeapYear(nextDate.getFullYear())) {
        nextDate.setDate(28);
    }

    return formatDate(nextDate) || '';
}

/**
 * Get today's date in YYYY-MM-DD format without timezone conversion.
 * This is safer than using toISOString() which can cause timezone shifts.
 *
 * @returns Today's date as YYYY-MM-DD string
 */
export function getTodayString(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Convert a date string to YYYY-MM-DD format without timezone conversion.
 * Handles cases where the input might be in different formats.
 *
 * @param dateInput - The date to format (string or Date object)
 * @returns Formatted date string in YYYY-MM-DD format or empty string if invalid
 */
export function toDateString(dateInput: string | Date | null | undefined): string {
    if (!dateInput) {
        return '';
    }

    // If it's already in YYYY-MM-DD format, return as-is
    if (typeof dateInput === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateInput)) {
        return dateInput;
    }

    try {
        let date: Date;

        if (typeof dateInput === 'string') {
            // Add time component to force local timezone interpretation
            date = new Date(dateInput + 'T00:00:00');
        } else {
            date = dateInput;
        }

        if (isNaN(date.getTime())) {
            return '';
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    } catch {
        return '';
    }
}
