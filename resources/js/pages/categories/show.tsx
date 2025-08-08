import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Edit, ExternalLink, Folder } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    };
    status: string;
    computed_status: string;
    is_overdue: boolean;
    start_date: string;
    end_date?: string;
    next_billing_date: string | null;
}

interface Category {
    id: number;
    name: string;
    color?: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    display_color: string;
    subscriptions: Subscription[];
}

interface ShowCategoryProps {
    category: Category;
}

export default function ShowCategory({ category }: ShowCategoryProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Categories',
            href: '/categories',
        },
        {
            title: category.name,
            href: `/categories/${category.id}`,
        },
    ];

    const formatCurrency = (amount: number | string | null | undefined, currency: { symbol: string; code: string }) => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        const safeAmount = typeof numAmount === 'number' && !isNaN(numAmount) ? numAmount : 0;
        return `${currency.symbol}${safeAmount.toFixed(2)} ${currency.code}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={category.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-6 w-6 rounded-full" style={{ backgroundColor: category.display_color }} />
                        <div>
                            <h1 className="text-3xl font-bold">{category.name}</h1>
                            {category.description && <p className="text-muted-foreground">{category.description}</p>}
                        </div>
                        <div className="flex gap-2">{!category.is_active && <Badge variant="secondary">Inactive</Badge>}</div>
                    </div>
                    <Link href={route('categories.edit', category.id)}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Category Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Category Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Name</label>
                                <p className="text-sm">{category.name}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Color</label>
                                <div className="flex items-center gap-2">
                                    <div className="h-4 w-4 rounded-full" style={{ backgroundColor: category.display_color }} />
                                    <p className="text-sm">{category.display_color}</p>
                                </div>
                            </div>

                            {category.description && (
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Description</label>
                                    <p className="text-sm">{category.description}</p>
                                </div>
                            )}

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Status</label>
                                <p className="text-sm">{category.is_active ? 'Active' : 'Inactive'}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Subscriptions</label>
                                <p className="text-sm">
                                    {category.subscriptions.length} subscription{category.subscriptions.length !== 1 ? 's' : ''}
                                </p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Created</label>
                                <p className="text-sm">{formatDate(category.created_at)}</p>
                            </div>

                            <div>
                                <label className="text-muted-foreground text-sm font-medium">Last Updated</label>
                                <p className="text-sm">{formatDate(category.updated_at)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Associated Subscriptions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Subscriptions in this Category</CardTitle>
                            <CardDescription>Subscriptions assigned to this category</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {category.subscriptions.length === 0 ? (
                                <div className="py-8 text-center">
                                    <Folder className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                    <p className="text-muted-foreground">No subscriptions are assigned to this category</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {category.subscriptions.map((subscription, index) => (
                                        <div key={subscription.id}>
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="font-medium">{subscription.name}</h4>
                                                    <p className="text-muted-foreground text-sm">
                                                        {formatCurrency(subscription.price, subscription.currency)}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {subscription.computed_status === 'ended' ? (
                                                            // For ended subscriptions, show duration
                                                            <>
                                                                Started: {formatDate(subscription.start_date)}
                                                                {subscription.end_date && <> • Ended: {formatDate(subscription.end_date)}</>}
                                                            </>
                                                        ) : // For active subscriptions, show next billing date if available
                                                        subscription.next_billing_date ? (
                                                            <>Next billing: {formatDate(subscription.next_billing_date)}</>
                                                        ) : (
                                                            <>Started: {formatDate(subscription.start_date)}</>
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            subscription.computed_status === 'ended'
                                                                ? 'outline'
                                                                : subscription.computed_status === 'active' && subscription.is_overdue
                                                                  ? 'destructive'
                                                                  : 'default'
                                                        }
                                                    >
                                                        {subscription.computed_status === 'ended'
                                                            ? 'ended'
                                                            : subscription.computed_status === 'active' && subscription.is_overdue
                                                              ? 'overdue'
                                                              : 'active'}
                                                    </Badge>
                                                    <Link href={route('subscriptions.show', subscription.id)}>
                                                        <Button size="sm" variant="outline">
                                                            <ExternalLink className="h-3 w-3" />
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                            {index < category.subscriptions.length - 1 && <Separator className="mt-4" />}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
