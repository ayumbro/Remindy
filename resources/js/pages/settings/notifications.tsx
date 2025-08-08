import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { Bell, Clock, Eye, EyeOff, Mail, Send, Server, Webhook } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: '/settings/notifications',
    },
];

interface NotificationSettings {
    notification_time_utc: string;
    default_email_enabled: boolean;
    default_webhook_enabled: boolean;
    default_reminder_intervals: number[];
    notification_email: string;
    webhook_url: string | null;
    webhook_headers: Record<string, string> | null;
    // SMTP settings (required)
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_username: string | null;
    smtp_password?: string;
    smtp_encryption: string | null;
    smtp_from_address: string | null;
    smtp_from_name: string | null;
}

interface AvailableInterval {
    value: number;
    label: string;
}

interface Props {
    notificationSettings: NotificationSettings;
    availableIntervals: AvailableInterval[];
}

export default function NotificationSettingsPage({ notificationSettings, availableIntervals }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const [testingEmail, setTestingEmail] = useState(false);
    const [testEmailSent, setTestEmailSent] = useState(false);
    
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm<NotificationSettings>({
        notification_time_utc: notificationSettings.notification_time_utc,
        default_email_enabled: notificationSettings.default_email_enabled,
        default_webhook_enabled: notificationSettings.default_webhook_enabled,
        default_reminder_intervals: notificationSettings.default_reminder_intervals || [],
        notification_email: notificationSettings.notification_email || '',
        webhook_url: notificationSettings.webhook_url || '',
        webhook_headers: notificationSettings.webhook_headers || {},
        // SMTP settings (required)
        smtp_host: notificationSettings.smtp_host || '',
        smtp_port: notificationSettings.smtp_port || 587,
        smtp_username: notificationSettings.smtp_username || '',
        smtp_password: '',
        smtp_encryption: notificationSettings.smtp_encryption || 'none',
        smtp_from_address: notificationSettings.smtp_from_address || '',
        smtp_from_name: notificationSettings.smtp_from_name || 'Remindy',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.notifications.update'));
    };

    const handleIntervalChange = (value: number, checked: boolean) => {
        if (checked) {
            setData('default_reminder_intervals', [...data.default_reminder_intervals, value].sort((a, b) => b - a));
        } else {
            setData('default_reminder_intervals', data.default_reminder_intervals.filter(v => v !== value));
        }
    };


    const handleTestEmail = () => {
        setTestingEmail(true);
        setTestEmailSent(false);
        router.post(route('settings.notifications.test-email'), data, {
            preserveScroll: true,
            onSuccess: () => {
                setTestEmailSent(true);
                setTimeout(() => setTestEmailSent(false), 5000); // Hide after 5 seconds
            },
            onFinish: () => setTestingEmail(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall 
                        title="Default notification settings" 
                        description="Configure how you receive reminders for your subscriptions" 
                    />

                    <form onSubmit={submit} className="space-y-6">
                        {/* General Settings Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="h-5 w-5" />
                                    Notification preferences
                                </CardTitle>
                                <CardDescription>
                                    These settings apply to all new subscriptions by default
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Notification Time */}
                                <div className="grid gap-2">
                                    <Label htmlFor="notification_time" className="flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        Daily notification time (UTC)
                                    </Label>
                                    <Input
                                        id="notification_time"
                                        type="time"
                                        value={data.notification_time_utc.substring(0, 5)}
                                        onChange={(e) => setData('notification_time_utc', e.target.value + ':00')}
                                        className="max-w-xs"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Time when daily reminder emails will be sent (in UTC timezone)
                                    </p>
                                    <InputError message={errors.notification_time_utc} />
                                </div>

                                {/* Email Notifications */}
                                <div className="space-y-4">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="email_enabled"
                                            checked={data.default_email_enabled}
                                            onCheckedChange={(checked) => setData('default_email_enabled', checked as boolean)}
                                        />
                                        <Label htmlFor="email_enabled" className="flex items-center gap-2 cursor-pointer">
                                            <Mail className="h-4 w-4" />
                                            Enable email notifications by default
                                        </Label>
                                    </div>

                                    {data.default_email_enabled && (
                                        <div className="ml-6 grid gap-2">
                                            <Label htmlFor="notification_email">
                                                Notification email address <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="notification_email"
                                                type="email"
                                                placeholder="your-email@example.com"
                                                value={data.notification_email}
                                                onChange={(e) => setData('notification_email', e.target.value)}
                                                className="max-w-md"
                                                required={data.default_email_enabled}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                All subscription reminders will be sent to this email address
                                            </p>
                                            <InputError message={errors.notification_email} />
                                        </div>
                                    )}
                                </div>

                                {/* Webhook Notifications (Disabled) */}
                                <div className="space-y-4 opacity-50">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="webhook_enabled"
                                            checked={data.default_webhook_enabled}
                                            onCheckedChange={(checked) => setData('default_webhook_enabled', checked as boolean)}
                                            disabled
                                        />
                                        <Label htmlFor="webhook_enabled" className="flex items-center gap-2 cursor-not-allowed">
                                            <Webhook className="h-4 w-4" />
                                            Enable webhook notifications (Coming soon)
                                        </Label>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* SMTP Settings Card - Only show if email is enabled */}
                        {data.default_email_enabled && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Server className="h-5 w-5" />
                                        SMTP configuration <span className="text-destructive">*</span>
                                    </CardTitle>
                                    <CardDescription>
                                        SMTP settings are required for sending email notifications
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                            {/* SMTP Host & Port */}
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="smtp_host">
                                                        SMTP host <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        id="smtp_host"
                                                        placeholder="smtp.gmail.com"
                                                        value={data.smtp_host || ''}
                                                        onChange={(e) => setData('smtp_host', e.target.value)}
                                                        required={data.default_email_enabled}
                                                    />
                                                    <InputError message={errors.smtp_host} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="smtp_port">
                                                        Port <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        id="smtp_port"
                                                        type="number"
                                                        placeholder="587"
                                                        value={data.smtp_port || ''}
                                                        onChange={(e) => setData('smtp_port', parseInt(e.target.value))}
                                                        required={data.default_email_enabled}
                                                    />
                                                    <InputError message={errors.smtp_port} />
                                                </div>
                                            </div>

                                            {/* SMTP Encryption */}
                                            <div className="grid gap-2">
                                                <Label htmlFor="smtp_encryption">Encryption</Label>
                                                <Select 
                                                    value={data.smtp_encryption || 'none'}
                                                    onValueChange={(value) => setData('smtp_encryption', value === 'none' ? null : value)}
                                                >
                                                    <SelectTrigger className="max-w-md">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="tls">TLS</SelectItem>
                                                        <SelectItem value="ssl">SSL</SelectItem>
                                                        <SelectItem value="starttls">STARTTLS</SelectItem>
                                                        <SelectItem value="none">None</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors.smtp_encryption} />
                                            </div>

                                            {/* SMTP Username */}
                                            <div className="grid gap-2">
                                                <Label htmlFor="smtp_username">
                                                    Username <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="smtp_username"
                                                    placeholder="your-email@gmail.com"
                                                    value={data.smtp_username || ''}
                                                    onChange={(e) => setData('smtp_username', e.target.value)}
                                                    className="max-w-md"
                                                    required={data.default_email_enabled}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Usually your email address
                                                </p>
                                                <InputError message={errors.smtp_username} />
                                            </div>

                                            {/* SMTP Password */}
                                            <div className="grid gap-2">
                                                <Label htmlFor="smtp_password">
                                                    Password / App password
                                                </Label>
                                                <div className="flex gap-2 max-w-md">
                                                    <Input
                                                        id="smtp_password"
                                                        type={showPassword ? 'text' : 'password'}
                                                        placeholder="Leave empty to keep current password"
                                                        value={data.smtp_password || ''}
                                                        onChange={(e) => setData('smtp_password', e.target.value)}
                                                        className="flex-1"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        onClick={() => setShowPassword(!showPassword)}
                                                    >
                                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                    </Button>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    For Gmail/Yahoo, use an app-specific password. Leave empty to keep existing password.
                                                </p>
                                                <InputError message={errors.smtp_password} />
                                            </div>

                                            {/* From Address & Name */}
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="smtp_from_address">
                                                        From address <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        id="smtp_from_address"
                                                        type="email"
                                                        placeholder="noreply@example.com"
                                                        value={data.smtp_from_address || ''}
                                                        onChange={(e) => setData('smtp_from_address', e.target.value)}
                                                        required={data.default_email_enabled}
                                                    />
                                                    <InputError message={errors.smtp_from_address} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="smtp_from_name">
                                                        From name <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        id="smtp_from_name"
                                                        placeholder="Remindy"
                                                        value={data.smtp_from_name || ''}
                                                        onChange={(e) => setData('smtp_from_name', e.target.value)}
                                                        required={data.default_email_enabled}
                                                    />
                                                    <InputError message={errors.smtp_from_name} />
                                                </div>
                                            </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Reminder Intervals Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Default reminder schedule</CardTitle>
                                <CardDescription>
                                    Choose when to receive reminders before billing dates
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    {availableIntervals.map((interval) => (
                                        <div key={interval.value} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`interval-${interval.value}`}
                                                checked={data.default_reminder_intervals.includes(interval.value)}
                                                onCheckedChange={(checked) => handleIntervalChange(interval.value, checked as boolean)}
                                            />
                                            <Label 
                                                htmlFor={`interval-${interval.value}`}
                                                className="text-sm cursor-pointer"
                                            >
                                                {interval.label}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError className="mt-2" message={errors.default_reminder_intervals} />
                            </CardContent>
                        </Card>

                        {/* Submit and Test Buttons */}
                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                Save changes
                            </Button>

                            {data.default_email_enabled && (
                                <div className="flex items-center gap-2">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        onClick={handleTestEmail}
                                        disabled={testingEmail || !data.notification_email || !data.smtp_host || !data.smtp_username || !data.smtp_from_address || !data.smtp_from_name}
                                    >
                                        <Send className="h-4 w-4 mr-2" />
                                        {testingEmail ? 'Sending...' : 'Send test email'}
                                    </Button>
                                    {testEmailSent && (
                                        <Transition
                                            show={testEmailSent}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-green-600">Test email sent!</p>
                                        </Transition>
                                    )}
                                </div>
                            )}

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-muted-foreground">Saved.</p>
                            </Transition>
                        </div>
                    </form>

                </div>
            </SettingsLayout>
        </AppLayout>
    );
}