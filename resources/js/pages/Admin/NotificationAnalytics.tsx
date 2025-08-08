import HeadingSmall from '@/components/heading-small';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { BarChart3, TrendingUp, AlertTriangle, CheckCircle, Clock, Mail, Webhook } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin',
    },
    {
        title: 'Notification Analytics',
        href: '/admin/notifications/analytics',
    },
];

interface DeliveryMetrics {
    total_sent: number;
    total_delivered: number;
    total_failed: number;
    delivery_rate: number;
    failure_rate: number;
    avg_delivery_time: number;
    by_channel: {
        email: {
            sent: number;
            delivered: number;
            failed: number;
            delivery_rate: number;
        };
        webhook: {
            sent: number;
            delivered: number;
            failed: number;
            delivery_rate: number;
        };
    };
    recent_trends: {
        date: string;
        sent: number;
        delivered: number;
        failed: number;
    }[];
}

interface HealthReport {
    overall_health: 'excellent' | 'good' | 'warning' | 'critical';
    issues: {
        type: string;
        severity: 'low' | 'medium' | 'high';
        message: string;
        count?: number;
    }[];
    recommendations: string[];
    last_updated: string;
}

interface Props {
    metrics: DeliveryMetrics;
    healthReport: HealthReport;
}

export default function AdminNotificationAnalytics({ metrics, healthReport }: Props) {
    const getHealthBadge = (health: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            excellent: 'default',
            good: 'default',
            warning: 'secondary',
            critical: 'destructive',
        };
        
        return (
            <Badge variant={variants[health] || 'outline'}>
                {health}
            </Badge>
        );
    };

    const getSeverityBadge = (severity: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            low: 'outline',
            medium: 'secondary',
            high: 'destructive',
        };
        
        return (
            <Badge variant={variants[severity] || 'outline'}>
                {severity}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Notification Analytics" />
            
            <div className="space-y-6">
                <div className="flex items-center gap-2">
                    <BarChart3 className="h-5 w-5" />
                    <HeadingSmall>Admin - Notification Analytics</HeadingSmall>
                </div>

                {/* System Health */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CheckCircle className="h-5 w-5" />
                            System Health
                        </CardTitle>
                        <CardDescription>
                            Overall notification system health and status
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-4 mb-4">
                            <span className="text-sm font-medium">Overall Health:</span>
                            {getHealthBadge(healthReport.overall_health)}
                            <span className="text-sm text-muted-foreground">
                                Last updated: {new Date(healthReport.last_updated).toLocaleString()}
                            </span>
                        </div>

                        {healthReport.issues.length > 0 && (
                            <div className="space-y-2 mb-4">
                                <h4 className="text-sm font-medium flex items-center gap-2">
                                    <AlertTriangle className="h-4 w-4" />
                                    Issues Detected
                                </h4>
                                {healthReport.issues.map((issue, index) => (
                                    <div key={index} className="flex items-center gap-2 p-2 bg-muted rounded">
                                        {getSeverityBadge(issue.severity)}
                                        <span className="text-sm">{issue.message}</span>
                                        {issue.count && (
                                            <Badge variant="outline">{issue.count}</Badge>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        {healthReport.recommendations.length > 0 && (
                            <div className="space-y-2">
                                <h4 className="text-sm font-medium flex items-center gap-2">
                                    <TrendingUp className="h-4 w-4" />
                                    Recommendations
                                </h4>
                                <ul className="space-y-1">
                                    {healthReport.recommendations.map((recommendation, index) => (
                                        <li key={index} className="text-sm text-muted-foreground">
                                            • {recommendation}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Overall Metrics */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Total Sent</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.total_sent.toLocaleString()}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Delivered</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{metrics.total_delivered.toLocaleString()}</div>
                            <div className="text-sm text-muted-foreground">
                                {metrics.delivery_rate.toFixed(1)}% delivery rate
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Failed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{metrics.total_failed.toLocaleString()}</div>
                            <div className="text-sm text-muted-foreground">
                                {metrics.failure_rate.toFixed(1)}% failure rate
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Avg Delivery Time</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.avg_delivery_time.toFixed(1)}s</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Channel Performance */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-4 w-4" />
                                Email Performance
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between items-center">
                                <span className="text-sm">Delivery Rate</span>
                                <span className="font-medium">{metrics.by_channel.email.delivery_rate.toFixed(1)}%</span>
                            </div>
                            <Progress value={metrics.by_channel.email.delivery_rate} className="h-2" />
                            
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="text-lg font-bold">{metrics.by_channel.email.sent}</div>
                                    <div className="text-xs text-muted-foreground">Sent</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-green-600">{metrics.by_channel.email.delivered}</div>
                                    <div className="text-xs text-muted-foreground">Delivered</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-red-600">{metrics.by_channel.email.failed}</div>
                                    <div className="text-xs text-muted-foreground">Failed</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Webhook className="h-4 w-4" />
                                Webhook Performance
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between items-center">
                                <span className="text-sm">Delivery Rate</span>
                                <span className="font-medium">{metrics.by_channel.webhook.delivery_rate.toFixed(1)}%</span>
                            </div>
                            <Progress value={metrics.by_channel.webhook.delivery_rate} className="h-2" />
                            
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="text-lg font-bold">{metrics.by_channel.webhook.sent}</div>
                                    <div className="text-xs text-muted-foreground">Sent</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-green-600">{metrics.by_channel.webhook.delivered}</div>
                                    <div className="text-xs text-muted-foreground">Delivered</div>
                                </div>
                                <div>
                                    <div className="text-lg font-bold text-red-600">{metrics.by_channel.webhook.failed}</div>
                                    <div className="text-xs text-muted-foreground">Failed</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-4 w-4" />
                            Recent Trends
                        </CardTitle>
                        <CardDescription>
                            Notification delivery trends over the last 7 days
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {metrics.recent_trends.map((trend, index) => (
                                <div key={index} className="flex items-center justify-between p-3 border rounded">
                                    <div className="font-medium">
                                        {new Date(trend.date).toLocaleDateString()}
                                    </div>
                                    <div className="flex gap-4 text-sm">
                                        <span>Sent: <strong>{trend.sent}</strong></span>
                                        <span className="text-green-600">Delivered: <strong>{trend.delivered}</strong></span>
                                        <span className="text-red-600">Failed: <strong>{trend.failed}</strong></span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
