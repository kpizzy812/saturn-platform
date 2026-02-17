import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useSearch } from '@/hooks/useSearch';

const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('useSearch', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should return empty results for short query', () => {
        const { result } = renderHook(() => useSearch('a'));
        expect(result.current.results).toEqual([]);
        expect(result.current.isLoading).toBe(false);
    });

    it('should return empty results for empty query', () => {
        const { result } = renderHook(() => useSearch(''));
        expect(result.current.results).toEqual([]);
        expect(result.current.isLoading).toBe(false);
    });

    it('should set isLoading to true for valid query', () => {
        mockFetch.mockImplementation(() => new Promise(() => {})); // Never resolves
        const { result } = renderHook(() => useSearch('test'));
        expect(result.current.isLoading).toBe(true);
    });

    it('should fetch results after debounce', async () => {
        const mockResults = {
            results: [
                { type: 'server', uuid: 'abc', name: 'Production', description: null, href: '/servers/abc' },
            ],
        };

        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockResults),
        });

        const { result } = renderHook(() => useSearch('prod'));

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(1);
        }, { timeout: 2000 });

        await waitFor(() => {
            expect(result.current.results).toHaveLength(1);
            expect(result.current.results[0].name).toBe('Production');
            expect(result.current.isLoading).toBe(false);
        });

        expect(mockFetch).toHaveBeenCalledWith(
            '/web-api/search?q=prod',
            expect.objectContaining({
                headers: expect.objectContaining({ 'Accept': 'application/json' }),
            }),
        );
    });

    it('should not fetch for queries shorter than 2 chars', async () => {
        const { result } = renderHook(() => useSearch('p'));

        // Wait longer than debounce
        await new Promise((r) => setTimeout(r, 500));

        expect(mockFetch).not.toHaveBeenCalled();
        expect(result.current.results).toEqual([]);
    });

    it('should handle fetch errors gracefully', async () => {
        mockFetch.mockResolvedValue({
            ok: false,
            status: 500,
        });

        const { result } = renderHook(() => useSearch('test'));

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        }, { timeout: 2000 });

        expect(result.current.results).toEqual([]);
    });

    it('should clear results when query becomes too short', async () => {
        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                results: [{ type: 'server', uuid: 'abc', name: 'Test', description: null, href: '/servers/abc' }],
            }),
        });

        const { result, rerender } = renderHook(({ q }) => useSearch(q), {
            initialProps: { q: 'test' },
        });

        await waitFor(() => {
            expect(result.current.results).toHaveLength(1);
        }, { timeout: 2000 });

        rerender({ q: 'a' });

        expect(result.current.results).toEqual([]);
        expect(result.current.isLoading).toBe(false);
    });
});
