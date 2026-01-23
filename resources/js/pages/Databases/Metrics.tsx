import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Select } from '@/components/ui';
import { ArrowLeft, Activity, Database, Cpu, HardDrive, Network, TrendingUp, AlertCircle, Loader2 } from 'lucide-react';
import { useDatabaseMetricsHistory } from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

interface MetricData {
    timestamp: string;
    value: number;
}

export default function DatabaseMetrics({ database }: Props) {
    const [timeRange, setTimeRange] = useState<'1h' | '6h' | '24h' | '7d' | '30d'>('24h');

    const { metrics, hasHistoricalData, isLoading, error } = useDatabaseMetricsHistory({
        uuid: database.uuid,
        timeRange,
        autoRefresh: true,
        refreshInterval: 60000, // Refresh every minute
    });

    return (
        <AppLayout
            title={`${database.name} - Metrics`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Metrics' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {database.name}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Database Metrics</h1>
                    <p className="text-foreground-muted">Performance and resource utilization metrics</p>
                </div>
                <Select
                    value={timeRange}
                    onChange={(e) => setTimeRange(e.target.value as typeof timeRange)}
                    options={[
                        { value: '1h', label: 'Last Hour' },
                        { value: '6h', label: 'Last 6 Hours' },
                        { value: '24h', label: 'Last 24 Hours' },
                        { value: '7d', label: 'Last 7 Days' },
                        { value: '30d', label: 'Last 30 Days' },
                    ]}
                />
            </div>

            {/* Loading State */}
            {isLoading && (
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    <span className="ml-3 text-foreground-muted">Loading metrics...</span>
                </div>
            )}

            {/* Error State */}
            {error && !isLoading && (
                <Card className="border-danger/20 bg-danger/5">
                    <CardContent className="flex items-center gap-3 p-6">
                        <AlertCircle className="h-5 w-5 text-danger" />
                        <div>
                            <p className="font-medium text-foreground">Failed to load metrics</p>
                            <p className="text-sm text-foreground-muted">{error}</p>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* No Historical Data Yet */}
            {!isLoading && !error && !hasHistoricalData && (
                <Card className="border-warning/20 bg-warning/5">
                    <CardContent className="flex items-center gap-3 p-6">
                        <AlertCircle className="h-5 w-5 text-warning" />
                        <div>
                            <p className="font-medium text-foreground">No historical data yet</p>
                            <p className="text-sm text-foreground-muted">
                                Metrics collection has started. Historical data will appear as metrics are collected every 5 minutes.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Metrics Content */}
            {!isLoading && !error && metrics && (
                <>
                    {/* Overview Cards */}
                    <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <MetricCard
                            title="CPU Usage"
                            current={`${metrics.cpu.current.toFixed(1)}%`}
                            subtitle={`Avg: ${metrics.cpu.average.toFixed(1)}% | Peak: ${metrics.cpu.peak.toFixed(1)}%`}
                            icon={Cpu}
                            color="text-primary"
                            bgColor="bg-primary/10"
                            trend={calculateTrend(metrics.cpu.data)}
                        />
                        <MetricCard
                            title="Memory"
                            current={`${metrics.memory.current.toFixed(2)} GB`}
                            subtitle={`${metrics.memory.percentage.toFixed(1)}% of ${metrics.memory.total.toFixed(1)} GB`}
                            icon={Activity}
                            color="text-info"
                            bgColor="bg-info/10"
                            trend={calculateTrend(metrics.memory.data)}
                        />
                        <MetricCard
                            title="Storage"
                            current={`${metrics.storage.used.toFixed(2)} GB`}
                            subtitle={`${metrics.storage.percentage.toFixed(1)}% of ${metrics.storage.total} GB`}
                            icon={HardDrive}
                            color="text-success"
                            bgColor="bg-success/10"
                        />
                        <MetricCard
                            title="Active Connections"
                            current={metrics.connections.current.toString()}
                            subtitle={`${metrics.connections.percentage.toFixed(1)}% of ${metrics.connections.max} max`}
                            icon={Network}
                            color="text-warning"
                            bgColor="bg-warning/10"
                            trend={calculateTrend(metrics.connections.data)}
                        />
                        <MetricCard
                            title="Queries/sec"
                            current={metrics.queries.perSecond.toLocaleString()}
                            subtitle={`${metrics.queries.total.toLocaleString()} total | ${metrics.queries.slow} slow`}
                            icon={Database}
                            color="text-danger"
                            bgColor="bg-danger/10"
                            trend={calculateTrend(metrics.queries.data)}
                        />
                        <MetricCard
                            title="Network I/O"
                            current={`${(metrics.network.in + metrics.network.out).toFixed(2)} MB/s`}
                            subtitle={`In: ${metrics.network.in.toFixed(2)} MB/s | Out: ${metrics.network.out.toFixed(2)} MB/s`}
                            icon={TrendingUp}
                            color="text-primary"
                            bgColor="bg-primary/10"
                            trend={calculateTrend(metrics.network.data)}
                        />
                    </div>

                    {/* Detailed Charts */}
                    {hasHistoricalData && (
                        <div className="space-y-6">
                            {metrics.cpu.data.length > 0 && (
                                <ChartCard
                                    title="CPU Usage Over Time"
                                    data={metrics.cpu.data}
                                    unit="%"
                                    color="rgb(var(--color-primary))"
                                />
                            )}
                            {metrics.memory.data.length > 0 && (
                                <ChartCard
                                    title="Memory Usage Over Time"
                                    data={metrics.memory.data}
                                    unit=" GB"
                                    color="rgb(var(--color-info))"
                                />
                            )}
                            {metrics.connections.data.length > 0 && (
                                <ChartCard
                                    title="Active Connections Over Time"
                                    data={metrics.connections.data}
                                    unit=""
                                    color="rgb(var(--color-warning))"
                                />
                            )}
                            {metrics.queries.data.length > 0 && (
                                <ChartCard
                                    title="Query Rate Over Time"
                                    data={metrics.queries.data}
                                    unit=" q/s"
                                    color="rgb(var(--color-danger))"
                                />
                            )}
                        </div>
                    )}
                </>
            )}
        </AppLayout>
    );
}

interface MetricCardProps {
    title: string;
    current: string;
    subtitle: string;
    icon: React.ElementType;
    color: string;
    bgColor: string;
    trend?: string;
}

function MetricCard({ title, current, subtitle, icon: Icon, color, bgColor, trend }: MetricCardProps) {
    const isPositive = trend?.startsWith('+');
    const isNegative = trend?.startsWith('-');

    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <p className="text-sm text-foreground-muted">{title}</p>
                        <p className="mt-2 text-3xl font-bold text-foreground">{current}</p>
                        <p className="mt-1 text-xs text-foreground-muted">{subtitle}</p>
                    </div>
                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${bgColor}`}>
                        <Icon className={`h-6 w-6 ${color}`} />
                    </div>
                </div>
                {trend && (
                    <div className="mt-4 flex items-center gap-1">
                        <TrendingUp className={`h-3.5 w-3.5 ${isPositive ? 'text-success' : isNegative ? 'text-danger' : 'text-foreground-muted'}`} />
                        <span className={`text-xs font-medium ${isPositive ? 'text-success' : isNegative ? 'text-danger' : 'text-foreground-muted'}`}>
                            {trend}
                        </span>
                        <span className="text-xs text-foreground-muted">vs last period</span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface ChartCardProps {
    title: string;
    data: MetricData[];
    unit: string;
    color: string;
}

function ChartCard({ title, data, unit, color }: ChartCardProps) {
    if (data.length === 0) {
        return null;
    }

    const values = data.map(d => d.value);
    const maxValue = Math.max(...values, 1); // Avoid division by zero
    const minValue = Math.min(...values);
    const avgValue = values.reduce((sum, v) => sum + v, 0) / values.length;

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">{title}</h3>
                    <div className="flex items-center gap-4 text-sm text-foreground-muted">
                        <span>Min: {minValue.toFixed(1)}{unit}</span>
                        <span>Avg: {avgValue.toFixed(1)}{unit}</span>
                        <span>Max: {maxValue.toFixed(1)}{unit}</span>
                    </div>
                </div>

                {/* Simple SVG Chart */}
                <div className="relative h-64 w-full">
                    <svg className="h-full w-full" viewBox="0 0 1000 300" preserveAspectRatio="none">
                        {/* Grid lines */}
                        <line x1="0" y1="0" x2="1000" y2="0" stroke="currentColor" strokeOpacity="0.1" />
                        <line x1="0" y1="75" x2="1000" y2="75" stroke="currentColor" strokeOpacity="0.1" />
                        <line x1="0" y1="150" x2="1000" y2="150" stroke="currentColor" strokeOpacity="0.1" />
                        <line x1="0" y1="225" x2="1000" y2="225" stroke="currentColor" strokeOpacity="0.1" />
                        <line x1="0" y1="300" x2="1000" y2="300" stroke="currentColor" strokeOpacity="0.1" />

                        {/* Area under curve */}
                        <path
                            d={generateAreaPath(data, maxValue)}
                            fill={color}
                            fillOpacity="0.1"
                        />

                        {/* Line */}
                        <path
                            d={generateLinePath(data, maxValue)}
                            stroke={color}
                            strokeWidth="2"
                            fill="none"
                        />
                    </svg>
                </div>
            </CardContent>
        </Card>
    );
}

// Helper functions
function generateLinePath(data: MetricData[], maxValue: number): string {
    if (data.length === 0) return '';

    const points = data.map((d, i) => {
        const x = (i / Math.max(data.length - 1, 1)) * 1000;
        const y = 300 - (d.value / maxValue) * 300;
        return `${x},${y}`;
    });

    return `M ${points.join(' L ')}`;
}

function generateAreaPath(data: MetricData[], maxValue: number): string {
    if (data.length === 0) return '';

    const points = data.map((d, i) => {
        const x = (i / Math.max(data.length - 1, 1)) * 1000;
        const y = 300 - (d.value / maxValue) * 300;
        return `${x},${y}`;
    });

    return `M 0,300 L ${points.join(' L ')} L 1000,300 Z`;
}

function calculateTrend(data: MetricData[]): string | undefined {
    if (data.length < 2) return undefined;

    const midpoint = Math.floor(data.length / 2);
    const firstHalf = data.slice(0, midpoint);
    const secondHalf = data.slice(midpoint);

    if (firstHalf.length === 0 || secondHalf.length === 0) return undefined;

    const firstAvg = firstHalf.reduce((sum, d) => sum + d.value, 0) / firstHalf.length;
    const secondAvg = secondHalf.reduce((sum, d) => sum + d.value, 0) / secondHalf.length;

    if (firstAvg === 0) return undefined;

    const change = ((secondAvg - firstAvg) / firstAvg) * 100;

    if (Math.abs(change) < 0.1) return undefined;

    return change > 0 ? `+${change.toFixed(1)}%` : `${change.toFixed(1)}%`;
}
