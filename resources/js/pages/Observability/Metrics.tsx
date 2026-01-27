import { AppLayout } from '@/components/layout';
import { useState, useCallback, useRef, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, useToast } from '@/components/ui';
import { LineChart } from '@/components/ui/Chart';
import {
    Download,
    RefreshCw,
    Cpu,
    HardDrive,
    Network,
    MemoryStick,
    Loader2,
    AlertTriangle,
} from 'lucide-react';

interface MetricData {
    label: string;
    value: number;
}

interface MetricChart {
    id: string;
    title: string;
    type: 'cpu' | 'memory' | 'network' | 'disk';
    unit: string;
    data: MetricData[];
    current: string;
    avg: string;
    max: string;
    icon: any;
}

interface ServerOption {
    uuid: string;
    name: string;
}

interface Props {
    servers?: ServerOption[];
}

const timeRanges = [
    { label: 'Last 1 hour', value: '1h' },
    { label: 'Last 24 hours', value: '24h' },
    { label: 'Last 7 days', value: '7d' },
    { label: 'Last 30 days', value: '30d' },
];

function MetricChartCard({ chart }: { chart: MetricChart }) {
    const Icon = chart.icon;

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                            <Icon className="h-4 w-4 text-primary" />
                        </div>
                        <CardTitle>{chart.title}</CardTitle>
                    </div>
                    <Badge variant="default">{chart.current}</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <LineChart data={chart.data} height={180} />
                <div className="mt-4 grid grid-cols-3 gap-4 border-t border-border pt-4">
                    <div>
                        <p className="text-xs text-foreground-muted">Current</p>
                        <p className="text-sm font-semibold text-foreground">{chart.current}</p>
                    </div>
                    <div>
                        <p className="text-xs text-foreground-muted">Average</p>
                        <p className="text-sm font-semibold text-foreground">{chart.avg}</p>
                    </div>
                    <div>
                        <p className="text-xs text-foreground-muted">Peak</p>
                        <p className="text-sm font-semibold text-foreground">{chart.max}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function ObservabilityMetrics({ servers = [] }: Props) {
    const { addToast } = useToast();
    const [selectedServer, setSelectedServer] = useState(servers[0]?.uuid || '');
    const [selectedTimeRange, setSelectedTimeRange] = useState('24h');
    const [isLoading, setIsLoading] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [metricsData, setMetricsData] = useState<MetricChart[]>([]);
    const addToastRef = useRef(addToast);
    addToastRef.current = addToast;

    const fetchMetrics = useCallback(async () => {
        if (!selectedServer) return;

        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({
                timeRange: selectedTimeRange,
                includeProcesses: 'false',
                includeContainers: 'false',
            });

            const response = await fetch(`/api/v1/servers/${selectedServer}/sentinel/metrics?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || `Failed to fetch metrics (${response.status})`);
            }

            const data = await response.json();
            const metrics = data.metrics;
            const historical = data.historicalData;

            const charts: MetricChart[] = [];

            if (metrics?.cpu) {
                charts.push({
                    id: '1',
                    title: 'CPU Usage',
                    type: 'cpu',
                    unit: '%',
                    data: historical?.cpu?.data || [],
                    current: metrics.cpu.current || '--',
                    avg: historical?.cpu?.average || '--',
                    max: historical?.cpu?.peak || '--',
                    icon: Cpu,
                });
            }

            if (metrics?.memory) {
                charts.push({
                    id: '2',
                    title: 'Memory Usage',
                    type: 'memory',
                    unit: 'GB',
                    data: historical?.memory?.data || [],
                    current: metrics.memory.current || '--',
                    avg: historical?.memory?.average || '--',
                    max: historical?.memory?.peak || '--',
                    icon: MemoryStick,
                });
            }

            if (metrics?.network) {
                charts.push({
                    id: '3',
                    title: 'Network I/O',
                    type: 'network',
                    unit: 'MB/s',
                    data: historical?.network?.data || [],
                    current: metrics.network.current || 'N/A',
                    avg: historical?.network?.average || 'N/A',
                    max: historical?.network?.peak || 'N/A',
                    icon: Network,
                });
            }

            if (metrics?.disk) {
                charts.push({
                    id: '4',
                    title: 'Disk Usage',
                    type: 'disk',
                    unit: '%',
                    data: historical?.disk?.data || [],
                    current: metrics.disk.current || '--',
                    avg: historical?.disk?.average || '--',
                    max: historical?.disk?.peak || '--',
                    icon: HardDrive,
                });
            }

            setMetricsData(charts);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to fetch metrics';
            setError(message);
            setMetricsData([]);
        } finally {
            setIsLoading(false);
        }
    }, [selectedServer, selectedTimeRange]);

    useEffect(() => {
        fetchMetrics();
    }, [fetchMetrics]);

    const handleExport = useCallback(() => {
        if (metricsData.length === 0) {
            addToastRef.current('warning', 'No metrics data to export');
            return;
        }

        setIsExporting(true);

        try {
            const csvLines: string[] = [
                '# Saturn Metrics Export',
                `# Exported: ${new Date().toISOString()}`,
                `# Time Range: ${selectedTimeRange}`,
                `# Server: ${servers.find(s => s.uuid === selectedServer)?.name || selectedServer}`,
                '',
                'Metric,Type,Unit,Current,Average,Max',
            ];

            metricsData.forEach(chart => {
                csvLines.push(`${chart.title},${chart.type},${chart.unit},${chart.current},${chart.avg},${chart.max}`);
            });

            csvLines.push('');
            csvLines.push('# Time Series Data');
            csvLines.push('Metric,Time,Value');

            metricsData.forEach(chart => {
                chart.data.forEach(point => {
                    csvLines.push(`${chart.title},${point.label},${point.value}`);
                });
            });

            const csv = csvLines.join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `saturn-metrics-${selectedTimeRange}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            addToastRef.current('success', 'Metrics exported to CSV');
        } catch (exportError) {
            addToastRef.current('error', `Export failed: ${exportError instanceof Error ? exportError.message : 'Unknown error'}`);
        } finally {
            setIsExporting(false);
        }
    }, [metricsData, selectedTimeRange, selectedServer, servers]);

    return (
        <AppLayout
            title="Metrics"
            breadcrumbs={[{ label: 'Observability', href: '/observability' }, { label: 'Metrics' }]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Metrics Dashboard</h1>
                        <p className="text-foreground-muted">Monitor system performance and resource utilization</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={fetchMetrics} disabled={isLoading}>
                            {isLoading ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="mr-2 h-4 w-4" />
                            )}
                            {isLoading ? 'Refreshing...' : 'Refresh'}
                        </Button>
                        <Button variant="secondary" size="sm" onClick={handleExport} disabled={isExporting || metricsData.length === 0}>
                            {isExporting ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-2 h-4 w-4" />
                            )}
                            {isExporting ? 'Exporting...' : 'Export Data'}
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Server
                                </label>
                                <select
                                    value={selectedServer}
                                    onChange={(e) => setSelectedServer(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {servers.map((server) => (
                                        <option key={server.uuid} value={server.uuid}>
                                            {server.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Time Range
                                </label>
                                <select
                                    value={selectedTimeRange}
                                    onChange={(e) => setSelectedTimeRange(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {timeRanges.map((range) => (
                                        <option key={range.value} value={range.value}>
                                            {range.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Error State */}
                {error && (
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-3 text-yellow-500">
                                <AlertTriangle className="h-5 w-5" />
                                <div>
                                    <p className="font-medium">Unable to fetch metrics</p>
                                    <p className="mt-1 text-sm text-foreground-muted">{error}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Loading State */}
                {isLoading && metricsData.length === 0 && (
                    <div className="flex items-center justify-center py-12">
                        <Loader2 className="h-8 w-8 animate-spin text-foreground-muted" />
                    </div>
                )}

                {/* No Servers */}
                {servers.length === 0 && !isLoading && (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <HardDrive className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No servers found</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Add a server to start collecting metrics
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Metric Charts */}
                {metricsData.length > 0 && (
                    <div className="grid gap-6 md:grid-cols-2">
                        {metricsData.map((chart) => (
                            <MetricChartCard key={chart.id} chart={chart} />
                        ))}
                    </div>
                )}

                {/* No Data State */}
                {!isLoading && !error && metricsData.length === 0 && servers.length > 0 && (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Cpu className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No metrics available</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Sentinel may not be enabled on this server. Enable it in server settings to start collecting metrics.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
