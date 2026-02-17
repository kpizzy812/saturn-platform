import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { usePaletteBrowse } from '@/hooks/usePaletteBrowse';

const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('usePaletteBrowse', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should return empty items initially', () => {
        const { result } = renderHook(() => usePaletteBrowse());
        expect(result.current.items).toEqual([]);
        expect(result.current.isLoading).toBe(false);
    });

    it('should fetch browse items for a type', async () => {
        const mockItems = {
            items: [
                { id: 'uuid-1', name: 'Project One', description: null, href: '/projects/uuid-1', has_children: true, child_type: 'environments' },
                { id: 'uuid-2', name: 'Project Two', description: 'Desc', href: '/projects/uuid-2', has_children: true, child_type: 'environments' },
            ],
        };

        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockItems),
        });

        const { result } = renderHook(() => usePaletteBrowse());

        await act(async () => {
            await result.current.fetchBrowse('projects');
        });

        expect(result.current.items).toHaveLength(2);
        expect(result.current.items[0].name).toBe('Project One');
        expect(result.current.isLoading).toBe(false);

        expect(mockFetch).toHaveBeenCalledWith(
            '/web-api/command-palette/browse?type=projects',
            expect.objectContaining({
                headers: expect.objectContaining({ 'Accept': 'application/json' }),
            }),
        );
    });

    it('should pass parent_uuid parameter', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ items: [] }),
        });

        const { result } = renderHook(() => usePaletteBrowse());

        await act(async () => {
            await result.current.fetchBrowse('environments', 'project-uuid-1');
        });

        expect(mockFetch).toHaveBeenCalledWith(
            '/web-api/command-palette/browse?type=environments&parent_uuid=project-uuid-1',
            expect.anything(),
        );
    });

    it('should cache results for 30 seconds', async () => {
        const mockItems = { items: [{ id: '1', name: 'Test', description: null, href: '/test', has_children: false, child_type: null }] };

        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockItems),
        });

        const { result } = renderHook(() => usePaletteBrowse());

        // First fetch
        await act(async () => {
            await result.current.fetchBrowse('servers');
        });
        expect(mockFetch).toHaveBeenCalledTimes(1);

        // Second fetch - should use cache
        await act(async () => {
            await result.current.fetchBrowse('servers');
        });
        expect(mockFetch).toHaveBeenCalledTimes(1); // Still 1
    });

    it('should handle fetch errors gracefully', async () => {
        mockFetch.mockResolvedValue({ ok: false, status: 500 });

        const { result } = renderHook(() => usePaletteBrowse());

        await act(async () => {
            await result.current.fetchBrowse('projects');
        });

        expect(result.current.items).toEqual([]);
        expect(result.current.isLoading).toBe(false);
    });

    it('should clear cache', async () => {
        const mockItems = { items: [{ id: '1', name: 'Test', description: null, href: '/test', has_children: false, child_type: null }] };

        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockItems),
        });

        const { result } = renderHook(() => usePaletteBrowse());

        await act(async () => {
            await result.current.fetchBrowse('servers');
        });
        expect(mockFetch).toHaveBeenCalledTimes(1);

        // Clear cache
        act(() => {
            result.current.clearCache();
        });

        // Fetch again - should make new request
        await act(async () => {
            await result.current.fetchBrowse('servers');
        });
        expect(mockFetch).toHaveBeenCalledTimes(2);
    });

    it('should use separate cache keys for different types', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ items: [] }),
        });

        const { result } = renderHook(() => usePaletteBrowse());

        await act(async () => {
            await result.current.fetchBrowse('projects');
        });
        await act(async () => {
            await result.current.fetchBrowse('servers');
        });

        expect(mockFetch).toHaveBeenCalledTimes(2);
    });
});
