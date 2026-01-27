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
     * @deprecated Auto-scroll is now handled by LogsContainer component
     * This option is kept for backward compatibility but has no effect
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
        filterLevel = 'all',
        onLogEntry,
        onStreamStart,
        onStreamStop,
    } = options;

    const page = usePage();
    const auth = (page.props as any).auth;
    const teamId = (page.props as any).team?.id;

    const [logs, setLogs] = React.useState<LogEntry[]>([]);
    const [isStreaming, setIsStreaming] = React.useState(false);
    const [isConnected, setIsConnected] = React.useState(false);
    const [isPolling, setIsPolling] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const pollingIntervalRef = React.useRef<ReturnType<typeof setInterval> | undefined>(undefined);
    const lastLogIdRef = React.useRef<string | null>(null);
    const isPausedRef = React.useRef(false);
    const lastTimestampRef = React.useRef<number | null>(null);
    const isInitialLoadRef = React.useRef(true);
    const seenLogHashesRef = React.useRef<Set<string>>(new Set());

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
        },
        [filterLevel, maxLogEntries, onLogEntry]
    );

    /**
     * Fetch logs from API with incremental loading support
     */
    const fetchLogs = React.useCallback(async () => {
        try {
            // Only show loading on initial load
            if (isInitialLoadRef.current) {
                setLoading(true);
            }
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
            const params = new URLSearchParams();
            if (resourceType === 'application' && lastTimestampRef.current && !isInitialLoadRef.current) {
                // For container logs, use timestamp-based incremental fetching
                params.append('since', lastTimestampRef.current.toString());
            } else if (lastLogIdRef.current && resourceType === 'deployment') {
                params.append('after', lastLogIdRef.current);
            }

            if (params.toString()) {
                endpoint += `?${params.toString()}`;
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

            // Update timestamp for next incremental fetch
            if (data.timestamp) {
                lastTimestampRef.current = data.timestamp;
            }

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

                // Only process if there are new logs
                if (lines.length > 0) {
                    const newEntries: LogEntry[] = [];

                    lines.forEach((line: string) => {
                        // Create a hash of the line to detect duplicates
                        const logHash = line.trim();
                        if (seenLogHashesRef.current.has(logHash)) {
                            return; // Skip duplicate
                        }
                        seenLogHashesRef.current.add(logHash);

                        // Parse timestamp from docker logs --timestamps format
                        // Format: 2024-01-26T17:30:00.123456789Z log message
                        const timestampMatch = line.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z)\s+(.*)$/);
                        let timestamp = new Date().toISOString();
                        let message = line;

                        if (timestampMatch) {
                            timestamp = timestampMatch[1];
                            message = timestampMatch[2];
                        }

                        newEntries.push({
                            id: `${resourceType}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                            timestamp,
                            message,
                            level: message.toLowerCase().includes('error') ? 'error' : 'info',
                            source: resourceType,
                        });
                    });

                    // Only update state if we have new entries
                    if (newEntries.length > 0) {
                        setLogs((prevLogs) => {
                            const combined = [...prevLogs, ...newEntries];
                            // Limit to maxLogEntries
                            if (combined.length > maxLogEntries) {
                                return combined.slice(-maxLogEntries);
                            }
                            return combined;
                        });
                    }
                }
            }

            // Handle service logs format (has containers array)
            if (data.containers && Array.isArray(data.containers)) {
                data.containers.forEach((container: any) => {
                    if (container.logs) {
                        const lines = container.logs.split('\n').filter(Boolean);
                        lines.forEach((line: string, index: number) => {
                            const logHash = `${container.name}:${line.trim()}`;
                            if (seenLogHashesRef.current.has(logHash)) {
                                return;
                            }
                            seenLogHashesRef.current.add(logHash);

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

            isInitialLoadRef.current = false;
        } catch (err) {
            console.error('Failed to fetch logs:', err);
            setError(err instanceof Error ? err : new Error('Failed to fetch logs'));
        } finally {
            setLoading(false);
        }
    }, [resourceType, resourceId, addLogEntry, maxLogEntries]);

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
        lastTimestampRef.current = null;
        seenLogHashesRef.current.clear();
        isInitialLoadRef.current = true;
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
