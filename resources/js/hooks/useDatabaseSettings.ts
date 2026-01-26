import * as React from 'react';

// MySQL Settings Types
export interface MysqlSettings {
    slowQueryLog: boolean;
    binaryLogging: boolean;
    maxConnections: number | null;
    innodbBufferPoolSize: string | null;
    queryCacheSize: string | null;
    queryTimeout: number | null;
}

// Redis Persistence Types
export interface RdbSaveRule {
    seconds: number;
    changes: number;
}

export interface RedisPersistence {
    rdbEnabled: boolean;
    rdbSaveRules: RdbSaveRule[];
    aofEnabled: boolean;
    aofFsync: string;
    rdbLastSaveTime: string | null;
    rdbLastBgsaveStatus: string;
}

// MongoDB Storage Settings Types
export interface MongoStorageSettings {
    storageEngine: string;
    cacheSize: string | null;
    journalEnabled: boolean;
    directoryPerDb: boolean;
}

interface UseDatabaseSettingsOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseMysqlSettingsReturn {
    settings: MysqlSettings | null;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

interface UseRedisPersistenceReturn {
    persistence: RedisPersistence | null;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

interface UseMongoStorageSettingsReturn {
    settings: MongoStorageSettings | null;
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

/**
 * Hook for fetching MySQL/MariaDB settings.
 * Retrieves slow_query_log, binary logging, max_connections, buffer pool size, etc.
 */
export function useMysqlSettings({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseDatabaseSettingsOptions): UseMysqlSettingsReturn {
    const [settings, setSettings] = React.useState<MysqlSettings | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchSettings = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/mysql/settings`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch MySQL settings: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setSettings(data.settings);
            } else {
                setSettings(null);
                setError(data.error || 'MySQL settings not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch MySQL settings');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchSettings, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchSettings]);

    return {
        settings,
        isLoading,
        error,
        refetch: fetchSettings,
    };
}

/**
 * Hook for fetching Redis persistence settings.
 * Retrieves RDB snapshots configuration, AOF settings, last save time, etc.
 */
export function useRedisPersistence({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseDatabaseSettingsOptions): UseRedisPersistenceReturn {
    const [persistence, setPersistence] = React.useState<RedisPersistence | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchPersistence = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/redis/persistence`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch Redis persistence settings: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setPersistence(data.persistence);
            } else {
                setPersistence(null);
                setError(data.error || 'Redis persistence settings not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch Redis persistence settings');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchPersistence();
    }, [fetchPersistence]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchPersistence, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchPersistence]);

    return {
        persistence,
        isLoading,
        error,
        refetch: fetchPersistence,
    };
}

/**
 * Hook for fetching MongoDB storage settings.
 * Retrieves storage engine, WiredTiger cache size, journal status, etc.
 */
export function useMongoStorageSettings({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseDatabaseSettingsOptions): UseMongoStorageSettingsReturn {
    const [settings, setSettings] = React.useState<MongoStorageSettings | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchSettings = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/api/databases/${uuid}/mongodb/storage-settings`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch MongoDB storage settings: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setSettings(data.settings);
            } else {
                setSettings(null);
                setError(data.error || 'MongoDB storage settings not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch MongoDB storage settings');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchSettings, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchSettings]);

    return {
        settings,
        isLoading,
        error,
        refetch: fetchSettings,
    };
}

/**
 * Helper function to format RDB save rules as readable string.
 */
export function formatRdbSaveRules(rules: RdbSaveRule[]): string {
    if (!rules || rules.length === 0) {
        return 'Disabled';
    }

    return rules
        .map((rule) => {
            const seconds = rule.seconds;
            const changes = rule.changes;
            const timeStr =
                seconds >= 3600
                    ? `${Math.floor(seconds / 3600)}h`
                    : seconds >= 60
                      ? `${Math.floor(seconds / 60)}m`
                      : `${seconds}s`;
            return `${timeStr}/${changes} changes`;
        })
        .join(', ');
}
