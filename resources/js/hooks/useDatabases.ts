import * as React from 'react';
import type { StandaloneDatabase, DatabaseType } from '@/types';

interface UseDatabasesOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabasesReturn {
    databases: StandaloneDatabase[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createDatabase: (type: DatabaseType, data: CreateDatabaseData) => Promise<StandaloneDatabase>;
}

interface UseDatabaseOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabaseReturn {
    database: StandaloneDatabase | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateDatabase: (data: Partial<StandaloneDatabase>) => Promise<void>;
    startDatabase: () => Promise<void>;
    stopDatabase: () => Promise<void>;
    restartDatabase: () => Promise<void>;
    deleteDatabase: () => Promise<void>;
}

interface CreateDatabaseData {
    name: string;
    description?: string;
    environment_id: number;
    destination_id: number;
    [key: string]: any;
}

interface DatabaseBackup {
    id: number;
    uuid: string;
    database_id: number;
    filename: string;
    size: string;
    status: 'completed' | 'in_progress' | 'failed';
    created_at: string;
}

interface UseDatabaseBackupsOptions {
    databaseUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDatabaseBackupsReturn {
    backups: DatabaseBackup[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createBackup: () => Promise<void>;
    deleteBackup: (backupUuid: string) => Promise<void>;
}

/**
 * Fetch all databases for the current team
 */
export function useDatabases({
    autoRefresh = false,
    refreshInterval = 30000,
}: UseDatabasesOptions = {}): UseDatabasesReturn {
    const [databases, setDatabases] = React.useState<StandaloneDatabase[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchDatabases = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/databases', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch databases: ${response.statusText}`);
            }

            const data = await response.json();
            setDatabases(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch databases'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createDatabase = React.useCallback(async (type: DatabaseType, data: CreateDatabaseData): Promise<StandaloneDatabase> => {
        try {
            const response = await fetch(`/api/v1/databases/${type}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to create database: ${response.statusText}`);
            }

            const database = await response.json();

            // Refresh the databases list
            await fetchDatabases();

            return database;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create database');
        }
    }, [fetchDatabases]);

    // Initial fetch
    React.useEffect(() => {
        fetchDatabases();
    }, [fetchDatabases]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchDatabases();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDatabases]);

    return {
        databases,
        isLoading,
        error,
        refetch: fetchDatabases,
        createDatabase,
    };
}

/**
 * Fetch and manage a single database
 */
export function useDatabase({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseDatabaseOptions): UseDatabaseReturn {
    const [database, setDatabase] = React.useState<StandaloneDatabase | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchDatabase = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/databases/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch database: ${response.statusText}`);
            }

            const data = await response.json();
            setDatabase(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch database'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const updateDatabase = React.useCallback(async (data: Partial<StandaloneDatabase>) => {
        try {
            const response = await fetch(`/api/v1/databases/${uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update database: ${response.statusText}`);
            }

            const updated = await response.json();
            setDatabase(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update database');
        }
    }, [uuid]);

    const startDatabase = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/databases/${uuid}/start`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to start database: ${response.statusText}`);
            }

            await fetchDatabase();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to start database');
        }
    }, [uuid, fetchDatabase]);

    const stopDatabase = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/databases/${uuid}/stop`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to stop database: ${response.statusText}`);
            }

            await fetchDatabase();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to stop database');
        }
    }, [uuid, fetchDatabase]);

    const restartDatabase = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/databases/${uuid}/restart`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to restart database: ${response.statusText}`);
            }

            await fetchDatabase();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to restart database');
        }
    }, [uuid, fetchDatabase]);

    const deleteDatabase = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/databases/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete database: ${response.statusText}`);
            }

            setDatabase(null);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete database');
        }
    }, [uuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchDatabase();
    }, [fetchDatabase]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchDatabase();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDatabase]);

    return {
        database,
        isLoading,
        error,
        refetch: fetchDatabase,
        updateDatabase,
        startDatabase,
        stopDatabase,
        restartDatabase,
        deleteDatabase,
    };
}

/**
 * Fetch and manage database backups
 */
export function useDatabaseBackups({
    databaseUuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseDatabaseBackupsOptions): UseDatabaseBackupsReturn {
    const [backups, setBackups] = React.useState<DatabaseBackup[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchBackups = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/databases/${databaseUuid}/backups`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch backups: ${response.statusText}`);
            }

            const data = await response.json();
            setBackups(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch backups'));
        } finally {
            setIsLoading(false);
        }
    }, [databaseUuid]);

    const createBackup = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/databases/${databaseUuid}/backups`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to create backup: ${response.statusText}`);
            }

            await fetchBackups();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create backup');
        }
    }, [databaseUuid, fetchBackups]);

    const deleteBackup = React.useCallback(async (backupUuid: string) => {
        try {
            const response = await fetch(`/api/v1/databases/${databaseUuid}/backups/${backupUuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete backup: ${response.statusText}`);
            }

            await fetchBackups();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete backup');
        }
    }, [databaseUuid, fetchBackups]);

    // Initial fetch
    React.useEffect(() => {
        fetchBackups();
    }, [fetchBackups]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchBackups();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchBackups]);

    return {
        backups,
        isLoading,
        error,
        refetch: fetchBackups,
        createBackup,
        deleteBackup,
    };
}
