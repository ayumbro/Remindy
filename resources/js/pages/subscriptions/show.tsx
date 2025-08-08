import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePickerInput } from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Edit, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Subscription {
    id: number;
    name: string;
    description?: string;
    price: number;
    currency: { code: string; symbol: string; name: string };
    payment_method?: { name: string };
    billing_cycle: string;
    billing_interval: number;
    start_date: string;
    next_billing_date: string | null;
    end_date?: string;
    computed_status: string;
    is_overdue: boolean;
    website_url?: string;
    notes?: string;
    categories: Array<{ id: number; name: string; color?: string; display_color: string }>;
    payment_histories: Array<{
        id: number;
        amount: number;
        payment_date: string;
        status: string;
        notes?: string;
        currency: { code: string; symbol: string };
        payment_method?: { name: string };
        attachments?: Array<{
            id: number;
            original_name: string;
            file_path: string;
            file_type: string;
            file_size: number;
            download_url: string;
        }>;
    }>;
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

interface SubscriptionShowProps {
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

interface ExistingAttachment {
    id: number;
    original_name: string;
    file_size: number;
    file_type: string;
}

export default function SubscriptionShow({ subscription, paymentMethods, currencies }: SubscriptionShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userDateFormat = auth.user?.date_format || 'Y-m-d';
    const [paymentToDelete, setPaymentToDelete] = useState<{
        id: number;
        amount: number;
        payment_date: string;
        currency: { symbol: string; code: string };
    } | null>(null);
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const [editingPayment, setEditingPayment] = useState<any>(null);
    const [existingAttachments, setExistingAttachments] = useState<ExistingAttachment[]>([]);
    const [attachmentsToRemove, setAttachmentsToRemove] = useState<number[]>([]);

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
    ];

    const formatCurrency = (amount: number | string, currency: { symbol: string; code: string }) => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        const safeAmount = isNaN(numAmount) ? 0 : numAmount;
        return `${currency.symbol}${safeAmount.toFixed(2)} ${currency.code}`;
    };

    const getBillingCycleText = (cycle: string, interval: number) => {
        const cycleText = interval > 1 ? `${interval} ${cycle}s` : cycle;
        return `Every ${cycleText}`;
    };

    const getStatusColor = (subscription: Subscription) => {
        // Ended subscriptions always show as outline (gray), never overdue
        if (subscription.computed_status === 'ended') {
            return 'outline';
        }
        // Only active subscriptions can be overdue
        if (subscription.computed_status === 'active' && subscription.is_overdue) {
            return 'destructive'; // Red for overdue
        }
        return 'default'; // Green for active
    };

    const getStatusText = (subscription: Subscription) => {
        // Ended subscriptions always show as "ended", never "overdue"
        if (subscription.computed_status === 'ended') {
            return 'ended';
        }
        // Only active subscriptions can show as overdue
        if (subscription.computed_status === 'active' && subscription.is_overdue) {
            return 'overdue';
        }
        return 'active';
    };

    const handleDeletePayment = (payment: { id: number; amount: number; payment_date: string; currency: { symbol: string; code: string } }) => {
        router.delete(`/payment-histories/${payment.id}`, {
            onSuccess: () => {
                setPaymentToDelete(null);
            },
        });
    };

    // Check if subscription has ended (current date is past end_date)
    // Only hide the button if there's an end_date and it's in the past
    const isSubscriptionEnded = subscription.end_date ? new Date() > new Date(subscription.end_date) : false;

    // Show Mark Paid button only if subscription hasn't ended
    const shouldShowMarkPaidButton = !isSubscriptionEnded;

    // Form for payment modal
    const { data, setData, post, put, processing, errors, reset } = useForm<PaymentForm>({
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        payment_method_id: '',
        currency_id: subscription.currency_id.toString(), // Default to subscription's currency
        notes: '',
        attachments: [],
    });

    const openMarkPaidModal = () => {
        setEditingPayment(null);
        setExistingAttachments([]);
        setAttachmentsToRemove([]);
        reset();
        setData({
            amount: subscription.price.toString(),
            payment_date: new Date().toISOString().split('T')[0],
            payment_method_id: subscription.payment_method?.id?.toString() || '',
            currency_id: subscription.currency_id.toString(), // Default to subscription's currency
            notes: '',
            attachments: [],
        });
        setIsPaymentModalOpen(true);
    };

    const openEditPaymentModal = (payment: any) => {
        setEditingPayment(payment);

        // Convert payment_date to YYYY-MM-DD format for HTML date input
        let formattedDate = payment.payment_date;
        if (payment.payment_date) {
            // If payment_date is already in YYYY-MM-DD format, use it directly
            // Otherwise, try to parse and format it
            const date = new Date(payment.payment_date);
            if (!isNaN(date.getTime())) {
                formattedDate = date.toISOString().split('T')[0];
            }
        }

        // Load existing attachments
        const existingAttachmentsList = payment.attachments || [];
        setExistingAttachments(existingAttachmentsList);
        setAttachmentsToRemove([]);

        // Ensure all fields have valid values
        const paymentData = {
            amount: payment.amount ? payment.amount.toString() : '',
            payment_date: formattedDate || '',
            payment_method_id: payment.payment_method?.id?.toString() || '',
            currency_id: payment.currency?.id?.toString() || subscription.currency_id.toString(),
            notes: payment.notes || '',
            attachments: [], // This will hold new files to upload
        };

        setData(paymentData);
        setIsPaymentModalOpen(true);
    };

    const closePaymentModal = () => {
        setIsPaymentModalOpen(false);
        setEditingPayment(null);
        setExistingAttachments([]);
        setAttachmentsToRemove([]);
        reset();
        // Ensure currency is reset to subscription's currency
        setData('currency_id', subscription.currency_id.toString());
    };

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
        // Find the attachment in the original payment data and restore it
        if (editingPayment && editingPayment.attachments) {
            const attachmentToRestore = editingPayment.attachments.find((att: any) => att.id === attachmentId);
            if (attachmentToRestore) {
                setExistingAttachments((prev) => [...prev, attachmentToRestore]);
            }
        }
    };

    const handleFileOperations = async (paymentHistoryId: number) => {
        try {
            // Handle attachment removals first
            if (attachmentsToRemove.length > 0) {
                for (const attachmentId of attachmentsToRemove) {
                    await new Promise((resolve, reject) => {
                        router.delete(`/payment-attachments/${attachmentId}`, {
                            preserveState: true,
                            preserveScroll: true,
                            onSuccess: () => resolve(true),
                            onError: (errors) => reject(errors),
                        });
                    });
                }
            }

            // Handle new file uploads
            if (data.attachments.length > 0) {
                const formData = new FormData();
                data.attachments.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });

                await new Promise((resolve, reject) => {
                    router.post(`/payment-histories/${paymentHistoryId}/attachments`, formData, {
                        forceFormData: true,
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => resolve(true),
                        onError: (errors) => reject(errors),
                    });
                });
            }

            // Close modal after all operations complete
            closePaymentModal();
            // Refresh page to show updated data
            setTimeout(() => window.location.reload(), 100);
        } catch (error) {
            console.error('File operations failed:', error);
            alert('Payment updated, but there was an issue with file operations. Please try again.');
        }
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handlePaymentSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Client-side validation
        if (!data.amount || data.amount.trim() === '' || parseFloat(data.amount) <= 0) {
            alert('Please enter a valid amount greater than 0.');
            return;
        }
        if (!data.payment_date || data.payment_date.trim() === '') {
            alert('Please select a payment date.');
            return;
        }

        // Validate payment date is not in the future
        const selectedDate = new Date(data.payment_date);
        const today = new Date();
        today.setHours(23, 59, 59, 999); // Set to end of today

        if (selectedDate > today) {
            alert('Payment date cannot be in the future.');
            return;
        }

        // Prepare data for submission
        const submitData = {
            amount: data.amount,
            payment_date: data.payment_date,
            payment_method_id: data.payment_method_id === 'none' ? '' : data.payment_method_id,
            currency_id: data.currency_id,
            notes: data.notes,
        };

        // Check if we need to use FormData (for file uploads only)
        const hasNewFiles = data.attachments.length > 0;
        const hasAttachmentsToRemove = editingPayment && attachmentsToRemove.length > 0;
        const needsFormData = hasNewFiles; // Only use FormData for new files, not for removals

        if (needsFormData) {
            // Use FormData for file operations (both new payments and edits)
            const formData = new FormData();

            // Add HTTP method for Laravel (required for PUT with FormData)
            if (editingPayment) {
                formData.append('_method', 'PUT');
            }

            // Add form fields to FormData, ensuring no null/undefined values
            Object.keys(submitData).forEach((key) => {
                const value = submitData[key as keyof typeof submitData];
                const finalValue = value !== null && value !== undefined ? value.toString() : '';
                formData.append(key, finalValue);
            });

            // Add new files to form data
            data.attachments.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            // Note: Attachment removals are handled separately via individual DELETE requests

            if (editingPayment) {
                // Update existing payment with FormData (use POST with _method=PUT)
                post(`/payment-histories/${editingPayment.id}`, {
                    data: formData,
                    forceFormData: true,
                    onSuccess: () => {
                        // Handle file operations (removals) after successful payment update
                        if (hasAttachmentsToRemove) {
                            handleFileOperations(editingPayment.id);
                        } else {
                            closePaymentModal();
                            setTimeout(() => window.location.reload(), 100);
                        }
                    },
                    onError: (errors) => {
                        console.error('Payment update failed:', errors);
                        // Show specific error messages
                        if (errors.payment_date) {
                            alert(`Payment date error: ${errors.payment_date}`);
                        } else if (errors.amount) {
                            alert(`Amount error: ${errors.amount}`);
                        } else {
                            alert('Failed to update payment. Please check your input and try again.');
                        }
                    },
                });
            } else {
                // Create new payment with FormData
                post(`/subscriptions/${subscription.id}/mark-paid`, {
                    data: formData,
                    forceFormData: true,
                    onSuccess: () => {
                        closePaymentModal();
                        // Force page refresh to ensure updated data is displayed
                        window.location.reload();
                    },
                    onError: (errors) => {
                        console.error('Payment creation failed:', errors);
                        // Show specific error messages
                        if (errors.payment_date) {
                            alert(`Payment date error: ${errors.payment_date}`);
                        } else if (errors.amount) {
                            alert(`Amount error: ${errors.amount}`);
                        } else {
                            alert('Failed to create payment. Please check your input and try again.');
                        }
                    },
                });
            }
        } else {
            // No files involved, use regular form submission
            if (editingPayment) {
                // Note: Attachment removals are handled separately via individual DELETE requests

                // Update existing payment without files
                put(`/payment-histories/${editingPayment.id}`, {
                    data: submitData,
                    onSuccess: () => {
                        // Handle file operations (removals and additions) after successful payment update
                        if (hasAttachmentsToRemove || hasNewFiles) {
                            handleFileOperations(editingPayment.id);
                        } else {
                            closePaymentModal();
                            setTimeout(() => window.location.reload(), 100);
                        }
                    },
                    onError: (errors) => {
                        console.error('Payment update failed:', errors);
                        // Show specific error messages
                        if (errors.payment_date) {
                            alert(`Payment date error: ${errors.payment_date}`);
                        } else if (errors.amount) {
                            alert(`Amount error: ${errors.amount}`);
                        } else {
                            alert('Failed to update payment. Please check your input and try again.');
                        }
                    },
                });
            } else {
                // Create new payment without files
                post(`/subscriptions/${subscription.id}/mark-paid`, {
                    data: submitData,
                    onSuccess: () => {
                        closePaymentModal();
                        // Force page refresh to ensure updated data is displayed
                        window.location.reload();
                    },
                    onError: (errors) => {
                        console.error('Payment creation failed:', errors);
                        // Show specific error messages
                        if (errors.payment_date) {
                            alert(`Payment date error: ${errors.payment_date}`);
                        } else if (errors.amount) {
                            alert(`Amount error: ${errors.amount}`);
                        } else {
                            alert('Failed to create payment. Please check your input and try again.');
                        }
                    },
                });
            }
        }
    };

    if (!subscription) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Subscription Not Found" />
                <div className="flex h-full flex-1 flex-col gap-6 p-6">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-muted-foreground mb-4">Subscription not found</p>
                            <Button asChild>
                                <Link href="/subscriptions">Back to Subscriptions</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${subscription.name} - Subscription`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-3">
                            <h1 className="text-3xl font-bold">{subscription.name}</h1>
                            <Badge variant={getStatusColor(subscription)}>{getStatusText(subscription)}</Badge>
                        </div>
                        {subscription.description && <p className="text-muted-foreground">{subscription.description}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/subscriptions/${subscription.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        {shouldShowMarkPaidButton && <Button onClick={openMarkPaidModal}>Mark Paid</Button>}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Subscription Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Subscription Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Price</label>
                                <p className="text-2xl font-bold">{formatCurrency(subscription.price, subscription.currency)}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Billing Cycle</label>
                                <p>{getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Start Date</label>
                                <p>{formatDate(subscription.start_date, userDateFormat)}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">First Billing Date</label>
                                <p>{formatDate(subscription.first_billing_date, userDateFormat)}</p>
                            </div>

                            {/* Only show next billing date for active subscriptions */}
                            {subscription.computed_status !== 'ended' && subscription.next_billing_date && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Next Billing Date</label>
                                    <p>{formatDate(subscription.next_billing_date, userDateFormat)}</p>
                                </div>
                            )}

                            {subscription.end_date && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">End Date</label>
                                    <p>{formatDate(subscription.end_date, userDateFormat)}</p>
                                </div>
                            )}

                            {subscription.payment_method && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Payment Method</label>
                                    <p>{subscription.payment_method.name}</p>
                                </div>
                            )}

                            {subscription.website_url && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Website</label>
                                    <a
                                        href={subscription.website_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:underline"
                                    >
                                        {subscription.website_url}
                                    </a>
                                </div>
                            )}

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Categories</label>
                                <div className="mt-1 flex gap-1">
                                    {(subscription.categories || []).map((category) => (
                                        <Badge key={category.id} variant="outline" className="flex items-center gap-1 text-xs">
                                            <div className="h-2 w-2 flex-shrink-0 rounded-full" style={{ backgroundColor: category.display_color }} />
                                            {category.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            {subscription.notes && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Notes</label>
                                    <p className="text-sm">{subscription.notes}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Payment History */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment History</CardTitle>
                            <CardDescription>Recent payment records</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {(subscription.payment_histories || []).length === 0 ? (
                                    <p className="text-muted-foreground py-4 text-center">No payment history</p>
                                ) : (
                                    (subscription.payment_histories || []).map((payment, index) => (
                                        <div key={payment.id} className="flex items-center justify-between rounded-lg border p-3">
                                            <div className="flex-1">
                                                <p className="font-medium">{formatDate(payment.payment_date, userDateFormat)}</p>
                                                <p className="text-muted-foreground text-sm">{payment.payment_method?.name || 'Unknown method'}</p>
                                                {payment.notes && <p className="text-muted-foreground text-xs">{payment.notes}</p>}
                                                {payment.attachments && payment.attachments.length > 0 && (
                                                    <div className="mt-2">
                                                        <p className="text-muted-foreground mb-1 text-xs font-medium">
                                                            Attachments ({payment.attachments.length}):
                                                        </p>
                                                        <div className="flex flex-wrap gap-1">
                                                            {payment.attachments.map((attachment: any) => (
                                                                <div key={attachment.id} className="flex items-center gap-1">
                                                                    <a
                                                                        href={`/payment-attachments/${attachment.id}/download`}
                                                                        className="text-xs text-blue-600 underline hover:text-blue-800"
                                                                        title={`Download ${attachment.original_name}`}
                                                                    >
                                                                        {attachment.original_name}
                                                                    </a>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        className="h-4 w-4 p-0 text-red-500 hover:text-red-700"
                                                                        onClick={() => {
                                                                            if (confirm('Are you sure you want to delete this attachment?')) {
                                                                                router.delete(`/payment-attachments/${attachment.id}`);
                                                                            }
                                                                        }}
                                                                        title="Delete attachment"
                                                                    >
                                                                        <Trash2 className="h-3 w-3" />
                                                                    </Button>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-3 text-right">
                                                <div>
                                                    <p className="font-medium">{formatCurrency(payment.amount, payment.currency)}</p>
                                                    <Badge variant={payment.status === 'paid' ? 'default' : 'secondary'} className="text-xs">
                                                        {payment.status}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    {/* Edit button for all payments */}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-blue-600 hover:text-blue-700"
                                                        onClick={() => openEditPaymentModal(payment)}
                                                    >
                                                        <Edit className="h-3 w-3" />
                                                    </Button>
                                                    {/* Only show delete button for the most recent payment (index 0) */}
                                                    {index === 0 && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600 hover:text-red-700"
                                                            onClick={() => setPaymentToDelete(payment)}
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Delete Payment Confirmation Dialog */}
                <Dialog open={!!paymentToDelete} onOpenChange={() => setPaymentToDelete(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Payment Record</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this payment record of{' '}
                                {paymentToDelete && formatCurrency(paymentToDelete.amount, paymentToDelete.currency)} from{' '}
                                {paymentToDelete && formatDate(paymentToDelete.payment_date, userDateFormat)}?
                                <br />
                                <br />
                                This action cannot be undone and will recalculate the next billing date.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setPaymentToDelete(null)}>
                                Cancel
                            </Button>
                            <Button variant="destructive" onClick={() => paymentToDelete && handleDeletePayment(paymentToDelete)}>
                                Delete Payment
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Payment Modal */}
                <Dialog open={isPaymentModalOpen} onOpenChange={setIsPaymentModalOpen}>
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle>{editingPayment ? 'Edit Payment Record' : 'Mark as Paid'}</DialogTitle>
                            <DialogDescription>
                                {editingPayment ? 'Update the payment record details below.' : 'Record a new payment for this subscription.'}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handlePaymentSubmit} className="space-y-4">
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
                                    max={new Date().toISOString().split('T')[0]}
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
                                <Label htmlFor="payment-attachments">{editingPayment ? 'Add New Attachments' : 'Attachments'}</Label>
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

                                {/* Display existing attachments (for edit mode) */}
                                {editingPayment && existingAttachments.length > 0 && (
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
                                {editingPayment && attachmentsToRemove.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium text-red-600">Attachments to be removed:</p>
                                        <div className="space-y-1">
                                            {attachmentsToRemove.map((attachmentId) => {
                                                const attachment = editingPayment.attachments?.find((att: any) => att.id === attachmentId);
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

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={closePaymentModal}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {editingPayment ? 'Update Payment' : 'Save Payment'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
