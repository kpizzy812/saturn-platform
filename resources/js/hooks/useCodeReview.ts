import * as React from 'react';

/**
 * Code review status types
 */
export type CodeReviewStatus = 'pending' | 'analyzing' | 'completed' | 'failed';

/**
 * Violation severity types
 */
export type ViolationSeverity = 'critical' | 'high' | 'medium' | 'low';

/**
 * Violation source types
 */
export type ViolationSource = 'regex' | 'ast' | 'llm';

/**
 * A single code review violation
 */
export interface CodeReviewViolation {
    id: number;
    rule_id: string;
    source: ViolationSource;
    severity: ViolationSeverity;
    severity_color: string;
    confidence: number;
    file_path: string;
    line_number: number | null;
    location: string;
    message: string;
    snippet: string | null;
    suggestion: string | null;
    contains_secret: boolean;
    is_deterministic: boolean;
    created_at: string;
}

/**
 * Code review result
 */
export interface CodeReview {
    id: number;
    deployment_id: number;
    application_id: number;
    commit_sha: string;
    base_commit_sha: string | null;
    status: CodeReviewStatus;
    status_label: string;
    status_color: string;
    summary: string | null;
    files_analyzed: string[];
    files_count: number;
    violations_count: number;
    critical_count: number;
    has_violations: boolean;
    has_critical: boolean;
    violations_by_severity: Record<string, number>;
    llm_provider: string | null;
    llm_model: string | null;
    llm_failed: boolean;
    duration_ms: number | null;
    started_at: string | null;
    finished_at: string | null;
    error_message: string | null;
    created_at: string;
    violations?: CodeReviewViolation[];
}

/**
 * Code review service status
 */
export interface CodeReviewServiceStatus {
    enabled: boolean;
    mode: string;
    detectors: {
        secrets: boolean;
        dangerous_functions: boolean;
    };
    llm: {
        enabled: boolean;
        available: boolean;
        provider: string | null;
        model: string | null;
    };
}

interface UseCodeReviewOptions {
    deploymentUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseCodeReviewReturn {
    review: CodeReview | null;
    isLoading: boolean;
    isAnalyzing: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    triggerReview: () => Promise<void>;
}

interface UseCodeReviewStatusReturn {
    status: CodeReviewServiceStatus | null;
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
 * Hook for fetching and managing code review
 */
export function useCodeReview({
    deploymentUuid,
    autoRefresh = false,
    refreshInterval = 5000,
}: UseCodeReviewOptions): UseCodeReviewReturn {
    const [review, setReview] = React.useState<CodeReview | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [isAnalyzing, setIsAnalyzing] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchReview = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/web-api/deployments/${deploymentUuid}/code-review`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch code review: ${response.statusText}`);
            }

            const data = await response.json();

            // Handle 'not_found' status (no review yet)
            if (data.status === 'not_found' || data.review === null) {
                setReview(null);
                setIsAnalyzing(false);
                return;
            }

            setReview(data.review);
            setIsAnalyzing(data.status === 'analyzing');
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch code review'));
        } finally {
            setIsLoading(false);
        }
    }, [deploymentUuid]);

    const triggerReview = React.useCallback(async () => {
        try {
            setIsAnalyzing(true);
            setError(null);

            const response = await fetch(`/web-api/deployments/${deploymentUuid}/code-review`, {
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
                throw new Error(data.error || 'Failed to trigger code review');
            }

            const data = await response.json();

            // If already completed, set the review
            if (data.status === 'completed' && data.review) {
                setReview(data.review);
                setIsAnalyzing(false);
            } else {
                // Start polling for results
                await fetchReview();
            }
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to trigger code review'));
            setIsAnalyzing(false);
        }
    }, [deploymentUuid, fetchReview]);

    // Initial fetch
    React.useEffect(() => {
        fetchReview();
    }, [fetchReview]);

    // Auto-refresh only when analyzing (polling for results)
    React.useEffect(() => {
        // Only poll when autoRefresh is enabled AND currently analyzing
        if (!autoRefresh || !isAnalyzing) return;

        const interval = setInterval(() => {
            fetchReview();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, isAnalyzing, refreshInterval, fetchReview]);

    return {
        review,
        isLoading,
        isAnalyzing,
        error,
        refetch: fetchReview,
        triggerReview,
    };
}

/**
 * Hook for checking code review service status
 */
export function useCodeReviewStatus(): UseCodeReviewStatusReturn {
    const [status, setStatus] = React.useState<CodeReviewServiceStatus | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchStatus = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/web-api/code-review/status', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch code review status: ${response.statusText}`);
            }

            const data = await response.json();
            setStatus(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch code review status'));
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

export default useCodeReview;
