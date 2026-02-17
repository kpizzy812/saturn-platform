import { useState, useCallback } from 'react';

export interface RecentResource {
    type: 'project' | 'server' | 'application' | 'database' | 'service';
    name: string;
    uuid: string;
    href: string;
    timestamp: number;
}

const STORAGE_KEY = 'saturn-recent-resources';
const MAX_ITEMS = 5;

function loadRecent(): RecentResource[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed;
    } catch {
        return [];
    }
}

function saveRecent(items: RecentResource[]): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // localStorage full or unavailable
    }
}

export function useRecentResources() {
    const [recentItems, setRecentItems] = useState<RecentResource[]>(loadRecent);

    const addRecent = useCallback((item: Omit<RecentResource, 'timestamp'>) => {
        setRecentItems((prev) => {
            const filtered = prev.filter(
                (r) => !(r.type === item.type && r.uuid === item.uuid),
            );
            const next: RecentResource[] = [
                { ...item, timestamp: Date.now() },
                ...filtered,
            ].slice(0, MAX_ITEMS);
            saveRecent(next);
            return next;
        });
    }, []);

    const clearRecent = useCallback(() => {
        setRecentItems([]);
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // ignore
        }
    }, []);

    return { recentItems, addRecent, clearRecent };
}
