import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Edit, ExternalLink, LoaderCircle, X } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';

interface Subscription {
    id: number;
    name: string;
    price: number | string | null;
    currency: {
        code: string;
        symbol: string;
    } | null;
    status: string;
    next_billing_date: string | null;
}

interface PaymentMethod {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    display_name: string;
    subscriptions: Subscription[];
    image_url?: string;
}

interface ShowPaymentMethodProps {
    paymentMethod: PaymentMethod;
    paymentMethodTypes: Record<string, string>;
}

export default function ShowPaymentMethod({ paymentMethod, paymentMethodTypes }: ShowPaymentMethodProps) {
    const [showDeleteImageDialog, setShowDeleteImageDialog] = useState(false);
    const [isRemovingImage, setIsRemovingImage] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Payment Methods',
            href: '/payment-methods',
        },
        {
            title: paymentMethod.name,
            href: `/payment-methods/${paymentMethod.id}`,
        },
    ];

    const formatCurrency = (amount: number | string | null | undefined, currency: { symbol: string; code: string } | null | undefined) => {
        // Convert amount to number and validate
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        const safeAmount = typeof numAmount === 'number' && !isNaN(numAmount) ? numAmount : 0;

        if (!currency) {
            return `$${safeAmount.toFixed(2)} USD`; // Default fallback
        }
        return `${currency.symbol}${safeAmount.toFixed(2)} ${currency.code}`;
    };

    const removeImage = () => {
        setShowDeleteImageDialog(true);
    };

    const confirmRemoveImage = () => {
        setIsRemovingImage(true);
        setErrorMessage(null);

        // Use router.put to update the payment method with remove_image flag
        router.put(
            route('payment-methods.update', paymentMethod.id),
            {
                name: paymentMethod.name,
                description: paymentMethod.description || '',
                is_active: paymentMethod.is_active,
                remove_image: true,
            },
            {
                onSuccess: () => {
                    setShowDeleteImageDialog(false);
                    setIsRemovingImage(false);
                },
                onError: (errors) => {
                    setIsRemovingImage(false);
                    const imageError = errors.image;
                    if (imageError) {
                        setErrorMessage(Array.isArray(imageError) ? imageError[0] : imageError);
                    } else {
                        setErrorMessage('Failed to remove image. Please try again.');
                    }
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={paymentMethod.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {paymentMethod.image_url ? (
                            <div className="group relative">
                                <img src={paymentMethod.image_url} alt={paymentMethod.name} className="h-12 w-12 rounded object-cover" />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    className="absolute -top-1 -right-1 h-5 w-5 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                    onClick={removeImage}
                                    title="Remove image"
                                >
                                    <X className="h-3 w-3" />
                                </Button>
                            </div>
                        ) : (
                            <CreditCard className="h-5 w-5" />
                        )}
                        <div>
                            <h1 className="text-3xl font-bold">{paymentMethod.name}</h1>
                            {paymentMethod.description && <p className="text-muted-foreground">{paymentMethod.description}</p>}
                        </div>
                        <div className="flex gap-2">{!paymentMethod.is_active && <Badge variant="secondary">Inactive</Badge>}</div>
                    </div>
                    <Link href={route('payment-methods.edit', paymentMethod.id)}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Payment Method Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Method Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Name</label>
                                <p className="text-sm">{paymentMethod.name}</p>
                            </div>

                            {paymentMethod.description && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Description</label>
                                    <p className="text-sm">{paymentMethod.description}</p>
                                </div>
                            )}

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Status</label>
                                <p className="text-sm">{paymentMethod.is_active ? 'Active' : 'Inactive'}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Created</label>
                                <p className="text-sm">{formatDate(paymentMethod.created_at)}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Last Updated</label>
                                <p className="text-sm">{formatDate(paymentMethod.updated_at)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Associated Subscriptions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Associated Subscriptions</CardTitle>
                            <CardDescription>Subscriptions using this payment method</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {paymentMethod.subscriptions.length === 0 ? (
                                <div className="py-8 text-center">
                                    <p className="text-muted-foreground">No subscriptions are using this payment method</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {paymentMethod.subscriptions.map((subscription, index) => (
                                        <div key={subscription.id}>
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="font-medium">{subscription.name}</h4>
                                                    <p className="text-muted-foreground text-sm">
                                                        {formatCurrency(subscription.price, subscription.currency)}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        Next billing:{' '}
                                                        {subscription.next_billing_date
                                                            ? formatDate(subscription.next_billing_date) || 'Invalid date'
                                                            : 'Not scheduled'}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant={subscription.status === 'active' ? 'default' : 'secondary'}>
                                                        {subscription.status}
                                                    </Badge>
                                                    <Link href={route('subscriptions.show', subscription.id)}>
                                                        <Button size="sm" variant="outline">
                                                            <ExternalLink className="h-3 w-3" />
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                            {index < paymentMethod.subscriptions.length - 1 && <Separator className="mt-4" />}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Delete Image Confirmation Dialog */}
            <Dialog open={showDeleteImageDialog} onOpenChange={setShowDeleteImageDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Image</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove this payment method image? This action cannot be undone.
                        </DialogDescription>
                        {errorMessage && (
                            <div className="mt-2 rounded-md border border-red-200 bg-red-50 p-3">
                                <p className="text-sm text-red-600">{errorMessage}</p>
                            </div>
                        )}
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeleteImageDialog(false)} disabled={isRemovingImage}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmRemoveImage} disabled={isRemovingImage}>
                            {isRemovingImage && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Remove Image
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
