import * as React from 'react';
import { useRealtimeStatus } from './useRealtimeStatus';

interface MetricValue {
    current: string;
    percentage: number;
    trend: number[];
    total?: string;
}

interface NetworkMetric {
    current: string;
    in: string;
    out: string;
}

interface ServerMetrics {
    cpu: MetricValue;
    memory: MetricValue;
    disk: MetricValue;
    network?: NetworkMetric;
}

interface Alert {
    id: number;
    title: string;
    message: string;
    severity: 'critical' | 'warning' | 'info';
    timestamp: string;
}

interface HistoricalDataPoint {
    label: string;
    value: number;
}

interface HistoricalMetric {
    data: HistoricalDataPoint[];
    average: string;
    peak: string;
}

interface HistoricalData {
    cpu: HistoricalMetric;
    memory: HistoricalMetric;
    disk: HistoricalMetric;
    network?: HistoricalMetric;
}

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

interface UseSentinelMetricsOptions {
    serverUuid: string;
    timeRange?: '1h' | '24h' | '7d' | '30d';
    autoRefresh?: boolean;
    refreshInterval?: number;
    includeProcesses?: boolean;
    includeContainers?: boolean;
}

interface UseSentinelMetricsReturn {
    metrics: ServerMetrics | null;
    alerts: Alert[] | null;
    historicalData: HistoricalData | null;
    processes: ProcessInfo[] | null;
    containers: ContainerStats[] | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Custom hook for fetching and managing Sentinel server metrics
 *
 * Provides real-time server health monitoring with CPU, memory, disk, and network metrics.
 * Supports auto-refresh and WebSocket updates for live data.
 *
 * @example
 * ```tsx
 * const { metrics, alerts, isLoading } = useSentinelMetrics({
 *   serverUuid: 'server-uuid-123',
 *   autoRefresh: true,
 *   refreshInterval: 5000,
 * });
 * ```
 */
export function useSentinelMetrics({
    serverUuid,
    timeRange = '24h',
    autoRefresh = false,
    refreshInterval = 30000, // 30 seconds default
    includeProcesses = false,
    includeContainers = false,
}: UseSentinelMetricsOptions): UseSentinelMetricsReturn {
    const [metrics, setMetrics] = React.useState<ServerMetrics | null>(null);
    const [alerts, setAlerts] = React.useState<Alert[] | null>(null);
    const [historicalData, setHistoricalData] = React.useState<HistoricalData | null>(null);
    const [processes, setProcesses] = React.useState<ProcessInfo[] | null>(null);
    const [containers, setContainers] = React.useState<ContainerStats[] | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    /**
     * Fetch metrics from API
     */
    const fetchMetrics = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const params = new URLSearchParams({
                timeRange,
                includeProcesses: includeProcesses.toString(),
                includeContainers: includeContainers.toString(),
            });

            const response = await fetch(`/servers/${serverUuid}/sentinel/metrics/json?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch metrics: ${response.statusText}`);
            }

            const data = await response.json();

            // Update state with fetched data
            setMetrics(data.metrics || null);
            setAlerts(data.alerts || []);
            setHistoricalData(data.historicalData || null);

            if (includeProcesses) {
                setProcesses(data.processes || []);
            }

            if (includeContainers) {
                setContainers(data.containers || []);
            }
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch metrics'));
            setMetrics(null);
            setAlerts([]);
            setHistoricalData(null);

            if (includeProcesses) {
                setProcesses([]);
            }

            if (includeContainers) {
                setContainers([]);
            }
        } finally {
            setIsLoading(false);
        }
    }, [serverUuid, timeRange, includeProcesses, includeContainers]);

    // Initial fetch
    React.useEffect(() => {
        fetchMetrics();
    }, [fetchMetrics]);

    // Auto-refresh with interval
    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(() => {
            fetchMetrics();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchMetrics]);

    // Real-time updates via WebSocket
    useRealtimeStatus({
        onServerStatusChange: (data) => {
            // When server status changes, refetch metrics
            if (data.serverId.toString() === serverUuid) {
                fetchMetrics();
            }
        },
    });

    return {
        metrics,
        alerts,
        historicalData,
        processes,
        containers,
        isLoading,
        error,
        refetch: fetchMetrics,
    };
}

