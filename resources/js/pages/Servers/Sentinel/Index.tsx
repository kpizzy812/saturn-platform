import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { LineChart } from '@/components/ui/Chart';
import {
    Activity,
    Cpu,
    MemoryStick,
    HardDrive,
    AlertTriangle,
    CheckCircle2,
    RefreshCw,
    Clock,
    Server as ServerIcon
} from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import type { Server } from '@/types';
import { Link } from '@inertiajs/react';

interface Props {
    server: Server;
}

interface MetricCardProps {
    title: string;
    value: string;
    percentage: number;
    icon: React.ReactNode;
    color: 'info' | 'warning' | 'danger' | 'success';
    trend?: number[];
}

function MetricCard({ title, value, percentage, icon, color, trend }: MetricCardProps) {
    const colorClasses = {
        info: {
            bg: 'bg-info/10',
            text: 'text-info',
            border: 'border-info/20',
        },
        warning: {
            bg: 'bg-warning/10',
            text: 'text-warning',
            border: 'border-warning/20',
        },
        danger: {
            bg: 'bg-danger/10',
            text: 'text-danger',
            border: 'border-danger/20',
        },
        success: {
            bg: 'bg-success/10',
            text: 'text-success',
            border: 'border-success/20',
        },
    };

    const config = colorClasses[color];
    const getStatusColor = () => {
        if (percentage >= 90) return 'danger';
        if (percentage >= 75) return 'warning';
        return 'success';
    };

    const statusColor = getStatusColor();

    return (
        <Card>
            <CardContent className="p-5">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${config.bg}`}>
                            <div className={config.text}>{icon}</div>
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">{title}</p>
                            <p className="mt-1 text-2xl font-bold text-foreground">{value}</p>
                        </div>
                    </div>
                    <Badge variant={statusColor}>{percentage}%</Badge>
                </div>
                {trend && trend.length > 0 && (
                    <div className="mt-4">
                        <LineChart
                            data={trend.map((val, i) => ({ label: `${i}`, value: val }))}
                            height={60}
                            color={
                                statusColor === 'danger'
                                    ? 'rgb(239, 68, 68)'
                                    : statusColor === 'warning'
                                    ? 'rgb(245, 158, 11)'
                                    : 'rgb(34, 197, 94)'
                            }
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface AlertItemProps {
    title: string;
    message: string;
    severity: 'critical' | 'warning' | 'info';
    timestamp: string;
}

function AlertItem({ title, message, severity, timestamp }: AlertItemProps) {
    const severityConfig = {
        critical: {
            icon: <AlertTriangle className="h-4 w-4" />,
            badge: 'danger' as const,
            iconBg: 'bg-danger/10',
            iconColor: 'text-danger',
        },
        warning: {
            icon: <AlertTriangle className="h-4 w-4" />,
            badge: 'warning' as const,
            iconBg: 'bg-warning/10',
            iconColor: 'text-warning',
        },
        info: {
            icon: <Activity className="h-4 w-4" />,
            badge: 'info' as const,
            iconBg: 'bg-info/10',
            iconColor: 'text-info',
        },
    };

    const config = severityConfig[severity];

    return (
        <div className="flex items-start gap-3 rounded-lg border border-border/50 bg-background-secondary/30 p-4 transition-colors hover:border-border">
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${config.iconBg}`}>
                <div className={config.iconColor}>{config.icon}</div>
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <h4 className="font-medium text-foreground">{title}</h4>
                    <Badge variant={config.badge} size="sm">{severity}</Badge>
                </div>
                <p className="mt-1 text-sm text-foreground-muted">{message}</p>
                <div className="mt-2 flex items-center gap-1.5 text-xs text-foreground-subtle">
                    <Clock className="h-3 w-3" />
                    <span>{timestamp}</span>
                </div>
            </div>
        </div>
    );
}

export default function SentinelIndex({ server }: Props) {
    const [autoRefresh, setAutoRefresh] = useState(true);

    const {
        metrics,
        alerts,
        isLoading,
        error,
        refetch
    } = useSentinelMetrics({
        serverUuid: server.uuid,
        autoRefresh,
        refreshInterval: 5000,
    });

    const healthStatus = metrics ? (
        metrics.cpu.percentage < 75 && metrics.memory.percentage < 85 && metrics.disk.percentage < 90
            ? 'healthy'
            : metrics.cpu.percentage >= 90 || metrics.memory.percentage >= 95 || metrics.disk.percentage >= 95
            ? 'critical'
            : 'degraded'
    ) : 'unknown';

    const healthConfig = {
        healthy: {
            icon: <CheckCircle2 className="h-5 w-5" />,
            text: 'Healthy',
            color: 'text-success',
            bg: 'bg-success/10',
            dot: 'bg-success',
        },
        degraded: {
            icon: <AlertTriangle className="h-5 w-5" />,
            text: 'Degraded',
            color: 'text-warning',
            bg: 'bg-warning/10',
            dot: 'bg-warning',
        },
        critical: {
            icon: <AlertTriangle className="h-5 w-5" />,
            text: 'Critical',
            color: 'text-danger',
            bg: 'bg-danger/10',
            dot: 'bg-danger',
        },
        unknown: {
            icon: <Activity className="h-5 w-5" />,
            text: 'Unknown',
            color: 'text-foreground-muted',
            bg: 'bg-background-tertiary',
            dot: 'bg-foreground-subtle',
        },
    };

    const health = healthConfig[healthStatus];

    return (
        <AppLayout
            title={`Sentinel - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Sentinel' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-background-secondary">
                            <ServerIcon className="h-6 w-6 text-foreground-muted" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-foreground">Server Health Monitor</h1>
                            <p className="mt-0.5 text-sm text-foreground-muted">
                                Real-time monitoring for {server.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setAutoRefresh(!autoRefresh)}
                            className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                autoRefresh
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                            }`}
                        >
                            <RefreshCw className={`h-4 w-4 ${autoRefresh ? 'animate-spin' : ''}`} />
                            Auto-refresh {autoRefresh ? 'On' : 'Off'}
                        </button>
                        <button
                            onClick={() => refetch()}
                            disabled={isLoading}
                            className="flex items-center gap-2 rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90 disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                    </div>
                </div>

                {/* Health Status */}
                <Card className="mb-6">
                    <CardContent className="p-5">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${health.bg}`}>
                                    <div className={health.color}>{health.icon}</div>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-lg font-semibold text-foreground">Server Status</h2>
                                        <div className={`h-2 w-2 rounded-full ${health.dot} animate-pulse`} />
                                    </div>
                                    <p className="mt-0.5 text-sm text-foreground-muted">
                                        {health.text} - All systems {healthStatus === 'healthy' ? 'operational' : 'require attention'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <Link
                                    href={`/servers/${server.uuid}/sentinel/metrics`}
                                    className="text-sm font-medium text-primary hover:text-primary/80"
                                >
                                    View Detailed Metrics →
                                </Link>
                                <Link
                                    href={`/servers/${server.uuid}/sentinel/alerts`}
                                    className="text-sm font-medium text-primary hover:text-primary/80"
                                >
                                    Manage Alerts →
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {error && (
                    <div className="mb-6 rounded-lg border border-danger/50 bg-danger/10 p-4">
                        <p className="text-sm font-medium text-danger">
                            Failed to load metrics: {error.message}
                        </p>
                    </div>
                )}

                {/* Metrics Grid */}
                <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <MetricCard
                        title="CPU Usage"
                        value={metrics?.cpu.current || '0%'}
                        percentage={metrics?.cpu.percentage || 0}
                        icon={<Cpu className="h-6 w-6" />}
                        color="info"
                        trend={metrics?.cpu.trend}
                    />
                    <MetricCard
                        title="Memory"
                        value={metrics?.memory.current || '0 GB'}
                        percentage={metrics?.memory.percentage || 0}
                        icon={<MemoryStick className="h-6 w-6" />}
                        color="warning"
                        trend={metrics?.memory.trend}
                    />
                    <MetricCard
                        title="Disk Usage"
                        value={metrics?.disk.current || '0 GB'}
                        percentage={metrics?.disk.percentage || 0}
                        icon={<HardDrive className="h-6 w-6" />}
                        color="success"
                        trend={metrics?.disk.trend}
                    />
                </div>

                {/* Active Alerts */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Active Alerts</CardTitle>
                            {alerts && alerts.length > 0 && (
                                <Badge variant="warning">{alerts.length} active</Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {alerts && alerts.length > 0 ? (
                            <div className="space-y-3">
                                {alerts.slice(0, 5).map((alert, index) => (
                                    <AlertItem
                                        key={index}
                                        title={alert.title}
                                        message={alert.message}
                                        severity={alert.severity}
                                        timestamp={alert.timestamp}
                                    />
                                ))}
                                {alerts.length > 5 && (
                                    <Link
                                        href={`/servers/${server.uuid}/sentinel/alerts`}
                                        className="mt-4 block text-center text-sm font-medium text-primary hover:text-primary/80"
                                    >
                                        View all {alerts.length} alerts →
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-success/10">
                                    <CheckCircle2 className="h-8 w-8 text-success" />
                                </div>
                                <h3 className="mt-4 text-lg font-medium text-foreground">No Active Alerts</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Your server is running smoothly
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
