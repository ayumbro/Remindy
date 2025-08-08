import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Calendar, CheckCircle, Clock, CreditCard, Edit, Eye, Filter, Plus, XCircle } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs = (t: (key: string) => string): BreadcrumbItem[] => [
    {
        title: t('app.nav.dashboard'),
        href: '/dashboard',
    },
    {
        title: t('app.nav.subscriptions'),
        href: '/subscriptions',
    },
];

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
    end_date?: string;
    next_billing_date: string | null;
    computed_status: string;
    is_overdue: boolean;
    website_url?: string;
    categories: Array<{ id: number; name: string; color?: string; display_color: string }>;
}

interface Category {
    id: number;
    name: string;
    color?: string;
    display_color: string;
}

interface SubscriptionsIndexProps {
    subscriptions: {
        data: Subscription[];
        links: any;
        meta: any;
    };
    filters: {
        status?: string;
        search?: string;
        categories?: number[];
    };
    categories?: Category[];
}

export default function SubscriptionsIndex({ subscriptions = { data: [], links: [], meta: {} }, filters = {}, categories = [] }: SubscriptionsIndexProps) {
    const { auth } = usePage<SharedData>().props;
    const { t } = useTranslations();
    const userDateFormat = auth.user?.date_format || 'Y-m-d';
    const [selectedCategory, setSelectedCategory] = useState<string>(
        filters.categories && filters.categories.length > 0 ? filters.categories[0].toString() : '0'
    );

    const handleCategoryChange = (categoryId: string) => {
        setSelectedCategory(categoryId);
        
        // Navigate with the new filter
        const params = new URLSearchParams(window.location.search);
        
        // Remove ALL existing category filters (handles array parameters properly)
        const keysToDelete: string[] = [];
        params.forEach((value, key) => {
            if (key.startsWith('categories')) {
                keysToDelete.push(key);
            }
        });
        keysToDelete.forEach(key => params.delete(key));
        
        if (categoryId !== '0') {
            // Add the selected category
            params.append('categories[]', categoryId);
        }
        
        // Preserve other filters like status
        router.get('/subscriptions?' + params.toString(), {}, { preserveState: true, preserveScroll: true });
    };

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
            return t('subscriptions.status.ended');
        }
        // Only active subscriptions can show as overdue
        if (subscription.computed_status === 'active' && subscription.is_overdue) {
            return t('subscriptions.status.overdue');
        }
        return t('subscriptions.status.active');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(t)}>
            <Head title={t('app.nav.subscriptions')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <CreditCard className="text-primary h-8 w-8" />
                        <div>
                            <h1 className="text-3xl font-bold">{t('app.nav.subscriptions')}</h1>
                            <p className="text-muted-foreground">{t('subscriptions.description')}</p>
                        </div>
                    </div>
                    <Button asChild className="gap-2">
                        <Link href="/subscriptions/create">
                            <Plus className="h-4 w-4" />
                            {t('subscriptions.add_subscription')}
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs defaultValue={filters.status || 'all'} className="flex-1">
                        <TabsList className="grid w-full grid-cols-5">
                        <TabsTrigger value="all" asChild>
                            <Link href="/subscriptions?status=all" className="flex items-center gap-2">
                                <Filter className="h-4 w-4" />
                                {t('subscriptions.filters.all')}
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="active" asChild>
                            <Link href="/subscriptions?status=active" className="flex items-center gap-2">
                                <CheckCircle className="h-4 w-4" />
                                {t('subscriptions.status.active')}
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="upcoming" asChild>
                            <Link href="/subscriptions?status=upcoming" className="flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                {t('subscriptions.filters.upcoming')}
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="overdue" asChild>
                            <Link href="/subscriptions?status=overdue" className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4" />
                                {t('subscriptions.status.overdue')}
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="ended" asChild>
                            <Link href="/subscriptions?status=ended" className="flex items-center gap-2">
                                <XCircle className="h-4 w-4" />
                                {t('subscriptions.status.ended')}
                            </Link>
                        </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    
                    {/* Category Filter */}
                    {categories.length > 0 && (
                        <div className="flex items-center gap-2">
                            <Filter className="h-4 w-4 text-muted-foreground" />
                            <Select 
                                value={selectedCategory}
                                onValueChange={handleCategoryChange}
                            >
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Filter by category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="0">All Categories</SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            <div className="flex items-center gap-2">
                                                {category.display_color && (
                                                    <div 
                                                        className="h-3 w-3 rounded-full" 
                                                        style={{ backgroundColor: category.display_color }}
                                                    />
                                                )}
                                                {category.name}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {/* Subscriptions List */}
                {(subscriptions.data || []).length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <CreditCard className="text-muted-foreground mb-4 h-12 w-12" />
                            <h3 className="mb-2 text-lg font-semibold">{t('subscriptions.no_subscriptions')}</h3>
                            <p className="text-muted-foreground mb-4">{t('subscriptions.get_started')}</p>
                            <Button asChild className="gap-2">
                                <Link href="/subscriptions/create">
                                    <Plus className="h-4 w-4" />
                                    {t('subscriptions.add_first_subscription')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                {t('app.nav.subscriptions')} ({(subscriptions.data || []).length})
                            </CardTitle>
                            <CardDescription>{t('subscriptions.manage_track')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {/* Desktop Table View */}
                            <div className="table-responsive hidden md:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Categories</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Price</TableHead>
                                            <TableHead>Billing</TableHead>
                                            <TableHead>Next/End Date</TableHead>
                                            <TableHead>Payment Method</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(subscriptions.data || []).map((subscription) => (
                                            <TableRow key={subscription.id} className="hover:bg-muted/50">
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{subscription.name}</div>
                                                        {subscription.description && (
                                                            <div className="text-muted-foreground text-sm">{subscription.description}</div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {subscription.categories && subscription.categories.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {subscription.categories.slice(0, 2).map((category) => (
                                                                <Badge 
                                                                    key={category.id} 
                                                                    variant="outline" 
                                                                    className="text-xs"
                                                                    style={{
                                                                        borderColor: category.display_color,
                                                                        color: category.display_color
                                                                    }}
                                                                >
                                                                    {category.name}
                                                                </Badge>
                                                            ))}
                                                            {subscription.categories.length > 2 && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    +{subscription.categories.length - 2}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={getStatusColor(subscription)}>{getStatusText(subscription)}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-medium">{formatCurrency(subscription.price, subscription.currency)}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {subscription.computed_status === 'ended' ? (
                                                        <div className="text-sm">
                                                            <div>Started: {formatDate(subscription.start_date, userDateFormat)}</div>
                                                            {subscription.end_date && (
                                                                <div className="text-muted-foreground">
                                                                    Ended: {formatDate(subscription.end_date, userDateFormat)}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        subscription.next_billing_date && (
                                                            <div className="text-sm">
                                                                <div className="flex items-center gap-1">
                                                                    <Calendar className="h-3 w-3" />
                                                                    {formatDate(subscription.next_billing_date, userDateFormat)}
                                                                </div>
                                                            </div>
                                                        )
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {subscription.payment_method ? (
                                                        <div className="text-sm">{subscription.payment_method.name}</div>
                                                    ) : (
                                                        <div className="text-muted-foreground text-sm">None</div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/subscriptions/${subscription.id}`}>
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/subscriptions/${subscription.id}/edit`}>
                                                                <Edit className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Mobile Card View */}
                            <div className="space-y-4 md:hidden">
                                {(subscriptions.data || []).map((subscription) => (
                                    <div key={subscription.id} className="mobile-card theme-transition">
                                        <div className="mobile-card-header">
                                            <div className="flex items-center gap-3">
                                                <div>
                                                    <h3 className="font-medium">{subscription.name}</h3>
                                                    {subscription.description && (
                                                        <p className="text-muted-foreground text-sm">{subscription.description}</p>
                                                    )}
                                                </div>
                                            </div>
                                            <Badge variant={getStatusColor(subscription)}>{getStatusText(subscription)}</Badge>
                                        </div>

                                        <div className="mobile-card-content">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Price:</span>
                                                <span className="font-medium">{formatCurrency(subscription.price, subscription.currency)}</span>
                                            </div>

                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Billing:</span>
                                                <span>{getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}</span>
                                            </div>

                                            {subscription.computed_status === 'ended' ? (
                                                <div className="space-y-1">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Started:</span>
                                                        <span>{formatDate(subscription.start_date, userDateFormat)}</span>
                                                    </div>
                                                    {subscription.end_date && (
                                                        <div className="flex justify-between">
                                                            <span className="text-muted-foreground">Ended:</span>
                                                            <span>{formatDate(subscription.end_date, userDateFormat)}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : (
                                                subscription.next_billing_date && (
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Next billing:</span>
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="h-3 w-3" />
                                                            {formatDate(subscription.next_billing_date, userDateFormat)}
                                                        </span>
                                                    </div>
                                                )
                                            )}

                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Payment method:</span>
                                                <span>{subscription.payment_method ? subscription.payment_method.name : 'None'}</span>
                                            </div>

                                            {subscription.categories && subscription.categories.length > 0 && (
                                                <div className="flex flex-wrap gap-1 pt-2">
                                                    {subscription.categories.slice(0, 3).map((category) => (
                                                        <Badge key={category.id} variant="outline" className="text-xs">
                                                            {category.name}
                                                        </Badge>
                                                    ))}
                                                    {subscription.categories.length > 3 && (
                                                        <Badge variant="outline" className="text-xs">
                                                            +{subscription.categories.length - 3}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex gap-2 pt-2">
                                            <Button variant="outline" size="sm" asChild className="flex-1">
                                                <Link href={`/subscriptions/${subscription.id}`} className="flex items-center justify-center gap-2">
                                                    <Eye className="h-4 w-4" />
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="outline" size="sm" asChild className="flex-1">
                                                <Link
                                                    href={`/subscriptions/${subscription.id}/edit`}
                                                    className="flex items-center justify-center gap-2"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Pagination */}
                {subscriptions.meta && subscriptions.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {(subscriptions.links || []).map((link: any, index: number) => (
                            <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" asChild={!!link.url} disabled={!link.url}>
                                {link.url ? (
                                    <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
