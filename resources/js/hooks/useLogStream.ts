import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { getEcho } from '@/lib/echo';
import { router } from '@inertiajs/react';

/**
 * Log entry structure
 */
export interface LogEntry {
    id: string;
    timestamp: string;
    message: string;
    level?: 'info' | 'error' | 'warning' | 'debug';
    source?: string;
}

/**
 * Log stream event from WebSocket
 */
interface LogStreamEvent {
    deploymentId?: number;
    applicationId?: number;
    message: string;
    timestamp: string;
    level?: string;
}

interface UseLogStreamOptions {
    /**
     * Resource type to stream logs for
     */
    resourceType: 'application' | 'deployment' | 'database' | 'service';

    /**
     * Resource UUID or ID
     */
    resourceId: string | number;

    /**
     * Enable WebSocket streaming (default: true)
     */
    enableWebSocket?: boolean;

    /**
     * Polling interval in milliseconds when WebSocket is unavailable (default: 2000)
     */
    pollingInterval?: number;

    /**
     * Maximum number of log entries to keep in memory (default: 1000)
     */
    maxLogEntries?: number;

    /**
     * Auto-scroll to bottom on new logs (default: true)
     */
    autoScroll?: boolean;

    /**
     * Filter logs by level
     */
    filterLevel?: 'info' | 'error' | 'warning' | 'debug' | 'all';

    /**
     * Callback when new log entry arrives
     */
    onLogEntry?: (entry: LogEntry) => void;

    /**
     * Callback when streaming starts
     */
    onStreamStart?: () => void;

    /**
     * Callback when streaming stops
     */
    onStreamStop?: () => void;
}

interface UseLogStreamReturn {
    /**
     * Log entries
     */
    logs: LogEntry[];

    /**
     * Whether logs are currently streaming
     */
    isStreaming: boolean;

    /**
     * Whether WebSocket is connected
     */
    isConnected: boolean;

    /**
     * Whether using polling fallback
     */
    isPolling: boolean;

    /**
     * Loading state for initial log fetch
     */
    loading: boolean;

    /**
     * Error state
     */
    error: Error | null;

    /**
     * Clear all logs
     */
    clearLogs: () => void;

    /**
     * Pause/resume streaming
     */
    toggleStreaming: () => void;

    /**
     * Manually fetch latest logs
     */
    refresh: () => Promise<void>;

    /**
     * Download logs as a file
     */
    downloadLogs: () => void;
}

/**
 * Custom hook for streaming logs in real-time
 *
 * Streams logs from applications, deployments, databases, or services via:
 * - WebSocket (Laravel Echo) for real-time updates
 * - Polling fallback when WebSocket unavailable
 *
 * @example
 * ```tsx
 * const { logs, isStreaming, clearLogs, downloadLogs } = useLogStream({
 *   resourceType: 'deployment',
 *   resourceId: deploymentUuid,
 *   onLogEntry: (entry) => {
 *     console.log('New log:', entry.message);
 *   }
 * });
 * ```
 */
export function useLogStream(options: UseLogStreamOptions): UseLogStreamReturn {
    const {
        resourceType,
        resourceId,
        enableWebSocket = true,
        pollingInterval = 2000,
        maxLogEntries = 1000,
        autoScroll = true,
        filterLevel = 'all',
        onLogEntry,
        onStreamStart,
        onStreamStop,
    } = options;

    const page = usePage();
    const auth = page.props.auth;
    const teamId = auth?.team?.id;

    const [logs, setLogs] = React.useState<LogEntry[]>([]);
    const [isStreaming, setIsStreaming] = React.useState(false);
    const [isConnected, setIsConnected] = React.useState(false);
    const [isPolling, setIsPolling] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const pollingIntervalRef = React.useRef<NodeJS.Timeout>();
    const lastLogIdRef = React.useRef<string | null>(null);
    const isPausedRef = React.useRef(false);

    /**
     * Add a new log entry
     */
    const addLogEntry = React.useCallback(
        (entry: LogEntry) => {
            // Check if we should filter this log
            if (filterLevel !== 'all' && entry.level !== filterLevel) {
                return;
            }

            setLogs((prevLogs) => {
                const newLogs = [...prevLogs, entry];

                // Limit log entries to maxLogEntries
                if (newLogs.length > maxLogEntries) {
                    return newLogs.slice(-maxLogEntries);
                }

                return newLogs;
            });

            lastLogIdRef.current = entry.id;
            onLogEntry?.(entry);

            // Auto-scroll if enabled
            if (autoScroll) {
                // Scroll to bottom in next tick
                setTimeout(() => {
                    const logContainer = document.querySelector('[data-log-container]');
                    if (logContainer) {
                        logContainer.scrollTop = logContainer.scrollHeight;
                    }
                }, 0);
            }
        },
        [filterLevel, maxLogEntries, autoScroll, onLogEntry]
    );

    /**
     * Fetch logs from API
     */
    const fetchLogs = React.useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            // Build endpoint based on resource type (use web routes with CSRF for session auth)
            let endpoint = '';
            switch (resourceType) {
                case 'application':
                    endpoint = `/applications/${resourceId}/logs/json`;
                    break;
                case 'deployment':
                    endpoint = `/deployments/${resourceId}/logs/json`;
                    break;
                case 'database':
                    endpoint = `/databases/${resourceId}/logs/json`;
                    break;
                case 'service':
                    endpoint = `/services/${resourceId}/logs/json`;
                    break;
            }

            // Add query parameters for incremental fetching
            if (lastLogIdRef.current) {
                endpoint += `?after=${lastLogIdRef.current}`;
            }

            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch logs: ${response.status}`);
            }

            const data = await response.json();

            // Handle deployment logs format (has logs array)
            if (data.logs && Array.isArray(data.logs)) {
                const newLogs = data.logs.filter((log: any) => !log.hidden);
                const startOrder = lastLogIdRef.current ? parseInt(lastLogIdRef.current.split('-')[1] || '0', 10) : 0;

                newLogs.forEach((log: any, index: number) => {
                    const logOrder = log.order || (startOrder + index + 1);
                    // Skip logs we've already seen
                    if (lastLogIdRef.current && logOrder <= startOrder) {
                        return;
                    }

                    addLogEntry({
                        id: `${resourceType}-${logOrder}`,
                        timestamp: log.timestamp || new Date().toISOString(),
                        message: log.output || log.message || '',
                        level: log.type === 'stderr' ? 'error' : 'info',
                        source: resourceType,
                    });
                });
            }

            // Handle container logs format (has container_logs string or containers array)
            if (data.container_logs) {
                const lines = data.container_logs.split('\n').filter(Boolean);
                lines.forEach((line: string, index: number) => {
                    addLogEntry({
                        id: `${resourceType}-${Date.now()}-${index}`,
                        timestamp: new Date().toISOString(),
                        message: line,
                        level: line.toLowerCase().includes('error') ? 'error' : 'info',
                        source: resourceType,
                    });
                });
            }

            // Handle service logs format (has containers array)
            if (data.containers && Array.isArray(data.containers)) {
                data.containers.forEach((container: any) => {
                    if (container.logs) {
                        const lines = container.logs.split('\n').filter(Boolean);
                        lines.forEach((line: string, index: number) => {
                            addLogEntry({
                                id: `${container.name}-${Date.now()}-${index}`,
                                timestamp: new Date().toISOString(),
                                message: `[${container.name}] ${line}`,
                                level: line.toLowerCase().includes('error') ? 'error' : 'info',
                                source: container.name,
                            });
                        });
                    }
                });
            }
        } catch (err) {
            console.error('Failed to fetch logs:', err);
            setError(err instanceof Error ? err : new Error('Failed to fetch logs'));
        } finally {
            setLoading(false);
        }
    }, [resourceType, resourceId, addLogEntry]);

    /**
     * Connect to WebSocket for log streaming
     */
    const connectWebSocket = React.useCallback(() => {
        if (!enableWebSocket || !teamId || !resourceId) {
            return false;
        }

        try {
            const echo = getEcho();
            if (!echo) {
                // Echo initialization failed, but don't throw - just return false
                // This allows graceful fallback to polling or silent failure in tests
                console.warn('Echo is not available for log streaming, falling back to polling');
                setIsConnected(false);
                return false;
            }

            // Subscribe to resource-specific channel
            const channelName = `${resourceType}.${resourceId}.logs`;
            const channel = echo.private(channelName);

            // Listen for log events
            channel.listen('LogEntry', (event: LogStreamEvent) => {
                if (isPausedRef.current) {
                    return;
                }

                const logEntry: LogEntry = {
                    id: `${Date.now()}-${Math.random()}`,
                    timestamp: event.timestamp,
                    message: event.message,
                    level: event.level as LogEntry['level'],
                    source: resourceType,
                };

                addLogEntry(logEntry);
            });

            setIsConnected(true);
            setIsStreaming(true);
            setError(null);
            onStreamStart?.();

            return true;
        } catch (err) {
            console.error('WebSocket connection failed for logs:', err);
            setError(err instanceof Error ? err : new Error('WebSocket connection failed'));
            setIsConnected(false);
            return false;
        }
    }, [enableWebSocket, teamId, resourceId, resourceType, addLogEntry, onStreamStart]);

    /**
     * Start polling as fallback
     */
    const startPolling = React.useCallback(() => {
        if (!pollingInterval || pollingInterval <= 0) {
            return;
        }

        setIsPolling(true);
        setIsStreaming(true);
        onStreamStart?.();

        // Clear existing polling interval
        if (pollingIntervalRef.current) {
            clearInterval(pollingIntervalRef.current);
        }

        // Initial fetch
        fetchLogs();

        // Set up polling interval
        pollingIntervalRef.current = setInterval(() => {
            if (!isPausedRef.current) {
                fetchLogs();
            }
        }, pollingInterval);
    }, [pollingInterval, fetchLogs, onStreamStart]);

    /**
     * Stop streaming
     */
    const stopStreaming = React.useCallback(() => {
        // Clear polling interval
        if (pollingIntervalRef.current) {
            clearInterval(pollingIntervalRef.current);
            pollingIntervalRef.current = undefined;
        }

        // Leave WebSocket channel
        const echo = getEcho();
        if (echo && resourceId) {
            const channelName = `${resourceType}.${resourceId}.logs`;
            echo.leave(channelName);
        }

        setIsStreaming(false);
        setIsPolling(false);
        setIsConnected(false);
        onStreamStop?.();
    }, [resourceId, resourceType, onStreamStop]);

    /**
     * Toggle streaming pause/resume
     */
    const toggleStreaming = React.useCallback(() => {
        isPausedRef.current = !isPausedRef.current;

        if (isPausedRef.current) {
            setIsStreaming(false);
        } else {
            setIsStreaming(true);
        }
    }, []);

    /**
     * Clear all logs
     */
    const clearLogs = React.useCallback(() => {
        setLogs([]);
        lastLogIdRef.current = null;
    }, []);

    /**
     * Download logs as a file
     */
    const downloadLogs = React.useCallback(() => {
        const logText = logs
            .map((log) => `[${log.timestamp}] ${log.level?.toUpperCase() || 'INFO'}: ${log.message}`)
            .join('\n');

        const blob = new Blob([logText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${resourceType}-${resourceId}-logs-${new Date().toISOString()}.log`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }, [logs, resourceType, resourceId]);

    /**
     * Initialize streaming on mount
     */
    React.useEffect(() => {
        // Try WebSocket first
        const connected = connectWebSocket();

        if (!connected && enableWebSocket) {
            // Fallback to polling
            console.warn('WebSocket unavailable, falling back to polling');
            startPolling();
        } else if (!connected) {
            // WebSocket disabled, use polling
            startPolling();
        }

        // Cleanup on unmount
        return () => {
            stopStreaming();
        };
    }, [resourceType, resourceId]); // Re-initialize when resource changes

    return {
        logs,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        refresh: fetchLogs,
        downloadLogs,
    };
}
