import { Head, useForm } from '@inertiajs/react';
import { Bell, Calendar, DollarSign, FileText, LoaderCircle, Settings, Tag, Info } from 'lucide-react';
import { FormEventHandler } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import CategoryMultiSelector from '@/components/category-multi-selector';
import PaymentMethodSelector from '@/components/payment-method-selector';
import AppLayout from '@/layouts/app-layout';
import { validateEndDateAfterStartDate } from '@/lib/validation';
import { type BreadcrumbItem } from '@/types';
import { BILLING_CYCLES } from '@/constants/subscription';

// Custom InputError component for better styling
const InputError = ({ message, className = '' }: { message?: string; className?: string }) => {
    return message ? <p className={`text-destructive text-sm ${className}`}>{message}</p> : null;
};

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    type?: string;
}

interface Category {
    id: number;
    name: string;
    color?: string;
    display_color: string;
}

interface NotificationSettings {
    email_enabled: boolean;
    webhook_enabled: boolean;
    reminder_intervals: number[];
    webhook_url: string | null;
    webhook_headers: Record<string, string> | null;
}

interface Subscription {
    id: number;
    name: string;
    description?: string;
    price: number;
    currency_id: number;
    payment_method_id?: number;
    billing_cycle: string;
    billing_interval: number;
    start_date: string;
    first_billing_date: string;
    next_billing_date: string;
    end_date?: string;
    computed_status: string;
    is_overdue: boolean;
    website_url?: string;
    notes?: string;
    categories: Category[];
    notification_settings?: NotificationSettings;
}

interface UserCurrencySettings {
    default_currency_id: number | null;
    enabled_currencies: number[];
}

interface EditSubscriptionProps {
    subscription: Subscription;
    currencies: Currency[];
    paymentMethods: PaymentMethod[];
    categories: Category[];
    userCurrencySettings: UserCurrencySettings;
    defaultNotificationSettings: NotificationSettings;
    availableIntervals: Array<{ value: number; label: string }>;
}

interface SubscriptionForm {
    name: string;
    description: string;
    price: string;
    currency_id: string;
    payment_method_id: string;
    billing_cycle: string;
    billing_interval: string;
    start_date: string;
    first_billing_date: string;
    end_date: string;
    website_url: string;
    notes: string;
    category_ids: number[];
    // Notification settings
    notifications_enabled: boolean;
    use_default_notifications: boolean;
    email_enabled: boolean;
    webhook_enabled: boolean;
    reminder_intervals: number[];
}

export default function EditSubscription({
    subscription,
    currencies = [],
    paymentMethods = [],
    categories = [],
    userCurrencySettings,
    defaultNotificationSettings,
    availableIntervals = [],
}: EditSubscriptionProps) {
    // Safety check: if subscription is not provided, show error
    if (!subscription) {
        return (
            <AppLayout breadcrumbs={[]}>
                <Head title="Edit Subscription" />
                <div className="flex h-full flex-1 flex-col gap-6 p-6">
                    <div className="text-center">
                        <h1 className="text-2xl font-bold text-red-600">Error</h1>
                        <p className="text-muted-foreground">Subscription not found or failed to load.</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Subscriptions',
            href: '/subscriptions',
        },
        {
            title: subscription?.name || 'Subscription',
            href: `/subscriptions/${subscription?.id}`,
        },
        {
            title: 'Edit',
            href: `/subscriptions/${subscription?.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm<SubscriptionForm>({
        name: subscription?.name || '',
        description: subscription?.description || '',
        price: subscription?.price?.toString() || '',
        currency_id: subscription?.currency_id?.toString() || '',
        payment_method_id: subscription?.payment_method_id ? subscription.payment_method_id.toString() : 'none',
        billing_cycle: subscription?.billing_cycle || 'monthly',
        billing_interval: subscription?.billing_interval?.toString() || '1',
        start_date: subscription?.start_date || '',
        first_billing_date: subscription?.first_billing_date || '',
        end_date: subscription?.end_date || '',
        website_url: subscription?.website_url || '',
        notes: subscription?.notes || '',
        category_ids: subscription?.categories?.map((cat) => cat.id) || [],
        // Notification settings - use actual database fields, not effective settings
        notifications_enabled: subscription?.notifications_enabled ?? true,
        use_default_notifications: subscription?.use_default_notifications ?? true,
        email_enabled: subscription?.email_enabled ?? defaultNotificationSettings?.email_enabled ?? true,
        webhook_enabled: subscription?.webhook_enabled ?? defaultNotificationSettings?.webhook_enabled ?? false,
        reminder_intervals: subscription?.reminder_intervals ?? defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
    });

    // Helper function to handle interval changes
    const handleIntervalChange = (interval: number, checked: boolean) => {
        const currentIntervals = data.reminder_intervals;
        if (checked) {
            if (!currentIntervals.includes(interval)) {
                setData(
                    'reminder_intervals',
                    [...currentIntervals, interval].sort((a, b) => b - a),
                );
            }
        } else {
            setData(
                'reminder_intervals',
                currentIntervals.filter((i) => i !== interval),
            );
        }
    };

    // Helper function to reset to default settings
    const resetToDefaults = () => {
        setData({
            ...data,
            use_default_notifications: true,
            email_enabled: defaultNotificationSettings?.email_enabled ?? true,
            webhook_enabled: defaultNotificationSettings?.webhook_enabled ?? false,
            reminder_intervals: defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
        });
    };

    // Helper function to calculate next billing date preview
    const calculateNextBillingDatePreview = () => {
        if (!data.first_billing_date || !subscription) {
            return null;
        }

        try {
            const firstBillingDate = new Date(data.first_billing_date);
            const paymentCount = 0; // For preview, assume no payments made yet

            // Simple calculation based on billing cycle
            let nextDate = new Date(firstBillingDate);

            switch (subscription.billing_cycle) {
                case 'daily':
                    nextDate.setDate(nextDate.getDate() + (subscription.billing_interval * paymentCount));
                    break;
                case 'weekly':
                    nextDate.setDate(nextDate.getDate() + (7 * subscription.billing_interval * paymentCount));
                    break;
                case 'monthly':
                    nextDate.setMonth(nextDate.getMonth() + (subscription.billing_interval * paymentCount));
                    break;
                case 'quarterly':
                    nextDate.setMonth(nextDate.getMonth() + (3 * subscription.billing_interval * paymentCount));
                    break;
                case 'yearly':
                    nextDate.setFullYear(nextDate.getFullYear() + (subscription.billing_interval * paymentCount));
                    break;
                default:
                    return null;
            }

            return nextDate.toISOString().split('T')[0];
        } catch (error) {
            return null;
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Client-side validation
        if (!data.name.trim()) {
            return;
        }
        if (!data.price || parseFloat(data.price) <= 0) {
            return;
        }
        if (!data.currency_id) {
            return;
        }

        // First billing date validation removed - no constraints needed

        if (data.end_date && !validateEndDateAfterStartDate(data.start_date, data.end_date, true)) {
            return;
        }
        const { billing_cycle, billing_interval, ...updateData } = data;

        put(route('subscriptions.update', subscription?.id), {
            data: {
                ...updateData,
                payment_method_id: updateData.payment_method_id === 'none' ? '' : updateData.payment_method_id,
            },
        });
    };

    const handleCategoryChange = (categoryIds: number[]) => {
        setData('category_ids', categoryIds);
    };



    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${subscription.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Edit Subscription</h1>
                    <p className="text-muted-foreground">Update the details of your subscription</p>
                </div>

                <Card className="max-w-4xl">
                    <CardHeader>
                        <CardTitle>Edit Subscription</CardTitle>
                        <CardDescription>Update the details of your subscription</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-8">
                            {/* Basic Information Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <Settings className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Basic Information</h3>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-name">Subscription Name *</Label>
                                        <Input
                                            id="edit-subscription-name"
                                            name="name"
                                            type="text"
                                            required
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            disabled={processing}
                                            placeholder="e.g., Netflix Premium"
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-price">Price *</Label>
                                        <Input
                                            id="edit-subscription-price"
                                            name="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="999999.99"
                                            required
                                            value={data.price}
                                            onChange={(e) => setData('price', e.target.value)}
                                            disabled={processing}
                                            placeholder="0.00"
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                </div>

                                {/* Description */}
                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-description">Description</Label>
                                    <Textarea
                                        id="edit-subscription-description"
                                        name="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        disabled={processing}
                                        placeholder="Brief description of the subscription"
                                        rows={3}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                {/* Website URL */}
                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-website-url">Website URL</Label>
                                    <Input
                                        id="edit-subscription-website-url"
                                        name="website_url"
                                        type="url"
                                        value={data.website_url}
                                        onChange={(e) => setData('website_url', e.target.value)}
                                        disabled={processing}
                                        placeholder="https://example.com"
                                        autoComplete="url"
                                    />
                                    <InputError message={errors.website_url} />
                                </div>
                            </div>

                            {/* Billing Information Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <DollarSign className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Billing Information</h3>
                                </div>

                                {/* Currency and Payment Method */}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-currency">Currency *</Label>
                                        <Select value={data.currency_id} onValueChange={(value) => setData('currency_id', value)} name="currency_id">
                                            <SelectTrigger id="edit-subscription-currency">
                                                <SelectValue placeholder="Select currency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {currencies.map((currency) => (
                                                    <SelectItem key={currency.id} value={currency.id.toString()}>
                                                        {currency.symbol} {currency.code} - {currency.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.currency_id} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-payment-method">Payment Method</Label>
                                        <PaymentMethodSelector
                                            paymentMethods={paymentMethods}
                                            selectedPaymentMethodId={data.payment_method_id}
                                            onPaymentMethodChange={(paymentMethodId) => setData('payment_method_id', paymentMethodId)}
                                            onPaymentMethodCreated={() => {}}
                                            placeholder="Select payment method..."
                                            disabled={processing}
                                            error={errors.payment_method_id}
                                            allowCreate={true}
                                        />
                                    </div>
                                </div>

                                {/* Billing Cycle Information */}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-billing-cycle">Billing Cycle</Label>
                                        <Select value={data.billing_cycle} disabled name="billing_cycle">
                                            <SelectTrigger id="edit-subscription-billing-cycle" className="bg-muted">
                                                <SelectValue placeholder="Select billing cycle" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {BILLING_CYCLES.map((cycle) => (
                                                    <SelectItem key={cycle.value} value={cycle.value}>
                                                        {cycle.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs">Billing cycle cannot be changed after subscription creation</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-billing-interval">Billing Interval</Label>
                                        <Input
                                            id="edit-subscription-billing-interval"
                                            name="billing_interval"
                                            type="number"
                                            min="1"
                                            max="12"
                                            value={data.billing_interval}
                                            disabled={true}
                                            placeholder="1"
                                            autoComplete="off"
                                            className="bg-muted"
                                        />
                                        <p className="text-muted-foreground text-xs">Billing interval cannot be changed after subscription creation</p>
                                    </div>
                                </div>
                            </div>

                            {/* Dates Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <Calendar className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Dates</h3>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-start-date">Start Date *</Label>
                                        <DatePickerInput
                                            id="edit-subscription-start-date"
                                            name="start_date"
                                            value={data.start_date}
                                            onChange={(value) => setData('start_date', value)}
                                            disabled={processing}
                                            placeholder="Start date"
                                            error={!!errors.start_date}
                                        />
                                        <InputError message={errors.start_date} />
                                        <p className="text-muted-foreground text-xs">Changing the start date will recalculate billing cycles</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-first-billing-date">First Billing Date *</Label>
                                        <DatePickerInput
                                            id="edit-subscription-first-billing-date"
                                            name="first_billing_date"
                                            value={data.first_billing_date}
                                            onChange={(value) => setData('first_billing_date', value)}
                                            disabled={processing}
                                            placeholder="First billing date"
                                            error={!!errors.first_billing_date}
                                        />
                                        <InputError message={errors.first_billing_date} />
                                        <p className="text-muted-foreground text-xs">Can be before, on, or after the start date. Changes will recalculate billing cycles.</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit-subscription-end-date">End Date</Label>
                                        <DatePickerInput
                                            id="edit-subscription-end-date"
                                            name="end_date"
                                            value={data.end_date}
                                            onChange={(value) => setData('end_date', value)}
                                            disabled={processing}
                                            error={!!errors.end_date}
                                            placeholder="Select end date (optional)"
                                            min={data.start_date}
                                        />
                                        <InputError message={errors.end_date} />
                                        <p className="text-muted-foreground text-xs">
                                            Leave empty for ongoing subscriptions. End date must be on or after the start date ({data.start_date}
                                            ).
                                        </p>
                                    </div>
                                </div>

                                {/* Next Billing Date Preview */}
                                {data.first_billing_date && subscription.billing_cycle !== 'one-time' && (
                                    <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div className="flex items-center gap-2 mb-2">
                                            <Info className="h-4 w-4 text-blue-600" />
                                            <h4 className="text-sm font-medium text-blue-900">Next Billing Date Preview</h4>
                                        </div>
                                        <p className="text-sm text-blue-700">
                                            Based on your current settings, the next billing date will be: {' '}
                                            <span className="font-medium">
                                                {calculateNextBillingDatePreview() || 'Unable to calculate'}
                                            </span>
                                        </p>
                                        <p className="text-xs text-blue-600 mt-1">
                                            This preview assumes no payments have been made yet. The actual next billing date will be calculated based on your payment history.
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Categories Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <Tag className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Categories</h3>
                                </div>

                                {/* Categories */}
                                <div className="space-y-2">
                                    <Label>Categories</Label>
                                    <CategoryMultiSelector
                                        categories={categories}
                                        selectedCategoryIds={data.category_ids}
                                        onCategoryChange={handleCategoryChange}
                                        onCategoryCreated={() => {}}
                                        placeholder="Select categories for this subscription..."
                                        disabled={processing}
                                        error={errors.category_ids}
                                        allowCreate={true}
                                    />
                                </div>
                            </div>

                            {/* Notifications Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <Bell className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Notifications</h3>
                                </div>

                                {/* Master Notifications Toggle */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="edit-notifications-enabled" className="text-base">Enable notifications</Label>
                                            <p className="text-muted-foreground text-sm">Turn on to receive reminders about upcoming billing dates</p>
                                        </div>
                                        <Switch
                                            id="edit-notifications-enabled"
                                            name="notifications_enabled"
                                            checked={data.notifications_enabled}
                                            onCheckedChange={(checked) => setData({
                                                ...data,
                                                notifications_enabled: checked,
                                                email_enabled: checked,
                                                webhook_enabled: checked ? data.webhook_enabled : false
                                            })}
                                        />
                                    </div>
                                </div>

                                {data.notifications_enabled && (
                                    <>
                                        {/* Use Default Settings */}
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label htmlFor="edit-use-default-notifications" className="text-base">Use Default Settings</Label>
                                                    <p className="text-muted-foreground text-sm">Use your default notification preferences for this subscription</p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Switch
                                                        id="edit-use-default-notifications"
                                                        name="use_default_notifications"
                                                        checked={data.use_default_notifications}
                                                        onCheckedChange={(checked) => {
                                                            setData('use_default_notifications', checked);
                                                            if (checked) {
                                                                resetToDefaults();
                                                            }
                                                        }}
                                                    />
                                                    {data.use_default_notifications && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Using defaults
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Notification Channel Options - Only show when not using defaults */}
                                        {!data.use_default_notifications && (
                                            <div className="space-y-4">
                                                <div className="space-y-0.5">
                                                    <Label className="text-base">Notification Channels</Label>
                                                    <p className="text-muted-foreground text-sm">Choose how you want to receive notifications for this subscription</p>
                                                </div>

                                                {/* Email Notifications Toggle */}
                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="edit-email-enabled" className="text-sm font-medium">Email notifications</Label>
                                                        <p className="text-muted-foreground text-xs">Receive reminders via email</p>
                                                    </div>
                                                    <Switch
                                                        id="edit-email-enabled"
                                                        name="email_enabled"
                                                        checked={data.email_enabled}
                                                        onCheckedChange={(checked) => setData('email_enabled', checked)}
                                                    />
                                                </div>

                                                {/* Webhook Notifications Toggle */}
                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="edit-webhook-enabled" className="text-sm font-medium text-muted-foreground">Webhook notifications</Label>
                                                        <p className="text-muted-foreground text-xs">Send notifications to external services (coming soon)</p>
                                                    </div>
                                                    <Switch
                                                        id="edit-webhook-enabled"
                                                        name="webhook_enabled"
                                                        checked={data.webhook_enabled}
                                                        onCheckedChange={(checked) => setData('webhook_enabled', checked)}
                                                        disabled={true}
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        {/* Reminder Schedule - Always show when notifications enabled */}
                                        <div className="space-y-4">
                                            <div className="space-y-0.5">
                                                <Label className="text-base">Reminder Schedule</Label>
                                                <p className="text-muted-foreground text-sm">
                                                    {data.use_default_notifications
                                                        ? "Using your default reminder schedule"
                                                        : "Choose when to receive reminders before billing dates"
                                                    }
                                                </p>
                                            </div>

                                            {!data.use_default_notifications ? (
                                                <div className="grid grid-cols-3 gap-3">
                                                    {availableIntervals.map((interval) => (
                                                        <div key={interval.value} className="flex items-center space-x-2">
                                                            <Checkbox
                                                                id={`interval-${interval.value}`}
                                                                name="reminder_intervals"
                                                                checked={data.reminder_intervals.includes(interval.value)}
                                                                onCheckedChange={(checked) =>
                                                                    handleIntervalChange(interval.value, checked as boolean)
                                                                }
                                                            />
                                                            <Label htmlFor={`interval-${interval.value}`} className="text-sm">
                                                                {interval.label}
                                                            </Label>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="bg-muted/50 rounded-lg p-3">
                                                    <p className="mb-2 text-sm font-medium">Current Default Schedule:</p>
                                                    <div className="text-muted-foreground space-y-1 text-xs">
                                                        <p>
                                                            • Reminders: {' '}
                                                            {defaultNotificationSettings?.reminder_intervals
                                                                ?.map((i) => availableIntervals.find((ai) => ai.value === i)?.label)
                                                                .join(', ') || 'None'}
                                                        </p>
                                                    </div>
                                                </div>
                                            )}
                                            <InputError message={errors.reminder_intervals} />
                                        </div>
                                    </>
                                )}
                            </div>

                            {/* Additional Information Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Additional Information</h3>
                                </div>

                                {/* Notes */}
                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-notes">Notes</Label>
                                    <Textarea
                                        id="edit-subscription-notes"
                                        name="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        disabled={processing}
                                        placeholder="Additional notes about this subscription"
                                        rows={3}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                            </div>

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-6">
                                <Button type="submit" disabled={processing}>
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    Update Subscription
                                </Button>

                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
