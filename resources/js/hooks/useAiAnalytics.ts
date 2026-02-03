import { useState, useCallback, useEffect } from 'react';
import type {
    AiUsageStats,
    AiCommandStats,
    AiRatingStats,
    AiDailyStats,
} from '@/types/ai-chat';

interface UseAiAnalyticsOptions {
    autoLoad?: boolean;
    period?: '7d' | '30d' | '90d';
}

interface UseAiAnalyticsReturn {
    usage: AiUsageStats | null;
    commands: AiCommandStats[];
    ratings: AiRatingStats | null;
    daily: AiDailyStats[];
    isLoading: boolean;
    error: string | null;
    loadUsage: (period?: string) => Promise<void>;
    loadCommands: () => Promise<void>;
    loadRatings: () => Promise<void>;
    loadDaily: (days?: number) => Promise<void>;
    loadAll: () => Promise<void>;
}

export function useAiAnalytics(options: UseAiAnalyticsOptions = {}): UseAiAnalyticsReturn {
    const { autoLoad = true, period = '30d' } = options;

    const [usage, setUsage] = useState<AiUsageStats | null>(null);
    const [commands, setCommands] = useState<AiCommandStats[]>([]);
    const [ratings, setRatings] = useState<AiRatingStats | null>(null);
    const [daily, setDaily] = useState<AiDailyStats[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadUsage = useCallback(async (loadPeriod?: string) => {
        try {
            const response = await fetch(
                `/web-api/ai-analytics/usage?period=${loadPeriod || period}`
            );
            if (!response.ok) throw new Error('Failed to load usage stats');
            const data = await response.json();
            setUsage(data);
        } catch (err) {
            console.error('Failed to load usage:', err);
            throw err;
        }
    }, [period]);

    const loadCommands = useCallback(async () => {
        try {
            const response = await fetch('/web-api/ai-analytics/commands');
            if (!response.ok) throw new Error('Failed to load command stats');
            const data = await response.json();
            setCommands(data.commands);
        } catch (err) {
            console.error('Failed to load commands:', err);
            throw err;
        }
    }, []);

    const loadRatings = useCallback(async () => {
        try {
            const response = await fetch('/web-api/ai-analytics/ratings');
            if (!response.ok) throw new Error('Failed to load rating stats');
            const data = await response.json();
            setRatings(data);
        } catch (err) {
            console.error('Failed to load ratings:', err);
            throw err;
        }
    }, []);

    const loadDaily = useCallback(async (days: number = 30) => {
        try {
            const response = await fetch(
                `/web-api/ai-analytics/daily?days=${days}`
            );
            if (!response.ok) throw new Error('Failed to load daily stats');
            const data = await response.json();
            setDaily(data.daily);
        } catch (err) {
            console.error('Failed to load daily stats:', err);
            throw err;
        }
    }, []);

    const loadAll = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            await Promise.all([
                loadUsage(),
                loadCommands(),
                loadRatings(),
                loadDaily(),
            ]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load analytics');
        } finally {
            setIsLoading(false);
        }
    }, [loadUsage, loadCommands, loadRatings, loadDaily]);

    // Auto-load on mount
    useEffect(() => {
        if (autoLoad) {
            loadAll();
        }
    }, [autoLoad, loadAll]);

    return {
        usage,
        commands,
        ratings,
        daily,
        isLoading,
        error,
        loadUsage,
        loadCommands,
        loadRatings,
        loadDaily,
        loadAll,
    };
}
