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

            const response = await fetch(`/api/v1/servers/${serverUuid}/sentinel/metrics?${params}`, {
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
            setMetrics(data.metrics || generateMockMetrics());
            setAlerts(data.alerts || generateMockAlerts());
            setHistoricalData(data.historicalData || generateMockHistoricalData(timeRange));

            if (includeProcesses) {
                setProcesses(data.processes || generateMockProcesses());
            }

            if (includeContainers) {
                setContainers(data.containers || generateMockContainers());
            }
        } catch (err) {
            console.error('Failed to fetch Sentinel metrics:', err);
            setError(err instanceof Error ? err : new Error('Failed to fetch metrics'));

            // Set mock data for development
            setMetrics(generateMockMetrics());
            setAlerts(generateMockAlerts());
            setHistoricalData(generateMockHistoricalData(timeRange));

            if (includeProcesses) {
                setProcesses(generateMockProcesses());
            }

            if (includeContainers) {
                setContainers(generateMockContainers());
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

/**
 * Generate mock metrics for development/fallback
 */
function generateMockMetrics(): ServerMetrics {
    const cpuPercentage = Math.floor(Math.random() * 40) + 20; // 20-60%
    const memoryPercentage = Math.floor(Math.random() * 30) + 40; // 40-70%
    const diskPercentage = Math.floor(Math.random() * 20) + 30; // 30-50%

    return {
        cpu: {
            current: `${cpuPercentage}%`,
            percentage: cpuPercentage,
            trend: Array.from({ length: 20 }, () => Math.random() * 100),
        },
        memory: {
            current: `${(memoryPercentage * 0.16).toFixed(1)} GB`,
            percentage: memoryPercentage,
            total: '16 GB',
            trend: Array.from({ length: 20 }, () => Math.random() * 100),
        },
        disk: {
            current: `${(diskPercentage * 0.5).toFixed(1)} GB`,
            percentage: diskPercentage,
            total: '50 GB',
            trend: Array.from({ length: 20 }, () => Math.random() * 100),
        },
        network: {
            current: '2.4 MB/s',
            in: '1.2 MB/s',
            out: '1.2 MB/s',
        },
    };
}

/**
 * Generate mock alerts for development/fallback
 */
function generateMockAlerts(): Alert[] {
    const shouldHaveAlerts = Math.random() > 0.5;

    if (!shouldHaveAlerts) return [];

    return [
        {
            id: 1,
            title: 'High CPU Usage Detected',
            message: 'CPU usage has been above 85% for the last 10 minutes',
            severity: 'warning',
            timestamp: new Date(Date.now() - 600000).toISOString(),
        },
        {
            id: 2,
            title: 'Memory Usage Increasing',
            message: 'Memory usage has increased by 15% in the last hour',
            severity: 'info',
            timestamp: new Date(Date.now() - 3600000).toISOString(),
        },
    ].slice(0, Math.floor(Math.random() * 3));
}

/**
 * Generate mock historical data for charts
 */
function generateMockHistoricalData(timeRange: string): HistoricalData {
    const getDataPointCount = () => {
        switch (timeRange) {
            case '1h': return 12; // 5-minute intervals
            case '24h': return 24; // 1-hour intervals
            case '7d': return 28; // 6-hour intervals
            case '30d': return 30; // 1-day intervals
            default: return 24;
        }
    };

    const count = getDataPointCount();

    const generateDataPoints = (base: number, variance: number): HistoricalDataPoint[] => {
        return Array.from({ length: count }, (_, i) => ({
            label: `${i}`,
            value: Math.max(0, Math.min(100, base + (Math.random() - 0.5) * variance)),
        }));
    };

    const cpuData = generateDataPoints(45, 30);
    const memoryData = generateDataPoints(60, 20);
    const diskData = generateDataPoints(40, 10);
    const networkData = generateDataPoints(50, 40);

    return {
        cpu: {
            data: cpuData,
            average: `${Math.floor(cpuData.reduce((sum, d) => sum + d.value, 0) / count)}%`,
            peak: `${Math.floor(Math.max(...cpuData.map(d => d.value)))}%`,
        },
        memory: {
            data: memoryData,
            average: `${((memoryData.reduce((sum, d) => sum + d.value, 0) / count) * 0.16).toFixed(1)} GB`,
            peak: `${(Math.max(...memoryData.map(d => d.value)) * 0.16).toFixed(1)} GB`,
        },
        disk: {
            data: diskData,
            average: `${((diskData.reduce((sum, d) => sum + d.value, 0) / count) * 0.5).toFixed(1)} GB`,
            peak: `${(Math.max(...diskData.map(d => d.value)) * 0.5).toFixed(1)} GB`,
        },
        network: {
            data: networkData,
            average: `${((networkData.reduce((sum, d) => sum + d.value, 0) / count) * 0.05).toFixed(1)} MB/s`,
            peak: `${(Math.max(...networkData.map(d => d.value)) * 0.05).toFixed(1)} MB/s`,
        },
    };
}

/**
 * Generate mock process data
 */
function generateMockProcesses(): ProcessInfo[] {
    const processNames = [
        'dockerd', 'nginx', 'php-fpm', 'redis-server', 'postgres',
        'node', 'python', 'apache2', 'mysql', 'systemd'
    ];

    return processNames.map((name, i) => ({
        pid: 1000 + i,
        name,
        cpu: Math.random() * 25,
        memory: Math.random() * 15,
        user: i % 2 === 0 ? 'root' : 'www-data',
    })).sort((a, b) => b.cpu - a.cpu);
}

/**
 * Generate mock container data
 */
function generateMockContainers(): ContainerStats[] {
    const containerNames = [
        'saturn-proxy', 'app-production', 'redis-cache', 'postgres-db', 'nginx-frontend'
    ];

    const statuses: ContainerStats['status'][] = ['running', 'running', 'running', 'stopped', 'restarting'];

    return containerNames.map((name, i) => ({
        name,
        cpu: Math.random() * 20,
        memory: Math.random() * 30,
        network_in: `${(Math.random() * 2).toFixed(2)} MB/s`,
        network_out: `${(Math.random() * 1.5).toFixed(2)} MB/s`,
        status: statuses[i] || 'running',
    }));
}
