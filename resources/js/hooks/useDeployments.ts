import * as React from 'react';
import type { Deployment } from '@/types';

interface UseDeploymentsOptions {
    applicationUuid?: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDeploymentsReturn {
    deployments: Deployment[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    startDeployment: (applicationUuid: string, force?: boolean) => Promise<Deployment>;
    cancelDeployment: (deploymentUuid: string) => Promise<void>;
}

interface UseDeploymentOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseDeploymentReturn {
    deployment: Deployment | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    cancel: () => Promise<void>;
}

/**
 * Get CSRF token from meta tag
 */
function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

/**
 * Fetch deployments (all or filtered by application)
 */
export function useDeployments({
    applicationUuid,
    autoRefresh = false,
    refreshInterval = 10000, // 10 seconds for active deployments
}: UseDeploymentsOptions = {}): UseDeploymentsReturn {
    const [deployments, setDeployments] = React.useState<Deployment[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchDeployments = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const url = applicationUuid
                ? `/applications/${applicationUuid}/deployments/json`
                : '/api/v1/deployments';

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch deployments: ${response.statusText}`);
            }

            const data = await response.json();
            setDeployments(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch deployments'));
        } finally {
            setIsLoading(false);
        }
    }, [applicationUuid]);

    const startDeployment = React.useCallback(async (applicationUuid: string, force = false): Promise<Deployment> => {
        try {
            const response = await fetch('/api/v1/deploy', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
                body: JSON.stringify({
                    uuid: applicationUuid,
                    force,
                }),
            });

            if (!response.ok) {
                throw new Error(`Failed to start deployment: ${response.statusText}`);
            }

            const deployment = await response.json();

            // Refresh the deployments list
            await fetchDeployments();

            return deployment;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to start deployment');
        }
    }, [fetchDeployments]);

    const cancelDeployment = React.useCallback(async (deploymentUuid: string) => {
        try {
            const response = await fetch(`/api/v1/deployments/${deploymentUuid}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to cancel deployment: ${response.statusText}`);
            }

            // Refresh the deployments list
            await fetchDeployments();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to cancel deployment');
        }
    }, [fetchDeployments]);

    // Initial fetch
    React.useEffect(() => {
        fetchDeployments();
    }, [fetchDeployments]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchDeployments();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDeployments]);

    return {
        deployments,
        isLoading,
        error,
        refetch: fetchDeployments,
        startDeployment,
        cancelDeployment,
    };
}

/**
 * Fetch and manage a single deployment
 */
export function useDeployment({
    uuid,
    autoRefresh = false,
    refreshInterval = 5000, // 5 seconds for active deployment
}: UseDeploymentOptions): UseDeploymentReturn {
    const [deployment, setDeployment] = React.useState<Deployment | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchDeployment = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/deployments/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch deployment: ${response.statusText}`);
            }

            const data = await response.json();
            setDeployment(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch deployment'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const cancel = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/deployments/${uuid}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to cancel deployment: ${response.statusText}`);
            }

            await fetchDeployment();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to cancel deployment');
        }
    }, [uuid, fetchDeployment]);

    // Initial fetch
    React.useEffect(() => {
        fetchDeployment();
    }, [fetchDeployment]);

    // Auto-refresh (especially useful for in-progress deployments)
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchDeployment();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchDeployment]);

    return {
        deployment,
        isLoading,
        error,
        refetch: fetchDeployment,
        cancel,
    };
}
