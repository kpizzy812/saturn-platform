import * as React from 'react';

interface Branch {
    name: string;
    is_default: boolean;
}

interface BranchesResponse {
    branches: Branch[];
    default_branch: string;
    platform: string;
}

interface UseGitBranchesOptions {
    /** Debounce delay in milliseconds */
    debounceMs?: number;
}

interface UseGitBranchesReturn {
    branches: Branch[];
    defaultBranch: string | null;
    platform: string | null;
    isLoading: boolean;
    error: string | null;
    fetchBranches: (repositoryUrl: string) => void;
    clearBranches: () => void;
}

/**
 * Hook to fetch branches from a public git repository.
 * Supports GitHub, GitLab, and Bitbucket.
 */
export function useGitBranches({
    debounceMs = 500,
}: UseGitBranchesOptions = {}): UseGitBranchesReturn {
    const [branches, setBranches] = React.useState<Branch[]>([]);
    const [defaultBranch, setDefaultBranch] = React.useState<string | null>(null);
    const [platform, setPlatform] = React.useState<string | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const debounceTimerRef = React.useRef<NodeJS.Timeout | null>(null);
    const abortControllerRef = React.useRef<AbortController | null>(null);

    const clearBranches = React.useCallback(() => {
        setBranches([]);
        setDefaultBranch(null);
        setPlatform(null);
        setError(null);
    }, []);

    const fetchBranchesInternal = React.useCallback(async (repositoryUrl: string) => {
        // Cancel any in-flight request
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        // Validate URL format
        const isValidUrl = /^https?:\/\/(github\.com|gitlab\.com|bitbucket\.org)\/[^/]+\/[^/]+/.test(repositoryUrl);
        if (!isValidUrl) {
            clearBranches();
            return;
        }

        abortControllerRef.current = new AbortController();

        try {
            setIsLoading(true);
            setError(null);

            // Get CSRF token for web route
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

            const response = await fetch(
                `/web-api/git/branches?repository_url=${encodeURIComponent(repositoryUrl)}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                    signal: abortControllerRef.current.signal,
                }
            );

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || `Failed to fetch branches (${response.status})`);
            }

            const data: BranchesResponse = await response.json();
            setBranches(data.branches);
            setDefaultBranch(data.default_branch);
            setPlatform(data.platform);
        } catch (err) {
            if (err instanceof Error && err.name === 'AbortError') {
                // Request was cancelled, ignore
                return;
            }
            setError(err instanceof Error ? err.message : 'Failed to fetch branches');
            setBranches([]);
            setDefaultBranch(null);
            setPlatform(null);
        } finally {
            setIsLoading(false);
        }
    }, [clearBranches]);

    const fetchBranches = React.useCallback((repositoryUrl: string) => {
        // Clear existing debounce timer
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        // If empty URL, clear state
        if (!repositoryUrl.trim()) {
            clearBranches();
            return;
        }

        // Debounce the fetch
        debounceTimerRef.current = setTimeout(() => {
            fetchBranchesInternal(repositoryUrl);
        }, debounceMs);
    }, [fetchBranchesInternal, debounceMs, clearBranches]);

    // Cleanup on unmount
    React.useEffect(() => {
        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, []);

    return {
        branches,
        defaultBranch,
        platform,
        isLoading,
        error,
        fetchBranches,
        clearBranches,
    };
}
