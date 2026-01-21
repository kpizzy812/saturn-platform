import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useServices, useService, useServiceEnvs } from '@/hooks/useServices';
import type { Service } from '@/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('useServices', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {}));

            const { result } = renderHook(() => useServices());

            expect(result.current.isLoading).toBe(true);
            expect(result.current.services).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch services successfully', async () => {
            const mockServices: Service[] = [
                {
                    id: 1,
                    uuid: 'service-1',
                    name: 'Test Service 1',
                    description: 'Description 1',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
                {
                    id: 2,
                    uuid: 'service-2',
                    name: 'Test Service 2',
                    description: 'Description 2',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'stopped',
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockServices,
            });

            const { result } = renderHook(() => useServices());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.services).toEqual(mockServices);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/services', {
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
                statusText: 'Internal Server Error',
            });

            const { result } = renderHook(() => useServices());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch services');
            expect(result.current.services).toEqual([]);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useServices());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch services when refetch is called', async () => {
            const mockServices: Service[] = [
                {
                    id: 1,
                    uuid: 'service-1',
                    name: 'Test Service',
                    description: 'Description',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockServices,
            });

            const { result } = renderHook(() => useServices());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedServices = [
                ...mockServices,
                {
                    id: 2,
                    uuid: 'service-2',
                    name: 'New Service',
                    description: 'New Description',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'stopped',
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedServices,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.services).toHaveLength(2);
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('createService mutation', () => {
        it('should create a new service', async () => {
            const mockServices: Service[] = [];
            const newService: Service = {
                id: 1,
                uuid: 'service-1',
                name: 'New Service',
                description: 'New Description',
                docker_compose_raw: 'version: "3"',
                environment_id: 1,
                destination_id: 1,
                status: 'stopped',
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockServices,
            });

            const { result } = renderHook(() => useServices());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newService,
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newService],
            });

            const createdService = await result.current.createService({
                name: 'New Service',
                description: 'New Description',
                docker_compose_raw: 'version: "3"',
                environment_id: 1,
                destination_id: 1,
            });

            expect(createdService).toEqual(newService);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/services', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: 'New Service',
                    description: 'New Description',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                }),
            });

            await waitFor(() => {
                expect(result.current.services).toHaveLength(1);
            });
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockServices: Service[] = [
                {
                    id: 1,
                    uuid: 'service-1',
                    name: 'Test Service',
                    description: 'Description',
                    docker_compose_raw: 'version: "3"',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockServices,
            });

            renderHook(() => useServices({ autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});

describe('useService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch a single service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.service).toEqual(mockService);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should update a service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const updatedService = {
            ...mockService,
            name: 'Updated Service',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => updatedService,
        });

        await result.current.updateService({ name: 'Updated Service' });

        await waitFor(() => {
            expect(result.current.service?.name).toBe('Updated Service');
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ name: 'Updated Service' }),
        });
    });

    it('should start a service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'stopped',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ ...mockService, status: 'running' }),
        });

        await result.current.startService();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/start', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should stop a service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ ...mockService, status: 'stopped' }),
        });

        await result.current.stopService();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/stop', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should restart a service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        await result.current.restartService();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/restart', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should delete a service', async () => {
        const mockService: Service = {
            id: 1,
            uuid: 'service-1',
            name: 'Test Service',
            description: 'Description',
            docker_compose_raw: 'version: "3"',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockService,
        });

        const { result } = renderHook(() => useService({ uuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        await result.current.deleteService();

        await waitFor(() => {
            expect(result.current.service).toBe(null);
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });
});

describe('useServiceEnvs', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch service environment variables', async () => {
        const mockEnvs = [
            {
                id: 1,
                uuid: 'env-1',
                key: 'DATABASE_URL',
                value: 'postgres://localhost',
                is_preview: false,
                is_build_time: true,
            },
            {
                id: 2,
                uuid: 'env-2',
                key: 'API_KEY',
                value: 'secret',
                is_preview: false,
                is_build_time: false,
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvs,
        });

        const { result } = renderHook(() => useServiceEnvs({ serviceUuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.envs).toEqual(mockEnvs);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/envs', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should create an environment variable', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        const { result } = renderHook(() => useServiceEnvs({ serviceUuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        const newEnv = {
            id: 1,
            uuid: 'env-1',
            key: 'NEW_VAR',
            value: 'new_value',
            is_preview: false,
            is_build_time: false,
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [newEnv],
        });

        await result.current.createEnv({
            key: 'NEW_VAR',
            value: 'new_value',
            is_preview: false,
            is_build_time: false,
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/envs', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                key: 'NEW_VAR',
                value: 'new_value',
                is_preview: false,
                is_build_time: false,
            }),
        });
    });

    it('should update an environment variable', async () => {
        const mockEnvs = [
            {
                id: 1,
                uuid: 'env-1',
                key: 'DATABASE_URL',
                value: 'postgres://localhost',
                is_preview: false,
                is_build_time: true,
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvs,
        });

        const { result } = renderHook(() => useServiceEnvs({ serviceUuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [
                {
                    ...mockEnvs[0],
                    value: 'postgres://updated',
                },
            ],
        });

        await result.current.updateEnv('env-1', { value: 'postgres://updated' });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/envs', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                uuid: 'env-1',
                value: 'postgres://updated',
            }),
        });
    });

    it('should delete an environment variable', async () => {
        const mockEnvs = [
            {
                id: 1,
                uuid: 'env-1',
                key: 'DATABASE_URL',
                value: 'postgres://localhost',
                is_preview: false,
                is_build_time: true,
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvs,
        });

        const { result } = renderHook(() => useServiceEnvs({ serviceUuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        await result.current.deleteEnv('env-1');

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/envs/env-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should bulk update environment variables', async () => {
        const mockEnvs = [
            {
                id: 1,
                uuid: 'env-1',
                key: 'DATABASE_URL',
                value: 'postgres://localhost',
                is_preview: false,
                is_build_time: true,
            },
            {
                id: 2,
                uuid: 'env-2',
                key: 'API_KEY',
                value: 'secret',
                is_preview: false,
                is_build_time: false,
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvs,
        });

        const { result } = renderHook(() => useServiceEnvs({ serviceUuid: 'service-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const updatedEnvs = [
            { uuid: 'env-1', value: 'postgres://updated' },
            { uuid: 'env-2', value: 'new_secret' },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvs,
        });

        await result.current.bulkUpdateEnvs(updatedEnvs);

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/services/service-1/envs/bulk', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ envs: updatedEnvs }),
        });
    });
});
