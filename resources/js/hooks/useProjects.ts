import * as React from 'react';
import type { Project, Environment } from '@/types';

interface UseProjectsOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseProjectsReturn {
    projects: Project[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createProject: (data: CreateProjectData) => Promise<Project>;
}

interface UseProjectOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseProjectReturn {
    project: Project | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateProject: (data: Partial<Project>) => Promise<void>;
    deleteProject: () => Promise<void>;
    createEnvironment: (name: string, description?: string) => Promise<Environment>;
    deleteEnvironment: (envName: string) => Promise<void>;
}

interface CreateProjectData {
    name: string;
    description?: string;
}

interface UseProjectEnvironmentsOptions {
    projectUuid: string;
}

interface UseProjectEnvironmentsReturn {
    environments: Environment[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Fetch all projects for the current team
 */
export function useProjects({
    autoRefresh = false,
    refreshInterval = 30000,
}: UseProjectsOptions = {}): UseProjectsReturn {
    const [projects, setProjects] = React.useState<Project[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchProjects = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/projects', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch projects: ${response.statusText}`);
            }

            const data = await response.json();
            setProjects(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch projects'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createProject = React.useCallback(async (data: CreateProjectData): Promise<Project> => {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/projects', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to create project: ${response.statusText}`);
            }

            const project = await response.json();

            // Refresh the projects list
            await fetchProjects();

            return project;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create project');
        }
    }, [fetchProjects]);

    // Initial fetch
    React.useEffect(() => {
        fetchProjects();
    }, [fetchProjects]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchProjects();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchProjects]);

    return {
        projects,
        isLoading,
        error,
        refetch: fetchProjects,
        createProject,
    };
}

/**
 * Fetch and manage a single project
 */
export function useProject({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseProjectOptions): UseProjectReturn {
    const [project, setProject] = React.useState<Project | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchProject = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/projects/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch project: ${response.statusText}`);
            }

            const data = await response.json();
            setProject(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch project'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const updateProject = React.useCallback(async (data: Partial<Project>) => {
        try {
            const response = await fetch(`/api/v1/projects/${uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update project: ${response.statusText}`);
            }

            const updated = await response.json();
            setProject(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update project');
        }
    }, [uuid]);

    const deleteProject = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/projects/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete project: ${response.statusText}`);
            }

            setProject(null);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete project');
        }
    }, [uuid]);

    const createEnvironment = React.useCallback(async (name: string, description?: string): Promise<Environment> => {
        try {
            const response = await fetch(`/api/v1/projects/${uuid}/environments`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ name, description }),
            });

            if (!response.ok) {
                throw new Error(`Failed to create environment: ${response.statusText}`);
            }

            const environment = await response.json();

            // Refresh the project to get updated environments
            await fetchProject();

            return environment;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create environment');
        }
    }, [uuid, fetchProject]);

    const deleteEnvironment = React.useCallback(async (envName: string) => {
        try {
            const response = await fetch(`/api/v1/projects/${uuid}/environments/${envName}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete environment: ${response.statusText}`);
            }

            // Refresh the project to get updated environments
            await fetchProject();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete environment');
        }
    }, [uuid, fetchProject]);

    // Initial fetch
    React.useEffect(() => {
        fetchProject();
    }, [fetchProject]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchProject();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchProject]);

    return {
        project,
        isLoading,
        error,
        refetch: fetchProject,
        updateProject,
        deleteProject,
        createEnvironment,
        deleteEnvironment,
    };
}

/**
 * Fetch project environments
 */
export function useProjectEnvironments({
    projectUuid,
}: UseProjectEnvironmentsOptions): UseProjectEnvironmentsReturn {
    const [environments, setEnvironments] = React.useState<Environment[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchEnvironments = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/projects/${projectUuid}/environments`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch environments: ${response.statusText}`);
            }

            const data = await response.json();
            setEnvironments(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch environments'));
        } finally {
            setIsLoading(false);
        }
    }, [projectUuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchEnvironments();
    }, [fetchEnvironments]);

    return {
        environments,
        isLoading,
        error,
        refetch: fetchEnvironments,
    };
}
