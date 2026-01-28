import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { LineChart } from '@/components/ui/Chart';
import {
    Activity,
    Cpu,
    MemoryStick,
    HardDrive,
    RefreshCw,
    ArrowLeft,
    TrendingUp,
    TrendingDown,
    Server as ServerIcon
} from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import type { Server } from '@/types';

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
    trendDirection?: 'up' | 'down';
}

function MetricCard({ title, value, percentage, icon, color, trend, trendDirection }: MetricCardProps) {
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
                    <div className="flex flex-col items-end gap-1">
                        <Badge variant={statusColor}>{percentage}%</Badge>
                        {trendDirection && (
                            <div className={`flex items-center gap-1 text-xs ${
                                trendDirection === 'up' ? 'text-danger' : 'text-success'
                            }`}>
                                {trendDirection === 'up' ? (
                                    <TrendingUp className="h-3 w-3" />
                                ) : (
                                    <TrendingDown className="h-3 w-3" />
                                )}
                            </div>
                        )}
                    </div>
                </div>
                {trend && trend.length > 0 && (
                    <div className="mt-4">
                        <LineChart
                            data={trend.map((val, i) => ({ label: `${i}`, value: val }))}
                            height={80}
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

export default function ServerMetricsIndex({ server }: Props) {
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [timeRange, setTimeRange] = useState<'1h' | '6h' | '24h' | '7d'>('1h');

    const {
        metrics,
        isLoading,
        error,
        refetch
    } = useSentinelMetrics({
        serverUuid: server.uuid,
        autoRefresh,
        refreshInterval: 5000,
    });

    const getTrendDirection = (trend: number[] | undefined): 'up' | 'down' | undefined => {
        if (!trend || trend.length < 2) return undefined;
        const recent = trend.slice(-5);
        const avg = recent.reduce((a, b) => a + b, 0) / recent.length;
        const lastValue = recent[recent.length - 1];
        return lastValue > avg ? 'up' : 'down';
    };

    return (
        <AppLayout
            title={`${server.name} - Metrics`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Metrics' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-6">
                    <Link
                        href={`/servers/${server.uuid}`}
                        className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Server
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-background-secondary">
                                <Activity className="h-6 w-6 text-foreground-muted" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold text-foreground">Server Metrics</h1>
                                <p className="mt-0.5 text-sm text-foreground-muted">
                                    Real-time performance monitoring for {server.name}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-1">
                                {(['1h', '6h', '24h', '7d'] as const).map((range) => (
                                    <button
                                        key={range}
                                        onClick={() => setTimeRange(range)}
                                        className={`rounded px-3 py-1.5 text-sm font-medium transition-colors ${
                                            timeRange === range
                                                ? 'bg-foreground text-background'
                                                : 'text-foreground-muted hover:text-foreground'
                                        }`}
                                    >
                                        {range}
                                    </button>
                                ))}
                            </div>
                            <button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                    autoRefresh
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                }`}
                            >
                                <RefreshCw className={`h-4 w-4 ${autoRefresh ? 'animate-spin' : ''}`} />
                                Auto-refresh
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
                </div>

                {error && (
                    <div className="mb-6 rounded-lg border border-danger/50 bg-danger/10 p-4">
                        <p className="text-sm font-medium text-danger">
                            Failed to load metrics: {error.message}
                        </p>
                    </div>
                )}

                {/* Metrics Grid */}
                <div className="mb-6 grid gap-4 lg:grid-cols-3">
                    <MetricCard
                        title="CPU Usage"
                        value={metrics?.cpu.current || '0%'}
                        percentage={metrics?.cpu.percentage || 0}
                        icon={<Cpu className="h-6 w-6" />}
                        color="info"
                        trend={metrics?.cpu.trend}
                        trendDirection={getTrendDirection(metrics?.cpu.trend)}
                    />
                    <MetricCard
                        title="Memory Usage"
                        value={metrics?.memory.current || '0 GB'}
                        percentage={metrics?.memory.percentage || 0}
                        icon={<MemoryStick className="h-6 w-6" />}
                        color="warning"
                        trend={metrics?.memory.trend}
                        trendDirection={getTrendDirection(metrics?.memory.trend)}
                    />
                    <MetricCard
                        title="Disk Usage"
                        value={metrics?.disk.current || '0 GB'}
                        percentage={metrics?.disk.percentage || 0}
                        icon={<HardDrive className="h-6 w-6" />}
                        color="success"
                        trend={metrics?.disk.trend}
                        trendDirection={getTrendDirection(metrics?.disk.trend)}
                    />
                </div>

                {/* Detailed Charts */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>CPU History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {metrics?.cpu.trend && metrics.cpu.trend.length > 0 ? (
                                <LineChart
                                    data={metrics.cpu.trend.map((val, i) => ({
                                        label: `${i}m`,
                                        value: val
                                    }))}
                                    height={200}
                                    color="rgb(59, 130, 246)"
                                />
                            ) : (
                                <div className="flex h-48 items-center justify-center text-sm text-foreground-muted">
                                    No data available
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Memory History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {metrics?.memory.trend && metrics.memory.trend.length > 0 ? (
                                <LineChart
                                    data={metrics.memory.trend.map((val, i) => ({
                                        label: `${i}m`,
                                        value: val
                                    }))}
                                    height={200}
                                    color="rgb(245, 158, 11)"
                                />
                            ) : (
                                <div className="flex h-48 items-center justify-center text-sm text-foreground-muted">
                                    No data available
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Additional Info */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>About Metrics</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-foreground-muted">
                        <p>
                            Server metrics are collected in real-time and updated every 5 seconds when auto-refresh is enabled.
                        </p>
                        <ul className="ml-4 list-disc space-y-1">
                            <li>CPU usage shows the current processor utilization percentage</li>
                            <li>Memory usage displays RAM consumption across all processes</li>
                            <li>Disk usage shows total disk space used vs. available</li>
                            <li>Trends indicate performance over the selected time range</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
