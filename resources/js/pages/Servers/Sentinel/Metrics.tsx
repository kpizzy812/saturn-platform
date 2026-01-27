import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { LineChart, BarChart } from '@/components/ui/Chart';
import {
    Cpu,
    MemoryStick,
    HardDrive,
    Network,
    Activity,
    RefreshCw,
    Download,
} from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import type { Server } from '@/types';

interface Props {
    server: Server;
}

type TimeRange = '1h' | '24h' | '7d' | '30d';

interface ProcessInfo {
    pid: number;
    name: string;
    cpu: number;
    memory: number;
    user: string;
}

interface ContainerStats {
    name: string;
    cpu: number;
    memory: number;
    network_in: string;
    network_out: string;
    status: 'running' | 'stopped' | 'restarting';
}

export default function SentinelMetrics({ server }: Props) {
    const [timeRange, setTimeRange] = useState<TimeRange>('24h');
    const [autoRefresh, setAutoRefresh] = useState(true);

    const {
        metrics,
        historicalData,
        processes,
        containers,
        isLoading,
        error,
        refetch,
    } = useSentinelMetrics({
        serverUuid: server.uuid,
        timeRange,
        autoRefresh,
        refreshInterval: 10000,
        includeProcesses: true,
        includeContainers: true,
    });

    const timeRangeOptions: { value: TimeRange; label: string }[] = [
        { value: '1h', label: '1 Hour' },
        { value: '24h', label: '24 Hours' },
        { value: '7d', label: '7 Days' },
        { value: '30d', label: '30 Days' },
    ];

    const exportMetrics = () => {
        // In production, this would generate a CSV or JSON export
        const data = {
            server: server.name,
            timeRange,
            metrics: historicalData,
            exportedAt: new Date().toISOString(),
        };

        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${server.name}-metrics-${timeRange}.json`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout
            title={`Metrics - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Sentinel', href: `/servers/${server.uuid}/sentinel` },
                { label: 'Metrics' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Detailed Metrics</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Historical performance data for {server.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={exportMetrics}
                            className="flex items-center gap-2 rounded-lg bg-background-secondary px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-background-tertiary"
                        >
                            <Download className="h-4 w-4" />
                            Export
                        </button>
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

                {/* Time Range Selector */}
                <Card className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-foreground">Time Range:</span>
                            <div className="flex items-center gap-2">
                                {timeRangeOptions.map((option) => (
                                    <button
                                        key={option.value}
                                        onClick={() => setTimeRange(option.value)}
                                        className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                            timeRange === option.value
                                                ? 'bg-foreground text-background'
                                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                        }`}
                                    >
                                        {option.label}
                                    </button>
                                ))}
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

                {/* Current Stats Summary */}
                <div className="mb-6 grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                    <Cpu className="h-5 w-5 text-info" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">CPU Usage</p>
                                    <p className="text-xl font-bold text-foreground">
                                        {metrics?.cpu.current || '0%'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                    <MemoryStick className="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Memory</p>
                                    <p className="text-xl font-bold text-foreground">
                                        {metrics?.memory.current || '0 GB'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <HardDrive className="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Disk</p>
                                    <p className="text-xl font-bold text-foreground">
                                        {metrics?.disk.current || '0 GB'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <Network className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Network</p>
                                    <p className="text-xl font-bold text-foreground">
                                        {metrics?.network?.current || '0 MB/s'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* CPU Chart */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Cpu className="h-5 w-5 text-info" />
                            CPU Usage Over Time
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p className="text-foreground-muted">Current</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {metrics?.cpu.current || '0%'}
                                </p>
                            </div>
                            <div>
                                <p className="text-foreground-muted">Average</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {historicalData?.cpu.average || '0%'}
                                </p>
                            </div>
                            <div>
                                <p className="text-foreground-muted">Peak</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {historicalData?.cpu.peak || '0%'}
                                </p>
                            </div>
                        </div>
                        {historicalData?.cpu.data && (
                            <LineChart
                                data={historicalData.cpu.data}
                                height={250}
                                color="rgb(99, 102, 241)"
                            />
                        )}
                    </CardContent>
                </Card>

                {/* Memory Chart */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MemoryStick className="h-5 w-5 text-warning" />
                            Memory Usage Over Time
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p className="text-foreground-muted">Current</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {metrics?.memory.current || '0 GB'}
                                </p>
                            </div>
                            <div>
                                <p className="text-foreground-muted">Average</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {historicalData?.memory.average || '0 GB'}
                                </p>
                            </div>
                            <div>
                                <p className="text-foreground-muted">Peak</p>
                                <p className="text-lg font-semibold text-foreground">
                                    {historicalData?.memory.peak || '0 GB'}
                                </p>
                            </div>
                        </div>
                        {historicalData?.memory.data && (
                            <LineChart
                                data={historicalData.memory.data}
                                height={250}
                                color="rgb(245, 158, 11)"
                            />
                        )}
                    </CardContent>
                </Card>

                {/* Disk and Network Charts Grid */}
                <div className="mb-6 grid gap-6 lg:grid-cols-2">
                    {/* Disk Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HardDrive className="h-5 w-5 text-success" />
                                Disk Usage
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-foreground-muted">Used</p>
                                    <p className="text-lg font-semibold text-foreground">
                                        {metrics?.disk.current || '0 GB'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-foreground-muted">Total</p>
                                    <p className="text-lg font-semibold text-foreground">
                                        {metrics?.disk.total || '0 GB'}
                                    </p>
                                </div>
                            </div>
                            {historicalData?.disk.data && (
                                <LineChart
                                    data={historicalData.disk.data}
                                    height={200}
                                    color="rgb(34, 197, 94)"
                                />
                            )}
                        </CardContent>
                    </Card>

                    {/* Network Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="h-5 w-5 text-primary" />
                                Network Traffic
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-foreground-muted">Inbound</p>
                                    <p className="text-lg font-semibold text-foreground">
                                        {metrics?.network?.in || '0 MB/s'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-foreground-muted">Outbound</p>
                                    <p className="text-lg font-semibold text-foreground">
                                        {metrics?.network?.out || '0 MB/s'}
                                    </p>
                                </div>
                            </div>
                            {historicalData?.network?.data && (
                                <LineChart
                                    data={historicalData.network.data}
                                    height={200}
                                    color="rgb(168, 85, 247)"
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Top Processes */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5" />
                            Top Processes
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {processes && processes.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border">
                                        <tr>
                                            <th className="pb-3 text-left font-medium text-foreground-muted">PID</th>
                                            <th className="pb-3 text-left font-medium text-foreground-muted">Process</th>
                                            <th className="pb-3 text-left font-medium text-foreground-muted">User</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">CPU %</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">Memory %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {processes.slice(0, 10).map((process: ProcessInfo) => (
                                            <tr key={process.pid} className="border-b border-border/50">
                                                <td className="py-3 text-foreground-muted">{process.pid}</td>
                                                <td className="py-3 font-medium text-foreground">{process.name}</td>
                                                <td className="py-3 text-foreground-muted">{process.user}</td>
                                                <td className="py-3 text-right text-foreground">{process.cpu}%</td>
                                                <td className="py-3 text-right text-foreground">{process.memory}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-foreground-muted">
                                No process data available
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Container Statistics */}
                <Card>
                    <CardHeader>
                        <CardTitle>Container Statistics</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {containers && containers.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border">
                                        <tr>
                                            <th className="pb-3 text-left font-medium text-foreground-muted">Container</th>
                                            <th className="pb-3 text-left font-medium text-foreground-muted">Status</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">CPU %</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">Memory %</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">Network In</th>
                                            <th className="pb-3 text-right font-medium text-foreground-muted">Network Out</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {containers.map((container: ContainerStats) => (
                                            <tr key={container.name} className="border-b border-border/50">
                                                <td className="py-3 font-medium text-foreground">{container.name}</td>
                                                <td className="py-3">
                                                    <Badge
                                                        variant={
                                                            container.status?.startsWith('running')
                                                                ? 'success'
                                                                : container.status?.startsWith('stopped')
                                                                ? 'secondary'
                                                                : 'warning'
                                                        }
                                                        size="sm"
                                                    >
                                                        {container.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right text-foreground">{container.cpu}%</td>
                                                <td className="py-3 text-right text-foreground">{container.memory}%</td>
                                                <td className="py-3 text-right text-foreground">{container.network_in}</td>
                                                <td className="py-3 text-right text-foreground">{container.network_out}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-foreground-muted">
                                No container data available
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
