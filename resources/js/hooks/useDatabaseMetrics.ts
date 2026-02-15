import * as React from 'react';

export interface PostgresMetrics {
    activeConnections: number | null;
    maxConnections: number;
    databaseSize: string;
    queriesPerSec: number | null;
    cacheHitRatio: string | null;
}

export interface MysqlMetrics {
    activeConnections: number | null;
    maxConnections: number;
    databaseSize: string;
    queriesPerSec: number | null;
    slowQueries: number | null;
}

export interface RedisMetrics {
    totalKeys: number | null;
    memoryUsed: string;
    opsPerSec: number | null;
    hitRate: string | null;
}

export interface MongoMetrics {
    collections: number | null;
    documents: number | null;
    databaseSize: string;
    indexSize: string;
}

export interface ClickhouseMetrics {
    totalTables: number | null;
    totalRows: number | null;
    databaseSize: string;
    queriesPerSec: number | null;
}

export type DatabaseMetrics =
    | PostgresMetrics
    | MysqlMetrics
    | RedisMetrics
    | MongoMetrics
    | ClickhouseMetrics;

interface UseDatabaseMetricsOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabaseMetricsReturn {
    metrics: DatabaseMetrics | null;
    isLoading: boolean;
    isAvailable: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

/**
 * Custom hook for fetching real-time database metrics
 *
 * Provides live database health metrics via SSH commands to the database container.
 * Supports auto-refresh for live data updates.
 *
 * @example
 * ```tsx
 * const { metrics, isLoading, isAvailable } = useDatabaseMetrics({
 *   uuid: 'database-uuid-123',
 *   autoRefresh: true,
 *   refreshInterval: 30000,
 * });
 * ```
 */
export function useDatabaseMetrics({
    uuid,
    autoRefresh = true,
    refreshInterval = 30000, // 30 seconds default
}: UseDatabaseMetricsOptions): UseDatabaseMetricsReturn {
    const [metrics, setMetrics] = React.useState<DatabaseMetrics | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [isAvailable, setIsAvailable] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const fetchMetrics = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);

            const response = await fetch(`/_internal/databases/${uuid}/metrics`, {
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
                setIsAvailable(true);
            } else {
                setMetrics(null);
                setIsAvailable(false);
                setError(data.error || 'Metrics not available');
            }
        } catch (err) {
            console.error('[Saturn] Failed to fetch database metrics:', err);
            setError(err instanceof Error ? err.message : 'Failed to fetch metrics');
            setIsAvailable(false);
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

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

    return {
        metrics,
        isLoading,
        isAvailable,
        error,
        refetch: fetchMetrics,
    };
}

/**
 * Helper function to format metric value with fallback
 */
export function formatMetricValue(
    value: number | string | null | undefined,
    suffix: string = '',
    fallback: string = 'N/A'
): string {
    if (value === null || value === undefined) {
        return fallback;
    }
    if (typeof value === 'string') {
        return value === 'N/A' ? fallback : value;
    }
    return `${value.toLocaleString()}${suffix}`;
}
