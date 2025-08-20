import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Edit, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { getTodayString, toDateString } from '@/lib/utils';

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

interface ExistingAttachment {
    id: number;
    original_name: string;
    file_size: number;
    file_type: string;
}

interface Payment {
    id: number;
    amount: number;
    payment_date: string;
    notes?: string;
    currency: { id: number; code: string; symbol: string; name: string };
    payment_method?: { id: number; name: string };
    attachments?: ExistingAttachment[];
}

interface PaymentEditProps {
    subscription: Subscription;
    payment: Payment;
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

export default function PaymentEdit({ subscription, payment, paymentMethods, currencies }: PaymentEditProps) {
    const [existingAttachments, setExistingAttachments] = useState<ExistingAttachment[]>(payment.attachments || []);
    const [attachmentsToRemove, setAttachmentsToRemove] = useState<number[]>([]);

    // Convert payment_date to YYYY-MM-DD format for HTML date input
    // Use utility function to avoid timezone conversion issues
    const formattedDate = toDateString(payment.payment_date);

    const { data, setData, put, post, processing, errors } = useForm<PaymentForm>({
        amount: payment.amount ? payment.amount.toString() : '',
        payment_date: formattedDate || '',
        payment_method_id: payment.payment_method?.id?.toString() || '',
        currency_id: payment.currency?.id?.toString() || subscription.currency?.id?.toString() || '',
        notes: payment.notes || '',
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

    const removeExistingAttachment = (attachmentId: number) => {
        setAttachmentsToRemove((prev) => [...prev, attachmentId]);
        setExistingAttachments((prev) => prev.filter((att) => att.id !== attachmentId));
    };

    const restoreExistingAttachment = (attachmentId: number) => {
        setAttachmentsToRemove((prev) => prev.filter((id) => id !== attachmentId));
        const attachmentToRestore = payment.attachments?.find((att) => att.id === attachmentId);
        if (attachmentToRestore) {
            setExistingAttachments((prev) => [...prev, attachmentToRestore]);
        }
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handleFileOperations = async (paymentId: number) => {
        // Handle attachment removals
        for (const attachmentId of attachmentsToRemove) {
            try {
                await fetch(`/payment-attachments/${attachmentId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });
            } catch (error) {
                console.error('Failed to delete attachment:', error);
            }
        }

        // Handle new file uploads if any
        if (data.attachments.length > 0) {
            const formData = new FormData();
            data.attachments.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            try {
                await fetch(`/payment-histories/${paymentId}/attachments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: formData,
                });
            } catch (error) {
                console.error('Failed to upload new attachments:', error);
            }
        }

        // Redirect to subscription detail page
        window.location.href = `/subscriptions/${subscription.id}`;
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

        const hasNewFiles = data.attachments.length > 0;
        const hasAttachmentsToRemove = attachmentsToRemove.length > 0;
        const needsFormData = hasNewFiles;

        if (needsFormData) {
            // Use FormData for file operations
            const formData = new FormData();
            formData.append('_method', 'PUT');

            // Add form fields to FormData
            Object.keys(submitData).forEach((key) => {
                const value = submitData[key as keyof typeof submitData];
                const finalValue = value !== null && value !== undefined ? value.toString() : '';
                formData.append(key, finalValue);
            });

            // Add new files to form data
            data.attachments.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            post(`/payment-histories/${payment.id}`, {
                data: formData,
                forceFormData: true,
                onSuccess: () => {
                    if (hasAttachmentsToRemove) {
                        handleFileOperations(payment.id);
                    } else {
                        window.location.href = `/subscriptions/${subscription.id}`;
                    }
                },
                onError: (errors) => {
                    console.error('Payment update failed:', errors);
                },
            });
        } else {
            // Regular form submission without files
            put(`/payment-histories/${payment.id}`, {
                data: submitData,
                onSuccess: () => {
                    if (hasAttachmentsToRemove || hasNewFiles) {
                        handleFileOperations(payment.id);
                    } else {
                        window.location.href = `/subscriptions/${subscription.id}`;
                    }
                },
                onError: (errors) => {
                    console.error('Payment update failed:', errors);
                },
            });
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Subscriptions', href: '/subscriptions' },
        { title: subscription.name, href: `/subscriptions/${subscription.id}` },
        { title: 'Edit Payment', href: `/subscriptions/${subscription.id}/payments/${payment.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Payment - ${subscription.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Edit Payment</h1>
                    <p className="text-muted-foreground">Update the payment record for {subscription.name}</p>
                </div>

                <Card className="max-w-4xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Edit className="text-primary h-5 w-5" />
                            <div>
                                <CardTitle>Payment Details</CardTitle>
                                <CardDescription>Update the details of this payment record</CardDescription>
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
                                <Label htmlFor="payment-attachments">Add New Attachments</Label>
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

                                {/* Display existing attachments */}
                                {existingAttachments.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Existing Attachments:</p>
                                        <div className="space-y-1">
                                            {existingAttachments.map((attachment) => (
                                                <div
                                                    key={attachment.id}
                                                    className="flex items-center justify-between rounded-md border bg-blue-50 p-2"
                                                >
                                                    <div className="flex items-center space-x-2">
                                                        <span className="text-sm">{attachment.original_name}</span>
                                                        <span className="text-muted-foreground text-xs">
                                                            ({formatFileSize(attachment.file_size)})
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center space-x-1">
                                                        <a
                                                            href={`/payment-attachments/${attachment.id}/download`}
                                                            className="text-xs text-blue-600 underline hover:text-blue-800"
                                                            title={`Download ${attachment.original_name}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            Download
                                                        </a>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeExistingAttachment(attachment.id)}
                                                            disabled={processing}
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Display attachments marked for removal */}
                                {attachmentsToRemove.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium text-red-600">Attachments to be removed:</p>
                                        <div className="space-y-1">
                                            {attachmentsToRemove.map((attachmentId) => {
                                                const attachment = payment.attachments?.find((att) => att.id === attachmentId);
                                                return attachment ? (
                                                    <div
                                                        key={attachmentId}
                                                        className="flex items-center justify-between rounded-md border border-red-200 bg-red-50 p-2"
                                                    >
                                                        <div className="flex items-center space-x-2">
                                                            <span className="text-sm text-red-600 line-through">{attachment.original_name}</span>
                                                            <span className="text-xs text-red-500">(will be deleted)</span>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => restoreExistingAttachment(attachmentId)}
                                                            disabled={processing}
                                                            className="text-blue-500 hover:text-blue-700"
                                                        >
                                                            Restore
                                                        </Button>
                                                    </div>
                                                ) : null;
                                            })}
                                        </div>
                                    </div>
                                )}

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
                                    Update Payment
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
