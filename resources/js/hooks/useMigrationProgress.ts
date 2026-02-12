import * as React from 'react';
import { getEcho } from '@/lib/echo';
import type { EnvironmentMigration, EnvironmentMigrationStatus } from '@/types';

/**
 * WebSocket event data for migration progress updates.
 */
interface MigrationProgressEvent {
    uuid: string;
    status: EnvironmentMigrationStatus;
    progress: number;
    current_step: string | null;
    log_entry: string | null;
    error_message: string | null;
}

interface UseMigrationProgressOptions {
    /** Migration UUID to subscribe to */
    migrationUuid: string;
    /** Enable WebSocket (default: true) */
    enableWebSocket?: boolean;
    /** Polling interval in ms when WebSocket unavailable (default: 3000) */
    pollingInterval?: number;
    /** Callback when migration completes */
    onComplete?: (migration: EnvironmentMigration) => void;
    /** Callback when migration fails */
    onFailed?: (error: string) => void;
}

interface UseMigrationProgressReturn {
    /** Current migration data */
    migration: EnvironmentMigration | null;
    /** Whether initial load is in progress */
    isLoading: boolean;
    /** Connection error */
    error: Error | null;
    /** Whether WebSocket is connected */
    isConnected: boolean;
    /** Accumulated log entries from WebSocket */
    logEntries: string[];
    /** Refetch migration data from API */
    refetch: () => Promise<void>;
}

/**
 * Hook for real-time migration progress via WebSocket with polling fallback.
 */
export function useMigrationProgress({
    migrationUuid,
    enableWebSocket = true,
    pollingInterval = 3000,
    onComplete,
    onFailed,
}: UseMigrationProgressOptions): UseMigrationProgressReturn {
    const [migration, setMigration] = React.useState<EnvironmentMigration | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);
    const [isConnected, setIsConnected] = React.useState(false);
    const [logEntries, setLogEntries] = React.useState<string[]>([]);

    const onCompleteRef = React.useRef(onComplete);
    const onFailedRef = React.useRef(onFailed);
    onCompleteRef.current = onComplete;
    onFailedRef.current = onFailed;

    // Fetch migration data from API
    const fetchMigration = React.useCallback(async () => {
        if (!migrationUuid) return;

        try {
            const response = await fetch(`/api/v1/migrations/${migrationUuid}`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch migration: ${response.statusText}`);
            }

            const data = await response.json();
            const migrationData = data.migration || data;
            setMigration(migrationData);
            setError(null);

            // Parse existing logs into entries
            if (migrationData.logs) {
                setLogEntries(migrationData.logs.split('\n').filter(Boolean));
            }

            return migrationData;
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch migration'));
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [migrationUuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchMigration();
    }, [fetchMigration]);

    // WebSocket subscription
    React.useEffect(() => {
        if (!enableWebSocket || !migrationUuid) return;

        const echo = getEcho();
        if (!echo) {
            // No WebSocket available, rely on polling
            return;
        }

        const channel = echo.private(`migration.${migrationUuid}`);

        channel.listen('MigrationProgressUpdated', (event: MigrationProgressEvent) => {
            setIsConnected(true);

            setMigration(prev => {
                if (!prev) return prev;
                return {
                    ...prev,
                    status: event.status,
                    progress: event.progress,
                    current_step: event.current_step ?? prev.current_step,
                    error_message: event.error_message ?? prev.error_message,
                };
            });

            // Append new log entry
            if (event.log_entry) {
                setLogEntries(prev => [...prev, event.log_entry!]);
            }

            // Trigger callbacks for terminal states
            if (event.status === 'completed') {
                onCompleteRef.current?.(migration!);
            } else if (event.status === 'failed') {
                onFailedRef.current?.(event.error_message ?? 'Migration failed');
            }
        });

        return () => {
            echo.leave(`migration.${migrationUuid}`);
            setIsConnected(false);
        };
    }, [enableWebSocket, migrationUuid]);

    // Polling fallback when WebSocket is not connected and migration is active
    React.useEffect(() => {
        if (isConnected) return; // WebSocket active, no polling needed
        if (!migration) return;

        const isActive = ['pending', 'approved', 'in_progress'].includes(migration.status);
        if (!isActive) return;

        const interval = setInterval(() => {
            fetchMigration();
        }, pollingInterval);

        return () => clearInterval(interval);
    }, [isConnected, migration?.status, pollingInterval, fetchMigration]);

    return {
        migration,
        isLoading,
        error,
        isConnected,
        logEntries,
        refetch: fetchMigration,
    };
}
