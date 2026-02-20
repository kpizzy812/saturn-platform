import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useFavorites } from '@/hooks/useFavorites';

const STORAGE_KEY = 'saturn-explicit-favorites';

describe('useFavorites', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('should return empty favorites initially', () => {
        const { result } = renderHook(() => useFavorites());
        expect(result.current.favorites).toEqual([]);
    });

    it('should add a favorite via toggleFavorite', () => {
        const { result } = renderHook(() => useFavorites());

        act(() => {
            result.current.toggleFavorite({
                type: 'server',
                id: 'abc-123',
                name: 'Production',
                href: '/servers/abc-123',
            });
        });

        expect(result.current.favorites).toHaveLength(1);
        expect(result.current.favorites[0].name).toBe('Production');
        expect(result.current.favorites[0].type).toBe('server');
    });

    it('should remove a favorite when toggled again', () => {
        const { result } = renderHook(() => useFavorites());
        const item = { type: 'server', id: 'abc-123', name: 'Production', href: '/servers/abc-123' };

        act(() => {
            result.current.toggleFavorite(item);
        });
        expect(result.current.favorites).toHaveLength(1);

        act(() => {
            result.current.toggleFavorite(item);
        });
        expect(result.current.favorites).toHaveLength(0);
    });

    it('should check isFavorite correctly', () => {
        const { result } = renderHook(() => useFavorites());

        expect(result.current.isFavorite('server', 'abc-123')).toBe(false);

        act(() => {
            result.current.toggleFavorite({
                type: 'server',
                id: 'abc-123',
                name: 'Production',
                href: '/servers/abc-123',
            });
        });

        expect(result.current.isFavorite('server', 'abc-123')).toBe(true);
        expect(result.current.isFavorite('application', 'abc-123')).toBe(false);
    });

    it('should persist to localStorage', () => {
        const { result } = renderHook(() => useFavorites());

        act(() => {
            result.current.toggleFavorite({
                type: 'server',
                id: 'abc-123',
                name: 'Production',
                href: '/servers/abc-123',
            });
        });

        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY)!);
        expect(stored).toHaveLength(1);
        expect(stored[0].name).toBe('Production');
    });

    it('should load from localStorage on mount', () => {
        const stored = [
            { type: 'server', id: 'saved-1', name: 'Saved', href: '/servers/saved-1' },
        ];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));

        const { result } = renderHook(() => useFavorites());
        expect(result.current.favorites).toHaveLength(1);
        expect(result.current.favorites[0].name).toBe('Saved');
        expect(result.current.isFavorite('server', 'saved-1')).toBe(true);
    });

    it('should handle corrupted localStorage gracefully', () => {
        localStorage.setItem(STORAGE_KEY, 'not valid json');

        const { result } = renderHook(() => useFavorites());
        expect(result.current.favorites).toEqual([]);
    });

    it('should remove favorite by removeFavorite', () => {
        const { result } = renderHook(() => useFavorites());

        act(() => {
            result.current.toggleFavorite({ type: 'server', id: 'abc', name: 'S1', href: '/s/abc' });
            result.current.toggleFavorite({ type: 'application', id: 'def', name: 'A1', href: '/a/def' });
        });

        expect(result.current.favorites).toHaveLength(2);

        act(() => {
            result.current.removeFavorite('server', 'abc');
        });

        expect(result.current.favorites).toHaveLength(1);
        expect(result.current.favorites[0].type).toBe('application');
    });

    it('should allow different types with same id', () => {
        const { result } = renderHook(() => useFavorites());

        act(() => {
            result.current.toggleFavorite({ type: 'server', id: 'abc', name: 'Server', href: '/servers/abc' });
            result.current.toggleFavorite({ type: 'application', id: 'abc', name: 'App', href: '/applications/abc' });
        });

        expect(result.current.favorites).toHaveLength(2);
        expect(result.current.isFavorite('server', 'abc')).toBe(true);
        expect(result.current.isFavorite('application', 'abc')).toBe(true);
    });

    it('should enforce max 20 favorites', () => {
        const { result } = renderHook(() => useFavorites());

        for (let i = 0; i < 25; i++) {
            act(() => {
                result.current.toggleFavorite({
                    type: 'server',
                    id: `id-${i}`,
                    name: `Server ${i}`,
                    href: `/servers/id-${i}`,
                });
            });
        }

        expect(result.current.favorites.length).toBeLessThanOrEqual(20);
    });
});
