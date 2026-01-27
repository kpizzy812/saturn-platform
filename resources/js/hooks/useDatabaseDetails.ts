import * as React from 'react';

export interface DatabaseExtension {
    name: string;
    version: string;
    enabled: boolean;
    description: string;
}

export interface DatabaseUser {
    name: string;
    role: string;
    connections: number;
}

export interface DatabaseLog {
    timestamp: string;
    level: string;
    message: string;
}

interface UseDatabaseDetailsOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabaseExtensionsReturn {
    extensions: DatabaseExtension[];
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    toggleExtension: (name: string, enable: boolean) => Promise<boolean>;
}

interface UseDatabaseUsersReturn {
    users: DatabaseUser[];
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

interface UseDatabaseLogsReturn {
    logs: DatabaseLog[];
    isLoading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
}

/**
 * Hook for fetching PostgreSQL extensions.
 */
export function useDatabaseExtensions({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseDatabaseDetailsOptions): UseDatabaseExtensionsReturn {
    const [extensions, setExtensions] = React.useState<DatabaseExtension[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchExtensions = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/extensions`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch extensions: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setExtensions(data.extensions || []);
            } else {
                setExtensions([]);
                setError(data.error || 'Extensions not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch extensions');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const toggleExtension = React.useCallback(async (name: string, enable: boolean): Promise<boolean> => {
        if (!uuid) return false;

        try {
            const response = await fetch(`/_internal/databases/${uuid}/extensions/toggle`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'include',
                body: JSON.stringify({ extension: name, enable }),
            });

            const data = await response.json();

            if (data.success) {
                // Refetch to get updated list
                await fetchExtensions();
                return true;
            } else {
                setError(data.error || 'Failed to toggle extension');
                return false;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to toggle extension');
            return false;
        }
    }, [uuid, fetchExtensions]);

    React.useEffect(() => {
        fetchExtensions();
    }, [fetchExtensions]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchExtensions, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchExtensions]);

    return {
        extensions,
        isLoading,
        error,
        refetch: fetchExtensions,
        toggleExtension,
    };
}

/**
 * Hook for fetching database users.
 */
export function useDatabaseUsers({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseDatabaseDetailsOptions): UseDatabaseUsersReturn {
    const [users, setUsers] = React.useState<DatabaseUser[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchUsers = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/users`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch users: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setUsers(data.users || []);
            } else {
                setUsers([]);
                setError(data.error || 'Users not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch users');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchUsers, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchUsers]);

    return {
        users,
        isLoading,
        error,
        refetch: fetchUsers,
    };
}

/**
 * Hook for fetching database logs.
 */
export function useDatabaseLogs({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseDatabaseDetailsOptions): UseDatabaseLogsReturn {
    const [logs, setLogs] = React.useState<DatabaseLog[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchLogs = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/logs?lines=100`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch logs: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setLogs(data.logs || []);
            } else {
                setLogs([]);
                setError(data.error || 'Logs not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch logs');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchLogs, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchLogs]);

    return {
        logs,
        isLoading,
        error,
        refetch: fetchLogs,
    };
}
