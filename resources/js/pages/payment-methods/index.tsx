import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, CreditCard, Edit, Eye, Plus, Power, PowerOff, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Payment Methods',
        href: '/payment-methods',
    },
];

interface PaymentMethod {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    subscriptions_count?: number;
    payment_histories_count?: number;
    display_name: string;
    image_url?: string;
}

interface PaymentMethodsIndexProps {
    paymentMethods: PaymentMethod[];
    paymentMethodTypes: Record<string, string>;
}

export default function PaymentMethodsIndex({ paymentMethods = [], paymentMethodTypes }: PaymentMethodsIndexProps) {
    const { errors } = usePage().props as any;
    const [paymentMethodToDelete, setPaymentMethodToDelete] = useState<PaymentMethod | null>(null);
    const [paymentMethodToToggle, setPaymentMethodToToggle] = useState<PaymentMethod | null>(null);
    const [deletionError, setDeletionError] = useState<string | null>(null);

    const handleDelete = (paymentMethod: PaymentMethod) => {
        // Clear any previous error
        setDeletionError(null);

        router.delete(route('payment-methods.destroy', paymentMethod.id), {
            onSuccess: () => {
                setPaymentMethodToDelete(null);
                setDeletionError(null);
            },
            onError: (errors) => {
                // Handle validation errors from the backend
                if (errors.payment_method) {
                    setDeletionError(errors.payment_method);
                } else {
                    setDeletionError('An error occurred while trying to delete the payment method.');
                }
            },
        });
    };

    const handleToggleStatus = (paymentMethod: PaymentMethod) => {
        router.patch(
            route('payment-methods.toggle-status', paymentMethod.id),
            {},
            {
                onSuccess: () => {
                    setPaymentMethodToToggle(null);
                },
            },
        );
    };

    const closeDeleteDialog = () => {
        setPaymentMethodToDelete(null);
        setDeletionError(null);
    };

    const closeToggleDialog = () => {
        setPaymentMethodToToggle(null);
    };

    const getTotalUsageCount = (paymentMethod: PaymentMethod) => {
        return (paymentMethod.subscriptions_count || 0) + (paymentMethod.payment_histories_count || 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Methods" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Payment Methods</h1>
                        <p className="text-muted-foreground">Manage your payment methods for subscriptions</p>
                    </div>
                    <Link href={route('payment-methods.create')}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Payment Method
                        </Button>
                    </Link>
                </div>

                {paymentMethods.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <CreditCard className="text-muted-foreground mb-4 h-12 w-12" />
                            <h3 className="mb-2 text-lg font-semibold">No payment methods yet</h3>
                            <p className="text-muted-foreground mb-4 text-center">Add your first payment method to start tracking subscriptions</p>
                            <Link href={route('payment-methods.create')}>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Payment Method
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {paymentMethods.map((paymentMethod) => (
                            <Card
                                key={paymentMethod.id}
                                className={`theme-transition hover-lift ${
                                    !paymentMethod.is_active ? 'opacity-60' : ''
                                } ${paymentMethod.is_active ? 'border-l-primary border-l-4' : 'border-l-muted border-l-4'}`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="relative">
                                                {paymentMethod.image_url ? (
                                                    <img
                                                        src={paymentMethod.image_url}
                                                        alt={paymentMethod.name}
                                                        className="shadow-enhanced h-10 w-10 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <div className="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-lg">
                                                        <CreditCard className="text-primary h-5 w-5" />
                                                    </div>
                                                )}
                                                {paymentMethod.is_active && (
                                                    <div className="border-background absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 bg-green-500"></div>
                                                )}
                                            </div>
                                            <div>
                                                <CardTitle className="flex items-center gap-2 text-base">{paymentMethod.name}</CardTitle>
                                                {paymentMethod.description && (
                                                    <CardDescription className="text-sm">{paymentMethod.description}</CardDescription>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex gap-1">
                                            <Badge
                                                variant={paymentMethod.is_active ? 'default' : 'secondary'}
                                                className={
                                                    paymentMethod.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ''
                                                }
                                            >
                                                {paymentMethod.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    <div className="flex items-center justify-between">
                                        <div className="flex gap-2">
                                            <Link href={route('payment-methods.show', paymentMethod.id)}>
                                                <Button size="sm" variant="outline">
                                                    <Eye className="h-3 w-3" />
                                                </Button>
                                            </Link>
                                            <Link href={route('payment-methods.edit', paymentMethod.id)}>
                                                <Button size="sm" variant="outline">
                                                    <Edit className="h-3 w-3" />
                                                </Button>
                                            </Link>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className={
                                                    paymentMethod.is_active
                                                        ? 'text-amber-600 hover:text-amber-700'
                                                        : 'text-green-600 hover:text-green-700'
                                                }
                                                onClick={() => setPaymentMethodToToggle(paymentMethod)}
                                                title={paymentMethod.is_active ? 'Disable payment method' : 'Enable payment method'}
                                            >
                                                {paymentMethod.is_active ? <PowerOff className="h-3 w-3" /> : <Power className="h-3 w-3" />}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="text-red-600 hover:text-red-700"
                                                onClick={() => setPaymentMethodToDelete(paymentMethod)}
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-muted-foreground text-xs">
                                                <div>{paymentMethod.subscriptions_count || 0} subscriptions</div>
                                                <div>{paymentMethod.payment_histories_count || 0} payments</div>
                                                <div className="font-medium">{getTotalUsageCount(paymentMethod)} total usage</div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
                <Dialog open={!!paymentMethodToDelete} onOpenChange={closeDeleteDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Payment Method</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{paymentMethodToDelete?.name}"? This action cannot be undone.
                                {paymentMethodToDelete && getTotalUsageCount(paymentMethodToDelete) > 0 && (
                                    <span className="mt-2 block text-amber-600">
                                        Warning: This payment method is currently used by {paymentMethodToDelete.subscriptions_count || 0}{' '}
                                        subscription(s) and has {paymentMethodToDelete.payment_histories_count || 0} payment history record(s).
                                    </span>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        {/* Error Alert */}
                        {deletionError && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    {deletionError}
                                    <span className="mt-2 block">
                                        To delete this payment method, please first remove or reassign all subscriptions that use it, or consider
                                        disabling it instead to preserve historical data.
                                    </span>
                                </AlertDescription>
                            </Alert>
                        )}

                        <DialogFooter>
                            <Button variant="outline" onClick={closeDeleteDialog}>
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => paymentMethodToDelete && handleDelete(paymentMethodToDelete)}
                                disabled={paymentMethodToDelete && getTotalUsageCount(paymentMethodToDelete) > 0}
                                title={
                                    paymentMethodToDelete && getTotalUsageCount(paymentMethodToDelete) > 0
                                        ? 'Cannot delete payment method with active usage'
                                        : ''
                                }
                            >
                                {paymentMethodToDelete && getTotalUsageCount(paymentMethodToDelete) > 0 ? 'Cannot Delete' : 'Delete Payment Method'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Disable/Enable Confirmation Dialog */}
                <Dialog open={!!paymentMethodToToggle} onOpenChange={closeToggleDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{paymentMethodToToggle?.is_active ? 'Disable' : 'Enable'} Payment Method</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to {paymentMethodToToggle?.is_active ? 'disable' : 'enable'} "{paymentMethodToToggle?.name}"?
                                {paymentMethodToToggle?.is_active && paymentMethodToToggle.subscriptions_count > 0 && (
                                    <span className="mt-2 block text-amber-600">
                                        This payment method is currently used by {paymentMethodToToggle.subscriptions_count} subscription(s).
                                        Disabling it will prevent it from being used for new payments, but existing subscriptions will keep their
                                        reference.
                                    </span>
                                )}
                                {!paymentMethodToToggle?.is_active && (
                                    <span className="mt-2 block text-green-600">
                                        Enabling this payment method will make it available for use in new subscriptions and payments.
                                    </span>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter>
                            <Button variant="outline" onClick={closeToggleDialog}>
                                Cancel
                            </Button>
                            <Button
                                variant={paymentMethodToToggle?.is_active ? 'secondary' : 'default'}
                                onClick={() => paymentMethodToToggle && handleToggleStatus(paymentMethodToToggle)}
                            >
                                {paymentMethodToToggle?.is_active ? 'Disable' : 'Enable'} Payment Method
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
