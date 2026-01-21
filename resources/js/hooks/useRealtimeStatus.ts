import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { getEcho, isEchoConnected } from '@/lib/echo';
import type { Application, StandaloneDatabase, Service, Server } from '@/types';

/**
 * Event data structures for WebSocket events
 */
interface ApplicationStatusEvent {
    applicationId: number;
    status: Application['status'];
}

interface DatabaseStatusEvent {
    databaseId: number;
    status: string;
}

interface ServiceStatusEvent {
    serviceId: number;
    status: string;
}

interface ServerStatusEvent {
    serverId: number;
    isReachable: boolean;
    isUsable: boolean;
}

interface DeploymentEvent {
    deploymentId: number;
    applicationId: number;
    status: 'queued' | 'in_progress' | 'finished' | 'failed' | 'cancelled';
}

/**
 * Callback types for status updates
 */
type StatusUpdateCallback<T> = (data: T) => void;

interface UseRealtimeStatusOptions {
    /**
     * Enable WebSocket connection (default: true)
     * Set to false to disable WebSocket and use polling only
     */
    enableWebSocket?: boolean;

    /**
     * Polling interval in milliseconds when WebSocket is unavailable (default: 5000)
     */
    pollingInterval?: number;

    /**
     * Callback when connection status changes
     */
    onConnectionChange?: (connected: boolean) => void;

    /**
     * Application status change callback
     */
    onApplicationStatusChange?: StatusUpdateCallback<ApplicationStatusEvent>;

    /**
     * Database status change callback
     */
    onDatabaseStatusChange?: StatusUpdateCallback<DatabaseStatusEvent>;

    /**
     * Service status change callback
     */
    onServiceStatusChange?: StatusUpdateCallback<ServiceStatusEvent>;

    /**
     * Server status change callback
     */
    onServerStatusChange?: StatusUpdateCallback<ServerStatusEvent>;

    /**
     * Deployment created callback
     */
    onDeploymentCreated?: StatusUpdateCallback<DeploymentEvent>;

    /**
     * Deployment finished callback
     */
    onDeploymentFinished?: StatusUpdateCallback<DeploymentEvent>;
}

interface UseRealtimeStatusReturn {
    /**
     * Whether WebSocket is currently connected
     */
    isConnected: boolean;

    /**
     * Connection error if any
     */
    error: Error | null;

    /**
     * Manually reconnect to WebSocket
     */
    reconnect: () => void;

    /**
     * Whether currently using polling fallback
     */
    isPolling: boolean;
}

/**
 * Custom hook for real-time status updates via WebSocket or polling
 *
 * Subscribes to Laravel Echo channels and listens for status change events:
 * - ApplicationStatusChanged (team channel)
 * - DatabaseStatusChanged (user channel)
 * - ServiceStatusChanged (team channel)
 * - ServerStatusChanged (team channel)
 * - DeploymentCreated (team channel)
 * - DeploymentFinished (team channel)
 *
 * Falls back to polling if WebSocket is unavailable.
 *
 * @example
 * ```tsx
 * const { isConnected, isPolling } = useRealtimeStatus({
 *   onApplicationStatusChange: (data) => {
 *     console.log('App status changed:', data);
 *     // Update your state here
 *   },
 *   onDeploymentCreated: (data) => {
 *     console.log('New deployment:', data);
 *   }
 * });
 * ```
 */
export function useRealtimeStatus(options: UseRealtimeStatusOptions = {}): UseRealtimeStatusReturn {
    const {
        enableWebSocket = true,
        pollingInterval = 5000,
        onConnectionChange,
        onApplicationStatusChange,
        onDatabaseStatusChange,
        onServiceStatusChange,
        onServerStatusChange,
        onDeploymentCreated,
        onDeploymentFinished,
    } = options;

    const page = usePage();
    const auth = page.props.auth;
    const teamId = auth?.team?.id;
    const userId = auth?.user?.id;

    const [isConnected, setIsConnected] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);
    const [isPolling, setIsPolling] = React.useState(false);
    const [reconnectAttempts, setReconnectAttempts] = React.useState(0);

    const pollingIntervalRef = React.useRef<NodeJS.Timeout>();
    const maxReconnectAttempts = 3;

    /**
     * Attempt to connect to WebSocket
     */
    const connectWebSocket = React.useCallback(() => {
        if (!enableWebSocket || !teamId || !userId) {
            return false;
        }

        try {
            const echo = getEcho();
            if (!echo) {
                // Echo initialization failed, but don't throw - just return false
                // This allows graceful fallback to polling or silent failure in tests
                console.warn('Echo is not available, WebSocket connection skipped');
                setError(new Error('Echo initialization failed'));
                setIsConnected(false);
                onConnectionChange?.(false);
                return false;
            }

            // Subscribe to team channel for team-wide events
            const teamChannel = echo.private(`team.${teamId}`);

            // Application status changes
            if (onApplicationStatusChange) {
                teamChannel.listen('ApplicationStatusChanged', (e: ApplicationStatusEvent) => {
                    onApplicationStatusChange(e);
                });
            }

            // Service status changes
            if (onServiceStatusChange) {
                teamChannel.listen('ServiceStatusChanged', (e: ServiceStatusEvent) => {
                    onServiceStatusChange(e);
                });
            }

            // Server status changes
            if (onServerStatusChange) {
                teamChannel.listen('ServerReachabilityChanged', (e: ServerStatusEvent) => {
                    onServerStatusChange(e);
                });
            }

            // Deployment events
            if (onDeploymentCreated) {
                teamChannel.listen('DeploymentCreated', (e: DeploymentEvent) => {
                    onDeploymentCreated(e);
                });
            }

            if (onDeploymentFinished) {
                teamChannel.listen('DeploymentFinished', (e: DeploymentEvent) => {
                    onDeploymentFinished(e);
                });
            }

            // Database status changes (also on team channel for consistency)
            if (onDatabaseStatusChange) {
                teamChannel.listen('DatabaseStatusChanged', (e: DatabaseStatusEvent) => {
                    onDatabaseStatusChange(e);
                });
            }

            setIsConnected(true);
            setError(null);
            setReconnectAttempts(0);
            onConnectionChange?.(true);

            return true;
        } catch (err) {
            console.error('WebSocket connection failed:', err);
            setError(err instanceof Error ? err : new Error('WebSocket connection failed'));
            setIsConnected(false);
            onConnectionChange?.(false);
            return false;
        }
    }, [
        enableWebSocket,
        teamId,
        userId,
        onApplicationStatusChange,
        onDatabaseStatusChange,
        onServiceStatusChange,
        onServerStatusChange,
        onDeploymentCreated,
        onDeploymentFinished,
        onConnectionChange,
    ]);

    /**
     * Start polling as fallback
     */
    const startPolling = React.useCallback(() => {
        if (!pollingInterval || pollingInterval <= 0) {
            return;
        }

        setIsPolling(true);

        // Clear existing polling interval
        if (pollingIntervalRef.current) {
            clearInterval(pollingIntervalRef.current);
        }

        // Set up new polling interval
        pollingIntervalRef.current = setInterval(() => {
            // In production, this would fetch status from API
            // For now, this is a placeholder for the polling logic
            // You would implement API calls here to fetch latest status
            console.debug('Polling for status updates...');
        }, pollingInterval);

        return () => {
            if (pollingIntervalRef.current) {
                clearInterval(pollingIntervalRef.current);
            }
        };
    }, [pollingInterval]);

    /**
     * Reconnect to WebSocket
     */
    const reconnect = React.useCallback(() => {
        if (reconnectAttempts >= maxReconnectAttempts) {
            console.warn('Max reconnection attempts reached, falling back to polling');
            startPolling();
            return;
        }

        setReconnectAttempts((prev) => prev + 1);
        const connected = connectWebSocket();

        if (!connected) {
            // Try again after delay
            setTimeout(() => {
                reconnect();
            }, 2000 * (reconnectAttempts + 1)); // Exponential backoff
        }
    }, [reconnectAttempts, connectWebSocket, startPolling]);

    /**
     * Initialize connection on mount
     */
    React.useEffect(() => {
        const connected = connectWebSocket();

        if (!connected && enableWebSocket) {
            // Start reconnection attempts
            reconnect();
        } else if (!connected) {
            // WebSocket disabled, start polling
            startPolling();
        }

        // Cleanup on unmount
        return () => {
            if (pollingIntervalRef.current) {
                clearInterval(pollingIntervalRef.current);
            }

            const echo = getEcho();
            if (echo && teamId) {
                echo.leave(`team.${teamId}`);
            }
        };
    }, []); // Only run on mount/unmount

    return {
        isConnected,
        error,
        reconnect,
        isPolling,
    };
}
