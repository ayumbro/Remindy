import { Head, useForm } from '@inertiajs/react';
import { Bell, Calendar, DollarSign, FileText, LoaderCircle, Plus, Settings, Tag } from 'lucide-react';
import { FormEventHandler } from 'react';

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
import { validateSubscriptionDates } from '@/lib/validation';
import { type BreadcrumbItem } from '@/types';
import { BILLING_CYCLES } from '@/constants/subscription';

// Custom InputError component for better styling
const InputError = ({ message, className = '' }: { message?: string; className?: string }) => {
    return message ? <p className={`text-destructive text-sm ${className}`}>{message}</p> : null;
};

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
        title: 'Create',
        href: '/subscriptions/create',
    },
];

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

interface UserCurrencySettings {
    default_currency_id: number | null;
    enabled_currencies: number[];
}

interface NotificationSettings {
    email_enabled: boolean;
    webhook_enabled: boolean;
    reminder_intervals: number[];
    notification_email: string;
    webhook_url: string | null;
}

interface AvailableInterval {
    value: number;
    label: string;
}

interface CreateSubscriptionProps {
    currencies: Currency[];
    paymentMethods: PaymentMethod[];
    categories: Category[];
    userCurrencySettings: UserCurrencySettings;
    defaultNotificationSettings: NotificationSettings;
    availableIntervals: AvailableInterval[];
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

export default function CreateSubscription({
    currencies = [],
    paymentMethods = [],
    categories = [],
    userCurrencySettings,
    defaultNotificationSettings,
    availableIntervals = [],
}: CreateSubscriptionProps) {
    // Set default currency if available
    const defaultCurrencyId = userCurrencySettings?.default_currency_id ? userCurrencySettings.default_currency_id.toString() : '';

    const { data, setData, post, processing, errors, reset } = useForm<SubscriptionForm>({
        name: '',
        description: '',
        price: '',
        currency_id: defaultCurrencyId,
        payment_method_id: 'none',
        billing_cycle: 'monthly',
        billing_interval: '1',
        start_date: new Date().toISOString().split('T')[0],
        first_billing_date: new Date().toISOString().split('T')[0],
        end_date: '',
        website_url: '',
        notes: '',
        category_ids: [],
        // Notification settings - inherit from user's default preferences
        notifications_enabled: defaultNotificationSettings?.email_enabled ?? true,
        use_default_notifications: true,
        email_enabled: defaultNotificationSettings?.email_enabled ?? true,
        webhook_enabled: defaultNotificationSettings?.webhook_enabled ?? false,
        reminder_intervals: defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
    });

    // Auto-update first billing date when start date changes
    const handleStartDateChange = (newStartDate: string) => {
        setData('start_date', newStartDate);
        setData('first_billing_date', newStartDate);

        if (data.end_date && new Date(data.end_date) < new Date(newStartDate)) {
            setData('end_date', '');
        }
    };

    const handleFirstBillingDateChange = (newFirstBillingDate: string) => {
        setData('first_billing_date', newFirstBillingDate);
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
        if (!data.billing_cycle) {
            return;
        }
        if (!data.billing_interval || parseInt(data.billing_interval) < 1) {
            return;
        }
        if (!data.start_date) {
            return;
        }
        if (!data.first_billing_date) {
            return;
        }

        const dateValidation = validateSubscriptionDates(data.start_date, data.end_date || undefined);
        if (!dateValidation.isValid) {
            return;
        }

        post(route('subscriptions.store'), {
            data: {
                ...data,
                payment_method_id: data.payment_method_id === 'none' ? '' : data.payment_method_id,
            },
            onSuccess: () => {
                reset();
            },
        });
    };

    const handleCategoryChange = (categoryIds: number[]) => {
        setData('category_ids', categoryIds);
    };

    const handleBillingCycleChange = (newBillingCycle: string) => {
        setData('billing_cycle', newBillingCycle);
        setData('billing_interval', '1');
    };

    const handleIntervalChange = (interval: number, checked: boolean) => {
        const currentIntervals = data.reminder_intervals;
        if (checked) {
            setData(
                'reminder_intervals',
                [...currentIntervals, interval].sort((a, b) => b - a),
            );
        } else {
            setData(
                'reminder_intervals',
                currentIntervals.filter((i: number) => i !== interval),
            );
        }
    };

    // Helper function to reset to default notification settings
    const resetToDefaults = () => {
        setData({
            ...data,
            use_default_notifications: true,
            email_enabled: defaultNotificationSettings?.email_enabled ?? true,
            webhook_enabled: defaultNotificationSettings?.webhook_enabled ?? false,
            reminder_intervals: defaultNotificationSettings?.reminder_intervals ?? [7, 3, 1],
        });
    };

    const handleBillingIntervalChange = (newBillingInterval: string) => {
        const interval = parseInt(newBillingInterval);
        if (interval > 0 && interval <= 12) {
            setData('billing_interval', newBillingInterval);
        }
    };



    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Subscription" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Create Subscription</h1>
                    <p className="text-muted-foreground">Add a new subscription to track your recurring payments</p>
                </div>

                <Card className="max-w-4xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Plus className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Create New Subscription</CardTitle>
                                <CardDescription>Enter the details of your new subscription</CardDescription>
                            </div>
                        </div>
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
                                        <Label htmlFor="name">Subscription Name *</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            type="text"
                                            required
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            disabled={processing}
                                            placeholder="e.g., Netflix Premium"
                                            className={errors.name ? 'border-destructive' : ''}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="price">Price *</Label>
                                        <Input
                                            id="price"
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
                                            className={errors.price ? 'border-destructive' : ''}
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        disabled={processing}
                                        placeholder="Brief description of the subscription"
                                        rows={3}
                                        className={errors.description ? 'border-destructive' : ''}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="website_url">Website URL</Label>
                                    <Input
                                        id="website_url"
                                        name="website_url"
                                        type="url"
                                        value={data.website_url}
                                        onChange={(e) => setData('website_url', e.target.value)}
                                        disabled={processing}
                                        placeholder="https://example.com"
                                        className={errors.website_url ? 'border-destructive' : ''}
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
                                        <Label htmlFor="currency_id">Currency *</Label>
                                        <Select value={data.currency_id} onValueChange={(value) => setData('currency_id', value)} name="currency_id">
                                            <SelectTrigger id="currency_id" className={errors.currency_id ? 'border-destructive' : ''}>
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
                                        <Label htmlFor="payment_method_id">Payment Method</Label>
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
                                        <Label htmlFor="billing_cycle">Billing Cycle *</Label>
                                        <Select value={data.billing_cycle} onValueChange={handleBillingCycleChange} name="billing_cycle">
                                            <SelectTrigger id="billing_cycle" className={errors.billing_cycle ? 'border-destructive' : ''}>
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
                                        <InputError message={errors.billing_cycle} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="billing_interval">Billing Interval *</Label>
                                        <Input
                                            id="billing_interval"
                                            name="billing_interval"
                                            type="number"
                                            min="1"
                                            max="12"
                                            required
                                            value={data.billing_interval}
                                            onChange={(e) => handleBillingIntervalChange(e.target.value)}
                                            disabled={processing}
                                            placeholder="1"
                                            className={errors.billing_interval ? 'border-destructive' : ''}
                                        />
                                        <InputError message={errors.billing_interval} />
                                        <p className="text-muted-foreground text-xs">e.g., "2" for every 2 {data.billing_cycle}s</p>
                                    </div>
                                </div>
                            </div>

                            {/* Dates Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <Calendar className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Dates</h3>
                                </div>
                                {/* Date Fields */}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="start_date">Start Date *</Label>
                                        <DatePickerInput
                                            id="start_date"
                                            name="start_date"
                                            required
                                            value={data.start_date}
                                            onChange={(value) => handleStartDateChange(value)}
                                            disabled={processing}
                                            error={!!errors.start_date}
                                            placeholder="Select start date"
                                        />
                                        <InputError message={errors.start_date} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="first_billing_date">First Billing Date *</Label>
                                        <DatePickerInput
                                            id="first_billing_date"
                                            name="first_billing_date"
                                            required
                                            value={data.first_billing_date}
                                            onChange={(value) => handleFirstBillingDateChange(value)}
                                            disabled={processing}
                                            error={!!errors.first_billing_date}
                                            placeholder="Select first billing date"
                                        />
                                        <InputError message={errors.first_billing_date} />
                                        <p className="text-muted-foreground text-xs">
                                            Can be before, on, or after the start date. This determines when billing cycles begin.
                                        </p>
                                    </div>
                                </div>

                                {/* End Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="end_date">End Date (Optional)</Label>
                                    <DatePickerInput
                                        id="end_date"
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
                                        Leave empty for ongoing subscriptions. End date must be on or after the start date.
                                    </p>
                                </div>
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
                                    {categories.length > 0 ? (
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
                                    ) : (
                                        <div className="text-muted-foreground py-8 text-center">
                                            <Tag className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                            <p>No categories available</p>
                                            <p className="mt-1 text-xs">Categories can be created in the settings</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Additional Information Section */}
                            <div className="space-y-6">
                                <div className="flex items-center gap-2 border-b pb-2">
                                    <FileText className="h-5 w-5 text-primary" />
                                    <h3 className="text-lg font-semibold">Additional Information</h3>
                                </div>

                                {/* Notes */}
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        disabled={processing}
                                        placeholder="Additional notes about this subscription"
                                        rows={3}
                                        className={errors.notes ? 'border-destructive' : ''}
                                    />
                                    <InputError message={errors.notes} />
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
                                            <Label htmlFor="notifications_enabled" className="text-base">Enable notifications</Label>
                                            <p className="text-muted-foreground text-sm">Turn on to receive reminders about upcoming billing dates</p>
                                        </div>
                                        <Switch
                                            id="notifications_enabled"
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
                                                    <Label htmlFor="use_default_notifications" className="text-base">Use Default Settings</Label>
                                                    <p className="text-muted-foreground text-sm">
                                                        Use your default notification preferences for this subscription
                                                    </p>
                                                </div>
                                                <Switch
                                                    id="use_default_notifications"
                                                    name="use_default_notifications"
                                                    checked={data.use_default_notifications}
                                                    onCheckedChange={(checked) => {
                                                        setData('use_default_notifications', checked);
                                                        if (checked) {
                                                            resetToDefaults();
                                                        }
                                                    }}
                                                />
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
                                                        <Label htmlFor="email_enabled" className="text-sm font-medium">Email notifications</Label>
                                                        <p className="text-muted-foreground text-xs">Receive reminders via email</p>
                                                    </div>
                                                    <Switch
                                                        id="email_enabled"
                                                        name="email_enabled"
                                                        checked={data.email_enabled}
                                                        onCheckedChange={(checked) => setData('email_enabled', checked)}
                                                    />
                                                </div>

                                                {/* Webhook Notifications Toggle */}
                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="webhook_enabled" className="text-sm font-medium text-muted-foreground">Webhook notifications</Label>
                                                        <p className="text-muted-foreground text-xs">Send notifications to external services (coming soon)</p>
                                                    </div>
                                                    <Switch
                                                        id="webhook_enabled"
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
                                                                name={`reminder_intervals`}
                                                                checked={data.reminder_intervals.includes(interval.value)}
                                                                onCheckedChange={(checked) =>
                                                                    handleIntervalChange(interval.value, checked as boolean)
                                                                }
                                                            />
                                                            <Label htmlFor={`interval-${interval.value}`}>{interval.label}</Label>
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

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-6">
                                <Button type="submit" disabled={processing} className="gap-2">
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Create Subscription
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
