import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, AlertTriangle, Calendar, CheckCircle, Clock, CreditCard, DollarSign, TrendingUp } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    stats: {
        totalSubscriptions: number;
        activeSubscriptions: number;
        upcomingBills: number;
        expiredBills: number;
    };
    spendingByCurrency: Record<
        string,
        {
            currency: { code: string; symbol: string; name: string };
            total: number;
            count: number;
        }
    >;

    upcomingBills: Array<{
        id: number;
        name: string;
        price: number;
        currency: { code: string; symbol: string };
        start_date: string;
        end_date?: string;
        next_billing_date: string | null;
        payment_method?: { name: string };
        computed_status: string;
        is_overdue: boolean;
    }>;
    expiredBills: Array<{
        id: number;
        name: string;
        price: number;
        currency: { code: string; symbol: string };
        start_date: string;
        end_date?: string;
        next_billing_date: string | null;
        payment_method?: { name: string };
        computed_status: string;
        is_overdue: boolean;
    }>;
    currentMonthForecast: Record<
        string,
        {
            currency: { code: string; symbol: string; name: string };
            total: number;
            count: number;
            subscriptions?: Array<{
                subscription: {
                    id: number;
                    name: string;
                    billing_cycle: string;
                    billing_interval: number;
                };
                forecast_amount: number;
            }>;
        }
    >;
}

export default function Dashboard({
    stats = { totalSubscriptions: 0, activeSubscriptions: 0, upcomingBills: 0, expiredBills: 0 },
    spendingByCurrency = {},
    upcomingBills = [],
    expiredBills = [],
    currentMonthForecast = {},
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userDateFormat = auth.user?.date_format || 'Y-m-d';

    const formatCurrency = (amount: number | string, currency: { symbol: string; code: string }) => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        const safeAmount = isNaN(numAmount) ? 0 : numAmount;
        return `${currency.symbol}${safeAmount.toFixed(2)} ${currency.code}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                {/* Stats Cards */}
                <div className="grid gap-3 sm:gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-l-primary border-l-4">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-6 sm:pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium sm:text-sm">
                                <span className="block sm:hidden">Total Subs</span>
                                <span className="hidden sm:block">Total Subscriptions</span>
                            </CardTitle>
                            <CreditCard className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent className="p-3 pt-0 sm:p-6 sm:pt-0">
                            <div className="text-primary text-xl font-bold sm:text-2xl">{stats.totalSubscriptions}</div>
                            <p className="text-muted-foreground mt-1 text-xs">All your subscriptions</p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-green-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-6 sm:pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium sm:text-sm">
                                <span className="block sm:hidden">Active Subs</span>
                                <span className="hidden sm:block">Active Subscriptions</span>
                            </CardTitle>
                            <CheckCircle className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent className="p-3 pt-0 sm:p-6 sm:pt-0">
                            <div className="text-xl font-bold text-green-600 sm:text-2xl">{stats.activeSubscriptions}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Currently active</p>
                            {stats.totalSubscriptions > 0 && (
                                <div className="mt-2">
                                    <Progress value={(stats.activeSubscriptions / stats.totalSubscriptions) * 100} className="h-1" />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-6 sm:pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium sm:text-sm">
                                <span className="block sm:hidden">Upcoming</span>
                                <span className="hidden sm:block">Upcoming Bills</span>
                            </CardTitle>
                            <Clock className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent className="p-3 pt-0 sm:p-6 sm:pt-0">
                            <div className="text-xl font-bold text-amber-600 sm:text-2xl">{stats.upcomingBills}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Next 7 days</p>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-red-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-6 sm:pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium sm:text-sm">
                                <span className="block sm:hidden">Expired</span>
                                <span className="hidden sm:block">Expired Bills</span>
                            </CardTitle>
                            <AlertTriangle className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent className="p-3 pt-0 sm:p-6 sm:pt-0">
                            <div className="text-xl font-bold text-red-600 sm:text-2xl">{stats.expiredBills}</div>
                            <p className="text-muted-foreground mt-1 text-xs">Require attention</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Financial Overview - Side by Side */}
                <div className="grid gap-4 sm:gap-6 md:grid-cols-2">
                    {/* Monthly Spending by Currency */}
                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <div className="flex items-center gap-2">
                                <DollarSign className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                <div className="min-w-0 flex-1">
                                    <CardTitle className="text-base sm:text-lg">
                                        <span className="block sm:hidden">Monthly Spending</span>
                                        <span className="hidden sm:block">Monthly Spending by Currency</span>
                                    </CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        <span className="block sm:hidden">Current month</span>
                                        <span className="hidden sm:block">Current month spending breakdown</span>
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="space-y-2 sm:space-y-3">
                                {Object.values(spendingByCurrency || {}).map((spending) => (
                                    <div
                                        key={spending.currency.code}
                                        className="from-primary/5 hover:from-primary/10 flex items-center justify-between rounded-lg border bg-gradient-to-r to-transparent p-3 transition-colors sm:p-4"
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                                            <div className="bg-primary h-2 w-2 rounded-full"></div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium sm:text-base">{spending.currency.name}</p>
                                                <p className="text-muted-foreground text-xs sm:text-sm">{spending.count || 0} payments</p>
                                            </div>
                                        </div>
                                        <div className="ml-2 text-right">
                                            <p className="text-primary text-sm font-bold sm:text-base">{formatCurrency(spending.total || 0, spending.currency)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {Object.keys(spendingByCurrency || {}).length === 0 && (
                                <div className="text-muted-foreground py-6 text-center sm:py-8">
                                    <Activity className="mx-auto mb-2 h-6 w-6 opacity-50 sm:h-8 sm:w-8" />
                                    <p className="text-sm sm:text-base">No spending data for this month</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Current Month Forecast */}
                    <Card>
                        <CardHeader className="p-4 sm:p-6">
                            <div className="flex items-center gap-2">
                                <TrendingUp className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                <div className="min-w-0 flex-1">
                                    <CardTitle className="text-base sm:text-lg">
                                        <span className="block sm:hidden">Month Forecast</span>
                                        <span className="hidden sm:block">Current Month Forecast</span>
                                    </CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        <span className="block sm:hidden">
                                            Expected for {new Date().toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
                                        </span>
                                        <span className="hidden sm:block">
                                            Expected bills for {new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                                            <br />
                                            <span className="text-xs">Calculated based on billing frequency and remaining days</span>
                                        </span>
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="space-y-3 sm:space-y-4">
                                {Object.values(currentMonthForecast || {}).map((forecast) => (
                                    <div
                                        key={forecast.currency.code}
                                        className="border-primary/20 from-primary/5 rounded-lg border bg-gradient-to-r to-transparent"
                                    >
                                        <div className="flex items-center justify-between p-3 sm:p-4">
                                            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                                                <div className="bg-primary h-2 w-2 rounded-full"></div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium sm:text-base">{forecast.currency.name}</p>
                                                    <p className="text-muted-foreground text-xs sm:text-sm">
                                                        {forecast.count || 0} subscription{(forecast.count || 0) !== 1 ? 's' : ''} contributing
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="ml-2 text-right">
                                                <p className="text-primary text-sm font-bold sm:text-base">{formatCurrency(forecast.total || 0, forecast.currency)}</p>
                                                <p className="text-primary/70 text-xs">Total forecast</p>
                                            </div>
                                        </div>

                                        {/* Detailed breakdown */}
                                        {forecast.subscriptions && forecast.subscriptions.length > 0 && (
                                            <div className="border-primary/20 bg-primary/5 border-t">
                                                <div className="space-y-1 p-2 sm:space-y-2 sm:p-3">
                                                    <p className="text-primary mb-1 text-xs font-medium sm:mb-2">Breakdown:</p>
                                                    {forecast.subscriptions.slice(0, 3).map((item, index) => (
                                                        <div key={index} className="flex items-center justify-between text-xs">
                                                            <span className="text-primary/80 min-w-0 flex-1 truncate">
                                                                <span className="truncate">{item.subscription.name}</span>
                                                                <span className="text-muted-foreground ml-1 hidden sm:inline">
                                                                    (
                                                                    {item.subscription.billing_interval > 1
                                                                        ? `${item.subscription.billing_interval} ${item.subscription.billing_cycle}s`
                                                                        : item.subscription.billing_cycle}
                                                                    )
                                                                </span>
                                                            </span>
                                                            <span className="text-primary ml-2 text-xs font-medium">
                                                                {formatCurrency(item.forecast_amount, forecast.currency)}
                                                            </span>
                                                        </div>
                                                    ))}
                                                    {forecast.subscriptions.length > 3 && (
                                                        <div className="text-muted-foreground pt-1 text-xs">
                                                            +{forecast.subscriptions.length - 3} more subscription
                                                            {forecast.subscriptions.length - 3 !== 1 ? 's' : ''}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                            {Object.keys(currentMonthForecast || {}).length === 0 && (
                                <div className="text-muted-foreground py-6 text-center sm:py-8">
                                    <Calendar className="mx-auto mb-2 h-6 w-6 opacity-50 sm:h-8 sm:w-8" />
                                    <p className="text-sm sm:text-base">No bills expected this month</p>
                                    <p className="mt-1 text-xs sm:text-sm">All subscriptions are either ended or billing outside this month</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 sm:gap-6 md:grid-cols-2">
                    {/* Upcoming Bills */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between p-4 sm:p-6">
                            <div className="min-w-0 flex-1">
                                <CardTitle className="text-base sm:text-lg">Upcoming Bills</CardTitle>
                                <CardDescription className="text-xs sm:text-sm">
                                    <span className="block sm:hidden">Next 30 days</span>
                                    <span className="hidden sm:block">Bills due in the next 30 days</span>
                                </CardDescription>
                            </div>
                            <Button asChild variant="outline" size="sm" className="ml-2 text-xs sm:text-sm">
                                <Link href="/subscriptions">Manage</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="space-y-3 sm:space-y-4">
                                {(upcomingBills || []).length === 0 ? (
                                    <div className="text-muted-foreground py-6 text-center sm:py-8">
                                        <p className="text-sm sm:text-base">No upcoming bills in the next 30 days</p>
                                    </div>
                                ) : (
                                    (upcomingBills || []).map((bill) => (
                                        <div key={bill.id} className="flex items-center justify-between rounded-lg border p-3">
                                            <div className="min-w-0 flex-1">
                                                <h4 className="truncate text-sm font-medium sm:text-base">{bill.name}</h4>
                                                <p className="text-muted-foreground text-xs sm:text-sm">
                                                    Due: {formatDate(bill.next_billing_date, userDateFormat)}
                                                </p>
                                                {bill.payment_method && (
                                                    <p className="text-muted-foreground truncate text-xs">via {bill.payment_method.name}</p>
                                                )}
                                            </div>
                                            <div className="ml-3 text-right">
                                                <p className="text-sm font-medium sm:text-base">{formatCurrency(bill.price, bill.currency)}</p>
                                                <Button asChild variant="outline" size="sm" className="mt-1 text-xs">
                                                    <Link href={`/subscriptions/${bill.id}`}>View</Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Expired Bills */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between p-4 sm:p-6">
                            <div className="min-w-0 flex-1">
                                <CardTitle className="text-base sm:text-lg">Expired Bills</CardTitle>
                                <CardDescription className="text-xs sm:text-sm">
                                    <span className="block sm:hidden">Need attention</span>
                                    <span className="hidden sm:block">Overdue bills that need attention</span>
                                </CardDescription>
                            </div>
                            <Button asChild variant="outline" size="sm" className="ml-2 text-xs sm:text-sm">
                                <Link href="/subscriptions">Manage</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                            <div className="space-y-3 sm:space-y-4">
                                {(expiredBills || []).length === 0 ? (
                                    <div className="text-muted-foreground py-6 text-center sm:py-8">
                                        <p className="text-sm sm:text-base">No expired bills - you're all caught up! 🎉</p>
                                    </div>
                                ) : (
                                    (expiredBills || []).map((bill) => (
                                        <div
                                            key={bill.id}
                                            className="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <h4 className="truncate text-sm font-medium sm:text-base">{bill.name}</h4>
                                                <p className="text-xs text-red-600 sm:text-sm">Overdue: {formatDate(bill.next_billing_date, userDateFormat)}</p>
                                                {bill.payment_method && (
                                                    <p className="text-muted-foreground truncate text-xs">via {bill.payment_method.name}</p>
                                                )}
                                            </div>
                                            <div className="ml-3 text-right">
                                                <p className="text-sm font-medium text-red-600 sm:text-base">{formatCurrency(bill.price, bill.currency)}</p>
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-1 border-red-300 text-xs text-red-600 hover:bg-red-100"
                                                >
                                                    <Link href={`/subscriptions/${bill.id}`}>Pay Now</Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
