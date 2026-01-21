import * as React from 'react';
import type { PreviewDeployment, PreviewDeploymentSettings } from '@/types';

interface UsePreviewDeploymentsOptions {
    applicationUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UsePreviewDeploymentsReturn {
    previews: PreviewDeployment[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createPreview: (pullRequestNumber: number) => Promise<PreviewDeployment>;
    deletePreview: (previewUuid: string) => Promise<void>;
    redeployPreview: (previewUuid: string) => Promise<void>;
}

interface UsePreviewOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UsePreviewReturn {
    preview: PreviewDeployment | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    redeploy: () => Promise<void>;
    delete: () => Promise<void>;
}

interface UsePreviewSettingsOptions {
    applicationUuid: string;
}

interface UsePreviewSettingsReturn {
    settings: PreviewDeploymentSettings | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateSettings: (data: Partial<PreviewDeploymentSettings>) => Promise<void>;
}

/**
 * Fetch preview deployments for an application
 */
export function usePreviewDeployments({
    applicationUuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UsePreviewDeploymentsOptions): UsePreviewDeploymentsReturn {
    const [previews, setPreviews] = React.useState<PreviewDeployment[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchPreviews = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/applications/${applicationUuid}/previews`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch preview deployments: ${response.statusText}`);
            }

            const data = await response.json();
            setPreviews(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch preview deployments'));
        } finally {
            setIsLoading(false);
        }
    }, [applicationUuid]);

    const createPreview = React.useCallback(async (pullRequestNumber: number): Promise<PreviewDeployment> => {
        try {
            const response = await fetch(`/api/v1/applications/${applicationUuid}/previews`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    pull_request_number: pullRequestNumber,
                }),
            });

            if (!response.ok) {
                throw new Error(`Failed to create preview deployment: ${response.statusText}`);
            }

            const preview = await response.json();

            // Refresh the previews list
            await fetchPreviews();

            return preview;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create preview deployment');
        }
    }, [applicationUuid, fetchPreviews]);

    const deletePreview = React.useCallback(async (previewUuid: string) => {
        try {
            const response = await fetch(`/api/v1/previews/${previewUuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete preview deployment: ${response.statusText}`);
            }

            // Refresh the previews list
            await fetchPreviews();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete preview deployment');
        }
    }, [fetchPreviews]);

    const redeployPreview = React.useCallback(async (previewUuid: string) => {
        try {
            const response = await fetch(`/api/v1/previews/${previewUuid}/redeploy`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to redeploy preview: ${response.statusText}`);
            }

            // Refresh the previews list
            await fetchPreviews();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to redeploy preview');
        }
    }, [fetchPreviews]);

    // Initial fetch
    React.useEffect(() => {
        fetchPreviews();
    }, [fetchPreviews]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchPreviews();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchPreviews]);

    return {
        previews,
        isLoading,
        error,
        refetch: fetchPreviews,
        createPreview,
        deletePreview,
        redeployPreview,
    };
}

/**
 * Fetch and manage a single preview deployment
 */
export function usePreview({
    uuid,
    autoRefresh = false,
    refreshInterval = 10000,
}: UsePreviewOptions): UsePreviewReturn {
    const [preview, setPreview] = React.useState<PreviewDeployment | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchPreview = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/previews/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch preview deployment: ${response.statusText}`);
            }

            const data = await response.json();
            setPreview(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch preview deployment'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const redeploy = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/previews/${uuid}/redeploy`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to redeploy preview: ${response.statusText}`);
            }

            await fetchPreview();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to redeploy preview');
        }
    }, [uuid, fetchPreview]);

    const deletePreview = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/previews/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete preview: ${response.statusText}`);
            }
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete preview');
        }
    }, [uuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchPreview();
    }, [fetchPreview]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchPreview();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchPreview]);

    return {
        preview,
        isLoading,
        error,
        refetch: fetchPreview,
        redeploy,
        delete: deletePreview,
    };
}

/**
 * Fetch and manage preview deployment settings for an application
 */
export function usePreviewSettings({
    applicationUuid,
}: UsePreviewSettingsOptions): UsePreviewSettingsReturn {
    const [settings, setSettings] = React.useState<PreviewDeploymentSettings | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchSettings = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/applications/${applicationUuid}/preview-settings`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch preview settings: ${response.statusText}`);
            }

            const data = await response.json();
            setSettings(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch preview settings'));
        } finally {
            setIsLoading(false);
        }
    }, [applicationUuid]);

    const updateSettings = React.useCallback(async (data: Partial<PreviewDeploymentSettings>) => {
        try {
            const response = await fetch(`/api/v1/applications/${applicationUuid}/preview-settings`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update preview settings: ${response.statusText}`);
            }

            const updated = await response.json();
            setSettings(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update preview settings');
        }
    }, [applicationUuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    return {
        settings,
        isLoading,
        error,
        refetch: fetchSettings,
        updateSettings,
    };
}
