import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Input } from '@/components/ui';
import { Download, Activity, Cpu, HardDrive, Network, TrendingUp, Bell, Settings } from 'lucide-react';

interface UsageMetric {
    label: string;
    current: number;
    limit: number;
    unit: string;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
}

interface ServiceUsage {
    id: number;
    name: string;
    cpu: number;
    memory: number;
    network: number;
    storage: number;
    cost: number;
}

interface UsageAlert {
    id: number;
    metric: string;
    threshold: number;
    enabled: boolean;
}

type TimeRange = '7d' | '30d' | '90d';

const usageMetrics: UsageMetric[] = [
    {
        label: 'CPU Hours',
        current: 342,
        limit: 500,
        unit: 'hours/mo',
        icon: Cpu,
        color: 'primary',
    },
    {
        label: 'Memory',
        current: 45.2,
        limit: 100,
        unit: 'GB/mo',
        icon: Activity,
        color: 'success',
    },
    {
        label: 'Network',
        current: 1240,
        limit: 2500,
        unit: 'GB/mo',
        icon: Network,
        color: 'info',
    },
    {
        label: 'Storage',
        current: 25.8,
        limit: 50,
        unit: 'GB',
        icon: HardDrive,
        color: 'warning',
    },
];

const serviceUsage: ServiceUsage[] = [
    { id: 1, name: 'Production API', cpu: 142, memory: 18.5, network: 520, storage: 12.4, cost: 45.20 },
    { id: 2, name: 'Marketing Site', cpu: 98, memory: 12.3, network: 380, storage: 6.8, cost: 28.40 },
    { id: 3, name: 'Staging Environment', cpu: 67, memory: 9.2, network: 240, storage: 4.2, cost: 18.90 },
    { id: 4, name: 'Documentation', cpu: 35, memory: 5.2, network: 100, storage: 2.4, cost: 9.50 },
];

const defaultAlerts: UsageAlert[] = [
    { id: 1, metric: 'CPU Hours', threshold: 80, enabled: true },
    { id: 2, metric: 'Memory', threshold: 80, enabled: true },
    { id: 3, metric: 'Network', threshold: 90, enabled: false },
    { id: 4, metric: 'Storage', threshold: 90, enabled: true },
];

// Mock chart data generator
const generateChartData = (days: number, max: number) => {
    return Array.from({ length: days }, (_, i) => ({
        day: i + 1,
        value: Math.floor(Math.random() * max) + max * 0.1,
    }));
};

export default function BillingUsage() {
    const [timeRange, setTimeRange] = React.useState<TimeRange>('30d');
    const [alerts, setAlerts] = React.useState<UsageAlert[]>(defaultAlerts);

    const days = timeRange === '7d' ? 7 : timeRange === '30d' ? 30 : 90;
    const cpuData = generateChartData(days, 30);
    const memoryData = generateChartData(days, 5);
    const networkData = generateChartData(days, 100);
    const storageData = generateChartData(days, 2);

    const getUsagePercentage = (current: number, limit: number) => {
        return Math.min((current / limit) * 100, 100);
    };

    const getUsageColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-danger';
        if (percentage >= 70) return 'bg-warning';
        return 'bg-primary';
    };

    const toggleAlert = (id: number) => {
        setAlerts((prev) =>
            prev.map((alert) =>
                alert.id === id ? { ...alert, enabled: !alert.enabled } : alert
            )
        );
    };

    const updateAlertThreshold = (id: number, threshold: number) => {
        setAlerts((prev) =>
            prev.map((alert) => (alert.id === id ? { ...alert, threshold } : alert))
        );
    };

    const handleExportReport = () => {
        console.log('Exporting usage report for:', timeRange);
    };

    const totalCost = serviceUsage.reduce((sum, service) => sum + service.cost, 0);
    const projectedCost = (totalCost / new Date().getDate()) * 30;

    return (
        <SettingsLayout activeSection="billing">
            <div className="space-y-6">
                {/* Header with Controls */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle>Detailed Usage Analytics</CardTitle>
                                <CardDescription>
                                    Monitor your resource consumption and costs
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-3">
                                {/* Time Range Selector */}
                                <div className="flex items-center gap-1 rounded-lg bg-background-secondary p-1">
                                    {(['7d', '30d', '90d'] as TimeRange[]).map((range) => (
                                        <button
                                            key={range}
                                            onClick={() => setTimeRange(range)}
                                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${
                                                timeRange === range
                                                    ? 'bg-primary text-white'
                                                    : 'text-foreground-muted hover:text-foreground'
                                            }`}
                                        >
                                            {range === '7d' ? '7 Days' : range === '30d' ? '30 Days' : '90 Days'}
                                        </button>
                                    ))}
                                </div>
                                <Button variant="secondary" onClick={handleExportReport}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Export Report
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Usage Overview */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {usageMetrics.map((metric) => {
                        const percentage = getUsagePercentage(metric.current, metric.limit);
                        const Icon = metric.icon;

                        return (
                            <Card key={metric.label}>
                                <CardContent className="pt-6">
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <div className={`flex h-10 w-10 items-center justify-center rounded-lg bg-${metric.color}/10`}>
                                                <Icon className={`h-5 w-5 text-${metric.color}`} />
                                            </div>
                                            <Badge variant={percentage >= 90 ? 'danger' : percentage >= 70 ? 'warning' : 'success'}>
                                                {percentage.toFixed(0)}%
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-foreground">{metric.label}</p>
                                            <p className="text-xs text-foreground-muted">
                                                {metric.current} / {metric.limit} {metric.unit}
                                            </p>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-background-tertiary">
                                            <div
                                                className={`h-full transition-all ${getUsageColor(percentage)}`}
                                                style={{ width: `${percentage}%` }}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Usage Charts */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* CPU Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">CPU Usage</CardTitle>
                            <CardDescription>Daily CPU hours consumption</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-tertiary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {cpuData.map((data, index) => (
                                        <div key={index} className="group relative flex-1 cursor-pointer">
                                            <div
                                                className="w-full rounded-t bg-primary/80 transition-all group-hover:bg-primary"
                                                style={{ height: `${(data.value / 35) * 100}%` }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.value.toFixed(1)}h
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Memory Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Memory Usage</CardTitle>
                            <CardDescription>Daily memory consumption</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-tertiary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {memoryData.map((data, index) => (
                                        <div key={index} className="group relative flex-1 cursor-pointer">
                                            <div
                                                className="w-full rounded-t bg-success/80 transition-all group-hover:bg-success"
                                                style={{ height: `${(data.value / 6) * 100}%` }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.value.toFixed(1)} GB
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Network Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Network Usage</CardTitle>
                            <CardDescription>Daily network transfer</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-tertiary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {networkData.map((data, index) => (
                                        <div key={index} className="group relative flex-1 cursor-pointer">
                                            <div
                                                className="w-full rounded-t bg-info/80 transition-all group-hover:bg-info"
                                                style={{ height: `${(data.value / 120) * 100}%` }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.value.toFixed(1)} GB
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Storage Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Storage Usage</CardTitle>
                            <CardDescription>Daily storage consumption</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-tertiary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {storageData.map((data, index) => (
                                        <div key={index} className="group relative flex-1 cursor-pointer">
                                            <div
                                                className="w-full rounded-t bg-warning/80 transition-all group-hover:bg-warning"
                                                style={{ height: `${(data.value / 2.5) * 100}%` }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.value.toFixed(1)} GB
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Per-Service Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle>Usage by Service</CardTitle>
                        <CardDescription>
                            Resource consumption breakdown for each service
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b border-border">
                                    <tr className="text-left text-sm text-foreground-muted">
                                        <th className="pb-3 font-medium">Service</th>
                                        <th className="pb-3 font-medium">CPU Hours</th>
                                        <th className="pb-3 font-medium">Memory (GB)</th>
                                        <th className="pb-3 font-medium">Network (GB)</th>
                                        <th className="pb-3 font-medium">Storage (GB)</th>
                                        <th className="pb-3 font-medium text-right">Cost</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {serviceUsage.map((service) => (
                                        <tr key={service.id} className="text-sm">
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <Activity className="h-4 w-4 text-foreground-muted" />
                                                    <span className="font-medium text-foreground">{service.name}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 text-foreground-muted">{service.cpu}</td>
                                            <td className="py-3 text-foreground-muted">{service.memory.toFixed(1)}</td>
                                            <td className="py-3 text-foreground-muted">{service.network}</td>
                                            <td className="py-3 text-foreground-muted">{service.storage.toFixed(1)}</td>
                                            <td className="py-3 text-right font-medium text-foreground">
                                                ${service.cost.toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t border-border">
                                    <tr className="text-sm font-semibold">
                                        <td className="pt-3 text-foreground">Total</td>
                                        <td className="pt-3 text-foreground-muted">
                                            {serviceUsage.reduce((sum, s) => sum + s.cpu, 0)}
                                        </td>
                                        <td className="pt-3 text-foreground-muted">
                                            {serviceUsage.reduce((sum, s) => sum + s.memory, 0).toFixed(1)}
                                        </td>
                                        <td className="pt-3 text-foreground-muted">
                                            {serviceUsage.reduce((sum, s) => sum + s.network, 0)}
                                        </td>
                                        <td className="pt-3 text-foreground-muted">
                                            {serviceUsage.reduce((sum, s) => sum + s.storage, 0).toFixed(1)}
                                        </td>
                                        <td className="pt-3 text-right text-foreground">${totalCost.toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* Cost Projection */}
                        <div className="mt-6 rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <TrendingUp className="h-5 w-5 text-primary" />
                                    <div>
                                        <p className="font-medium text-foreground">Projected Monthly Cost</p>
                                        <p className="text-xs text-foreground-muted">
                                            Based on current usage trends
                                        </p>
                                    </div>
                                </div>
                                <p className="text-2xl font-bold text-primary">${projectedCost.toFixed(2)}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Usage Alerts Configuration */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <Bell className="h-5 w-5 text-foreground-muted" />
                            <div>
                                <CardTitle>Usage Alerts</CardTitle>
                                <CardDescription>
                                    Get notified when you reach usage thresholds
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {alerts.map((alert) => (
                                <div
                                    key={alert.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                >
                                    <div className="flex items-center gap-4">
                                        <button
                                            onClick={() => toggleAlert(alert.id)}
                                            className={`flex h-10 w-10 items-center justify-center rounded-lg transition-colors ${
                                                alert.enabled
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-background-tertiary text-foreground-muted'
                                            }`}
                                        >
                                            <Bell className="h-5 w-5" />
                                        </button>
                                        <div>
                                            <p className="font-medium text-foreground">{alert.metric}</p>
                                            <p className="text-sm text-foreground-muted">
                                                Alert at {alert.threshold}% usage
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                type="number"
                                                value={alert.threshold}
                                                onChange={(e) =>
                                                    updateAlertThreshold(alert.id, parseInt(e.target.value))
                                                }
                                                className="w-20"
                                                min="50"
                                                max="100"
                                                disabled={!alert.enabled}
                                            />
                                            <span className="text-sm text-foreground-muted">%</span>
                                        </div>
                                        <Button
                                            variant={alert.enabled ? 'danger' : 'secondary'}
                                            size="sm"
                                            onClick={() => toggleAlert(alert.id)}
                                        >
                                            {alert.enabled ? 'Disable' : 'Enable'}
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
