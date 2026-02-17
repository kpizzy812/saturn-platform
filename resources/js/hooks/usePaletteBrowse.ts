import { useState, useRef, useCallback } from 'react';

export interface BrowseItem {
    id: string;
    name: string;
    description: string | null;
    href: string;
    has_children: boolean;
    child_type: string | null;
    meta?: {
        type?: string;
        project?: string;
        environment?: string;
    };
}

interface CacheEntry {
    items: BrowseItem[];
    timestamp: number;
}

const CACHE_TTL_MS = 30_000;

export function usePaletteBrowse() {
    const [items, setItems] = useState<BrowseItem[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const cacheRef = useRef<Map<string, CacheEntry>>(new Map());
    const abortRef = useRef<AbortController | null>(null);

    const fetchBrowse = useCallback(async (type: string, parentUuid?: string) => {
        // Build cache key
        const cacheKey = `${type}:${parentUuid || ''}`;

        // Check cache
        const cached = cacheRef.current.get(cacheKey);
        if (cached && Date.now() - cached.timestamp < CACHE_TTL_MS) {
            setItems(cached.items);
            return;
        }

        // Cancel inflight request
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setIsLoading(true);

        try {
            const params = new URLSearchParams({ type });
            if (parentUuid) {
                params.set('parent_uuid', parentUuid);
            }

            const res = await fetch(`/web-api/command-palette/browse?${params.toString()}`, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) throw new Error('Browse failed');

            const data: { items: BrowseItem[] } = await res.json();
            const entry: CacheEntry = { items: data.items, timestamp: Date.now() };
            cacheRef.current.set(cacheKey, entry);
            setItems(data.items);
        } catch (err) {
            if ((err as Error).name !== 'AbortError') {
                setItems([]);
            }
        } finally {
            if (!controller.signal.aborted) {
                setIsLoading(false);
            }
        }
    }, []);

    const clearCache = useCallback(() => {
        cacheRef.current.clear();
    }, []);

    return { items, isLoading, fetchBrowse, clearCache };
}
