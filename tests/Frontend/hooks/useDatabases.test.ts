import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useDatabases, useDatabase, useDatabaseBackups } from '@/hooks/useDatabases';
import type { StandaloneDatabase } from '@/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('useDatabases', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {}));

            const { result } = renderHook(() => useDatabases());

            expect(result.current.isLoading).toBe(true);
            expect(result.current.databases).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch databases successfully', async () => {
            const mockDatabases: StandaloneDatabase[] = [
                {
                    id: 1,
                    uuid: 'db-1',
                    name: 'PostgreSQL DB',
                    type: 'postgresql',
                    description: 'Production database',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
                {
                    id: 2,
                    uuid: 'db-2',
                    name: 'MySQL DB',
                    type: 'mysql',
                    description: 'Analytics database',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'stopped',
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDatabases,
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.databases).toEqual(mockDatabases);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });

        it('should fetch empty databases list', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.databases).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('error handling', () => {
        it('should handle fetch error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Internal Server Error',
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch databases');
            expect(result.current.databases).toEqual([]);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch databases when refetch is called', async () => {
            const mockDatabases: StandaloneDatabase[] = [
                {
                    id: 1,
                    uuid: 'db-1',
                    name: 'PostgreSQL DB',
                    type: 'postgresql',
                    description: 'Production database',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockDatabases,
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedDatabases = [
                ...mockDatabases,
                {
                    id: 2,
                    uuid: 'db-2',
                    name: 'Redis DB',
                    type: 'redis',
                    description: 'Cache database',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedDatabases,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.databases).toHaveLength(2);
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('createDatabase mutation', () => {
        it('should create a new database', async () => {
            const mockDatabases: StandaloneDatabase[] = [];
            const newDatabase: StandaloneDatabase = {
                id: 1,
                uuid: 'db-1',
                name: 'New DB',
                type: 'postgresql',
                description: 'New database',
                environment_id: 1,
                destination_id: 1,
                status: 'stopped',
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockDatabases,
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newDatabase,
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newDatabase],
            });

            const createdDatabase = await result.current.createDatabase('postgresql', {
                name: 'New DB',
                description: 'New database',
                environment_id: 1,
                destination_id: 1,
            });

            expect(createdDatabase).toEqual(newDatabase);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/postgresql', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: 'New DB',
                    description: 'New database',
                    environment_id: 1,
                    destination_id: 1,
                }),
            });

            await waitFor(() => {
                expect(result.current.databases).toHaveLength(1);
            });
        });

        it('should handle create database error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useDatabases());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(
                result.current.createDatabase('postgresql', {
                    name: 'New DB',
                    environment_id: 1,
                    destination_id: 1,
                })
            ).rejects.toThrow('Failed to create database');
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockDatabases: StandaloneDatabase[] = [
                {
                    id: 1,
                    uuid: 'db-1',
                    name: 'PostgreSQL DB',
                    type: 'postgresql',
                    description: 'Production database',
                    environment_id: 1,
                    destination_id: 1,
                    status: 'running',
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockDatabases,
            });

            renderHook(() => useDatabases({ autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});

describe('useDatabase', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch a single database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.database).toEqual(mockDatabase);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should update a database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const updatedDatabase = {
            ...mockDatabase,
            name: 'Updated DB',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => updatedDatabase,
        });

        await result.current.updateDatabase({ name: 'Updated DB' });

        await waitFor(() => {
            expect(result.current.database?.name).toBe('Updated DB');
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ name: 'Updated DB' }),
        });
    });

    it('should start a database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'stopped',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ ...mockDatabase, status: 'running' }),
        });

        await result.current.startDatabase();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/start', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should stop a database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({ ...mockDatabase, status: 'stopped' }),
        });

        await result.current.stopDatabase();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/stop', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should restart a database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        await result.current.restartDatabase();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/restart', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should delete a database', async () => {
        const mockDatabase: StandaloneDatabase = {
            id: 1,
            uuid: 'db-1',
            name: 'PostgreSQL DB',
            type: 'postgresql',
            description: 'Production database',
            environment_id: 1,
            destination_id: 1,
            status: 'running',
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockDatabase,
        });

        const { result } = renderHook(() => useDatabase({ uuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        await result.current.deleteDatabase();

        await waitFor(() => {
            expect(result.current.database).toBe(null);
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });
});

describe('useDatabaseBackups', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch database backups', async () => {
        const mockBackups = [
            {
                id: 1,
                uuid: 'backup-1',
                database_id: 1,
                filename: 'backup-2024-01-01.sql',
                size: '100MB',
                status: 'completed' as const,
                created_at: '2024-01-01',
            },
            {
                id: 2,
                uuid: 'backup-2',
                database_id: 1,
                filename: 'backup-2024-01-02.sql',
                size: '105MB',
                status: 'completed' as const,
                created_at: '2024-01-02',
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockBackups,
        });

        const { result } = renderHook(() => useDatabaseBackups({ databaseUuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.backups).toEqual(mockBackups);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/backups', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should create a backup', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        const { result } = renderHook(() => useDatabaseBackups({ databaseUuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        const newBackup = {
            id: 1,
            uuid: 'backup-1',
            database_id: 1,
            filename: 'backup-2024-01-01.sql',
            size: '100MB',
            status: 'in_progress' as const,
            created_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => [newBackup],
        });

        await result.current.createBackup();

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/backups', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        await waitFor(() => {
            expect(result.current.backups).toHaveLength(1);
        });
    });

    it('should delete a backup', async () => {
        const mockBackups = [
            {
                id: 1,
                uuid: 'backup-1',
                database_id: 1,
                filename: 'backup-2024-01-01.sql',
                size: '100MB',
                status: 'completed' as const,
                created_at: '2024-01-01',
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockBackups,
        });

        const { result } = renderHook(() => useDatabaseBackups({ databaseUuid: 'db-1' }));

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

        await result.current.deleteBackup('backup-1');

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/databases/db-1/backups/backup-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        await waitFor(() => {
            expect(result.current.backups).toHaveLength(0);
        });
    });

    it('should handle fetch backups error', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: false,
            statusText: 'Internal Server Error',
        });

        const { result } = renderHook(() => useDatabaseBackups({ databaseUuid: 'db-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.error).toBeInstanceOf(Error);
        expect(result.current.error?.message).toContain('Failed to fetch backups');
        expect(result.current.backups).toEqual([]);
    });
});
