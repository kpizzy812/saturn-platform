import { useState, useCallback } from 'react';

export interface FavoriteItem {
    type: string;
    id: string;
    name: string;
    href: string;
}

const STORAGE_KEY = 'saturn-explicit-favorites';
const MAX_FAVORITES = 20;

function loadFavorites(): FavoriteItem[] {
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

function saveFavorites(items: FavoriteItem[]): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // localStorage full or unavailable
    }
}

export function useFavorites() {
    const [favorites, setFavorites] = useState<FavoriteItem[]>(loadFavorites);

    const isFavorite = useCallback(
        (type: string, id: string): boolean => {
            return favorites.some((f) => f.type === type && f.id === id);
        },
        [favorites],
    );

    const toggleFavorite = useCallback((item: FavoriteItem) => {
        setFavorites((prev) => {
            const exists = prev.some((f) => f.type === item.type && f.id === item.id);
            let next: FavoriteItem[];

            if (exists) {
                next = prev.filter((f) => !(f.type === item.type && f.id === item.id));
            } else {
                next = [...prev, item].slice(0, MAX_FAVORITES);
            }

            saveFavorites(next);
            return next;
        });
    }, []);

    const removeFavorite = useCallback((type: string, id: string) => {
        setFavorites((prev) => {
            const next = prev.filter((f) => !(f.type === type && f.id === id));
            saveFavorites(next);
            return next;
        });
    }, []);

    return { favorites, isFavorite, toggleFavorite, removeFavorite };
}
