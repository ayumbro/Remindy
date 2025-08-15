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
            <div className="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <CreditCard className="text-primary h-6 w-6 sm:h-8 sm:w-8" />
                        <div className="min-w-0 flex-1">
                            <h1 className="text-2xl font-bold sm:text-3xl">{t('app.nav.subscriptions')}</h1>
                            <p className="text-muted-foreground text-sm sm:text-base">{t('subscriptions.description')}</p>
                        </div>
                    </div>
                    <Button asChild className="gap-2 self-start sm:self-auto">
                        <Link href="/subscriptions/create">
                            <Plus className="h-4 w-4" />
                            <span className="hidden sm:inline">{t('subscriptions.add_subscription')}</span>
                            <span className="sm:hidden">Add</span>
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <div className="space-y-4">
                    {/* Status Tabs */}
                    <Tabs defaultValue={filters.status || 'all'} className="w-full">
                        <TabsList className="flex h-auto w-full flex-wrap items-center justify-start gap-1 rounded-lg bg-muted p-1 text-muted-foreground sm:grid sm:h-9 sm:grid-cols-5 sm:gap-0">
                        <TabsTrigger value="active" asChild className="text-xs sm:text-sm">
                            <Link href="/subscriptions?status=active" className="flex items-center gap-1 sm:gap-2">
                                <CheckCircle className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">{t('subscriptions.status.active')}</span>
                                <span className="sm:hidden">Active</span>
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="upcoming" asChild className="text-xs sm:text-sm">
                            <Link href="/subscriptions?status=upcoming" className="flex items-center gap-1 sm:gap-2">
                                <Clock className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">{t('subscriptions.filters.upcoming')}</span>
                                <span className="sm:hidden">Soon</span>
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="overdue" asChild className="text-xs sm:text-sm">
                            <Link href="/subscriptions?status=overdue" className="flex items-center gap-1 sm:gap-2">
                                <AlertTriangle className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">{t('subscriptions.status.overdue')}</span>
                                <span className="sm:hidden">Late</span>
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="ended" asChild className="text-xs sm:text-sm">
                            <Link href="/subscriptions?status=ended" className="flex items-center gap-1 sm:gap-2">
                                <XCircle className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">{t('subscriptions.status.ended')}</span>
                                <span className="sm:hidden">Ended</span>
                            </Link>
                        </TabsTrigger>
                        <TabsTrigger value="all" asChild className="text-xs sm:text-sm">
                            <Link href="/subscriptions?status=all" className="flex items-center gap-1 sm:gap-2">
                                <Filter className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">{t('subscriptions.filters.all')}</span>
                                <span className="sm:hidden">All</span>
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
                                <SelectTrigger className="w-full min-w-0 sm:w-[200px]">
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
                                                <span className="truncate">{category.name}</span>
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
                        <CardContent className="flex flex-col items-center justify-center py-8 sm:py-12">
                            <CreditCard className="text-muted-foreground mb-4 h-8 w-8 sm:h-12 sm:w-12" />
                            <h3 className="mb-2 text-base font-semibold sm:text-lg">{t('subscriptions.no_subscriptions')}</h3>
                            <p className="text-muted-foreground mb-4 text-center text-sm sm:text-base">{t('subscriptions.get_started')}</p>
                            <Button asChild className="gap-2">
                                <Link href="/subscriptions/create">
                                    <Plus className="h-4 w-4" />
                                    <span className="hidden sm:inline">{t('subscriptions.add_first_subscription')}</span>
                                    <span className="sm:hidden">Add Subscription</span>
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <CardTitle className="flex items-center gap-2 text-lg sm:text-2xl">
                                <CreditCard className="h-4 w-4 sm:h-5 sm:w-5" />
                                <span className="hidden sm:inline">{t('app.nav.subscriptions')} ({(subscriptions.data || []).length})</span>
                                <span className="sm:hidden">Subscriptions ({(subscriptions.data || []).length})</span>
                            </CardTitle>
                            <CardDescription className="text-xs sm:text-sm">{t('subscriptions.manage_track')}</CardDescription>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            {/* Desktop Table View */}
                            <div className="table-responsive hidden lg:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="text-xs sm:text-sm">Name</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Categories</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Status</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Price</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Billing</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Next/End Date</TableHead>
                                            <TableHead className="text-xs sm:text-sm">Payment Method</TableHead>
                                            <TableHead className="text-right text-xs sm:text-sm">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(subscriptions.data || []).map((subscription) => (
                                            <TableRow key={subscription.id} className="hover:bg-muted/50">
                                                <TableCell className="py-2">
                                                    <div>
                                                        <div className="font-medium text-sm">{subscription.name}</div>
                                                        {subscription.description && (
                                                            <div className="text-muted-foreground text-xs truncate max-w-[200px]">{subscription.description}</div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    {subscription.categories && subscription.categories.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {subscription.categories.slice(0, 1).map((category) => (
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
                                                            {subscription.categories.length > 1 && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    +{subscription.categories.length - 1}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    <Badge variant={getStatusColor(subscription)} className="text-xs">{getStatusText(subscription)}</Badge>
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    <div className="font-medium text-sm">{formatCurrency(subscription.price, subscription.currency)}</div>
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    <div className="text-xs">
                                                        {getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    {subscription.computed_status === 'ended' ? (
                                                        <div className="text-xs">
                                                            <div>Started: {formatDate(subscription.start_date, userDateFormat)}</div>
                                                            {subscription.end_date && (
                                                                <div className="text-muted-foreground">
                                                                    Ended: {formatDate(subscription.end_date, userDateFormat)}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        subscription.next_billing_date && (
                                                            <div className="text-xs">
                                                                <div className="flex items-center gap-1">
                                                                    <Calendar className="h-3 w-3" />
                                                                    {formatDate(subscription.next_billing_date, userDateFormat)}
                                                                </div>
                                                            </div>
                                                        )
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    {subscription.payment_method ? (
                                                        <div className="text-xs truncate max-w-[120px]">{subscription.payment_method.name}</div>
                                                    ) : (
                                                        <div className="text-muted-foreground text-xs">None</div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right py-2">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/subscriptions/${subscription.id}`}>
                                                                <Eye className="h-3 w-3" />
                                                            </Link>
                                                        </Button>
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/subscriptions/${subscription.id}/edit`}>
                                                                <Edit className="h-3 w-3" />
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Tablet Compact View */}
                            <div className="hidden md:block lg:hidden">
                                <div className="space-y-2">
                                    {(subscriptions.data || []).map((subscription) => (
                                        <div key={subscription.id} className="rounded-lg border bg-card p-3 shadow-sm">
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <h3 className="truncate text-sm font-medium">{subscription.name}</h3>
                                                        <Badge variant={getStatusColor(subscription)} className="text-xs">{getStatusText(subscription)}</Badge>
                                                    </div>
                                                    {subscription.description && (
                                                        <p className="text-muted-foreground mt-1 truncate text-xs">{subscription.description}</p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm">
                                                    <span className="font-medium">{formatCurrency(subscription.price, subscription.currency)}</span>
                                                    <span className="text-muted-foreground text-xs">
                                                        {getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/subscriptions/${subscription.id}`}>
                                                            <Eye className="h-3 w-3" />
                                                        </Link>
                                                    </Button>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/subscriptions/${subscription.id}/edit`}>
                                                            <Edit className="h-3 w-3" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Mobile Card View */}
                            <div className="space-y-2 md:hidden">
                                {(subscriptions.data || []).map((subscription) => (
                                    <div key={subscription.id} className="rounded-lg border bg-card p-2 shadow-sm">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="truncate text-sm font-medium">{subscription.name}</h3>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <span className="font-medium text-xs">{formatCurrency(subscription.price, subscription.currency)}</span>
                                                    <span className="text-muted-foreground text-xs">
                                                        {getBillingCycleText(subscription.billing_cycle, subscription.billing_interval)}
                                                    </span>
                                                </div>
                                            </div>
                                            <Badge variant={getStatusColor(subscription)} className="text-xs">{getStatusText(subscription)}</Badge>
                                        </div>

                                        <div className="mt-2 space-y-1 text-xs">
                                            {subscription.next_billing_date && subscription.computed_status !== 'ended' && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Next billing:</span>
                                                    <span className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {formatDate(subscription.next_billing_date, userDateFormat)}
                                                    </span>
                                                </div>
                                            )}

                                            {subscription.payment_method && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Payment:</span>
                                                    <span className="truncate text-xs">{subscription.payment_method.name}</span>
                                                </div>
                                            )}

                                            {subscription.categories && subscription.categories.length > 0 && (
                                                <div className="flex flex-wrap gap-1 pt-1">
                                                    {subscription.categories.slice(0, 2).map((category) => (
                                                        <Badge key={category.id} variant="outline" className="text-xs">
                                                            {category.name}
                                                        </Badge>
                                                    ))}
                                                    {subscription.categories.length > 2 && (
                                                        <Badge variant="outline" className="text-xs">
                                                            +{subscription.categories.length - 2}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <div className="mt-2 flex gap-1">
                                            <Button variant="ghost" size="sm" asChild className="flex-1 text-xs h-7">
                                                <Link href={`/subscriptions/${subscription.id}`} className="flex items-center justify-center gap-1">
                                                    <Eye className="h-3 w-3" />
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" asChild className="flex-1 text-xs h-7">
                                                <Link
                                                    href={`/subscriptions/${subscription.id}/edit`}
                                                    className="flex items-center justify-center gap-1"
                                                >
                                                    <Edit className="h-3 w-3" />
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
                    <div className="flex flex-wrap justify-center gap-1 sm:gap-2">
                        {(subscriptions.links || []).map((link: any, index: number) => (
                            <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" className="text-xs sm:text-sm" asChild={!!link.url} disabled={!link.url}>
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
