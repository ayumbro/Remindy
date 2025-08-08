import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import { validateEndDateAfterStartDate } from '@/lib/validation';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

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
    end_date: string;
    website_url: string;
    notes: string;
    category_ids: number[];
    // Notification settings
    notifications_enabled: boolean;
    use_default_notifications: boolean;
    email_enabled: boolean;
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
        end_date: subscription?.end_date || '',
        website_url: subscription?.website_url || '',
        notes: subscription?.notes || '',
        category_ids: subscription?.categories?.map((cat) => cat.id) || [],
        // Notification settings
        notifications_enabled: subscription?.notification_settings ? true : false,
        use_default_notifications: !subscription?.notification_settings,
        email_enabled: subscription?.notification_settings?.email_enabled ?? defaultNotificationSettings?.email_enabled ?? true,
        reminder_intervals: subscription?.notification_settings?.reminder_intervals ??
            defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
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
            reminder_intervals: defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
        });
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

        // Validate end_date against start_date using utility function
        if (data.end_date && !validateEndDateAfterStartDate(subscription.start_date, data.end_date, true)) {
            return;
        }

        // Remove immutable fields from the data being sent to the backend
        const { billing_cycle, billing_interval, ...updateData } = data;

        put(route('subscriptions.update', subscription?.id), {
            data: {
                ...updateData,
                payment_method_id: updateData.payment_method_id === 'none' ? '' : updateData.payment_method_id,
            },
        });
    };

    const handleCategoryChange = (categoryId: number, checked: boolean) => {
        if (checked) {
            setData('category_ids', [...data.category_ids, categoryId]);
        } else {
            setData(
                'category_ids',
                data.category_ids.filter((id) => id !== categoryId),
            );
        }
    };

    const billingCycles = [
        { value: 'daily', label: 'Daily' },
        { value: 'weekly', label: 'Weekly' },
        { value: 'monthly', label: 'Monthly' },
        { value: 'quarterly', label: 'Quarterly' },
        { value: 'yearly', label: 'Yearly' },
        { value: 'one-time', label: 'One-time' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${subscription.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Edit Subscription</h1>
                    <p className="text-muted-foreground">Update the details of your subscription</p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Subscription Details</CardTitle>
                        <CardDescription>Update the details of your subscription</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Basic Information */}
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
                                    <Select value={data.payment_method_id} onValueChange={(value) => setData('payment_method_id', value)} name="payment_method_id">
                                        <SelectTrigger id="edit-subscription-payment-method">
                                            <SelectValue placeholder="Select payment method" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No payment method</SelectItem>
                                            {paymentMethods.map((method) => (
                                                <SelectItem key={method.id} value={method.id.toString()}>
                                                    {method.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.payment_method_id} />
                                </div>
                            </div>

                            {/* Billing Information */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-billing-cycle">Billing Cycle</Label>
                                    <Select value={data.billing_cycle} disabled name="billing_cycle">
                                        <SelectTrigger id="edit-subscription-billing-cycle" className="bg-muted">
                                            <SelectValue placeholder="Select billing cycle" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {billingCycles.map((cycle) => (
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

                            {/* Dates */}
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-start-date">Start Date</Label>
                                    <DatePickerInput
                                        id="edit-subscription-start-date"
                                        name="start_date"
                                        value={subscription?.start_date || ''}
                                        onChange={() => {}} // No-op since it's disabled
                                        disabled={true}
                                        placeholder="Start date"
                                        className="bg-muted"
                                    />
                                    <p className="text-muted-foreground text-xs">Start date cannot be changed after creation</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="edit-subscription-first-billing-date">First Billing Date</Label>
                                    <DatePickerInput
                                        id="edit-subscription-first-billing-date"
                                        name="first_billing_date"
                                        value={subscription?.first_billing_date || ''}
                                        onChange={() => {}} // No-op since it's disabled
                                        disabled={true}
                                        placeholder="First billing date"
                                        className="bg-muted"
                                    />
                                    <p className="text-muted-foreground text-xs">First billing date cannot be changed after creation</p>
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
                                        min={subscription.start_date}
                                    />
                                    <InputError message={errors.end_date} />
                                    <p className="text-muted-foreground text-xs">
                                        Leave empty for ongoing subscriptions. End date must be on or after the start date ({subscription.start_date}
                                        ).
                                    </p>
                                </div>
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

                            {/* Categories */}
                            {categories.length > 0 && (
                                <div className="space-y-2">
                                    <Label>Categories</Label>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {categories.map((category) => (
                                            <div key={category.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`edit-category-${category.id}`}
                                                    name={`category-${category.id}`}
                                                    checked={data.category_ids.includes(category.id)}
                                                    onCheckedChange={(checked) => handleCategoryChange(category.id, checked as boolean)}
                                                />
                                                <Label
                                                    htmlFor={`edit-category-${category.id}`}
                                                    className="flex items-center gap-2 text-sm font-normal"
                                                >
                                                    <div className="h-3 w-3 rounded-full" style={{ backgroundColor: category.display_color }} />
                                                    {category.name}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                    <InputError message={errors.category_ids} />
                                </div>
                            )}

                            {/* Notification Settings */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="edit-notifications-enabled" className="text-base">Notifications</Label>
                                        <p className="text-muted-foreground text-sm">Configure notification settings for this subscription</p>
                                    </div>
                                    <Switch
                                        id="edit-notifications-enabled"
                                        name="notifications_enabled"
                                        checked={data.notifications_enabled}
                                        onCheckedChange={(checked) => setData('notifications_enabled', checked)}
                                    />
                                </div>

                                {data.notifications_enabled && (
                                    <div className="border-muted space-y-4 border-l-2 pl-4">
                                        {/* Use Default Settings Toggle */}
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="edit-use-default-notifications" className="text-sm font-medium">Use Default Settings</Label>
                                                <p className="text-muted-foreground text-xs">Use your global notification preferences</p>
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

                                        {/* Custom Settings */}
                                        {!data.use_default_notifications && (
                                            <div className="space-y-4">
                                                {/* Email Settings */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center justify-between">
                                                        <Label htmlFor="edit-email-enabled" className="text-sm font-medium">Email Notifications</Label>
                                                        <Switch
                                                            id="edit-email-enabled"
                                                            name="email_enabled"
                                                            checked={data.email_enabled}
                                                            onCheckedChange={(checked) => setData('email_enabled', checked)}
                                                        />
                                                    </div>

                                                </div>

                                                {/* Reminder Intervals */}
                                                <div className="space-y-3">
                                                    <Label className="text-sm font-medium">Reminder Schedule</Label>
                                                    <p className="text-muted-foreground text-xs">
                                                        Choose when to receive reminders before billing dates
                                                    </p>
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
                                                </div>
                                            </div>
                                        )}

                                        {/* Default Settings Preview */}
                                        {data.use_default_notifications && (
                                            <div className="bg-muted/50 rounded-lg p-3">
                                                <p className="mb-2 text-sm font-medium">Current Default Settings:</p>
                                                <div className="text-muted-foreground space-y-1 text-xs">
                                                    <p>• Email: {defaultNotificationSettings?.email_enabled ? 'Enabled' : 'Disabled'}</p>
                                                    <p>
                                                        • Reminders:{' '}
                                                        {defaultNotificationSettings?.reminder_intervals
                                                            ?.map((i) => availableIntervals.find((ai) => ai.value === i)?.label)
                                                            .join(', ') || 'None'}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
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

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-4">
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
