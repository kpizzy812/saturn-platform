import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { useProjects, useProject, useProjectEnvironments } from '@/hooks/useProjects';
import type { Project, Environment } from '@/types';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Mock CSRF token for web routes
vi.spyOn(document, 'querySelector').mockImplementation((selector: string) => {
    if (selector === 'meta[name="csrf-token"]') {
        return { content: 'test-csrf-token' } as HTMLMetaElement;
    }
    return null;
});

describe('useProjects', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('initial state', () => {
        it('should start with loading state', () => {
            mockFetch.mockImplementation(() => new Promise(() => {})); // Never resolves

            const { result } = renderHook(() => useProjects());

            expect(result.current.isLoading).toBe(true);
            expect(result.current.projects).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('successful fetch', () => {
        it('should fetch projects successfully', async () => {
            const mockProjects: Project[] = [
                {
                    id: 1,
                    uuid: 'project-1',
                    name: 'Test Project 1',
                    description: 'Description 1',
                    environments: [],
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
                {
                    id: 2,
                    uuid: 'project-2',
                    name: 'Test Project 2',
                    description: 'Description 2',
                    environments: [],
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockProjects,
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.projects).toEqual(mockProjects);
            expect(result.current.error).toBe(null);
            expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });
        });

        it('should fetch empty projects list', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.projects).toEqual([]);
            expect(result.current.error).toBe(null);
        });
    });

    describe('error handling', () => {
        it('should handle fetch error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Internal Server Error',
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toContain('Failed to fetch projects');
            expect(result.current.projects).toEqual([]);
        });

        it('should handle network error', async () => {
            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            expect(result.current.error).toBeInstanceOf(Error);
            expect(result.current.error?.message).toBe('Network error');
        });
    });

    describe('refetch functionality', () => {
        it('should refetch projects when refetch is called', async () => {
            const mockProjects: Project[] = [
                {
                    id: 1,
                    uuid: 'project-1',
                    name: 'Test Project',
                    description: 'Description',
                    environments: [],
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockProjects,
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockClear();

            const updatedProjects = [
                ...mockProjects,
                {
                    id: 2,
                    uuid: 'project-2',
                    name: 'New Project',
                    description: 'New Description',
                    environments: [],
                    created_at: '2024-01-02',
                    updated_at: '2024-01-02',
                },
            ];

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => updatedProjects,
            });

            await result.current.refetch();

            await waitFor(() => {
                expect(result.current.projects).toHaveLength(2);
            });

            expect(mockFetch).toHaveBeenCalledTimes(1);
        });
    });

    describe('createProject mutation', () => {
        it('should create a new project', async () => {
            const mockProjects: Project[] = [];
            const newProject: Project = {
                id: 1,
                uuid: 'project-1',
                name: 'New Project',
                description: 'New Description',
                environments: [],
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            };

            // Initial fetch
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockProjects,
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            // Create project
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => newProject,
            });

            // Refetch after creation
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [newProject],
            });

            const createdProject = await result.current.createProject({
                name: 'New Project',
                description: 'New Description',
            });

            expect(createdProject).toEqual(newProject);
            expect(mockFetch).toHaveBeenCalledWith('/projects', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': 'test-csrf-token',
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: 'New Project',
                    description: 'New Description',
                }),
            });

            await waitFor(() => {
                expect(result.current.projects).toHaveLength(1);
            });
        });

        it('should handle create project error', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

            const { result } = renderHook(() => useProjects());

            await waitFor(() => {
                expect(result.current.isLoading).toBe(false);
            });

            mockFetch.mockResolvedValueOnce({
                ok: false,
                statusText: 'Bad Request',
            });

            await expect(
                result.current.createProject({
                    name: 'New Project',
                })
            ).rejects.toThrow('Failed to create project');
        });
    });

    describe('auto-refresh', () => {
        it('should auto-refresh when enabled', async () => {
            const mockProjects: Project[] = [
                {
                    id: 1,
                    uuid: 'project-1',
                    name: 'Test Project',
                    description: 'Description',
                    environments: [],
                    created_at: '2024-01-01',
                    updated_at: '2024-01-01',
                },
            ];

            mockFetch.mockResolvedValue({
                ok: true,
                json: async () => mockProjects,
            });

            renderHook(() => useProjects({ autoRefresh: true, refreshInterval: 100 }));

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(1);
            });

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledTimes(2);
            }, { timeout: 2000 });
        });
    });
});

describe('useProject', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch a single project', async () => {
        const mockProject: Project = {
            id: 1,
            uuid: 'project-1',
            name: 'Test Project',
            description: 'Description',
            environments: [],
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockProject,
        });

        const { result } = renderHook(() => useProject({ uuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.project).toEqual(mockProject);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should update a project', async () => {
        const mockProject: Project = {
            id: 1,
            uuid: 'project-1',
            name: 'Test Project',
            description: 'Description',
            environments: [],
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockProject,
        });

        const { result } = renderHook(() => useProject({ uuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        const updatedProject = {
            ...mockProject,
            name: 'Updated Project',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => updatedProject,
        });

        await result.current.updateProject({ name: 'Updated Project' });

        await waitFor(() => {
            expect(result.current.project?.name).toBe('Updated Project');
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1', {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ name: 'Updated Project' }),
        });
    });

    it('should delete a project', async () => {
        const mockProject: Project = {
            id: 1,
            uuid: 'project-1',
            name: 'Test Project',
            description: 'Description',
            environments: [],
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockProject,
        });

        const { result } = renderHook(() => useProject({ uuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        await result.current.deleteProject();

        await waitFor(() => {
            expect(result.current.project).toBe(null);
        });

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });

    it('should create an environment', async () => {
        const mockProject: Project = {
            id: 1,
            uuid: 'project-1',
            name: 'Test Project',
            description: 'Description',
            environments: [],
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        const mockEnvironment: Environment = {
            id: 1,
            name: 'production',
            description: 'Production environment',
            project_id: 1,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockProject,
        });

        const { result } = renderHook(() => useProject({ uuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvironment,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                ...mockProject,
                environments: [mockEnvironment],
            }),
        });

        const environment = await result.current.createEnvironment('production', 'Production environment');

        expect(environment).toEqual(mockEnvironment);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1/environments', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                name: 'production',
                description: 'Production environment',
            }),
        });
    });

    it('should delete an environment', async () => {
        const mockEnvironment: Environment = {
            id: 1,
            name: 'staging',
            description: 'Staging environment',
            project_id: 1,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        const mockProject: Project = {
            id: 1,
            uuid: 'project-1',
            name: 'Test Project',
            description: 'Description',
            environments: [mockEnvironment],
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockProject,
        });

        const { result } = renderHook(() => useProject({ uuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
        });

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                ...mockProject,
                environments: [],
            }),
        });

        await result.current.deleteEnvironment('staging');

        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1/environments/staging', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });
});

describe('useProjectEnvironments', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should fetch project environments', async () => {
        const mockEnvironments: Environment[] = [
            {
                id: 1,
                name: 'production',
                description: 'Production environment',
                project_id: 1,
                created_at: '2024-01-01',
                updated_at: '2024-01-01',
            },
            {
                id: 2,
                name: 'staging',
                description: 'Staging environment',
                project_id: 1,
                created_at: '2024-01-02',
                updated_at: '2024-01-02',
            },
        ];

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockEnvironments,
        });

        const { result } = renderHook(() => useProjectEnvironments({ projectUuid: 'project-1' }));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        expect(result.current.environments).toEqual(mockEnvironments);
        expect(result.current.error).toBe(null);
        expect(mockFetch).toHaveBeenCalledWith('/api/v1/projects/project-1/environments', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });
    });
});
