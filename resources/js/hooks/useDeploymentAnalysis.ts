import * as React from 'react';
import type { DeploymentLogAnalysis, AIServiceStatus } from '@/types';

interface UseDeploymentAnalysisOptions {
    deploymentUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
    enabled?: boolean; // Skip fetching when false (useful for conditional loading)
}

interface UseDeploymentAnalysisReturn {
    analysis: DeploymentLogAnalysis | null;
    isLoading: boolean;
    isAnalyzing: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    triggerAnalysis: () => Promise<void>;
}

interface UseAIServiceStatusReturn {
    status: AIServiceStatus | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Get CSRF token from meta tag
 */
function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

/**
 * Hook for fetching and managing deployment AI analysis
 */
export function useDeploymentAnalysis({
    deploymentUuid,
    autoRefresh = false,
    refreshInterval = 5000,
    enabled = true,
}: UseDeploymentAnalysisOptions): UseDeploymentAnalysisReturn {
    const [analysis, setAnalysis] = React.useState<DeploymentLogAnalysis | null>(null);
    const [isLoading, setIsLoading] = React.useState(enabled);
    const [isAnalyzing, setIsAnalyzing] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);
    const triggeredRef = React.useRef(false);
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>(undefined);

    // Cleanup timeout on unmount
    React.useEffect(() => {
        return () => clearTimeout(timeoutRef.current);
    }, []);

    const fetchAnalysis = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/web-api/deployments/${deploymentUuid}/analysis`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch analysis: ${response.statusText}`);
            }

            const data = await response.json();

            // Handle 'not_found' status (no analysis yet)
            if (data.status === 'not_found' || data.analysis === null) {
                setAnalysis(null);
                // Don't reset isAnalyzing if we just triggered - the job is still queued
                if (!triggeredRef.current) {
                    setIsAnalyzing(false);
                }
                return;
            }

            // Got actual analysis data
            triggeredRef.current = false;
            clearTimeout(timeoutRef.current);
            setAnalysis(data.analysis);
            setIsAnalyzing(data.status === 'analyzing');
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch analysis'));
        } finally {
            setIsLoading(false);
        }
    }, [deploymentUuid]);

    const triggerAnalysis = React.useCallback(async () => {
        try {
            setIsAnalyzing(true);
            setError(null);
            triggeredRef.current = true;

            // Safety timeout: give up after 2 minutes
            clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(() => {
                triggeredRef.current = false;
                setIsAnalyzing(false);
                setError(new Error('Analysis timed out — the job may still be processing'));
            }, 120_000);

            const response = await fetch(`/web-api/deployments/${deploymentUuid}/analyze`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to trigger analysis');
            }

            // Don't fetchAnalysis immediately — job is queued, auto-refresh will poll
        } catch (err) {
            triggeredRef.current = false;
            clearTimeout(timeoutRef.current);
            setError(err instanceof Error ? err : new Error('Failed to trigger analysis'));
            setIsAnalyzing(false);
        }
    }, [deploymentUuid]);

    // Initial fetch (only when enabled)
    React.useEffect(() => {
        if (enabled) {
            fetchAnalysis();
        }
    }, [fetchAnalysis, enabled]);

    // Auto-refresh when analyzing (polls every refreshInterval until analysis is done)
    React.useEffect(() => {
        if (!autoRefresh || !isAnalyzing) return;

        const interval = setInterval(() => {
            fetchAnalysis();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, isAnalyzing, refreshInterval, fetchAnalysis]);

    return {
        analysis,
        isLoading,
        isAnalyzing,
        error,
        refetch: fetchAnalysis,
        triggerAnalysis,
    };
}

/**
 * Hook for checking AI service status
 */
export function useAIServiceStatus(): UseAIServiceStatusReturn {
    const [status, setStatus] = React.useState<AIServiceStatus | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchStatus = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/web-api/ai/status', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch AI status: ${response.statusText}`);
            }

            const data = await response.json();
            setStatus(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch AI status'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    React.useEffect(() => {
        fetchStatus();
    }, [fetchStatus]);

    return {
        status,
        isLoading,
        error,
        refetch: fetchStatus,
    };
}
