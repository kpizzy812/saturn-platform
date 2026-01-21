import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';

// Mock Echo
const mockListen = vi.fn();
const mockChannelLeave = vi.fn();
const mockEchoLeave = vi.fn();

// Create a channel object that supports chaining
const createMockChannel = () => {
    const channel = {
        listen: vi.fn((event: string, callback: any) => {
            mockListen(event, callback);
            return channel; // Return self for chaining
        }),
        leave: mockChannelLeave,
        error: vi.fn().mockReturnThis(),
    };
    return channel;
};

const mockPrivateChannel = vi.fn(() => createMockChannel());
const mockChannel = vi.fn(() => createMockChannel());

const mockEcho = {
    private: mockPrivateChannel,
    channel: mockChannel,
    leave: mockEchoLeave,
    connector: {
        pusher: {
            connection: {
                state: 'connected',
            },
        },
    },
};

vi.mock('@/lib/echo', () => ({
    getEcho: vi.fn(() => mockEcho),
    isEchoConnected: vi.fn(() => true),
}));

// Mock usePage
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
                team: { id: 1, name: 'Test Team' },
            },
        },
    }),
}));

describe('useRealtimeStatus', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should start with disconnected state when WebSocket is disabled', () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 0, // Disable polling too
            }));

            expect(result.current.isConnected).toBe(false);
            expect(result.current.isPolling).toBe(false);
            expect(result.current.error).toBe(null);
        });
    });

    describe('WebSocket connection', () => {
        it('should connect to WebSocket on mount', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(mockPrivateChannel).toHaveBeenCalledWith('team.1');
            });
        });

        it('should set connected state after successful connection', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(result.current.isConnected).toBe(true);
            });
        });

        it('should listen to ApplicationStatusChanged events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onApplicationStatusChange: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('ApplicationStatusChanged', expect.any(Function));
            });
        });

        it('should listen to DatabaseStatusChanged events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onDatabaseStatusChange: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('DatabaseStatusChanged', expect.any(Function));
            });
        });

        it('should listen to ServiceStatusChanged events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onServiceStatusChange: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('ServiceStatusChanged', expect.any(Function));
            });
        });

        it('should listen to ServerReachabilityChanged events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onServerStatusChange: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('ServerReachabilityChanged', expect.any(Function));
            });
        });

        it('should listen to DeploymentCreated events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onDeploymentCreated: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('DeploymentCreated', expect.any(Function));
            });
        });

        it('should listen to DeploymentFinished events', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onDeploymentFinished: vi.fn(),
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('DeploymentFinished', expect.any(Function));
            });
        });
    });

    describe('event callbacks', () => {
        it('should call onApplicationStatusChange when event is received', async () => {
            const onApplicationStatusChange = vi.fn();

            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onApplicationStatusChange,
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalled();
            });

            // Simulate event
            const listenCall = mockListen.mock.calls.find(
                call => call[0] === 'ApplicationStatusChanged'
            );
            if (listenCall) {
                const callback = listenCall[1];
                callback({ applicationId: 1, status: 'running' });
            }

            expect(onApplicationStatusChange).toHaveBeenCalledWith({
                applicationId: 1,
                status: 'running',
            });
        });

        it('should call onDeploymentCreated when event is received', async () => {
            const onDeploymentCreated = vi.fn();

            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onDeploymentCreated,
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalled();
            });

            // Simulate event
            const listenCall = mockListen.mock.calls.find(
                call => call[0] === 'DeploymentCreated'
            );
            if (listenCall) {
                const callback = listenCall[1];
                callback({
                    deploymentId: 1,
                    applicationId: 1,
                    status: 'queued',
                });
            }

            expect(onDeploymentCreated).toHaveBeenCalledWith({
                deploymentId: 1,
                applicationId: 1,
                status: 'queued',
            });
        });

        it('should call onConnectionChange when connection status changes', async () => {
            const onConnectionChange = vi.fn();

            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onConnectionChange,
            }));

            await waitFor(() => {
                expect(onConnectionChange).toHaveBeenCalledWith(true);
            });
        });
    });

    describe('polling fallback', () => {
        it('should not start polling when WebSocket is enabled and connected', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                pollingInterval: 1000,
            }));

            await waitFor(() => {
                expect(result.current.isConnected).toBe(true);
            });

            expect(result.current.isPolling).toBe(false);
        });

        it('should start polling when WebSocket is disabled', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 1000,
            }));

            await waitFor(() => {
                expect(result.current.isPolling).toBe(true);
            });
        });

        it('should use custom polling interval', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 5000,
            }));

            await waitFor(() => {
                expect(mockListen).not.toHaveBeenCalled();
            });
        });
    });

    describe('reconnection', () => {
        it('should provide reconnect function', () => {
            const { result } = renderHook(() => useRealtimeStatus());

            expect(typeof result.current.reconnect).toBe('function');
        });

        it('should attempt to reconnect when reconnect is called', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(result.current.isConnected).toBe(true);
            });

            vi.clearAllMocks();

            // Call reconnect
            result.current.reconnect();

            await waitFor(() => {
                expect(mockPrivateChannel).toHaveBeenCalled();
            });
        });
    });

    describe('cleanup', () => {
        it('should leave channels on unmount', async () => {
            const { unmount } = renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(mockPrivateChannel).toHaveBeenCalled();
            });

            unmount();

            expect(mockEchoLeave).toHaveBeenCalledWith('team.1');
        });

        it('should clear polling interval on unmount', async () => {
            const clearIntervalSpy = vi.spyOn(global, 'clearInterval');

            const { unmount } = renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 1000,
            }));

            await waitFor(() => {
                expect(clearIntervalSpy).not.toHaveBeenCalled();
            });

            unmount();

            expect(clearIntervalSpy).toHaveBeenCalled();
        });
    });

    describe('error handling', () => {
        it('should handle WebSocket connection errors', async () => {
            // Import the mocked module
            const { getEcho } = await import('@/lib/echo');
            const mockGetEchoFn = vi.mocked(getEcho);

            // Override to throw error (persists across reconnection attempts)
            mockGetEchoFn.mockImplementation(() => {
                throw new Error('Connection failed');
            });

            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(result.current.error).not.toBe(null);
                expect(result.current.isConnected).toBe(false);
            });

            // Restore original mock
            mockGetEchoFn.mockImplementation(() => mockEcho);
        });

        it('should call onConnectionChange with false on error', async () => {
            const { getEcho } = await import('@/lib/echo');
            const mockGetEchoFn = vi.mocked(getEcho);

            const onConnectionChange = vi.fn();

            // Override to throw error (persists across reconnection attempts)
            mockGetEchoFn.mockImplementation(() => {
                throw new Error('Connection failed');
            });

            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onConnectionChange,
            }));

            await waitFor(() => {
                expect(onConnectionChange).toHaveBeenCalledWith(false);
            });

            // Restore original mock
            mockGetEchoFn.mockImplementation(() => mockEcho);
        });
    });

    describe('options', () => {
        it('should not connect when enableWebSocket is false', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
            }));

            await waitFor(() => {
                expect(result.current.isConnected).toBe(false);
            });

            expect(mockPrivateChannel).not.toHaveBeenCalled();
        });

        it('should respect pollingInterval option', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 10000,
            }));

            await waitFor(() => {
                expect(mockPrivateChannel).not.toHaveBeenCalled();
            });
        });

        it('should not start polling if pollingInterval is 0', async () => {
            const { result } = renderHook(() => useRealtimeStatus({
                enableWebSocket: false,
                pollingInterval: 0,
            }));

            await waitFor(() => {
                expect(result.current.isPolling).toBe(false);
            });
        });
    });

    describe('multiple callbacks', () => {
        it('should handle multiple event types simultaneously', async () => {
            const onApplicationStatusChange = vi.fn();
            const onDeploymentCreated = vi.fn();
            const onServerStatusChange = vi.fn();

            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
                onApplicationStatusChange,
                onDeploymentCreated,
                onServerStatusChange,
            }));

            await waitFor(() => {
                expect(mockListen).toHaveBeenCalledWith('ApplicationStatusChanged', expect.any(Function));
                expect(mockListen).toHaveBeenCalledWith('DeploymentCreated', expect.any(Function));
                expect(mockListen).toHaveBeenCalledWith('ServerReachabilityChanged', expect.any(Function));
            });
        });

        it('should not listen to events without callbacks', async () => {
            renderHook(() => useRealtimeStatus({
                enableWebSocket: true,
            }));

            await waitFor(() => {
                expect(mockListen).not.toHaveBeenCalled();
            });
        });
    });
});
