import * as React from 'react';
import type { Application } from '@/types';

interface UseApplicationsOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseApplicationsReturn {
    applications: Application[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

interface UseApplicationOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseApplicationReturn {
    application: Application | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateApplication: (data: Partial<Application>) => Promise<void>;
    startApplication: () => Promise<void>;
    stopApplication: () => Promise<void>;
    restartApplication: () => Promise<void>;
}

/**
 * Fetch all applications for the current team
 */
export function useApplications({
    autoRefresh = false,
    refreshInterval = 30000,
}: UseApplicationsOptions = {}): UseApplicationsReturn {
    const [applications, setApplications] = React.useState<Application[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchApplications = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/applications', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch applications: ${response.statusText}`);
            }

            const data = await response.json();
            setApplications(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch applications'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Initial fetch
    React.useEffect(() => {
        fetchApplications();
    }, [fetchApplications]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchApplications();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchApplications]);

    return {
        applications,
        isLoading,
        error,
        refetch: fetchApplications,
    };
}

/**
 * Fetch and manage a single application
 */
export function useApplication({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseApplicationOptions): UseApplicationReturn {
    const [application, setApplication] = React.useState<Application | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchApplication = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/applications/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch application: ${response.statusText}`);
            }

            const data = await response.json();
            setApplication(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch application'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const updateApplication = React.useCallback(async (data: Partial<Application>) => {
        try {
            const response = await fetch(`/api/v1/applications/${uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update application: ${response.statusText}`);
            }

            const updated = await response.json();
            setApplication(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update application');
        }
    }, [uuid]);

    const startApplication = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/applications/${uuid}/start`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to start application: ${response.statusText}`);
            }

            await fetchApplication();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to start application');
        }
    }, [uuid, fetchApplication]);

    const stopApplication = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/applications/${uuid}/stop`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to stop application: ${response.statusText}`);
            }

            await fetchApplication();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to stop application');
        }
    }, [uuid, fetchApplication]);

    const restartApplication = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/applications/${uuid}/restart`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to restart application: ${response.statusText}`);
            }

            await fetchApplication();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to restart application');
        }
    }, [uuid, fetchApplication]);

    // Initial fetch
    React.useEffect(() => {
        fetchApplication();
    }, [fetchApplication]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchApplication();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchApplication]);

    return {
        application,
        isLoading,
        error,
        refetch: fetchApplication,
        updateApplication,
        startApplication,
        stopApplication,
        restartApplication,
    };
}
