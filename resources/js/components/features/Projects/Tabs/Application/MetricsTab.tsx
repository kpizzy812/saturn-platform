import { useState, useEffect, useCallback } from 'react';
import { Gauge, RefreshCw, HardDrive } from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import type { SelectedService } from '../../types';

interface MetricsTabProps {
    service: SelectedService;
}

interface RequestStats {
    available: boolean;
    totalRequests: number;
    successRate: number;
    avgLatencyMs: number | null;
    statusCodes: {
        '2xx': number;
        '3xx': number;
        '4xx': number;
        '5xx': number;
    };
    error?: string;
}

export function MetricsTab({ service }: MetricsTabProps) {
    const [timeRange, setTimeRange] = useState<'1h' | '24h' | '7d' | '30d'>('24h');

    // Use the Sentinel metrics hook with the real server UUID
    const { metrics, historicalData, isLoading, error, refetch } = useSentinelMetrics({
        serverUuid: service.serverUuid || '',
        timeRange,
        autoRefresh: !!service.serverUuid,
        refreshInterval: 30000, // 30 seconds
    });

    // Request stats from container logs
    const [requestStats, setRequestStats] = useState<RequestStats | null>(null);
    const [requestStatsLoading, setRequestStatsLoading] = useState(false);

    const fetchRequestStats = useCallback(async () => {
        if (!service.uuid) return;
        setRequestStatsLoading(true);
        try {
            const response = await fetch(`/_internal/applications/${service.uuid}/request-stats?timeRange=${timeRange}`);
            const data = await response.json();
            setRequestStats(data);
        } catch {
            setRequestStats({ available: false, totalRequests: 0, successRate: 0, avgLatencyMs: null, statusCodes: { '2xx': 0, '3xx': 0, '4xx': 0, '5xx': 0 }, error: 'Failed to fetch request stats' });
        } finally {
            setRequestStatsLoading(false);
        }
    }, [service.uuid, timeRange]);

    useEffect(() => {
        fetchRequestStats();
    }, [fetchRequestStats]);

    const renderMiniChart = (data: number[], color: string, max: number = 100) => {
        const height = 40;
        const width = 200;
        const points = data.map((value, i) => {
            const x = (i / (data.length - 1)) * width;
            const y = height - (value / max) * height;
            return `${x},${y}`;
        }).join(' ');

        return (
            <svg viewBox={`0 0 ${width} ${height}`} className="h-10 w-full">
                <polyline
                    fill="none"
                    stroke={color}
                    strokeWidth="2"
                    points={points}
                />
                <polygon
                    fill={`${color}20`}
                    points={`0,${height} ${points} ${width},${height}`}
                />
            </svg>
        );
    };

    // Extract data from hook
    const cpuData = historicalData?.cpu?.data?.map(d => d.value) || [];
    const memoryData = historicalData?.memory?.data?.map(d => d.value) || [];
    const networkData = historicalData?.network?.data?.map(d => d.value) || [];

    // Show message if no server is associated
    if (!service.serverUuid) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center">
                <Gauge className="mb-4 h-12 w-12 text-foreground-muted opacity-50" />
                <p className="text-foreground-muted">Metrics unavailable</p>
                <p className="mt-1 text-sm text-foreground-subtle">
                    No server associated with this service
                </p>
            </div>
        );
    }

    if (isLoading && !metrics) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Time Range Selector */}
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Resource Usage</h3>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => refetch()}
                        className="rounded p-1 text-foreground-muted hover:text-foreground"
                        title="Refresh metrics"
                    >
                        <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                    </button>
                    <div className="flex gap-1 rounded-lg bg-background-secondary p-1">
                        {(['1h', '24h', '7d', '30d'] as const).map((range) => (
                            <button
                                key={range}
                                onClick={() => setTimeRange(range)}
                                className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                                    timeRange === range
                                        ? 'bg-primary text-white'
                                        : 'text-foreground-muted hover:text-foreground'
                                }`}
                            >
                                {range}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to fetch real-time metrics from server. Data may be stale.
                </div>
            )}

            {/* CPU Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                        <span className="text-sm font-medium text-foreground">CPU Usage</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-foreground">{metrics?.cpu?.current || '0%'}</span>
                        <span className="text-xs text-foreground-muted">of 1 vCPU</span>
                    </div>
                </div>
                {cpuData.length > 0 && renderMiniChart(cpuData, '#3b82f6')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: {historicalData?.cpu?.average || 'N/A'}</span>
                    <span>Max: {historicalData?.cpu?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Memory Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-emerald-500" />
                        <span className="text-sm font-medium text-foreground">Memory Usage</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-foreground">{metrics?.memory?.current || '0 GB'}</span>
                        <span className="text-xs text-foreground-muted">of {metrics?.memory?.total || '512 MB'}</span>
                    </div>
                </div>
                {memoryData.length > 0 && renderMiniChart(memoryData, '#10b981')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: {historicalData?.memory?.average || 'N/A'}</span>
                    <span>Max: {historicalData?.memory?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Network I/O */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-purple-500" />
                        <span className="text-sm font-medium text-foreground">Network I/O</span>
                    </div>
                    <div className="flex items-center gap-4 text-xs">
                        <span className="flex items-center gap-1">
                            <span className="h-2 w-2 rounded-full bg-purple-500" />
                            In: {metrics?.network?.in || 'N/A'}
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="h-2 w-2 rounded-full bg-pink-500" />
                            Out: {metrics?.network?.out || 'N/A'}
                        </span>
                    </div>
                </div>
                {networkData.length > 0 && (
                    <div className="relative">
                        {renderMiniChart(networkData, '#a855f7', 100)}
                    </div>
                )}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Total In: {historicalData?.network?.average || 'N/A'}</span>
                    <span>Total Out: {historicalData?.network?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Disk Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <HardDrive className="h-4 w-4 text-foreground-muted" />
                        <span className="text-sm font-medium text-foreground">Disk Usage</span>
                    </div>
                    <span className="text-sm text-foreground">
                        {metrics?.disk?.current || '0 GB'} / {metrics?.disk?.total || '5 GB'}
                    </span>
                </div>
                <div className="h-2 w-full rounded-full bg-background">
                    <div
                        className="h-2 rounded-full bg-orange-500 transition-all"
                        style={{ width: `${metrics?.disk?.percentage || 0}%` }}
                    />
                </div>
                <p className="mt-2 text-xs text-foreground-muted">
                    {metrics?.disk?.percentage || 0}% used
                </p>
            </div>

            {/* Request Stats */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <h4 className="mb-3 text-sm font-medium text-foreground">Request Stats ({timeRange})</h4>
                {requestStatsLoading ? (
                    <div className="flex items-center justify-center py-4">
                        <RefreshCw className="h-4 w-4 animate-spin text-foreground-muted" />
                    </div>
                ) : requestStats?.available && requestStats.totalRequests > 0 ? (
                    <>
                        <div className="grid grid-cols-3 gap-4">
                            <div className="text-center">
                                <p className="text-2xl font-bold text-foreground">
                                    {requestStats.totalRequests.toLocaleString()}
                                </p>
                                <p className="text-xs text-foreground-muted">Total Requests</p>
                            </div>
                            <div className="text-center">
                                <p className={`text-2xl font-bold ${requestStats.successRate >= 95 ? 'text-green-500' : requestStats.successRate >= 80 ? 'text-yellow-500' : 'text-red-500'}`}>
                                    {requestStats.successRate}%
                                </p>
                                <p className="text-xs text-foreground-muted">Success Rate</p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-foreground">
                                    {requestStats.avgLatencyMs !== null ? `${requestStats.avgLatencyMs}ms` : 'N/A'}
                                </p>
                                <p className="text-xs text-foreground-muted">Avg Latency</p>
                            </div>
                        </div>
                        {/* Status code breakdown */}
                        <div className="mt-3 flex items-center gap-3 text-xs text-foreground-muted">
                            <span className="text-green-500">2xx: {requestStats.statusCodes['2xx']}</span>
                            <span className="text-blue-500">3xx: {requestStats.statusCodes['3xx']}</span>
                            <span className="text-yellow-500">4xx: {requestStats.statusCodes['4xx']}</span>
                            <span className="text-red-500">5xx: {requestStats.statusCodes['5xx']}</span>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="grid grid-cols-3 gap-4">
                            <div className="text-center">
                                <p className="text-2xl font-bold text-foreground">--</p>
                                <p className="text-xs text-foreground-muted">Total Requests</p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-green-500">--</p>
                                <p className="text-xs text-foreground-muted">Success Rate</p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-foreground">--</p>
                                <p className="text-xs text-foreground-muted">Avg Latency</p>
                            </div>
                        </div>
                        <p className="mt-3 text-center text-xs text-foreground-subtle">
                            {requestStats?.error || 'No HTTP request data found in container logs for this period'}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}
