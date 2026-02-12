import * as React from 'react';
import type {
    EnvironmentMigration,
    EnvironmentMigrationOptions,
    MigrationCheckResult,
    MigrationTargets,
} from '@/types';

interface UseMigrationsOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
    statusFilter?: string;
}

interface UseMigrationsReturn {
    migrations: EnvironmentMigration[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createMigration: (data: CreateMigrationData) => Promise<{ migration: EnvironmentMigration; requires_approval: boolean }>;
    approveMigration: (uuid: string) => Promise<void>;
    rejectMigration: (uuid: string, reason: string) => Promise<void>;
    rollbackMigration: (uuid: string) => Promise<void>;
    cancelMigration: (uuid: string) => Promise<void>;
}

interface CreateMigrationData {
    source_type: 'application' | 'service' | 'database';
    source_uuid: string;
    target_environment_id: number;
    target_server_id: number;
    options?: EnvironmentMigrationOptions;
}

interface BatchMigrationData {
    target_environment_id: number;
    target_server_id: number;
    resources: Array<{ type: 'application' | 'service' | 'database'; uuid: string }>;
    options?: EnvironmentMigrationOptions;
}

interface BatchMigrationResult {
    migrations: Array<{
        type: string;
        name: string;
        migration: EnvironmentMigration;
        requires_approval: boolean;
    }>;
    errors: Array<{
        type: string;
        name: string;
        error: string;
    }>;
}

interface UsePendingMigrationsReturn {
    migrations: EnvironmentMigration[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

interface UseMigrationCheckOptions {
    sourceType: 'application' | 'service' | 'database';
    sourceUuid: string;
    targetEnvironmentId: number;
}

interface UseMigrationCheckReturn {
    result: MigrationCheckResult | null;
    isLoading: boolean;
    error: Error | null;
    check: () => Promise<void>;
}

interface UseMigrationTargetsReturn {
    targets: MigrationTargets | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Hook to fetch and manage environment migrations
 */
export function useMigrations({
    autoRefresh = false,
    refreshInterval = 10000,
    statusFilter,
}: UseMigrationsOptions = {}): UseMigrationsReturn {
    const [migrations, setMigrations] = React.useState<EnvironmentMigration[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchMigrations = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const params = new URLSearchParams();
            if (statusFilter) params.set('status', statusFilter);

            const response = await fetch(`/api/v1/migrations?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch migrations: ${response.statusText}`);
            }

            const data = await response.json();
            setMigrations(data.data || data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch migrations'));
        } finally {
            setIsLoading(false);
        }
    }, [statusFilter]);

    const createMigration = React.useCallback(
        async (data: CreateMigrationData): Promise<{ migration: EnvironmentMigration; requires_approval: boolean }> => {
            const response = await fetch('/api/v1/migrations', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to create migration: ${response.statusText}`);
            }

            const result = await response.json();
            await fetchMigrations();
            return {
                migration: result.migration,
                requires_approval: result.requires_approval,
            };
        },
        [fetchMigrations]
    );

    const approveMigration = React.useCallback(
        async (uuid: string) => {
            const response = await fetch(`/api/v1/migrations/${uuid}/approve`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to approve migration: ${response.statusText}`);
            }

            await fetchMigrations();
        },
        [fetchMigrations]
    );

    const rejectMigration = React.useCallback(
        async (uuid: string, reason: string) => {
            const response = await fetch(`/api/v1/migrations/${uuid}/reject`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ reason }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to reject migration: ${response.statusText}`);
            }

            await fetchMigrations();
        },
        [fetchMigrations]
    );

    const rollbackMigration = React.useCallback(
        async (uuid: string) => {
            const response = await fetch(`/api/v1/migrations/${uuid}/rollback`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to rollback migration: ${response.statusText}`);
            }

            await fetchMigrations();
        },
        [fetchMigrations]
    );

    const cancelMigration = React.useCallback(
        async (uuid: string) => {
            const response = await fetch(`/api/v1/migrations/${uuid}/cancel`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to cancel migration: ${response.statusText}`);
            }

            await fetchMigrations();
        },
        [fetchMigrations]
    );

    // Initial fetch
    React.useEffect(() => {
        fetchMigrations();
    }, [fetchMigrations]);

    // Auto-refresh for in-progress migrations
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchMigrations();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchMigrations]);

    return {
        migrations,
        isLoading,
        error,
        refetch: fetchMigrations,
        createMigration,
        approveMigration,
        rejectMigration,
        rollbackMigration,
        cancelMigration,
    };
}

/**
 * Hook to fetch pending migrations for approval
 */
export function usePendingMigrations(): UsePendingMigrationsReturn {
    const [migrations, setMigrations] = React.useState<EnvironmentMigration[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchMigrations = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/migrations/pending', {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch pending migrations: ${response.statusText}`);
            }

            const data = await response.json();
            setMigrations(data.data || data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch pending migrations'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    React.useEffect(() => {
        fetchMigrations();
    }, [fetchMigrations]);

    return {
        migrations,
        isLoading,
        error,
        refetch: fetchMigrations,
    };
}

/**
 * Hook to check migration feasibility
 */
export function useMigrationCheck({
    sourceType,
    sourceUuid,
    targetEnvironmentId,
}: UseMigrationCheckOptions): UseMigrationCheckReturn {
    const [result, setResult] = React.useState<MigrationCheckResult | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const check = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/migrations/check', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    source_type: sourceType,
                    source_uuid: sourceUuid,
                    target_environment_id: targetEnvironmentId,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to check migration: ${response.statusText}`);
            }

            const data = await response.json();
            setResult(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to check migration'));
        } finally {
            setIsLoading(false);
        }
    }, [sourceType, sourceUuid, targetEnvironmentId]);

    return {
        result,
        isLoading,
        error,
        check,
    };
}

/**
 * Hook to fetch available migration targets for a source
 * @param sourceType - Type of resource: 'application' | 'service' | 'database'
 * @param sourceUuid - UUID of the source resource
 * @param enabled - Whether to fetch targets (default: true)
 */
export function useMigrationTargets(
    sourceType: 'application' | 'service' | 'database',
    sourceUuid: string,
    enabled: boolean = true
): UseMigrationTargetsReturn {
    const [targets, setTargets] = React.useState<MigrationTargets | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchTargets = React.useCallback(async () => {
        if (!sourceUuid || !enabled) {
            setIsLoading(false);
            return;
        }

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/migrations/targets/${sourceType}/${sourceUuid}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch migration targets: ${response.statusText}`);
            }

            const data = await response.json();
            setTargets(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch migration targets'));
        } finally {
            setIsLoading(false);
        }
    }, [sourceType, sourceUuid, enabled]);

    React.useEffect(() => {
        if (enabled && sourceUuid) {
            fetchTargets();
        }
    }, [fetchTargets, enabled, sourceUuid]);

    return {
        targets,
        isLoading,
        error,
        refetch: fetchTargets,
    };
}

/**
 * Hook to fetch available migration targets for an environment (bulk migration)
 * @param environmentUuid - UUID of the source environment
 * @param enabled - Whether to fetch targets (default: true)
 */
export function useEnvironmentMigrationTargets(
    environmentUuid: string,
    enabled: boolean = true
): UseMigrationTargetsReturn {
    const [targets, setTargets] = React.useState<MigrationTargets | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchTargets = React.useCallback(async () => {
        if (!environmentUuid || !enabled) {
            setIsLoading(false);
            return;
        }

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/migrations/environment-targets/${environmentUuid}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch migration targets: ${response.statusText}`);
            }

            const data = await response.json();
            setTargets(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch migration targets'));
        } finally {
            setIsLoading(false);
        }
    }, [environmentUuid, enabled]);

    React.useEffect(() => {
        if (enabled && environmentUuid) {
            fetchTargets();
        }
    }, [fetchTargets, enabled, environmentUuid]);

    return {
        targets,
        isLoading,
        error,
        refetch: fetchTargets,
    };
}

/**
 * Batch create migrations for multiple resources.
 * Backend handles dependency ordering: databases → services → applications.
 */
export async function batchCreateMigrations(data: BatchMigrationData): Promise<BatchMigrationResult> {
    const response = await fetch('/api/v1/migrations/batch', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `Failed to create batch migrations: ${response.statusText}`);
    }

    return response.json();
}
