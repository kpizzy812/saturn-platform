import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { getEcho } from '@/lib/echo';

/**
 * Terminal data event from WebSocket
 */
interface TerminalDataEvent {
    serverId: number;
    data: string;
}

interface UseTerminalOptions {
    /**
     * Server ID to connect terminal to
     */
    serverId: number | string;

    /**
     * Enable WebSocket connection (default: true)
     */
    enableWebSocket?: boolean;

    /**
     * Callback when terminal connects
     */
    onConnect?: () => void;

    /**
     * Callback when terminal disconnects
     */
    onDisconnect?: () => void;

    /**
     * Callback when data arrives from terminal
     */
    onData?: (data: string) => void;

    /**
     * Callback when error occurs
     */
    onError?: (error: Error) => void;

    /**
     * Reconnection delay in milliseconds (default: 3000)
     */
    reconnectDelay?: number;

    /**
     * Maximum reconnection attempts (default: 10)
     */
    maxReconnectAttempts?: number;
}

interface UseTerminalReturn {
    /**
     * Whether terminal is connected
     */
    isConnected: boolean;

    /**
     * Whether terminal is connecting
     */
    isConnecting: boolean;

    /**
     * Error state
     */
    error: Error | null;

    /**
     * Current reconnect attempt
     */
    reconnectAttempt: number;

    /**
     * Send data to terminal
     */
    sendData: (data: string) => void;

    /**
     * Resize terminal
     */
    resize: (cols: number, rows: number) => void;

    /**
     * Manually connect terminal
     */
    connect: () => void;

    /**
     * Manually disconnect terminal
     */
    disconnect: () => void;

    /**
     * Reconnect terminal
     */
    reconnect: () => void;
}

/**
 * Custom hook for terminal WebSocket connection
 *
 * Manages WebSocket connection to server terminal:
 * - Sends input data to terminal
 * - Receives output data from terminal
 * - Handles terminal resize events
 * - Auto-reconnection on disconnect
 *
 * @example
 * ```tsx
 * const { isConnected, sendData, resize } = useTerminal({
 *   serverId: server.id,
 *   onData: (data) => {
 *     terminal.write(data);
 *   },
 *   onConnect: () => {
 *     console.log('Terminal connected');
 *   }
 * });
 * ```
 */
export function useTerminal(options: UseTerminalOptions): UseTerminalReturn {
    const {
        serverId,
        enableWebSocket = true,
        onConnect,
        onDisconnect,
        onData,
        onError,
        reconnectDelay = 3000,
        maxReconnectAttempts = 10,
    } = options;

    const page = usePage();
    const teamId = (page.props as any).team?.id;

    const [isConnected, setIsConnected] = React.useState(false);
    const [isConnecting, setIsConnecting] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);
    const [reconnectAttempt, setReconnectAttempt] = React.useState(0);

    const reconnectTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const manualReconnectTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const channelRef = React.useRef<any>(null);
    const shouldReconnectRef = React.useRef(true);
    const isMountedRef = React.useRef(true);

    /**
     * Send data to terminal
     */
    const sendData = React.useCallback(
        (data: string) => {
            if (!isConnected || !channelRef.current) {
                console.warn('Terminal not connected, cannot send data');
                return;
            }

            try {
                // Send data to terminal via WebSocket
                channelRef.current.whisper('terminal.input', {
                    serverId,
                    data,
                });
            } catch (err) {
                console.error('Failed to send terminal data:', err);
                const error = err instanceof Error ? err : new Error('Failed to send data');
                setError(error);
                onError?.(error);
            }
        },
        [isConnected, serverId, onError]
    );

    /**
     * Resize terminal
     */
    const resize = React.useCallback(
        (cols: number, rows: number) => {
            if (!isConnected || !channelRef.current) {
                return;
            }

            try {
                // Send resize event to terminal
                channelRef.current.whisper('terminal.resize', {
                    serverId,
                    cols,
                    rows,
                });
            } catch (err) {
                console.error('Failed to resize terminal:', err);
            }
        },
        [isConnected, serverId]
    );

    /**
     * Connect to WebSocket for terminal
     */
    const connect = React.useCallback(() => {
        if (!enableWebSocket || !teamId || !serverId) {
            return;
        }

        if (isConnected || isConnecting) {
            return;
        }

        setIsConnecting(true);
        setError(null);

        try {
            const echo = getEcho();
            if (!echo) {
                throw new Error('Failed to initialize Echo');
            }

            // Subscribe to terminal channel for this server
            const channelName = `terminal.${serverId}`;
            const channel = echo.private(channelName);

            channelRef.current = channel;

            // Listen for terminal output
            channel.listen('TerminalData', (event: TerminalDataEvent) => {
                if (event.serverId === Number(serverId)) {
                    onData?.(event.data);
                }
            });

            // Listen for connection confirmation
            channel.listen('TerminalConnected', () => {
                setIsConnected(true);
                setIsConnecting(false);
                setReconnectAttempt(0);
                setError(null);
                onConnect?.();
            });

            // Listen for disconnection
            channel.listen('TerminalDisconnected', () => {
                handleDisconnect();
            });

            // Handle subscription success
            channel.subscribed(() => {
                console.debug('Terminal channel subscribed');
            });

            // Handle subscription errors
            channel.error((error: any) => {
                console.error('Terminal channel subscription error:', error);
                const err = new Error('Failed to subscribe to terminal channel');
                setError(err);
                setIsConnecting(false);
                onError?.(err);

                // Try to reconnect
                if (shouldReconnectRef.current && reconnectAttempt < maxReconnectAttempts) {
                    scheduleReconnect();
                }
            });

        } catch (err) {
            console.error('Terminal connection failed:', err);
            const error = err instanceof Error ? err : new Error('Terminal connection failed');
            setError(error);
            setIsConnecting(false);
            onError?.(error);

            // Try to reconnect
            if (shouldReconnectRef.current && reconnectAttempt < maxReconnectAttempts) {
                scheduleReconnect();
            }
        }
    }, [enableWebSocket, teamId, serverId, isConnected, isConnecting, reconnectAttempt, maxReconnectAttempts, onData, onConnect, onError]);

    /**
     * Disconnect from terminal
     */
    const disconnect = React.useCallback(() => {
        shouldReconnectRef.current = false;

        // Clear all reconnection timeouts
        if (reconnectTimeoutRef.current) {
            clearTimeout(reconnectTimeoutRef.current);
            reconnectTimeoutRef.current = null;
        }
        if (manualReconnectTimeoutRef.current) {
            clearTimeout(manualReconnectTimeoutRef.current);
            manualReconnectTimeoutRef.current = null;
        }

        // Leave WebSocket channel
        if (channelRef.current) {
            const echo = getEcho();
            if (echo && serverId) {
                const channelName = `terminal.${serverId}`;
                echo.leave(channelName);
            }
            channelRef.current = null;
        }

        setIsConnected(false);
        setIsConnecting(false);
        onDisconnect?.();
    }, [serverId, onDisconnect]);

    /**
     * Handle disconnection and schedule reconnect
     */
    const handleDisconnect = React.useCallback(() => {
        setIsConnected(false);
        onDisconnect?.();

        // Try to reconnect if enabled
        if (shouldReconnectRef.current && reconnectAttempt < maxReconnectAttempts) {
            scheduleReconnect();
        }
    }, [reconnectAttempt, maxReconnectAttempts, onDisconnect]);

    /**
     * Schedule reconnection attempt
     */
    const scheduleReconnect = React.useCallback(() => {
        if (!isMountedRef.current) {
            return;
        }

        if (reconnectTimeoutRef.current) {
            clearTimeout(reconnectTimeoutRef.current);
        }

        setReconnectAttempt(prev => prev + 1);

        reconnectTimeoutRef.current = setTimeout(() => {
            if (isMountedRef.current) {
                console.debug(`Reconnecting terminal (attempt ${reconnectAttempt + 1}/${maxReconnectAttempts})`);
                connect();
            }
        }, reconnectDelay);
    }, [reconnectAttempt, maxReconnectAttempts, reconnectDelay, connect]);

    /**
     * Manually reconnect terminal
     */
    const reconnect = React.useCallback(() => {
        disconnect();
        shouldReconnectRef.current = true;
        setReconnectAttempt(0);

        // Clear any existing manual reconnect timeout
        if (manualReconnectTimeoutRef.current) {
            clearTimeout(manualReconnectTimeoutRef.current);
        }

        // Wait a bit before reconnecting - save ref for cleanup
        manualReconnectTimeoutRef.current = setTimeout(() => {
            if (isMountedRef.current) {
                connect();
            }
        }, 100);
    }, [disconnect, connect]);

    /**
     * Auto-connect on mount
     */
    React.useEffect(() => {
        isMountedRef.current = true;
        shouldReconnectRef.current = true;
        connect();

        // Cleanup on unmount
        return () => {
            isMountedRef.current = false;
            disconnect();
        };
    }, [serverId]); // Reconnect when server changes

    return {
        isConnected,
        isConnecting,
        error,
        reconnectAttempt,
        sendData,
        resize,
        connect,
        disconnect,
        reconnect,
    };
}
