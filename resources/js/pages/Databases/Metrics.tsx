import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Select } from '@/components/ui';
import { ArrowLeft, Activity, Database, Cpu, HardDrive, Network, Clock, TrendingUp } from 'lucide-react';
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

    // Mock metrics data - in real app, fetch from backend
    const metrics = {
        cpu: {
            current: 42.5,
            average: 38.2,
            peak: 87.3,
            data: generateMockData(50),
        },
        memory: {
            current: 1.8,
            total: 4.0,
            percentage: 45,
            data: generateMockData(50),
        },
        storage: {
            used: 12.4,
            total: 50,
            percentage: 24.8,
            data: generateMockData(50),
        },
        connections: {
            current: 24,
            max: 100,
            percentage: 24,
            data: generateMockData(50),
        },
        queries: {
            perSecond: 1240,
            total: 4_328_912,
            slow: 12,
            data: generateMockData(50),
        },
        network: {
            in: 2.4,
            out: 1.8,
            data: generateMockData(50),
        },
    };

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

            {/* Overview Cards */}
            <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <MetricCard
                    title="CPU Usage"
                    current={`${metrics.cpu.current}%`}
                    subtitle={`Avg: ${metrics.cpu.average}% | Peak: ${metrics.cpu.peak}%`}
                    icon={Cpu}
                    color="text-primary"
                    bgColor="bg-primary/10"
                    trend="+2.3%"
                />
                <MetricCard
                    title="Memory"
                    current={`${metrics.memory.current} GB`}
                    subtitle={`${metrics.memory.percentage}% of ${metrics.memory.total} GB`}
                    icon={Activity}
                    color="text-info"
                    bgColor="bg-info/10"
                    trend="+5.1%"
                />
                <MetricCard
                    title="Storage"
                    current={`${metrics.storage.used} GB`}
                    subtitle={`${metrics.storage.percentage}% of ${metrics.storage.total} GB`}
                    icon={HardDrive}
                    color="text-success"
                    bgColor="bg-success/10"
                    trend="+0.8%"
                />
                <MetricCard
                    title="Active Connections"
                    current={metrics.connections.current.toString()}
                    subtitle={`${metrics.connections.percentage}% of ${metrics.connections.max} max`}
                    icon={Network}
                    color="text-warning"
                    bgColor="bg-warning/10"
                    trend="-1.2%"
                />
                <MetricCard
                    title="Queries/sec"
                    current={metrics.queries.perSecond.toLocaleString()}
                    subtitle={`${metrics.queries.total.toLocaleString()} total | ${metrics.queries.slow} slow`}
                    icon={Database}
                    color="text-danger"
                    bgColor="bg-danger/10"
                    trend="+12.4%"
                />
                <MetricCard
                    title="Network I/O"
                    current={`${metrics.network.in + metrics.network.out} MB/s`}
                    subtitle={`In: ${metrics.network.in} MB/s | Out: ${metrics.network.out} MB/s`}
                    icon={TrendingUp}
                    color="text-primary"
                    bgColor="bg-primary/10"
                    trend="+8.7%"
                />
            </div>

            {/* Detailed Charts */}
            <div className="space-y-6">
                <ChartCard
                    title="CPU Usage Over Time"
                    data={metrics.cpu.data}
                    unit="%"
                    color="rgb(var(--color-primary))"
                />
                <ChartCard
                    title="Memory Usage Over Time"
                    data={metrics.memory.data}
                    unit="GB"
                    color="rgb(var(--color-info))"
                />
                <ChartCard
                    title="Active Connections Over Time"
                    data={metrics.connections.data}
                    unit=""
                    color="rgb(var(--color-warning))"
                />
                <ChartCard
                    title="Query Rate Over Time"
                    data={metrics.queries.data}
                    unit=" queries/sec"
                    color="rgb(var(--color-danger))"
                />
            </div>
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
                        <TrendingUp className={`h-3.5 w-3.5 ${isPositive ? 'text-success' : 'text-danger'}`} />
                        <span className={`text-xs font-medium ${isPositive ? 'text-success' : 'text-danger'}`}>
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
    const maxValue = Math.max(...data.map(d => d.value));
    const minValue = Math.min(...data.map(d => d.value));
    const avgValue = data.reduce((sum, d) => sum + d.value, 0) / data.length;

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
function generateMockData(points: number): MetricData[] {
    const data: MetricData[] = [];
    const now = Date.now();

    for (let i = 0; i < points; i++) {
        data.push({
            timestamp: new Date(now - (points - i) * 60000).toISOString(),
            value: Math.random() * 80 + 20, // Random value between 20-100
        });
    }

    return data;
}

function generateLinePath(data: MetricData[], maxValue: number): string {
    const points = data.map((d, i) => {
        const x = (i / (data.length - 1)) * 1000;
        const y = 300 - (d.value / maxValue) * 300;
        return `${x},${y}`;
    });

    return `M ${points.join(' L ')}`;
}

function generateAreaPath(data: MetricData[], maxValue: number): string {
    const points = data.map((d, i) => {
        const x = (i / (data.length - 1)) * 1000;
        const y = 300 - (d.value / maxValue) * 300;
        return `${x},${y}`;
    });

    return `M 0,300 L ${points.join(' L ')} L 1000,300 Z`;
}
