import * as React from 'react';
import type { ActivityLog } from '@/types';

interface UseTeamActivityOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
    perPage?: number;
}

interface UseTeamActivityFilters {
    search?: string;
    member?: string;
    action?: string;
    dateRange?: string;
}

interface UseTeamActivityReturn {
    activities: ActivityLog[];
    loading: boolean;
    error: Error | null;
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: UseTeamActivityFilters;
    setFilters: (filters: UseTeamActivityFilters) => void;
    refresh: () => Promise<void>;
    loadMore: () => Promise<void>;
    hasMore: boolean;
}

/**
 * Custom hook for fetching and managing team activity data
 *
 * Features:
 * - Fetch team activities from API
 * - Filter by action type, member, date range
 * - Search functionality
 * - Pagination support
 * - Auto-refresh capability
 */
export function useTeamActivity({
    autoRefresh = false,
    refreshInterval = 60000, // 1 minute
    perPage = 50,
}: UseTeamActivityOptions = {}): UseTeamActivityReturn {
    const [activities, setActivities] = React.useState<ActivityLog[]>([]);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);
    const [meta, setMeta] = React.useState({
        currentPage: 1,
        lastPage: 1,
        perPage,
        total: 0,
    });
    const [filters, setFilters] = React.useState<UseTeamActivityFilters>({});

    const fetchActivities = React.useCallback(async (page = 1, append = false) => {
        try {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams();
            params.set('per_page', String(perPage));
            params.set('page', String(page));

            if (filters.search) {
                params.set('search', filters.search);
            }
            if (filters.member && filters.member !== 'all') {
                params.set('member', filters.member);
            }
            if (filters.action && filters.action !== 'all') {
                params.set('action', filters.action);
            }
            if (filters.dateRange && filters.dateRange !== 'all') {
                params.set('date_range', filters.dateRange);
            }

            const response = await fetch(`/api/v1/teams/current/activities?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch activities: ${response.statusText}`);
            }

            const data = await response.json();

            setActivities(prev => append ? [...prev, ...data.data] : data.data);
            setMeta({
                currentPage: data.meta.current_page,
                lastPage: data.meta.last_page,
                perPage: data.meta.per_page,
                total: data.meta.total,
            });
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch activities'));
        } finally {
            setLoading(false);
        }
    }, [filters, perPage]);

    // Initial fetch and refetch on filter change
    React.useEffect(() => {
        fetchActivities(1, false);
    }, [fetchActivities]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchActivities(1, false);
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchActivities]);

    const refresh = React.useCallback(async () => {
        await fetchActivities(1, false);
    }, [fetchActivities]);

    const loadMore = React.useCallback(async () => {
        if (meta.currentPage < meta.lastPage) {
            await fetchActivities(meta.currentPage + 1, true);
        }
    }, [fetchActivities, meta.currentPage, meta.lastPage]);

    const hasMore = meta.currentPage < meta.lastPage;

    return {
        activities,
        loading,
        error,
        meta,
        filters,
        setFilters,
        refresh,
        loadMore,
        hasMore,
    };
}
