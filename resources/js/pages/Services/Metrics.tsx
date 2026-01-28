import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import { BarChart3, Cpu, MemoryStick, Network, Activity, RefreshCw, AlertCircle, Container } from 'lucide-react';
import { useServiceMetrics } from '@/hooks/useServiceMetrics';
import type { ContainerMetrics } from '@/hooks/useServiceMetrics';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

type TimeRange = '1h' | '6h' | '24h' | '7d';

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

export function MetricsTab({ service }: Props) {
    const [timeRange, setTimeRange] = useState<TimeRange>('24h');
    const [autoRefresh, setAutoRefresh] = useState(true);

    const { containers, summary, isLoading, error, refetch, lastUpdated } = useServiceMetrics({
        serviceUuid: service.uuid,
        autoRefresh,
        refreshInterval: 10000,
    });

    const timeRangeOptions: { value: TimeRange; label: string }[] = [
        { value: '1h', label: '1 Hour' },
        { value: '6h', label: '6 Hours' },
        { value: '24h', label: '24 Hours' },
        { value: '7d', label: '7 Days' },
    ];

    return (
        <div className="space-y-4">
            {/* Controls */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">Time Range:</span>
                            {timeRangeOptions.map((option) => (
                                <button
                                    key={option.value}
                                    onClick={() => setTimeRange(option.value)}
                                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                        timeRange === option.value
                                            ? 'bg-foreground text-background'
                                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            {lastUpdated && (
                                <span className="text-xs text-foreground-subtle">
                                    Updated {lastUpdated.toLocaleTimeString()}
                                </span>
                            )}
                            <button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    autoRefresh
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                }`}
                            >
                                <RefreshCw className={`h-4 w-4 ${autoRefresh ? 'animate-spin' : ''}`} />
                                Auto-refresh {autoRefresh ? 'On' : 'Off'}
                            </button>
                            <Button variant="secondary" size="sm" onClick={refetch}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Refresh Now
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Error State */}
            {error && !isLoading && containers.length === 0 && (
                <Card>
                    <CardContent className="p-8 text-center">
                        <AlertCircle className="mx-auto h-10 w-10 text-foreground-muted" />
                        <h3 className="mt-3 text-sm font-medium text-foreground">Unable to fetch metrics</h3>
                        <p className="mt-1 text-sm text-foreground-muted">{error}</p>
                        <Button variant="secondary" size="sm" className="mt-4" onClick={refetch}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Retry
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* Loading State */}
            {isLoading && containers.length === 0 && (
                <div className="grid gap-4 md:grid-cols-4">
                    {[...Array(4)].map((_, i) => (
                        <Card key={i}>
                            <CardContent className="p-4">
                                <div className="animate-pulse">
                                    <div className="flex items-center gap-3">
                                        <div className="h-10 w-10 rounded-lg bg-background-tertiary" />
                                        <div className="space-y-2">
                                            <div className="h-3 w-20 rounded bg-background-tertiary" />
                                            <div className="h-6 w-16 rounded bg-background-tertiary" />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Summary Metrics */}
            {summary && (
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                    <Cpu className="h-5 w-5 text-info" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">CPU Usage</p>
                                    <p className="text-2xl font-bold text-foreground">{summary.cpu_percent}%</p>
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
                                    <p className="text-2xl font-bold text-foreground">
                                        {formatBytes(summary.memory_used_bytes)}
                                    </p>
                                    <p className="text-xs text-foreground-subtle">
                                        {summary.memory_percent}% of {formatBytes(summary.memory_limit_bytes)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <Container className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Containers</p>
                                    <p className="text-2xl font-bold text-foreground">{summary.container_count}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <Activity className="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Status</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        {containers.length > 0 ? 'Running' : 'Stopped'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Per-Container Metrics */}
            {containers.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Container className="h-5 w-5" />
                            Container Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {containers.map((container) => (
                                <ContainerCard key={container.container_id} container={container} />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* CPU Usage per Container */}
            {containers.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Cpu className="h-5 w-5 text-info" />
                            CPU Usage by Container
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {containers.map((c) => (
                                <div key={c.container_id}>
                                    <div className="mb-1 flex items-center justify-between text-sm">
                                        <span className="font-medium text-foreground">{c.name}</span>
                                        <span className="text-foreground-muted">{c.cpu.formatted}</span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-background-tertiary">
                                        <div
                                            className="h-2 rounded-full bg-info transition-all duration-500"
                                            style={{ width: `${Math.min(c.cpu.percent, 100)}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Memory Usage per Container */}
            {containers.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MemoryStick className="h-5 w-5 text-warning" />
                            Memory Usage by Container
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {containers.map((c) => (
                                <div key={c.container_id}>
                                    <div className="mb-1 flex items-center justify-between text-sm">
                                        <span className="font-medium text-foreground">{c.name}</span>
                                        <span className="text-foreground-muted">{c.memory.used} / {c.memory.limit}</span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-background-tertiary">
                                        <div
                                            className="h-2 rounded-full bg-warning transition-all duration-500"
                                            style={{ width: `${Math.min(c.memory.percent, 100)}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Network I/O Table */}
            {containers.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Network className="h-5 w-5 text-primary" />
                            Network I/O
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-foreground-muted">
                                        <th className="pb-2 font-medium">Container</th>
                                        <th className="pb-2 font-medium">Received</th>
                                        <th className="pb-2 font-medium">Transmitted</th>
                                        <th className="pb-2 font-medium">Disk Read</th>
                                        <th className="pb-2 font-medium">Disk Write</th>
                                        <th className="pb-2 font-medium">PIDs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {containers.map((c) => (
                                        <tr key={c.container_id} className="border-b border-border/50">
                                            <td className="py-2 font-medium text-foreground">{c.name}</td>
                                            <td className="py-2 text-foreground-muted">{c.network.rx}</td>
                                            <td className="py-2 text-foreground-muted">{c.network.tx}</td>
                                            <td className="py-2 text-foreground-muted">{c.disk.read}</td>
                                            <td className="py-2 text-foreground-muted">{c.disk.write}</td>
                                            <td className="py-2 text-foreground-muted">{c.pids}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ContainerCard({ container }: { container: ContainerMetrics }) {
    return (
        <div className="rounded-lg border border-border bg-background-secondary p-4">
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div className="h-2 w-2 rounded-full bg-success" />
                    <span className="text-sm font-medium text-foreground">{container.name}</span>
                </div>
                <code className="text-xs text-foreground-subtle">{container.container_id.slice(0, 12)}</code>
            </div>
            <div className="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <p className="text-foreground-muted">CPU</p>
                    <p className="font-medium text-foreground">{container.cpu.formatted}</p>
                </div>
                <div>
                    <p className="text-foreground-muted">Memory</p>
                    <p className="font-medium text-foreground">{container.memory.used}</p>
                </div>
                <div>
                    <p className="text-foreground-muted">Net RX/TX</p>
                    <p className="font-medium text-foreground">{container.network.rx} / {container.network.tx}</p>
                </div>
                <div>
                    <p className="text-foreground-muted">PIDs</p>
                    <p className="font-medium text-foreground">{container.pids}</p>
                </div>
            </div>
        </div>
    );
}
