import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import {
    TrendingUp,
    TrendingDown,
    Activity,
    CheckCircle,
    XCircle,
    Clock,
    Zap,
} from 'lucide-react';

interface SystemMetrics {
    totalResources: number;
    activeResources: number;
    totalDeployments: number;
    successfulDeployments: number;
    failedDeployments: number;
    averageDeploymentTime: number; // in seconds
    deploymentsLast24h: number;
    deploymentsLast7d: number;
    successRate: number; // percentage
}

interface Props {
    metrics?: SystemMetrics;
}

const defaultMetrics: SystemMetrics = {
    totalResources: 0,
    activeResources: 0,
    totalDeployments: 0,
    successfulDeployments: 0,
    failedDeployments: 0,
    averageDeploymentTime: 0,
    deploymentsLast24h: 0,
    deploymentsLast7d: 0,
    successRate: 0,
};

function MetricCard({
    title,
    value,
    subtitle,
    icon: Icon,
    trend,
    trendValue,
}: {
    title: string;
    value: number | string;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
}) {
    const trendConfig = {
        up: { icon: TrendingUp, color: 'text-success', bg: 'bg-success/10' },
        down: { icon: TrendingDown, color: 'text-danger', bg: 'bg-danger/10' },
        neutral: { icon: Activity, color: 'text-foreground-muted', bg: 'bg-foreground-muted/10' },
    };

    const config = trend ? trendConfig[trend] : null;
    const TrendIcon = config?.icon;

    return (
        <Card variant="glass" hover>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <p className="text-sm text-foreground-muted">{title}</p>
                        <p className="mt-2 text-3xl font-bold text-foreground">{value}</p>
                        {subtitle && (
                            <p className="mt-1 text-xs text-foreground-subtle">{subtitle}</p>
                        )}
                        {trend && TrendIcon && trendValue && (
                            <div className="mt-3 flex items-center gap-1">
                                <div className={`rounded-full p-1 ${config?.bg}`}>
                                    <TrendIcon className={`h-3 w-3 ${config?.color}`} />
                                </div>
                                <span className={`text-xs ${config?.color}`}>{trendValue}</span>
                            </div>
                        )}
                    </div>
                    <div className="rounded-lg bg-primary/10 p-3">
                        <Icon className="h-6 w-6 text-primary" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function formatTime(seconds: number): string {
    if (seconds < 60) {
        return `${Math.round(seconds)}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.round(seconds % 60);
    return `${minutes}m ${remainingSeconds}s`;
}

export default function AdminMetricsIndex({ metrics = defaultMetrics }: Props) {
    return (
        <AdminLayout title="System Metrics">
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">System Metrics</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Performance metrics and deployment statistics
                    </p>
                </div>

                {/* Key Metrics Grid */}
                <div className="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        title="Total Resources"
                        value={metrics.totalResources}
                        subtitle={`${metrics.activeResources} active`}
                        icon={Activity}
                        trend="neutral"
                    />
                    <MetricCard
                        title="Total Deployments"
                        value={metrics.totalDeployments}
                        subtitle={`${metrics.deploymentsLast24h} in last 24h`}
                        icon={Zap}
                        trend="up"
                        trendValue={`+${metrics.deploymentsLast7d} this week`}
                    />
                    <MetricCard
                        title="Success Rate"
                        value={`${metrics.successRate.toFixed(1)}%`}
                        subtitle={`${metrics.successfulDeployments}/${metrics.totalDeployments} successful`}
                        icon={CheckCircle}
                        trend={metrics.successRate >= 90 ? 'up' : metrics.successRate >= 70 ? 'neutral' : 'down'}
                        trendValue={metrics.successRate >= 90 ? 'Excellent' : metrics.successRate >= 70 ? 'Good' : 'Needs attention'}
                    />
                    <MetricCard
                        title="Avg Deploy Time"
                        value={formatTime(metrics.averageDeploymentTime)}
                        subtitle="Average deployment duration"
                        icon={Clock}
                        trend="neutral"
                    />
                </div>

                {/* Deployment Stats */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Deployment Status</CardTitle>
                            <CardDescription>Breakdown of deployment outcomes</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between border-b border-border/50 pb-3">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-full bg-success/10 p-2">
                                            <CheckCircle className="h-4 w-4 text-success" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-foreground">Successful</p>
                                            <p className="text-xs text-foreground-subtle">
                                                Completed without errors
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm text-foreground-muted">
                                            {metrics.successfulDeployments}
                                        </span>
                                        <Badge variant="success" size="sm">
                                            {((metrics.successfulDeployments / Math.max(metrics.totalDeployments, 1)) * 100).toFixed(0)}%
                                        </Badge>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-full bg-danger/10 p-2">
                                            <XCircle className="h-4 w-4 text-danger" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-foreground">Failed</p>
                                            <p className="text-xs text-foreground-subtle">
                                                Deployments with errors
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm text-foreground-muted">
                                            {metrics.failedDeployments}
                                        </span>
                                        <Badge variant="danger" size="sm">
                                            {((metrics.failedDeployments / Math.max(metrics.totalDeployments, 1)) * 100).toFixed(0)}%
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                            <CardDescription>Deployment trends over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between border-b border-border/50 pb-3">
                                    <div>
                                        <p className="text-sm font-medium text-foreground">Last 24 Hours</p>
                                        <p className="text-xs text-foreground-subtle">Deployments today</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-2xl font-bold text-foreground">
                                            {metrics.deploymentsLast24h}
                                        </span>
                                        <Badge variant="info" size="sm">24h</Badge>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-foreground">Last 7 Days</p>
                                        <p className="text-xs text-foreground-subtle">This week's activity</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-2xl font-bold text-foreground">
                                            {metrics.deploymentsLast7d}
                                        </span>
                                        <Badge variant="primary" size="sm">7d</Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Coming Soon Section */}
                <Card variant="glass" className="mt-6">
                    <CardHeader>
                        <CardTitle>Advanced Metrics</CardTitle>
                        <CardDescription>More detailed analytics coming soon</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-lg border border-primary/10 bg-primary/[0.03] p-4">
                                <p className="text-sm font-medium text-foreground-muted">Resource Usage Trends</p>
                                <Badge variant="warning" size="sm" className="mt-2">Coming Soon</Badge>
                            </div>
                            <div className="rounded-lg border border-primary/10 bg-primary/[0.03] p-4">
                                <p className="text-sm font-medium text-foreground-muted">Team Performance</p>
                                <Badge variant="warning" size="sm" className="mt-2">Coming Soon</Badge>
                            </div>
                            <div className="rounded-lg border border-primary/10 bg-primary/[0.03] p-4">
                                <p className="text-sm font-medium text-foreground-muted">Cost Analytics</p>
                                <Badge variant="warning" size="sm" className="mt-2">Coming Soon</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
