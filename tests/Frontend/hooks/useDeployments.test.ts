import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useDeployments, useDeployment } from '@/hooks/useDeployments';
import type { Deployment } from '@/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('useDeployments', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {}));

            const { result } = renderHook(() => useDeployments());

            expect(result.current.isLoading).toBe(true);
            expect(result.current.deployments).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch all deployments successfully', async () => {
            const mockDeployments: Deployment[] = [
                {
                    id: 1,
                    uuid: 'deployment-1',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'in_progress',
                    commit_sha: 'abc123',
                    commit_message: 'Initial commit',
                    branch: 'main',
                    started_at: '2024-01-01T10:00:00Z',
                    finished_at: null,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
                {
                    id: 2,
                    uuid: 'deployment-2',
                    application_uuid: 'app-2',
                    application_name: 'Another App',
                    status: 'finished',
                    commit_sha: 'def456',
                    commit_message: 'Fix bugs',
                    branch: 'main',
                    started_at: '2024-01-01T09:00:00Z',
                    finished_at: '2024-01-01T09:05:00Z',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployments,
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.deployments).toEqual(mockDeployments);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deployments', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });

        it('should fetch deployments for specific application', async () => {
            const mockDeployments: Deployment[] = [
                {
                    id: 1,
                    uuid: 'deployment-1',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'finished',
                    commit_sha: 'abc123',
                    commit_message: 'Initial commit',
                    branch: 'main',
                    started_at: '2024-01-01T10:00:00Z',
                    finished_at: '2024-01-01T10:05:00Z',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployments,
            });

            const { result } = renderHook(() => useDeployments({ applicationUuid: 'app-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.deployments).toEqual(mockDeployments);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deployments/applications/app-1', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });

        it('should fetch empty deployments list', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.deployments).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('error handling', () => {
        it('should handle fetch error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Internal Server Error',
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch deployments');
            expect(result.current.deployments).toEqual([]);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch deployments when refetch is called', async () => {
            const mockDeployments: Deployment[] = [
                {
                    id: 1,
                    uuid: 'deployment-1',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'finished',
                    commit_sha: 'abc123',
                    commit_message: 'Initial commit',
                    branch: 'main',
                    started_at: '2024-01-01T10:00:00Z',
                    finished_at: '2024-01-01T10:05:00Z',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockDeployments,
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedDeployments = [
                ...mockDeployments,
                {
                    id: 2,
                    uuid: 'deployment-2',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'in_progress',
                    commit_sha: 'def456',
                    commit_message: 'New deployment',
                    branch: 'main',
                    started_at: '2024-01-01T11:00:00Z',
                    finished_at: null,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedDeployments,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.deployments).toHaveLength(2);
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('startDeployment mutation', () => {
        it('should start a new deployment', async () => {
            const mockDeployments: Deployment[] = [];
            const newDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'queued',
                commit_sha: 'abc123',
                commit_message: 'Deploy now',
                branch: 'main',
                started_at: null,
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployments,
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newDeployment,
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newDeployment],
            });

            const deployment = await result.current.startDeployment('app-1');

            expect(deployment).toEqual(newDeployment);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deploy', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    uuid: 'app-1',
                    force: false,
                }),
            });

            await waitFor(() => {
                expect(result.current.deployments).toHaveLength(1);
            });
        });

        it('should start a forced deployment', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            const newDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'queued',
                commit_sha: 'abc123',
                commit_message: 'Force deploy',
                branch: 'main',
                started_at: null,
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newDeployment,
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newDeployment],
            });

            await result.current.startDeployment('app-1', true);

            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deploy', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    uuid: 'app-1',
                    force: true,
                }),
            });
        });

        it('should handle start deployment error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(result.current.startDeployment('app-1')).rejects.toThrow('Failed to start deployment');
        });
    });

    describe('cancelDeployment mutation', () => {
        it('should cancel a deployment', async () => {
            const mockDeployments: Deployment[] = [
                {
                    id: 1,
                    uuid: 'deployment-1',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'in_progress',
                    commit_sha: 'abc123',
                    commit_message: 'Deploy',
                    branch: 'main',
                    started_at: '2024-01-01T10:00:00Z',
                    finished_at: null,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployments,
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
            });

            const cancelledDeployments = [
                {
                    ...mockDeployments[0],
                    status: 'cancelled',
                    finished_at: '2024-01-01T10:02:00Z',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => cancelledDeployments,
            });

            await result.current.cancelDeployment('deployment-1');

            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deployments/deployment-1/cancel', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            await waitFor(() => {
                expect(result.current.deployments[0].status).toBe('cancelled');
            });
        });

        it('should handle cancel deployment error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDeployments());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(result.current.cancelDeployment('deployment-1')).rejects.toThrow('Failed to cancel deployment');
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockDeployments: Deployment[] = [
                {
                    id: 1,
                    uuid: 'deployment-1',
                    application_uuid: 'app-1',
                    application_name: 'My App',
                    status: 'in_progress',
                    commit_sha: 'abc123',
                    commit_message: 'Deploy',
                    branch: 'main',
                    started_at: '2024-01-01T10:00:00Z',
                    finished_at: null,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockDeployments,
            });

            renderHook(() => useDeployments({ autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});

describe('useDeployment', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {}));

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            expect(result.current.isLoading).toBe(true);
            expect(result.current.deployment).toBe(null);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch a single deployment', async () => {
            const mockDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'in_progress',
                commit_sha: 'abc123',
                commit_message: 'Deploy',
                branch: 'main',
                started_at: '2024-01-01T10:00:00Z',
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployment,
            });

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.deployment).toEqual(mockDeployment);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deployments/deployment-1', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });
    });

    describe('error handling', () => {
        it('should handle fetch error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Not Found',
            });

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch deployment');
            expect(result.current.deployment).toBe(null);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch deployment when refetch is called', async () => {
            const mockDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'in_progress',
                commit_sha: 'abc123',
                commit_message: 'Deploy',
                branch: 'main',
                started_at: '2024-01-01T10:00:00Z',
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployment,
            });

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedDeployment = {
                ...mockDeployment,
                status: 'finished',
                finished_at: '2024-01-01T10:05:00Z',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedDeployment,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.deployment?.status).toBe('finished');
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('cancel functionality', () => {
        it('should cancel a deployment', async () => {
            const mockDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'in_progress',
                commit_sha: 'abc123',
                commit_message: 'Deploy',
                branch: 'main',
                started_at: '2024-01-01T10:00:00Z',
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployment,
            });

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
            });

            const cancelledDeployment = {
                ...mockDeployment,
                status: 'cancelled',
                finished_at: '2024-01-01T10:02:00Z',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => cancelledDeployment,
            });

            await result.current.cancel();

            expect(mockFetch).toHaveBeenCalledWith('/api/v1/deployments/deployment-1/cancel', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            await waitFor(() => {
                expect(result.current.deployment?.status).toBe('cancelled');
            });
        });

        it('should handle cancel error', async () => {
            const mockDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'in_progress',
                commit_sha: 'abc123',
                commit_message: 'Deploy',
                branch: 'main',
                started_at: '2024-01-01T10:00:00Z',
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDeployment,
            });

            const { result } = renderHook(() => useDeployment({ uuid: 'deployment-1' }));

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(result.current.cancel()).rejects.toThrow('Failed to cancel deployment');
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockDeployment: Deployment = {
                id: 1,
                uuid: 'deployment-1',
                application_uuid: 'app-1',
                application_name: 'My App',
                status: 'in_progress',
                commit_sha: 'abc123',
                commit_message: 'Deploy',
                branch: 'main',
                started_at: '2024-01-01T10:00:00Z',
                finished_at: null,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockDeployment,
            });

            renderHook(() => useDeployment({ uuid: 'deployment-1', autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});
