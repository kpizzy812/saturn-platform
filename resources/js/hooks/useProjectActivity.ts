import * as React from 'react';
import type { ActivityLog } from '@/types';

interface UseProjectActivityOptions {
    projectUuid: string;
    limit?: number;
}

interface UseProjectActivityReturn {
    activities: ActivityLog[];
    loading: boolean;
    error: Error | null;
    actionFilter: string;
    setActionFilter: (filter: string) => void;
    loadMore: () => Promise<void>;
    hasMore: boolean;
}

export function useProjectActivity({
    projectUuid,
    limit = 20,
}: UseProjectActivityOptions): UseProjectActivityReturn {
    const [activities, setActivities] = React.useState<ActivityLog[]>([]);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);
    const [hasMore, setHasMore] = React.useState(false);
    const [actionFilter, setActionFilter] = React.useState('');
    const [offset, setOffset] = React.useState(0);

    const fetchActivities = React.useCallback(async (currentOffset: number, append: boolean) => {
        try {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams();
            params.set('limit', String(limit));
            params.set('offset', String(currentOffset));
            if (actionFilter) {
                params.set('action', actionFilter);
            }

            const response = await fetch(`/web-api/projects/${projectUuid}/activities?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch activities: ${response.statusText}`);
            }

            const data = await response.json();
            setActivities(prev => append ? [...prev, ...data.data] : data.data);
            setHasMore(data.meta.has_more);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch activities'));
        } finally {
            setLoading(false);
        }
    }, [projectUuid, limit, actionFilter]);

    // Initial fetch and refetch on filter change
    React.useEffect(() => {
        setOffset(0);
        fetchActivities(0, false);
    }, [fetchActivities]);

    const loadMore = React.useCallback(async () => {
        const newOffset = offset + limit;
        setOffset(newOffset);
        await fetchActivities(newOffset, true);
    }, [fetchActivities, offset, limit]);

    return {
        activities,
        loading,
        error,
        actionFilter,
        setActionFilter,
        loadMore,
        hasMore,
    };
}
