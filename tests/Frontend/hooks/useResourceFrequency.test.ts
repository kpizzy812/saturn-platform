import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useResourceFrequency, getFavoritesFromStorage } from '@/hooks/useResourceFrequency';

const STORAGE_KEY = 'saturn-resource-frequency';

describe('useResourceFrequency', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('should return empty favorites initially', () => {
        const { result } = renderHook(() => useResourceFrequency());
        expect(result.current.getFavorites()).toEqual([]);
    });

    it('should track a visit', () => {
        const { result } = renderHook(() => useResourceFrequency());

        act(() => {
            result.current.addVisit({
                type: 'server',
                id: 'srv-1',
                name: 'Production',
                href: '/servers/srv-1',
            });
        });

        expect(result.current.tracked).toHaveLength(1);
        expect(result.current.tracked[0].visitCount).toBe(1);
        expect(result.current.tracked[0].name).toBe('Production');
    });

    it('should increment visit count for existing resource', () => {
        const { result } = renderHook(() => useResourceFrequency());

        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1' });
        });
        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1' });
        });
        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1' });
        });

        expect(result.current.tracked).toHaveLength(1);
        expect(result.current.tracked[0].visitCount).toBe(3);
    });

    it('should persist to localStorage', () => {
        const { result } = renderHook(() => useResourceFrequency());

        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1' });
        });

        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY)!);
        expect(stored).toHaveLength(1);
        expect(stored[0].name).toBe('Prod');
        expect(stored[0].visitCount).toBe(1);
    });

    it('should return top favorites by score', () => {
        const { result } = renderHook(() => useResourceFrequency());

        // Visit server 3 times
        for (let i = 0; i < 3; i++) {
            act(() => {
                result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1' });
            });
        }

        // Visit app once
        act(() => {
            result.current.addVisit({ type: 'application', id: 'app-1', name: 'API', href: '/applications/app-1' });
        });

        const favorites = result.current.getFavorites();
        expect(favorites.length).toBeGreaterThanOrEqual(1);
        // Server should rank higher due to more visits
        expect(favorites[0].name).toBe('Prod');
    });

    it('should limit favorites to 5', () => {
        const { result } = renderHook(() => useResourceFrequency());

        for (let i = 0; i < 10; i++) {
            act(() => {
                result.current.addVisit({ type: 'server', id: `srv-${i}`, name: `Server ${i}`, href: `/servers/srv-${i}` });
            });
        }

        const favorites = result.current.getFavorites();
        expect(favorites).toHaveLength(5);
    });

    it('should limit tracked resources to 50', () => {
        const { result } = renderHook(() => useResourceFrequency());

        for (let i = 0; i < 55; i++) {
            act(() => {
                result.current.addVisit({ type: 'server', id: `srv-${i}`, name: `Server ${i}`, href: `/servers/srv-${i}` });
            });
        }

        expect(result.current.tracked.length).toBeLessThanOrEqual(50);
    });

    it('should load from localStorage on mount', () => {
        const stored = [
            { type: 'server', id: 'srv-1', name: 'Saved', href: '/servers/srv-1', visitCount: 5, lastVisit: Date.now() },
        ];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));

        const { result } = renderHook(() => useResourceFrequency());
        expect(result.current.tracked).toHaveLength(1);
        expect(result.current.tracked[0].name).toBe('Saved');
    });

    it('should handle corrupted localStorage gracefully', () => {
        localStorage.setItem(STORAGE_KEY, 'not valid json');
        const { result } = renderHook(() => useResourceFrequency());
        expect(result.current.tracked).toEqual([]);
    });

    it('should update name and href on revisit', () => {
        const { result } = renderHook(() => useResourceFrequency());

        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'Old Name', href: '/servers/srv-1' });
        });
        act(() => {
            result.current.addVisit({ type: 'server', id: 'srv-1', name: 'New Name', href: '/servers/srv-1' });
        });

        expect(result.current.tracked[0].name).toBe('New Name');
        expect(result.current.tracked[0].visitCount).toBe(2);
    });

    it('should treat different types with same id as separate resources', () => {
        const { result } = renderHook(() => useResourceFrequency());

        act(() => {
            result.current.addVisit({ type: 'server', id: 'abc', name: 'Server', href: '/servers/abc' });
        });
        act(() => {
            result.current.addVisit({ type: 'application', id: 'abc', name: 'App', href: '/applications/abc' });
        });

        expect(result.current.tracked).toHaveLength(2);
    });
});

describe('getFavoritesFromStorage', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('should return empty for empty storage', () => {
        expect(getFavoritesFromStorage()).toEqual([]);
    });

    it('should return favorites from storage', () => {
        const stored = [
            { type: 'server', id: 'srv-1', name: 'Prod', href: '/servers/srv-1', visitCount: 10, lastVisit: Date.now() },
        ];
        localStorage.setItem('saturn-resource-frequency', JSON.stringify(stored));
        const favorites = getFavoritesFromStorage();
        expect(favorites).toHaveLength(1);
        expect(favorites[0].name).toBe('Prod');
    });
});
