import * as React from 'react';

interface CpuMetrics {
    percent: number;
    formatted: string;
}

interface MemoryMetrics {
    used: string;
    limit: string;
    percent: number;
    used_bytes: number;
    limit_bytes: number;
}

interface NetworkMetrics {
    rx: string;
    tx: string;
}

interface DiskMetrics {
    read: string;
    write: string;
}

export interface ApplicationMetrics {
    cpu: CpuMetrics;
    memory: MemoryMetrics;
    network: NetworkMetrics;
    disk: DiskMetrics;
    pids: string;
    container_id: string;
    container_name: string;
}

interface UseApplicationMetricsOptions {
    /**
     * Application UUID
     */
    applicationUuid: string;

    /**
     * Auto-refresh metrics (default: true)
     */
    autoRefresh?: boolean;

    /**
     * Refresh interval in milliseconds (default: 10000ms = 10 seconds)
     */
    refreshInterval?: number;

    /**
     * Enable metrics fetching (default: true)
     */
    enabled?: boolean;
}

interface UseApplicationMetricsReturn {
    metrics: ApplicationMetrics | null;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    lastUpdated: Date | null;
}

/**
 * Custom hook for fetching application container metrics
 *
 * Provides real-time container metrics including CPU, memory, network, and disk usage.
 * Uses docker stats via SSH to get live container statistics.
 *
 * @example
 * ```tsx
 * const { metrics, isLoading, error } = useApplicationMetrics({
 *   applicationUuid: 'app-uuid-123',
 *   autoRefresh: true,
 *   refreshInterval: 10000,
 * });
 *
 * if (metrics) {
 *   console.log(`CPU: ${metrics.cpu.percent}%`);
 *   console.log(`Memory: ${metrics.memory.used} / ${metrics.memory.limit}`);
 * }
 * ```
 */
export function useApplicationMetrics({
    applicationUuid,
    autoRefresh = true,
    refreshInterval = 10000,
    enabled = true,
}: UseApplicationMetricsOptions): UseApplicationMetricsReturn {
    const [metrics, setMetrics] = React.useState<ApplicationMetrics | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);
    const [lastUpdated, setLastUpdated] = React.useState<Date | null>(null);

    const isMountedRef = React.useRef(true);
    const intervalRef = React.useRef<NodeJS.Timeout | null>(null);

    /**
     * Fetch metrics from API
     */
    const fetchMetrics = React.useCallback(async () => {
        if (!applicationUuid || !enabled) {
            return;
        }

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/applications/${applicationUuid}/metrics`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch metrics: ${response.statusText}`);
            }

            const data = await response.json();

            if (!isMountedRef.current) {
                return;
            }

            if (data.error) {
                setError(data.error);
                setMetrics(null);
            } else if (data.metrics) {
                setMetrics(data.metrics);
                setLastUpdated(new Date());
            }
        } catch (err) {
            if (!isMountedRef.current) {
                return;
            }

            console.error('Failed to fetch application metrics:', err);
            setError(err instanceof Error ? err.message : 'Failed to fetch metrics');
            setMetrics(null);
        } finally {
            if (isMountedRef.current) {
                setIsLoading(false);
            }
        }
    }, [applicationUuid, enabled]);

    // Initial fetch
    React.useEffect(() => {
        isMountedRef.current = true;

        if (enabled) {
            fetchMetrics();
        }

        return () => {
            isMountedRef.current = false;
        };
    }, [fetchMetrics, enabled]);

    // Auto-refresh with interval
    React.useEffect(() => {
        if (!autoRefresh || !enabled || !refreshInterval) {
            return;
        }

        intervalRef.current = setInterval(() => {
            if (isMountedRef.current) {
                fetchMetrics();
            }
        }, refreshInterval);

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [autoRefresh, refreshInterval, fetchMetrics, enabled]);

    return {
        metrics,
        isLoading,
        error,
        refetch: fetchMetrics,
        lastUpdated,
    };
}
