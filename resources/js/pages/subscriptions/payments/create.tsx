import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { DollarSign, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { getTodayString } from '@/lib/utils';

interface Subscription {
    id: number;
    name: string;
    price: number;
    currency?: { code: string; symbol: string; name: string; id: number };
    payment_method?: { name: string; id: number };
}

interface PaymentMethod {
    id: number;
    name: string;
}

interface Currency {
    id: number;
    code: string;
    symbol: string;
    name: string;
}

interface PaymentCreateProps {
    subscription: Subscription;
    paymentMethods: PaymentMethod[];
    currencies: Currency[];
}

interface PaymentForm {
    amount: string;
    payment_date: string;
    payment_method_id: string;
    currency_id: string;
    notes: string;
    attachments: File[];
}

export default function PaymentCreate({ subscription, paymentMethods, currencies }: PaymentCreateProps) {
    const { data, setData, post, processing, errors } = useForm<PaymentForm>({
        amount: subscription.price?.toString() || '',
        payment_date: getTodayString(),
        payment_method_id: subscription.payment_method?.id?.toString() || '',
        currency_id: subscription.currency?.id?.toString() || '',
        notes: '',
        attachments: [],
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        setData('attachments', files);
    };

    const removeFile = (index: number) => {
        const newFiles = data.attachments.filter((_, i) => i !== index);
        setData('attachments', newFiles);
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Prepare data for submission
        const submitData = {
            amount: data.amount,
            payment_date: data.payment_date,
            payment_method_id: data.payment_method_id === 'none' ? '' : data.payment_method_id,
            currency_id: data.currency_id,
            notes: data.notes,
        };

        const hasFiles = data.attachments.length > 0;

        if (hasFiles) {
            // Use FormData for file uploads
            const formData = new FormData();

            // Add form fields to FormData
            Object.keys(submitData).forEach((key) => {
                const value = submitData[key as keyof typeof submitData];
                const finalValue = value !== null && value !== undefined ? value.toString() : '';
                formData.append(key, finalValue);
            });

            // Add files to form data
            data.attachments.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            post(`/subscriptions/${subscription.id}/mark-paid`, {
                data: formData,
                forceFormData: true,
                onSuccess: () => {
                    // Redirect to subscription detail page
                    window.location.href = `/subscriptions/${subscription.id}`;
                },
                onError: (errors) => {
                    console.error('Payment creation failed:', errors);
                },
            });
        } else {
            // Regular form submission without files
            post(`/subscriptions/${subscription.id}/mark-paid`, {
                data: submitData,
                onSuccess: () => {
                    // Redirect to subscription detail page
                    window.location.href = `/subscriptions/${subscription.id}`;
                },
                onError: (errors) => {
                    console.error('Payment creation failed:', errors);
                },
            });
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Subscriptions', href: '/subscriptions' },
        { title: subscription.name, href: `/subscriptions/${subscription.id}` },
        { title: 'Add Payment', href: `/subscriptions/${subscription.id}/payments/create` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Add Payment - ${subscription.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Add Payment</h1>
                    <p className="text-muted-foreground">Record a new payment for {subscription.name}</p>
                </div>

                <Card className="max-w-4xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <DollarSign className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Payment Details</CardTitle>
                                <CardDescription>Enter the details of the payment you want to record</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="payment-amount">Amount *</Label>
                                <Input
                                    id="payment-amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    disabled={processing}
                                    placeholder="0.00"
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-date">Payment Date *</Label>
                                <DatePickerInput
                                    id="payment-date"
                                    name="payment_date"
                                    required
                                    max={getTodayString()}
                                    value={data.payment_date}
                                    onChange={(value) => setData('payment_date', value)}
                                    disabled={processing}
                                    error={!!errors.payment_date}
                                    placeholder="Select payment date"
                                />
                                <InputError message={errors.payment_date} />
                                <p className="text-muted-foreground text-xs">Payment date cannot be in the future</p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-method">Payment Method</Label>
                                <Select value={data.payment_method_id} onValueChange={(value) => setData('payment_method_id', value)}>
                                    <SelectTrigger id="payment-method">
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

                            <div className="space-y-2">
                                <Label htmlFor="payment-currency">Currency *</Label>
                                <Select value={data.currency_id} onValueChange={(value) => setData('currency_id', value)}>
                                    <SelectTrigger id="payment-currency">
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencies.map((currency) => (
                                            <SelectItem key={currency.id} value={currency.id.toString()}>
                                                {currency.code} ({currency.symbol}) - {currency.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.currency_id} />
                                <p className="text-muted-foreground text-xs">Select the currency for this payment</p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-notes">Notes</Label>
                                <Textarea
                                    id="payment-notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    disabled={processing}
                                    placeholder="Optional notes about this payment"
                                    rows={3}
                                    maxLength={1000}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-attachments">Attachments</Label>
                                <Input
                                    id="payment-attachments"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx"
                                    onChange={handleFileChange}
                                    disabled={processing}
                                />
                                <p className="text-muted-foreground text-xs">
                                    Upload invoices, receipts, or other payment-related documents. Max 5 files, 10MB each. Supported: PDF, Images,
                                    Word, Excel.
                                </p>
                                <InputError message={errors.attachments} />

                                {/* Display selected files */}
                                {data.attachments.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Selected Files:</p>
                                        <div className="space-y-1">
                                            {data.attachments.map((file, index) => (
                                                <div key={index} className="bg-muted flex items-center justify-between rounded-md p-2">
                                                    <div className="flex items-center space-x-2">
                                                        <span className="text-sm">{file.name}</span>
                                                        <span className="text-muted-foreground text-xs">({formatFileSize(file.size)})</span>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => removeFile(index)}
                                                        disabled={processing}
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-6">
                                <Button type="submit" disabled={processing} className="gap-2">
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Save Payment
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
