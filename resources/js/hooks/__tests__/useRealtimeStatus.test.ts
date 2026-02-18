import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook } from '@testing-library/react';

// Mock Echo
const mockListen = vi.fn().mockReturnThis();
const mockPrivate = vi.fn(() => ({ listen: mockListen }));
const mockLeave = vi.fn();
const mockEcho = {
    private: mockPrivate,
    leave: mockLeave,
};

vi.mock('@/lib/echo', () => ({
    getEcho: () => mockEcho,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            team: { id: 1 },
            auth: { id: 42 },
        },
    }),
}));

import { useRealtimeStatus } from '../useRealtimeStatus';

describe('useRealtimeStatus', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('subscribes to team channel on mount', () => {
        renderHook(() => useRealtimeStatus({}));

        expect(mockPrivate).toHaveBeenCalledWith('team.1');
    });

    it('registers ApplicationStatusChanged listener when callback provided', () => {
        const onAppChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onApplicationStatusChange: onAppChange }));

        expect(mockListen).toHaveBeenCalledWith('ApplicationStatusChanged', expect.any(Function));
    });

    it('registers DatabaseStatusChanged listener when callback provided', () => {
        const onDbChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onDatabaseStatusChange: onDbChange }));

        expect(mockListen).toHaveBeenCalledWith('DatabaseStatusChanged', expect.any(Function));
    });

    it('registers ServiceStatusChanged listener when callback provided', () => {
        const onServiceChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onServiceStatusChange: onServiceChange }));

        expect(mockListen).toHaveBeenCalledWith('ServiceStatusChanged', expect.any(Function));
    });

    it('registers ServerReachabilityChanged listener when callback provided', () => {
        const onServerChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onServerStatusChange: onServerChange }));

        expect(mockListen).toHaveBeenCalledWith('ServerReachabilityChanged', expect.any(Function));
    });

    it('registers DeploymentCreated listener when callback provided', () => {
        const onDeployCreated = vi.fn();
        renderHook(() => useRealtimeStatus({ onDeploymentCreated: onDeployCreated }));

        expect(mockListen).toHaveBeenCalledWith('DeploymentCreated', expect.any(Function));
    });

    it('registers DeploymentFinished listener when callback provided', () => {
        const onDeployFinished = vi.fn();
        renderHook(() => useRealtimeStatus({ onDeploymentFinished: onDeployFinished }));

        expect(mockListen).toHaveBeenCalledWith('DeploymentFinished', expect.any(Function));
    });

    it('registers ResourceTransferStatusChanged listener when callback provided', () => {
        const onTransferChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onTransferStatusChange: onTransferChange }));

        expect(mockListen).toHaveBeenCalledWith('ResourceTransferStatusChanged', expect.any(Function));
    });

    it('does NOT register ResourceTransferStatusChanged when callback not provided', () => {
        renderHook(() => useRealtimeStatus({}));

        const eventNames = mockListen.mock.calls.map((call) => call[0]);
        expect(eventNames).not.toContain('ResourceTransferStatusChanged');
    });

    it('calls transfer callback with event data', () => {
        const onTransferChange = vi.fn();
        renderHook(() => useRealtimeStatus({ onTransferStatusChange: onTransferChange }));

        // Find the ResourceTransferStatusChanged handler
        const call = mockListen.mock.calls.find((c) => c[0] === 'ResourceTransferStatusChanged');
        expect(call).toBeDefined();

        const handler = call![1];
        const eventData = {
            transferId: 1,
            uuid: 'abc-123',
            status: 'transferring',
            progress: 50,
            currentStep: 'Copying data',
            transferredBytes: 1024,
            totalBytes: 2048,
            errorMessage: null,
        };

        handler(eventData);
        expect(onTransferChange).toHaveBeenCalledWith(eventData);
    });

    it('returns isConnected true after successful connection', () => {
        const { result } = renderHook(() => useRealtimeStatus({}));
        expect(result.current.isConnected).toBe(true);
    });

    it('returns isPolling false when WebSocket connects', () => {
        const { result } = renderHook(() => useRealtimeStatus({}));
        expect(result.current.isPolling).toBe(false);
    });

    it('does not subscribe when enableWebSocket is false', () => {
        renderHook(() => useRealtimeStatus({ enableWebSocket: false }));
        // Should not have called private channel immediately
        // (it will fall through to reconnect/polling logic)
    });

    it('does not register listeners without callbacks', () => {
        renderHook(() => useRealtimeStatus({}));

        const eventNames = mockListen.mock.calls.map((call) => call[0]);
        expect(eventNames).not.toContain('ApplicationStatusChanged');
        expect(eventNames).not.toContain('DatabaseStatusChanged');
        expect(eventNames).not.toContain('ServiceStatusChanged');
        expect(eventNames).not.toContain('ServerReachabilityChanged');
        expect(eventNames).not.toContain('DeploymentCreated');
        expect(eventNames).not.toContain('DeploymentFinished');
        expect(eventNames).not.toContain('ResourceTransferStatusChanged');
    });

    it('leaves channel on unmount', () => {
        const { unmount } = renderHook(() => useRealtimeStatus({}));
        unmount();

        expect(mockLeave).toHaveBeenCalledWith('team.1');
    });
});
