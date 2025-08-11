/**
 * Subscription-related constants and configurations
 */

export const BILLING_CYCLES = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
    { value: 'one-time', label: 'One-time' },
] as const;

export type BillingCycleValue = typeof BILLING_CYCLES[number]['value'];
