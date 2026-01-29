import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { getEcho } from '@/lib/echo';
import type { Application } from '@/types';

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
    const pageProps = page.props as any;
    const teamId = pageProps.team?.id;
    const userId = pageProps.auth?.id;

    const [isConnected, setIsConnected] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);
    const [isPolling, setIsPolling] = React.useState(false);
    const [reconnectAttempts, setReconnectAttempts] = React.useState(0);

    const pollingIntervalRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const reconnectTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const isMountedRef = React.useRef(true);
    const maxReconnectAttempts = 3;

    /**
     * Attempt to connect to WebSocket
     */
    const connectWebSocket = React.useCallback(() => {
        // Skip connection if user is not authenticated or has no team
        if (!teamId || !userId) {
            // Only log once, not on every retry
            return false;
        }

        if (!enableWebSocket) {
            return false;
        }

        try {
            const echo = getEcho();
            if (!echo) {
                // Echo initialization failed, but don't throw - just return false
                // This allows graceful fallback to polling or silent failure in tests
                console.warn('[useRealtimeStatus] Echo is not available, WebSocket connection skipped');
                setError(new Error('Echo initialization failed'));
                setIsConnected(false);
                onConnectionChange?.(false);
                return false;
            }

            console.debug('[useRealtimeStatus] Echo available, subscribing to team channel', { teamId });

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
                    console.debug('[useRealtimeStatus] ServiceStatusChanged event received', e);
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

            console.debug('[useRealtimeStatus] Successfully subscribed to team channel', { teamId });
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
     * Fetch status updates from API
     */
    const fetchStatusUpdates = React.useCallback(async () => {
        if (!teamId) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // Fetch applications status
            if (onApplicationStatusChange) {
                const appsResponse = await fetch('/api/v1/applications', {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (appsResponse.ok) {
                    const apps = await appsResponse.json();
                    if (Array.isArray(apps)) {
                        apps.forEach((app: { id: number; status: Application['status'] }) => {
                            onApplicationStatusChange({
                                applicationId: app.id,
                                status: app.status,
                            });
                        });
                    }
                }
            }

            // Fetch servers status
            if (onServerStatusChange) {
                const serversResponse = await fetch('/api/v1/servers', {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (serversResponse.ok) {
                    const servers = await serversResponse.json();
                    if (Array.isArray(servers)) {
                        servers.forEach((server: { id: number; is_reachable: boolean; is_usable: boolean }) => {
                            onServerStatusChange({
                                serverId: server.id,
                                isReachable: server.is_reachable,
                                isUsable: server.is_usable,
                            });
                        });
                    }
                }
            }

            // Fetch databases status
            if (onDatabaseStatusChange) {
                const dbResponse = await fetch('/api/v1/databases', {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (dbResponse.ok) {
                    const databases = await dbResponse.json();
                    if (Array.isArray(databases)) {
                        databases.forEach((db: { id: number; status: string }) => {
                            onDatabaseStatusChange({
                                databaseId: db.id,
                                status: db.status,
                            });
                        });
                    }
                }
            }

            // Fetch services status
            if (onServiceStatusChange) {
                const servicesResponse = await fetch('/api/v1/services', {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (servicesResponse.ok) {
                    const services = await servicesResponse.json();
                    if (Array.isArray(services)) {
                        services.forEach((service: { id: number; status: string }) => {
                            onServiceStatusChange({
                                serviceId: service.id,
                                status: service.status,
                            });
                        });
                    }
                }
            }
        } catch (err) {
            console.error('[useRealtimeStatus] Polling fetch error:', err);
        }
    }, [teamId, onApplicationStatusChange, onServerStatusChange, onDatabaseStatusChange, onServiceStatusChange]);

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

        // Immediate first fetch
        fetchStatusUpdates();

        // Set up new polling interval
        pollingIntervalRef.current = setInterval(() => {
            fetchStatusUpdates();
        }, pollingInterval);

        return () => {
            if (pollingIntervalRef.current) {
                clearInterval(pollingIntervalRef.current);
            }
        };
    }, [pollingInterval, fetchStatusUpdates]);

    /**
     * Reconnect to WebSocket
     */
    const reconnect = React.useCallback(() => {
        if (!isMountedRef.current) {
            return;
        }

        if (reconnectAttempts >= maxReconnectAttempts) {
            console.warn('Max reconnection attempts reached, falling back to polling');
            startPolling();
            return;
        }

        setReconnectAttempts((prev) => prev + 1);
        const connected = connectWebSocket();

        if (!connected) {
            // Clear any existing reconnect timeout
            if (reconnectTimeoutRef.current) {
                clearTimeout(reconnectTimeoutRef.current);
            }

            // Try again after delay with exponential backoff
            reconnectTimeoutRef.current = setTimeout(() => {
                if (isMountedRef.current) {
                    reconnect();
                }
            }, 2000 * (reconnectAttempts + 1));
        }
    }, [reconnectAttempts, connectWebSocket, startPolling]);

    /**
     * Initialize connection when auth data is available
     */
    React.useEffect(() => {
        isMountedRef.current = true;

        // Don't attempt connection if user is not authenticated
        if (!teamId || !userId) {
            return;
        }

        const connected = connectWebSocket();

        if (!connected && enableWebSocket) {
            // Start reconnection attempts
            reconnect();
        } else if (!connected) {
            // WebSocket disabled, start polling
            startPolling();
        }

        // Cleanup on unmount or when auth changes
        return () => {
            isMountedRef.current = false;

            // Clear reconnect timeout to prevent setState on unmounted component
            if (reconnectTimeoutRef.current) {
                clearTimeout(reconnectTimeoutRef.current);
                reconnectTimeoutRef.current = null;
            }

            if (pollingIntervalRef.current) {
                clearInterval(pollingIntervalRef.current);
                pollingIntervalRef.current = null;
            }

            const echo = getEcho();
            if (echo && teamId) {
                echo.leave(`team.${teamId}`);
            }

            // Reset connection state
            setIsConnected(false);
            setReconnectAttempts(0);
        };
    }, [teamId, userId, enableWebSocket, connectWebSocket, reconnect, startPolling]);

    return {
        isConnected,
        error,
        reconnect,
        isPolling,
    };
}
