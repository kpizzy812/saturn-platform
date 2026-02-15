import * as React from 'react';

interface MetricDataPoint {
    timestamp: string;
    value: number;
}

interface MetricWithHistory {
    data: MetricDataPoint[];
    current: number;
    average: number;
    peak: number;
}

interface MemoryMetric {
    data: MetricDataPoint[];
    current: number;
    total: number;
    percentage: number;
}

interface NetworkMetric {
    data: MetricDataPoint[];
    in: number;
    out: number;
}

interface ConnectionMetric {
    data: MetricDataPoint[];
    current: number;
    max: number;
    percentage: number;
}

interface QueryMetric {
    data: MetricDataPoint[];
    perSecond: number;
    total: number;
    slow: number;
}

interface StorageMetric {
    data: MetricDataPoint[];
    used: number;
    total: number;
    percentage: number;
}

export interface DatabaseMetricsHistory {
    cpu: MetricWithHistory;
    memory: MemoryMetric;
    network: NetworkMetric;
    connections: ConnectionMetric;
    queries: QueryMetric;
    storage: StorageMetric;
}

interface UseDatabaseMetricsHistoryOptions {
    uuid: string;
    timeRange?: '1h' | '6h' | '24h' | '7d' | '30d';
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabaseMetricsHistoryReturn {
    metrics: DatabaseMetricsHistory | null;
    hasHistoricalData: boolean;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

/**
 * Custom hook for fetching historical database metrics for charts
 *
 * @example
 * ```tsx
 * const { metrics, isLoading, hasHistoricalData } = useDatabaseMetricsHistory({
 *   uuid: 'database-uuid-123',
 *   timeRange: '24h',
 *   autoRefresh: true,
 *   refreshInterval: 60000,
 * });
 * ```
 */
export function useDatabaseMetricsHistory({
    uuid,
    timeRange = '24h',
    autoRefresh = true,
    refreshInterval = 60000, // 1 minute default
}: UseDatabaseMetricsHistoryOptions): UseDatabaseMetricsHistoryReturn {
    const [metrics, setMetrics] = React.useState<DatabaseMetricsHistory | null>(null);
    const [hasHistoricalData, setHasHistoricalData] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchMetrics = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);

            const response = await fetch(`/_internal/databases/${uuid}/metrics/history?timeRange=${timeRange}`, {
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

            if (data.available) {
                setMetrics(data.metrics);
                setHasHistoricalData(data.hasHistoricalData ?? false);
            } else {
                setMetrics(null);
                setHasHistoricalData(false);
                setError(data.error || 'Metrics not available');
            }
        } catch (err) {
            console.error('[Saturn] Failed to fetch database metrics history:', err);
            setError(err instanceof Error ? err.message : 'Failed to fetch metrics');
            setHasHistoricalData(false);
        } finally {
            setIsLoading(false);
        }
    }, [uuid, timeRange]);

    // Initial fetch and refetch when timeRange changes
    React.useEffect(() => {
        setIsLoading(true);
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

    return {
        metrics,
        hasHistoricalData,
        isLoading,
        error,
        refetch: fetchMetrics,
    };
}
