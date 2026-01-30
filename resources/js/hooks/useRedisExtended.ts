import * as React from 'react';

export interface RedisKey {
    name: string;
    type: string;
    ttl: string;
    size: string;
}

export interface RedisMemoryInfo {
    usedMemory: string;
    peakMemory: string;
    fragmentationRatio: string;
    maxMemory: string;
    evictionPolicy: string;
}

interface UseRedisOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseRedisKeysOptions extends UseRedisOptions {
    pattern?: string;
    limit?: number;
}

/**
 * Hook for fetching Redis keys with details.
 */
export function useRedisKeys({
    uuid,
    pattern = '*',
    limit = 100,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseRedisKeysOptions) {
    const [keys, setKeys] = React.useState<RedisKey[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchKeys = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const params = new URLSearchParams({
                pattern,
                limit: limit.toString(),
            });

            const response = await fetch(`/_internal/databases/${uuid}/redis/keys?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch keys: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setKeys(data.keys || []);
            } else {
                setError(data.error || 'Keys not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch keys');
        } finally {
            setIsLoading(false);
        }
    }, [uuid, pattern, limit]);

    React.useEffect(() => {
        fetchKeys();
    }, [fetchKeys]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchKeys, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchKeys]);

    return { keys, isLoading, error, refetch: fetchKeys };
}

/**
 * Hook for fetching Redis extended memory info.
 */
export function useRedisMemory({
    uuid,
    autoRefresh = true,
    refreshInterval = 30000,
}: UseRedisOptions) {
    const [memory, setMemory] = React.useState<RedisMemoryInfo | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchMemory = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/redis/memory`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch memory info: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setMemory(data.memory);
            } else {
                setError(data.error || 'Memory info not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch memory info');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchMemory();
    }, [fetchMemory]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchMemory, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchMemory]);

    return { memory, isLoading, error, refetch: fetchMemory };
}

/**
 * Hook for Redis flush operations.
 */
export function useRedisFlush(uuid: string) {
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const flush = React.useCallback(async (type: 'db' | 'all'): Promise<boolean> => {
        if (!uuid) return false;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/_internal/databases/${uuid}/redis/flush`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'include',
                body: JSON.stringify({ type }),
            });

            const data = await response.json();

            if (data.success) {
                return true;
            } else {
                setError(data.error || 'Flush failed');
                return false;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Flush failed');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    return { flush, isLoading, error };
}

/**
 * Hook for PostgreSQL maintenance operations (VACUUM/ANALYZE).
 */
export function usePostgresMaintenance(uuid: string) {
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const runMaintenance = React.useCallback(async (operation: 'vacuum' | 'analyze'): Promise<boolean> => {
        if (!uuid) return false;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/_internal/databases/${uuid}/postgres/maintenance`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'include',
                body: JSON.stringify({ operation }),
            });

            const data = await response.json();

            if (data.success) {
                return true;
            } else {
                setError(data.error || 'Maintenance operation failed');
                return false;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Maintenance operation failed');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    return { runMaintenance, isLoading, error };
}

export interface RedisKeyValue {
    key: string;
    type: string;
    value: string | string[] | { member: string; score: string }[] | Record<string, string>;
    length: number;
    ttl: number;
}

/**
 * Hook for fetching Redis key value.
 */
export function useRedisKeyValue(uuid: string) {
    const [keyValue, setKeyValue] = React.useState<RedisKeyValue | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const fetchKeyValue = React.useCallback(async (keyName: string): Promise<RedisKeyValue | null> => {
        if (!uuid || !keyName) return null;

        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({ key: keyName });
            const response = await fetch(`/_internal/databases/${uuid}/redis/keys/value?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch key value: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success) {
                const result: RedisKeyValue = {
                    key: data.key,
                    type: data.type,
                    value: data.value,
                    length: data.length,
                    ttl: data.ttl,
                };
                setKeyValue(result);
                return result;
            } else {
                setError(data.error || 'Key not found');
                return null;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch key value');
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const clearValue = React.useCallback(() => {
        setKeyValue(null);
        setError(null);
    }, []);

    return { keyValue, isLoading, error, fetchKeyValue, clearValue };
}

/**
 * Hook for setting Redis key value.
 */
export function useRedisSetKeyValue(uuid: string) {
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const setKeyValue = React.useCallback(async (
        keyName: string,
        type: string,
        value: string | string[] | { member: string; score: string }[] | Record<string, string>,
        ttl?: number
    ): Promise<boolean> => {
        if (!uuid || !keyName) return false;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/_internal/databases/${uuid}/redis/keys/value`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'include',
                body: JSON.stringify({
                    key: keyName,
                    type,
                    value,
                    ttl: ttl ?? -1,
                }),
            });

            const data = await response.json();

            if (data.success) {
                return true;
            } else {
                setError(data.error || 'Failed to set key value');
                return false;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to set key value');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    return { setKeyValue, isLoading, error };
}
