import * as React from 'react';

export interface ClickhouseQuery {
    query: string;
    duration: string;
    rows: string;
    timestamp: string;
    user: string;
}

export interface ClickhouseMergeStatus {
    activeMerges: number;
    partsCount: number;
    mergeRate: number;
}

export interface ClickhouseReplica {
    host: string;
    database: string;
    table: string;
    status: 'Healthy' | 'Read-only' | 'Delayed' | string;
    delay: string;
    isLeader: boolean;
}

export interface ClickhouseReplicationStatus {
    enabled: boolean;
    replicas: ClickhouseReplica[];
}

export interface ClickhouseSettings {
    maxThreads: number | null;
    maxMemoryUsage: string | null;
    maxConcurrentQueries: number | null;
    maxPartsInTotal: number | null;
    backgroundPoolSize: number | null;
    compressionMethod: string;
}

interface UseClickhouseQueriesOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

/**
 * Hook for fetching ClickHouse query log
 */
export function useClickhouseQueries({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseClickhouseQueriesOptions) {
    const [queries, setQueries] = React.useState<ClickhouseQuery[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchQueries = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/clickhouse/queries`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch queries: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setQueries(data.queries || []);
            } else {
                setError(data.error || 'Query log not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch queries');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchQueries();
    }, [fetchQueries]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchQueries, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchQueries]);

    return { queries, isLoading, error, refetch: fetchQueries };
}

/**
 * Hook for fetching ClickHouse merge status
 */
export function useClickhouseMergeStatus({
    uuid,
    autoRefresh = true,
    refreshInterval = 30000,
}: UseClickhouseQueriesOptions) {
    const [mergeStatus, setMergeStatus] = React.useState<ClickhouseMergeStatus | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchMergeStatus = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/clickhouse/merge-status`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch merge status: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setMergeStatus({
                    activeMerges: data.activeMerges ?? 0,
                    partsCount: data.partsCount ?? 0,
                    mergeRate: data.mergeRate ?? 0,
                });
            } else {
                setError(data.error || 'Merge status not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch merge status');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchMergeStatus();
    }, [fetchMergeStatus]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchMergeStatus, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchMergeStatus]);

    return { mergeStatus, isLoading, error, refetch: fetchMergeStatus };
}

/**
 * Hook for fetching ClickHouse replication status
 */
export function useClickhouseReplication({
    uuid,
    autoRefresh = true,
    refreshInterval = 30000,
}: UseClickhouseQueriesOptions) {
    const [replication, setReplication] = React.useState<ClickhouseReplicationStatus | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchReplication = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/clickhouse/replication`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch replication status: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setReplication({
                    enabled: data.enabled ?? false,
                    replicas: data.replicas ?? [],
                });
            } else {
                setError(data.error || 'Replication status not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch replication status');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchReplication();
    }, [fetchReplication]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchReplication, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchReplication]);

    return { replication, isLoading, error, refetch: fetchReplication };
}

/**
 * Hook for fetching ClickHouse settings
 */
export function useClickhouseSettings({ uuid }: { uuid: string }) {
    const [settings, setSettings] = React.useState<ClickhouseSettings | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchSettings = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/clickhouse/settings`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch settings: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setSettings(data.settings);
            } else {
                setError(data.error || 'Settings not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch settings');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    return { settings, isLoading, error, refetch: fetchSettings };
}
