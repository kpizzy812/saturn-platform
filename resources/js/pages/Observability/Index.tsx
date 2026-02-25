import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { Sparkline } from '@/components/ui/Chart';
import {
    Activity,
    AlertTriangle,
    TrendingUp,
    Server,
    Zap,
    ArrowRight,
    AlertCircle,
    CheckCircle,
    XCircle,
    Cpu,
    MemoryStick,
    HardDrive,
    Rocket,
    Clock,
    CheckCheck,
    RefreshCw,
    WifiOff,
} from 'lucide-react';

interface ServiceHealth {
    id: string;
    name: string;
    status: 'healthy' | 'degraded' | 'down' | 'unreachable';
    uptime: number;
    responseTime: number;
    errorRate: number;
}

interface Alert {
    id: string;
    severity: 'critical' | 'warning' | 'info';
    service: string;
    message: string;
    time: string;
}

interface MetricOverview {
    label: string;
    value: string;
    change: string;
    trend: 'up' | 'down' | 'neutral';
    data: (number | null)[];
}

interface DeploymentStats {
    today: number;
    week: number;
    successRate: number;
    avgDuration: number;
    success: number;
    failed: number;
}

const metricIconMap: Record<string, React.ComponentType<{ className?: string }>> = {
    'Avg CPU': Cpu,
    'Avg Memory': MemoryStick,
    'Avg Disk': HardDrive,
    'Active Deployments': Rocket,
};

// For resource metrics (CPU/Memory/Disk), rising = bad (red), falling = good (green)
const isResourceMetric = (label: string) => ['Avg CPU', 'Avg Memory', 'Avg Disk'].includes(label);

interface Props {
    metricsOverview?: MetricOverview[];
    services?: ServiceHealth[];
    recentAlerts?: Alert[];
    deploymentStats?: DeploymentStats;
}

function MetricCard({ metric }: { metric: MetricOverview }) {
    const Icon = metricIconMap[metric.label] || Activity;
    const isResource = isResourceMetric(metric.label);
    const isNA = metric.value === 'N/A';

    // For resource metrics: up is bad (red), down is good (green)
    const trendColor = isResource
        ? metric.trend === 'up'
            ? 'text-danger'
            : metric.trend === 'down'
              ? 'text-success'
              : 'text-foreground-muted'
        : metric.trend === 'up'
          ? 'text-success'
          : metric.trend === 'down'
            ? 'text-danger'
            : 'text-foreground-muted';

    const sparklineColor = isResource
        ? metric.trend === 'up'
            ? 'rgb(239, 68, 68)'
            : 'rgb(52, 211, 153)'
        : 'rgb(99, 102, 241)';

    // Filter out null values for sparkline rendering
    const sparklineData = metric.data.filter((v): v is number => v !== null);
    const hasSparklineData = sparklineData.length > 0 && sparklineData.some((v) => v > 0);

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${isNA ? 'bg-foreground-muted/10' : 'bg-primary/10'}`}>
                            <Icon className={`h-5 w-5 ${isNA ? 'text-foreground-muted' : 'text-primary'}`} />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">{metric.label}</p>
                            <p className={`text-2xl font-semibold ${isNA ? 'text-foreground-muted' : 'text-foreground'}`}>{metric.value}</p>
                        </div>
                    </div>
                    {metric.change && <span className={`text-sm font-medium ${trendColor}`}>{metric.change}</span>}
                </div>
                {hasSparklineData && <Sparkline data={sparklineData} color={sparklineColor} />}
                {isNA && (
                    <p className="text-xs text-foreground-subtle">No data available — server may be unreachable</p>
                )}
            </CardContent>
        </Card>
    );
}

function formatDuration(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return secs > 0 ? `${minutes}m ${secs}s` : `${minutes}m`;
}

function DeploymentStatsSection({ stats }: { stats: DeploymentStats }) {
    const rateColor = stats.successRate >= 90 ? 'text-success' : stats.successRate >= 70 ? 'text-warning' : 'text-danger';
    const rateBg = stats.successRate >= 90 ? 'bg-success/10' : stats.successRate >= 70 ? 'bg-warning/10' : 'bg-danger/10';

    return (
        <div>
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-foreground">Deployment Stats</h2>
                <span className="text-sm text-foreground-muted">Last 7 days</span>
            </div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="p-5">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${rateBg}`}>
                                <CheckCheck className={`h-5 w-5 ${rateColor}`} />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Success Rate</p>
                                <p className={`text-2xl font-semibold ${rateColor}`}>{stats.successRate}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Rocket className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Today / Week</p>
                                <p className="text-2xl font-semibold text-foreground">
                                    {stats.today}{' '}
                                    <span className="text-base font-normal text-foreground-muted">/ {stats.week}</span>
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Clock className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Avg Duration</p>
                                <p className="text-2xl font-semibold text-foreground">{formatDuration(stats.avgDuration)}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                <TrendingUp className="h-5 w-5 text-success" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Success / Failed</p>
                                <p className="text-2xl font-semibold text-foreground">
                                    <span className="text-success">{stats.success}</span>
                                    {' / '}
                                    <span className={stats.failed > 0 ? 'text-danger' : 'text-foreground-muted'}>{stats.failed}</span>
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function ServiceHealthCard({ service }: { service: ServiceHealth }) {
    const statusConfig = {
        healthy: {
            icon: CheckCircle,
            color: 'text-success',
            bg: 'bg-success/10',
            badge: 'success' as const,
        },
        degraded: {
            icon: AlertCircle,
            color: 'text-warning',
            bg: 'bg-warning/10',
            badge: 'warning' as const,
        },
        unreachable: {
            icon: WifiOff,
            color: 'text-warning',
            bg: 'bg-warning/10',
            badge: 'warning' as const,
        },
        down: {
            icon: XCircle,
            color: 'text-danger',
            bg: 'bg-danger/10',
            badge: 'danger' as const,
        },
    };

    const config = statusConfig[service.status] || statusConfig.down;
    const StatusIcon = config.icon;

    return (
        <Card className="transition-all duration-200 hover:border-border-hover">
            <CardContent className="p-5">
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-9 w-9 items-center justify-center rounded-lg ${config.bg}`}>
                            <StatusIcon className={`h-5 w-5 ${config.color}`} />
                        </div>
                        <h3 className="font-medium text-foreground">{service.name}</h3>
                    </div>
                    <Badge variant={config.badge}>{service.status}</Badge>
                </div>
                <div className="grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <p className="text-foreground-muted">Uptime</p>
                        <p className="font-medium text-foreground">{service.uptime}%</p>
                    </div>
                    <div>
                        <p className="text-foreground-muted">Latency</p>
                        <p className="font-medium text-foreground">{service.responseTime}ms</p>
                    </div>
                    <div>
                        <p className="text-foreground-muted">Errors</p>
                        <p className="font-medium text-foreground">{service.errorRate}%</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function AlertItem({ alert }: { alert: Alert }) {
    const severityConfig = {
        critical: {
            variant: 'danger' as const,
            icon: XCircle,
            color: 'text-danger',
        },
        warning: {
            variant: 'warning' as const,
            icon: AlertTriangle,
            color: 'text-warning',
        },
        info: {
            variant: 'info' as const,
            icon: AlertCircle,
            color: 'text-info',
        },
    };

    const config = severityConfig[alert.severity];
    const Icon = config.icon;

    return (
        <div className="flex items-start gap-3 rounded-lg border border-border bg-background-secondary/50 p-4 transition-colors hover:bg-background-secondary">
            <Icon className={`mt-0.5 h-4 w-4 ${config.color}`} />
            <div className="flex-1">
                <div className="mb-1 flex items-center gap-2">
                    <Badge variant={config.variant}>{alert.severity}</Badge>
                    <span className="text-sm font-medium text-foreground">{alert.service}</span>
                </div>
                <p className="text-sm text-foreground-muted">{alert.message}</p>
                <p className="mt-1 text-xs text-foreground-subtle">{alert.time}</p>
            </div>
        </div>
    );
}

export default function ObservabilityIndex({
    metricsOverview = [],
    services = [],
    recentAlerts = [],
    deploymentStats,
}: Props) {
    const [isRefreshing, setIsRefreshing] = React.useState(false);

    const handleRefresh = React.useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    }, []);

    // Auto-refresh every 60 seconds
    React.useEffect(() => {
        const interval = setInterval(() => {
            router.reload();
        }, 60000);
        return () => clearInterval(interval);
    }, []);

    return (
        <AppLayout title="Observability" breadcrumbs={[{ label: 'Observability' }]}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Observability Dashboard</h1>
                        <p className="text-foreground-muted">Monitor your infrastructure health and performance</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" onClick={handleRefresh} disabled={isRefreshing}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {isRefreshing ? 'Refreshing...' : 'Refresh'}
                        </Button>
                    </div>
                </div>

                {/* Metrics Overview */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {metricsOverview.length === 0 ? (
                        <div className="col-span-full p-12 text-center">
                            <Activity className="mx-auto h-12 w-12 text-foreground-muted" />
                            <h3 className="mt-4 text-lg font-medium text-foreground">No metrics available</h3>
                            <p className="mt-2 text-foreground-muted">Metrics will appear once servers report health data</p>
                        </div>
                    ) : (
                        metricsOverview.map((metric) => <MetricCard key={metric.label} metric={metric} />)
                    )}
                </div>

                {/* Deployment Stats */}
                {deploymentStats && <DeploymentStatsSection stats={deploymentStats} />}

                {/* Service Health Grid */}
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-foreground">Server Health</h2>
                        <Link href="/observability/metrics">
                            <Button variant="ghost" size="sm">
                                View All Metrics
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </div>
                    {services.length === 0 ? (
                        <Card className="p-12 text-center">
                            <Server className="mx-auto h-12 w-12 text-foreground-muted" />
                            <h3 className="mt-4 text-lg font-medium text-foreground">No servers monitored</h3>
                            <p className="mt-2 text-foreground-muted">Add a server to start monitoring</p>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {services.map((service) => (
                                <ServiceHealthCard key={service.id} service={service} />
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Recent Issues */}
                    <div>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-foreground">Recent Issues</h2>
                            <Link href="/observability/alerts">
                                <Button variant="ghost" size="sm">
                                    Manage Alerts
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                        {recentAlerts.length === 0 ? (
                            <Card className="p-12 text-center">
                                <CheckCircle className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No recent issues</h3>
                                <p className="mt-2 text-foreground-muted">All systems operating normally</p>
                            </Card>
                        ) : (
                            <div className="space-y-3">
                                {recentAlerts.map((alert) => (
                                    <AlertItem key={alert.id} alert={alert} />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Quick Links */}
                    <div>
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Quick Access</h2>
                        <div className="grid gap-3">
                            <Link
                                href="/observability/logs"
                                className="group flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-all hover:border-border-hover hover:bg-background-tertiary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Activity className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">Logs</p>
                                        <p className="text-sm text-foreground-muted">View centralized logs</p>
                                    </div>
                                </div>
                                <ArrowRight className="h-5 w-5 text-foreground-muted transition-transform group-hover:translate-x-1" />
                            </Link>
                            <Link
                                href="/observability/traces"
                                className="group flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-all hover:border-border-hover hover:bg-background-tertiary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Zap className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">Traces</p>
                                        <p className="text-sm text-foreground-muted">Distributed tracing</p>
                                    </div>
                                </div>
                                <ArrowRight className="h-5 w-5 text-foreground-muted transition-transform group-hover:translate-x-1" />
                            </Link>
                            <Link
                                href="/observability/metrics"
                                className="group flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-all hover:border-border-hover hover:bg-background-tertiary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <TrendingUp className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">Metrics</p>
                                        <p className="text-sm text-foreground-muted">Detailed metrics dashboard</p>
                                    </div>
                                </div>
                                <ArrowRight className="h-5 w-5 text-foreground-muted transition-transform group-hover:translate-x-1" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
