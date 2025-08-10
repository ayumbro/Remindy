import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
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

interface SubscriptionShowProps {
    subscription: Subscription;
}

export default function SubscriptionShow({ subscription }: SubscriptionShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userDateFormat = auth.user?.date_format || 'Y-m-d';
    const [paymentToDelete, setPaymentToDelete] = useState<{
        id: number;
        amount: number;
        payment_date: string;
        currency: { symbol: string; code: string };
    } | null>(null);


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
                        {shouldShowMarkPaidButton && (
                            <Button asChild>
                                <Link href={`/subscriptions/${subscription.id}/payments/create`}>Mark Paid</Link>
                            </Button>
                        )}
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
                                <p>{formatDate(subscription.next_billing_date, userDateFormat)}</p>
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
                                                        asChild
                                                    >
                                                        <Link href={`/subscriptions/${subscription.id}/payments/${payment.id}/edit`}>
                                                            <Edit className="h-3 w-3" />
                                                        </Link>
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


            </div>
        </AppLayout>
    );
}
