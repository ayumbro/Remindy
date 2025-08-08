/**
 * Validation utilities for the Remindi application.
 *
 * This module provides reusable validation functions for forms,
 * with a focus on date validation and subscription-specific rules.
 */

/**
 * Validates that an end date is not before a start date.
 *
 * @param startDate - The start date string (YYYY-MM-DD format)
 * @param endDate - The end date string (YYYY-MM-DD format)
 * @param allowSameDay - Whether to allow end date to be the same as start date (default: true)
 * @returns true if valid, false if invalid
 */
export function validateEndDateAfterStartDate(startDate: string, endDate: string, allowSameDay: boolean = true): boolean {
    if (!startDate || !endDate) {
        return true; // Allow empty dates
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

    // Check for invalid dates
    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        return false;
    }

    if (allowSameDay) {
        return end >= start;
    } else {
        return end > start;
    }
}

/**
 * Validates that a date is not in the future.
 *
 * @param dateString - The date string to validate (YYYY-MM-DD format)
 * @param allowToday - Whether to allow today's date (default: true)
 * @returns true if valid, false if invalid
 */
export function validateDateNotInFuture(dateString: string, allowToday: boolean = true): boolean {
    if (!dateString) {
        return true; // Allow empty dates
    }

    const inputDate = new Date(dateString);
    const today = new Date();

    // Set time to end of day for comparison
    today.setHours(23, 59, 59, 999);

    // Check for invalid date
    if (isNaN(inputDate.getTime())) {
        return false;
    }

    if (allowToday) {
        return inputDate <= today;
    } else {
        return inputDate < today;
    }
}

/**
 * Validates subscription date fields together.
 *
 * @param startDate - The subscription start date
 * @param endDate - The subscription end date (optional)
 * @param firstBillingDate - The first billing date (optional)
 * @returns Object with validation results and error messages
 */
export function validateSubscriptionDates(
    startDate: string,
    endDate?: string,
    firstBillingDate?: string,
): {
    isValid: boolean;
    errors: {
        startDate?: string;
        endDate?: string;
        firstBillingDate?: string;
    };
} {
    const errors: { startDate?: string; endDate?: string; firstBillingDate?: string } = {};

    // Validate start date is not empty
    if (!startDate) {
        errors.startDate = 'Start date is required.';
    }

    // Validate end date is after start date
    if (endDate && startDate) {
        if (!validateEndDateAfterStartDate(startDate, endDate, true)) {
            errors.endDate = 'End date must be on or after the start date.';
        }
    }

    // Validate first billing date is after start date
    if (firstBillingDate && startDate) {
        if (!validateEndDateAfterStartDate(startDate, firstBillingDate, true)) {
            errors.firstBillingDate = 'First billing date must be on or after the start date.';
        }
    }

    return {
        isValid: Object.keys(errors).length === 0,
        errors,
    };
}

/**
 * Formats a date for display in error messages.
 *
 * @param dateString - The date string to format
 * @returns Formatted date string or original if invalid
 */
export function formatDateForErrorMessage(dateString: string): string {
    if (!dateString) return '';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleDateString();
}

/**
 * Gets the minimum date attribute value for HTML date inputs.
 * Used to prevent selection of dates before a reference date.
 *
 * @param referenceDate - The reference date (YYYY-MM-DD format)
 * @returns Date string for min attribute or empty string if invalid
 */
export function getMinDateAttribute(referenceDate: string): string {
    if (!referenceDate) return '';

    const date = new Date(referenceDate);
    if (isNaN(date.getTime())) return '';

    return referenceDate; // Already in YYYY-MM-DD format
}

/**
 * Checks if a date string is in valid YYYY-MM-DD format.
 *
 * @param dateString - The date string to validate
 * @returns true if format is valid, false otherwise
 */
export function isValidDateFormat(dateString: string): boolean {
    if (!dateString) return false;

    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(dateString)) return false;

    const date = new Date(dateString);
    return !isNaN(date.getTime()) && dateString === date.toISOString().split('T')[0];
}
