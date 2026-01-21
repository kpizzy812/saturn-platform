import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button } from '@/components/ui';
import { Clock, HardDrive, TrendingUp, Download, DollarSign, Activity } from 'lucide-react';

interface UsageStats {
    buildMinutes: {
        used: number;
        limit: number;
        unit: string;
    };
    bandwidth: {
        used: number;
        limit: number;
        unit: string;
    };
    storage: {
        used: number;
        limit: number;
        unit: string;
    };
}

interface ProjectCost {
    id: number;
    name: string;
    buildMinutes: number;
    bandwidth: number;
    storage: number;
    cost: number;
}

const mockUsageStats: UsageStats = {
    buildMinutes: { used: 245, limit: 500, unit: 'minutes' },
    bandwidth: { used: 12.5, limit: 100, unit: 'GB' },
    storage: { used: 3.2, limit: 10, unit: 'GB' },
};

const mockProjectCosts: ProjectCost[] = [
    { id: 1, name: 'Production API', buildMinutes: 120, bandwidth: 6.2, storage: 1.5, cost: 24.50 },
    { id: 2, name: 'Marketing Site', buildMinutes: 65, bandwidth: 3.8, storage: 0.8, cost: 12.20 },
    { id: 3, name: 'Staging Environment', buildMinutes: 45, bandwidth: 2.1, storage: 0.7, cost: 8.40 },
    { id: 4, name: 'Documentation', buildMinutes: 15, bandwidth: 0.4, storage: 0.2, cost: 2.90 },
];

// Mock chart data (last 30 days)
const mockBuildMinutesData = Array.from({ length: 30 }, (_, i) => ({
    day: i + 1,
    minutes: Math.floor(Math.random() * 30) + 5,
}));

const mockBandwidthData = Array.from({ length: 30 }, (_, i) => ({
    day: i + 1,
    gb: Math.random() * 2 + 0.1,
}));

export default function UsageSettings() {
    const [usage] = React.useState<UsageStats>(mockUsageStats);
    const [projectCosts] = React.useState<ProjectCost[]>(mockProjectCosts);

    const getUsagePercentage = (used: number, limit: number) => {
        return Math.min((used / limit) * 100, 100);
    };

    const getUsageColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-danger';
        if (percentage >= 70) return 'bg-warning';
        return 'bg-primary';
    };

    const handleExportReport = () => {
        console.log('Exporting usage report...');
    };

    const totalCost = projectCosts.reduce((sum, project) => sum + project.cost, 0);

    return (
        <SettingsLayout activeSection="usage">
            <div className="space-y-6">
                {/* Current Period Usage */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Current Period Usage</CardTitle>
                                <CardDescription>
                                    March 1 - March 31, 2024
                                </CardDescription>
                            </div>
                            <Button variant="secondary" onClick={handleExportReport}>
                                <Download className="mr-2 h-4 w-4" />
                                Export Report
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-3">
                            {/* Build Minutes */}
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Clock className="h-5 w-5 text-primary" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground">Build Minutes</p>
                                        <p className="text-xs text-foreground-muted">
                                            {usage.buildMinutes.used} / {usage.buildMinutes.limit} {usage.buildMinutes.unit}
                                        </p>
                                    </div>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-background-tertiary">
                                    <div
                                        className={`h-full transition-all ${getUsageColor(
                                            getUsagePercentage(usage.buildMinutes.used, usage.buildMinutes.limit)
                                        )}`}
                                        style={{
                                            width: `${getUsagePercentage(usage.buildMinutes.used, usage.buildMinutes.limit)}%`,
                                        }}
                                    />
                                </div>
                                <p className="text-xs text-foreground-subtle">
                                    {getUsagePercentage(usage.buildMinutes.used, usage.buildMinutes.limit).toFixed(1)}% used
                                </p>
                            </div>

                            {/* Bandwidth */}
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                        <TrendingUp className="h-5 w-5 text-success" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground">Bandwidth</p>
                                        <p className="text-xs text-foreground-muted">
                                            {usage.bandwidth.used} / {usage.bandwidth.limit} {usage.bandwidth.unit}
                                        </p>
                                    </div>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-background-tertiary">
                                    <div
                                        className={`h-full transition-all ${getUsageColor(
                                            getUsagePercentage(usage.bandwidth.used, usage.bandwidth.limit)
                                        )}`}
                                        style={{
                                            width: `${getUsagePercentage(usage.bandwidth.used, usage.bandwidth.limit)}%`,
                                        }}
                                    />
                                </div>
                                <p className="text-xs text-foreground-subtle">
                                    {getUsagePercentage(usage.bandwidth.used, usage.bandwidth.limit).toFixed(1)}% used
                                </p>
                            </div>

                            {/* Storage */}
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                        <HardDrive className="h-5 w-5 text-warning" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground">Storage</p>
                                        <p className="text-xs text-foreground-muted">
                                            {usage.storage.used} / {usage.storage.limit} {usage.storage.unit}
                                        </p>
                                    </div>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-background-tertiary">
                                    <div
                                        className={`h-full transition-all ${getUsageColor(
                                            getUsagePercentage(usage.storage.used, usage.storage.limit)
                                        )}`}
                                        style={{
                                            width: `${getUsagePercentage(usage.storage.used, usage.storage.limit)}%`,
                                        }}
                                    />
                                </div>
                                <p className="text-xs text-foreground-subtle">
                                    {getUsagePercentage(usage.storage.used, usage.storage.limit).toFixed(1)}% used
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Usage Charts */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Build Minutes Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Build Minutes (Last 30 Days)</CardTitle>
                            <CardDescription>Daily build time usage</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-secondary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {mockBuildMinutesData.map((data, index) => (
                                        <div
                                            key={index}
                                            className="group relative flex-1 cursor-pointer"
                                        >
                                            <div
                                                className="w-full rounded-t bg-primary/80 transition-all group-hover:bg-primary"
                                                style={{
                                                    height: `${(data.minutes / 35) * 100}%`,
                                                }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.minutes}m
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-2 flex justify-between text-xs text-foreground-subtle">
                                <span>30 days ago</span>
                                <span>Today</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bandwidth Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Bandwidth (Last 30 Days)</CardTitle>
                            <CardDescription>Daily bandwidth usage</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="h-48 rounded-lg bg-background-secondary p-4">
                                <div className="flex h-full items-end justify-between gap-1">
                                    {mockBandwidthData.map((data, index) => (
                                        <div
                                            key={index}
                                            className="group relative flex-1 cursor-pointer"
                                        >
                                            <div
                                                className="w-full rounded-t bg-success/80 transition-all group-hover:bg-success"
                                                style={{
                                                    height: `${(data.gb / 2.5) * 100}%`,
                                                }}
                                            />
                                            <div className="absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded bg-foreground px-2 py-1 text-xs text-background group-hover:block">
                                                {data.gb.toFixed(1)} GB
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-2 flex justify-between text-xs text-foreground-subtle">
                                <span>30 days ago</span>
                                <span>Today</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Cost Breakdown */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Cost Breakdown by Project</CardTitle>
                                <CardDescription>
                                    Current billing period (March 2024)
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 rounded-lg bg-background-secondary px-4 py-2">
                                <DollarSign className="h-5 w-5 text-primary" />
                                <span className="text-2xl font-bold text-foreground">${totalCost.toFixed(2)}</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b border-border">
                                    <tr className="text-left text-sm text-foreground-muted">
                                        <th className="pb-3 font-medium">Project</th>
                                        <th className="pb-3 font-medium">Build Minutes</th>
                                        <th className="pb-3 font-medium">Bandwidth</th>
                                        <th className="pb-3 font-medium">Storage</th>
                                        <th className="pb-3 font-medium text-right">Cost</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {projectCosts.map((project) => (
                                        <tr key={project.id} className="text-sm">
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <Activity className="h-4 w-4 text-foreground-muted" />
                                                    <span className="font-medium text-foreground">{project.name}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 text-foreground-muted">{project.buildMinutes} min</td>
                                            <td className="py-3 text-foreground-muted">{project.bandwidth.toFixed(1)} GB</td>
                                            <td className="py-3 text-foreground-muted">{project.storage.toFixed(1)} GB</td>
                                            <td className="py-3 text-right font-medium text-foreground">
                                                ${project.cost.toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t border-border">
                                    <tr className="text-sm font-semibold">
                                        <td className="pt-3">Total</td>
                                        <td className="pt-3 text-foreground-muted">
                                            {projectCosts.reduce((sum, p) => sum + p.buildMinutes, 0)} min
                                        </td>
                                        <td className="pt-3 text-foreground-muted">
                                            {projectCosts.reduce((sum, p) => sum + p.bandwidth, 0).toFixed(1)} GB
                                        </td>
                                        <td className="pt-3 text-foreground-muted">
                                            {projectCosts.reduce((sum, p) => sum + p.storage, 0).toFixed(1)} GB
                                        </td>
                                        <td className="pt-3 text-right text-foreground">${totalCost.toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
