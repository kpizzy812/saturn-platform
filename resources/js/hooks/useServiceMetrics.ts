import * as React from 'react';

interface ContainerCpu {
    percent: number;
    formatted: string;
}

interface ContainerMemory {
    used: string;
    limit: string;
    percent: number;
    used_bytes: number;
    limit_bytes: number;
}

interface ContainerNetwork {
    rx: string;
    tx: string;
}

interface ContainerDisk {
    read: string;
    write: string;
}

export interface ContainerMetrics {
    name: string;
    container_id: string;
    cpu: ContainerCpu;
    memory: ContainerMemory;
    network: ContainerNetwork;
    disk: ContainerDisk;
    pids: string;
}

export interface ServiceMetricsSummary {
    cpu_percent: number;
    memory_used_bytes: number;
    memory_limit_bytes: number;
    memory_percent: number;
    container_count: number;
}

interface UseServiceMetricsOptions {
    serviceUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
    enabled?: boolean;
}

interface UseServiceMetricsReturn {
    containers: ContainerMetrics[];
    summary: ServiceMetricsSummary | null;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    lastUpdated: Date | null;
}

/**
 * Hook for fetching service container metrics via docker stats.
 * Returns per-container and aggregated summary metrics.
 */
export function useServiceMetrics({
    serviceUuid,
    autoRefresh = true,
    refreshInterval = 10000,
    enabled = true,
}: UseServiceMetricsOptions): UseServiceMetricsReturn {
    const [containers, setContainers] = React.useState<ContainerMetrics[]>([]);
    const [summary, setSummary] = React.useState<ServiceMetricsSummary | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);
    const [lastUpdated, setLastUpdated] = React.useState<Date | null>(null);

    const isMountedRef = React.useRef(true);
    const intervalRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchMetrics = React.useCallback(async () => {
        if (!serviceUuid || !enabled) return;

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/_internal/services/${serviceUuid}/container-stats`, {
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
            if (!isMountedRef.current) return;

            if (data.error && (!data.containers || data.containers.length === 0)) {
                setError(data.error);
                setContainers([]);
                setSummary(null);
            } else {
                setContainers(data.containers || []);
                setSummary(data.summary || null);
                setLastUpdated(new Date());
                setError(null);
            }
        } catch (err) {
            if (!isMountedRef.current) return;
            setError(err instanceof Error ? err.message : 'Failed to fetch metrics');
            setContainers([]);
            setSummary(null);
        } finally {
            if (isMountedRef.current) {
                setIsLoading(false);
            }
        }
    }, [serviceUuid, enabled]);

    React.useEffect(() => {
        isMountedRef.current = true;
        if (enabled) fetchMetrics();
        return () => { isMountedRef.current = false; };
    }, [fetchMetrics, enabled]);

    React.useEffect(() => {
        if (!autoRefresh || !enabled || !refreshInterval) return;

        intervalRef.current = setInterval(() => {
            if (isMountedRef.current) fetchMetrics();
        }, refreshInterval);

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [autoRefresh, refreshInterval, fetchMetrics, enabled]);

    return { containers, summary, isLoading, error, refetch: fetchMetrics, lastUpdated };
}
