import { useState, useCallback } from 'react';

export interface TrackedResource {
    type: string;
    id: string;
    name: string;
    href: string;
    visitCount: number;
    lastVisit: number;
}

export interface FavoriteResource {
    type: string;
    id: string;
    name: string;
    href: string;
    score: number;
}

const STORAGE_KEY = 'saturn-resource-frequency';
const MAX_TRACKED = 50;
const MAX_FAVORITES = 5;

function loadTracked(): TrackedResource[] {
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

function saveTracked(items: TrackedResource[]): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // localStorage full or unavailable
    }
}

function computeScore(resource: TrackedResource, now: number): number {
    const ageMs = now - resource.lastVisit;
    const ageHours = ageMs / (1000 * 60 * 60);
    // Recency score: 1.0 for just visited, decays to 0 over 7 days
    const recencyScore = Math.max(0, 1 - ageHours / (7 * 24));
    return resource.visitCount * 0.7 + recencyScore * 0.3;
}

export function getFavoritesFromStorage(): FavoriteResource[] {
    const tracked = loadTracked();
    if (tracked.length === 0) return [];

    const now = Date.now();
    return tracked
        .map((r) => ({
            type: r.type,
            id: r.id,
            name: r.name,
            href: r.href,
            score: computeScore(r, now),
        }))
        .sort((a, b) => b.score - a.score)
        .slice(0, MAX_FAVORITES);
}

export function useResourceFrequency() {
    const [tracked, setTracked] = useState<TrackedResource[]>(loadTracked);

    const addVisit = useCallback((resource: { type: string; id: string; name: string; href: string }) => {
        setTracked((prev) => {
            const existing = prev.find((r) => r.type === resource.type && r.id === resource.id);
            let next: TrackedResource[];

            if (existing) {
                next = prev.map((r) =>
                    r.type === resource.type && r.id === resource.id
                        ? { ...r, name: resource.name, href: resource.href, visitCount: r.visitCount + 1, lastVisit: Date.now() }
                        : r,
                );
            } else {
                next = [
                    ...prev,
                    { ...resource, visitCount: 1, lastVisit: Date.now() },
                ];
            }

            // Prune: keep top MAX_TRACKED by score
            if (next.length > MAX_TRACKED) {
                const now = Date.now();
                next.sort((a, b) => computeScore(b, now) - computeScore(a, now));
                next = next.slice(0, MAX_TRACKED);
            }

            saveTracked(next);
            return next;
        });
    }, []);

    const getFavorites = useCallback((): FavoriteResource[] => {
        if (tracked.length === 0) return [];
        const now = Date.now();
        return tracked
            .map((r) => ({
                type: r.type,
                id: r.id,
                name: r.name,
                href: r.href,
                score: computeScore(r, now),
            }))
            .sort((a, b) => b.score - a.score)
            .slice(0, MAX_FAVORITES);
    }, [tracked]);

    return { addVisit, getFavorites, tracked };
}
