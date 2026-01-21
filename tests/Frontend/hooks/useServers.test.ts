import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useServers, useServer, useServerResources, useServerDomains } from '@/hooks/useServers';
import type { Server } from '@/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('useServers', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {}));

            const { result } = renderHook(() => useServers());

            expect(result.current.isLoading).toBe(true);
            expect(result.current.servers).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch servers successfully', async () => {
            const mockServers: Server[] = [
                {
                    id: 1,
                    uuid: 'server-1',
                    name: 'Production Server',
                    description: 'Main production server',
                    ip: '192.168.1.1',
                    port: 22,
                    user: 'root',
                    is_reachable: true,
                    is_usable: true,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
                {
                    id: 2,
                    uuid: 'server-2',
                    name: 'Staging Server',
                    description: 'Staging environment server',
                    ip: '192.168.1.2',
                    port: 22,
                    user: 'root',
                    is_reachable: true,
                    is_usable: true,
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockServers,
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.servers).toEqual(mockServers);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });

        it('should fetch empty servers list', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.servers).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('error handling', () => {
        it('should handle fetch error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Internal Server Error',
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch servers');
            expect(result.current.servers).toEqual([]);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch servers when refetch is called', async () => {
            const mockServers: Server[] = [
                {
                    id: 1,
                    uuid: 'server-1',
                    name: 'Production Server',
                    description: 'Main production server',
                    ip: '192.168.1.1',
                    port: 22,
                    user: 'root',
                    is_reachable: true,
                    is_usable: true,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockServers,
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedServers = [
                ...mockServers,
                {
                    id: 2,
                    uuid: 'server-2',
                    name: 'New Server',
                    description: 'New server',
                    ip: '192.168.1.3',
                    port: 22,
                    user: 'root',
                    is_reachable: true,
                    is_usable: true,
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedServers,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.servers).toHaveLength(2);
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('createServer mutation', () => {
        it('should create a new server', async () => {
            const mockServers: Server[] = [];
            const newServer: Server = {
                id: 1,
                uuid: 'server-1',
                name: 'New Server',
                description: 'New production server',
                ip: '192.168.1.10',
                port: 22,
                user: 'root',
                is_reachable: false,
                is_usable: false,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockServers,
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newServer,
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newServer],
            });

            const createdServer = await result.current.createServer({
                name: 'New Server',
                description: 'New production server',
                ip: '192.168.1.10',
                port: 22,
                user: 'root',
            });

            expect(createdServer).toEqual(newServer);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: 'New Server',
                    description: 'New production server',
                    ip: '192.168.1.10',
                    port: 22,
                    user: 'root',
                }),
            });

            await waitFor(() => {
                expect(result.current.servers).toHaveLength(1);
            });
        });

        it('should handle create server error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useServers());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(
                result.current.createServer({
                    name: 'New Server',
                    ip: '192.168.1.10',
                })
            ).rejects.toThrow('Failed to create server');
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockServers: Server[] = [
                {
                    id: 1,
                    uuid: 'server-1',
                    name: 'Production Server',
                    description: 'Main production server',
                    ip: '192.168.1.1',
                    port: 22,
                    user: 'root',
                    is_reachable: true,
                    is_usable: true,
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockServers,
            });

            renderHook(() => useServers({ autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});

describe('useServer', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch a single server', async () => {
        const mockServer: Server = {
            id: 1,
            uuid: 'server-1',
            name: 'Production Server',
            description: 'Main production server',
            ip: '192.168.1.1',
            port: 22,
            user: 'root',
            is_reachable: true,
            is_usable: true,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockServer,
        });

        const { result } = renderHook(() => useServer({ uuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.server).toEqual(mockServer);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should update a server', async () => {
        const mockServer: Server = {
            id: 1,
            uuid: 'server-1',
            name: 'Production Server',
            description: 'Main production server',
            ip: '192.168.1.1',
            port: 22,
            user: 'root',
            is_reachable: true,
            is_usable: true,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockServer,
        });

        const { result } = renderHook(() => useServer({ uuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const updatedServer = {
            ...mockServer,
            name: 'Updated Server',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => updatedServer,
        });

        await result.current.updateServer({ name: 'Updated Server' });

        await waitFor(() => {
            expect(result.current.server?.name).toBe('Updated Server');
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ name: 'Updated Server' }),
        });
    });

    it('should delete a server', async () => {
        const mockServer: Server = {
            id: 1,
            uuid: 'server-1',
            name: 'Production Server',
            description: 'Main production server',
            ip: '192.168.1.1',
            port: 22,
            user: 'root',
            is_reachable: true,
            is_usable: true,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockServer,
        });

        const { result } = renderHook(() => useServer({ uuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        await result.current.deleteServer();

        await waitFor(() => {
            expect(result.current.server).toBe(null);
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should validate a server', async () => {
        const mockServer: Server = {
            id: 1,
            uuid: 'server-1',
            name: 'Production Server',
            description: 'Main production server',
            ip: '192.168.1.1',
            port: 22,
            user: 'root',
            is_reachable: false,
            is_usable: false,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockServer,
        });

        const { result } = renderHook(() => useServer({ uuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const validationResult = {
            is_reachable: true,
            is_usable: true,
            docker_installed: true,
            message: 'Server is ready',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => validationResult,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                ...mockServer,
                is_reachable: true,
                is_usable: true,
            }),
        });

        const result_validation = await result.current.validateServer();

        expect(result_validation).toEqual(validationResult);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1/validate', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should handle validate server error', async () => {
        const mockServer: Server = {
            id: 1,
            uuid: 'server-1',
            name: 'Production Server',
            description: 'Main production server',
            ip: '192.168.1.1',
            port: 22,
            user: 'root',
            is_reachable: false,
            is_usable: false,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockServer,
        });

        const { result } = renderHook(() => useServer({ uuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: false,
            statusText: 'Validation Failed',
        });

        await expect(result.current.validateServer()).rejects.toThrow('Failed to validate server');
    });
});

describe('useServerResources', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch server resources', async () => {
        const mockResources = [
            {
                id: 1,
                uuid: 'app-1',
                name: 'My Application',
                type: 'application' as const,
                status: 'running',
            },
            {
                id: 2,
                uuid: 'db-1',
                name: 'PostgreSQL',
                type: 'database' as const,
                status: 'running',
            },
            {
                id: 3,
                uuid: 'service-1',
                name: 'Redis',
                type: 'service' as const,
                status: 'stopped',
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockResources,
        });

        const { result } = renderHook(() => useServerResources({ serverUuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.resources).toEqual(mockResources);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1/resources', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should handle fetch resources error', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: false,
            statusText: 'Internal Server Error',
        });

        const { result } = renderHook(() => useServerResources({ serverUuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.error).toBeInstanceOf(Error);
        expect(result.current.error?.message).toContain('Failed to fetch server resources');
        expect(result.current.resources).toEqual([]);
    });

    it('should auto-refresh when enabled', async () => {
        const mockResources = [
            {
                id: 1,
                uuid: 'app-1',
                name: 'My Application',
                type: 'application' as const,
                status: 'running',
            },
        ];

        mockFetch.mockResolvedValue({
            ok: true,
            json: async () => mockResources,
        });

        renderHook(() => useServerResources({ serverUuid: 'server-1', autoRefresh: true, refreshInterval: 100 }));

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(1);
        });

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        }, { timeout: 2000 });
    });
});

describe('useServerDomains', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch server domains', async () => {
        const mockDomains = [
            {
                domain: 'example.com',
                ssl_status: 'active',
                verified_at: '2024-01-01',
            },
            {
                domain: 'api.example.com',
                ssl_status: 'pending',
                verified_at: null,
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDomains,
        });

        const { result } = renderHook(() => useServerDomains({ serverUuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.domains).toEqual(mockDomains);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/servers/server-1/domains', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should handle fetch domains error', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: false,
            statusText: 'Internal Server Error',
        });

        const { result } = renderHook(() => useServerDomains({ serverUuid: 'server-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.error).toBeInstanceOf(Error);
        expect(result.current.error?.message).toContain('Failed to fetch server domains');
        expect(result.current.domains).toEqual([]);
    });
});
