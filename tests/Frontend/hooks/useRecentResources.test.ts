import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useRecentResources } from '@/hooks/useRecentResources';

const STORAGE_KEY = 'saturn-recent-resources';

describe('useRecentResources', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('should return empty array initially', () => {
        const { result } = renderHook(() => useRecentResources());
        expect(result.current.recentItems).toEqual([]);
    });

    it('should add a recent resource', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({
                type: 'server',
                name: 'Production',
                uuid: 'abc-123',
                href: '/servers/abc-123',
            });
        });

        expect(result.current.recentItems).toHaveLength(1);
        expect(result.current.recentItems[0].name).toBe('Production');
        expect(result.current.recentItems[0].type).toBe('server');
        expect(result.current.recentItems[0].uuid).toBe('abc-123');
        expect(result.current.recentItems[0].timestamp).toBeGreaterThan(0);
    });

    it('should persist to localStorage', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({
                type: 'server',
                name: 'Production',
                uuid: 'abc-123',
                href: '/servers/abc-123',
            });
        });

        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY)!);
        expect(stored).toHaveLength(1);
        expect(stored[0].name).toBe('Production');
    });

    it('should deduplicate by type+uuid (newest first)', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({
                type: 'server',
                name: 'Old Name',
                uuid: 'abc-123',
                href: '/servers/abc-123',
            });
        });

        act(() => {
            result.current.addRecent({
                type: 'server',
                name: 'New Name',
                uuid: 'abc-123',
                href: '/servers/abc-123',
            });
        });

        expect(result.current.recentItems).toHaveLength(1);
        expect(result.current.recentItems[0].name).toBe('New Name');
    });

    it('should keep max 5 items', () => {
        const { result } = renderHook(() => useRecentResources());

        for (let i = 0; i < 7; i++) {
            act(() => {
                result.current.addRecent({
                    type: 'server',
                    name: `Server ${i}`,
                    uuid: `uuid-${i}`,
                    href: `/servers/uuid-${i}`,
                });
            });
        }

        expect(result.current.recentItems).toHaveLength(5);
        // Newest should be first
        expect(result.current.recentItems[0].name).toBe('Server 6');
    });

    it('should put newest items first', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({ type: 'server', name: 'First', uuid: 'a', href: '/servers/a' });
        });
        act(() => {
            result.current.addRecent({ type: 'application', name: 'Second', uuid: 'b', href: '/applications/b' });
        });

        expect(result.current.recentItems[0].name).toBe('Second');
        expect(result.current.recentItems[1].name).toBe('First');
    });

    it('should clear all recent items', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({ type: 'server', name: 'Test', uuid: 'abc', href: '/servers/abc' });
        });

        expect(result.current.recentItems).toHaveLength(1);

        act(() => {
            result.current.clearRecent();
        });

        expect(result.current.recentItems).toEqual([]);
        expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
    });

    it('should load from localStorage on mount', () => {
        const stored = [
            { type: 'server', name: 'Saved', uuid: 'saved-1', href: '/servers/saved-1', timestamp: Date.now() },
        ];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));

        const { result } = renderHook(() => useRecentResources());
        expect(result.current.recentItems).toHaveLength(1);
        expect(result.current.recentItems[0].name).toBe('Saved');
    });

    it('should handle corrupted localStorage gracefully', () => {
        localStorage.setItem(STORAGE_KEY, 'not valid json');

        const { result } = renderHook(() => useRecentResources());
        expect(result.current.recentItems).toEqual([]);
    });

    it('should allow different types with same uuid', () => {
        const { result } = renderHook(() => useRecentResources());

        act(() => {
            result.current.addRecent({ type: 'server', name: 'Same UUID', uuid: 'abc', href: '/servers/abc' });
        });
        act(() => {
            result.current.addRecent({ type: 'application', name: 'Same UUID', uuid: 'abc', href: '/applications/abc' });
        });

        expect(result.current.recentItems).toHaveLength(2);
    });
});
