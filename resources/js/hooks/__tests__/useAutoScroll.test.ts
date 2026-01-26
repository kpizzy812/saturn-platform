import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useAutoScroll } from '../useAutoScroll';

// Mock localStorage
const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: (key: string) => store[key] || null,
        setItem: (key: string, value: string) => {
            store[key] = value;
        },
        removeItem: (key: string) => {
            delete store[key];
        },
        clear: () => {
            store = {};
        },
    };
})();

Object.defineProperty(window, 'localStorage', { value: localStorageMock });

describe('useAutoScroll', () => {
    beforeEach(() => {
        localStorageMock.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('initialization', () => {
        it('should initialize with defaultEnabled=true by default', () => {
            const { result } = renderHook(() => useAutoScroll());
            expect(result.current.isEnabled).toBe(true);
        });

        it('should initialize with defaultEnabled=false when specified', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ defaultEnabled: false })
            );
            expect(result.current.isEnabled).toBe(false);
        });

        it('should restore state from localStorage when storageKey is provided', () => {
            localStorageMock.setItem('saturn:autoscroll:test-key', 'false');

            const { result } = renderHook(() =>
                useAutoScroll({ storageKey: 'test-key', defaultEnabled: true })
            );

            expect(result.current.isEnabled).toBe(false);
        });

        it('should use defaultEnabled when no persisted value exists', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ storageKey: 'new-key', defaultEnabled: true })
            );

            expect(result.current.isEnabled).toBe(true);
        });
    });

    describe('toggle and setEnabled', () => {
        it('should toggle autoscroll state', () => {
            const { result } = renderHook(() => useAutoScroll());

            expect(result.current.isEnabled).toBe(true);

            act(() => {
                result.current.toggle();
            });

            expect(result.current.isEnabled).toBe(false);

            act(() => {
                result.current.toggle();
            });

            expect(result.current.isEnabled).toBe(true);
        });

        it('should enable autoscroll', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ defaultEnabled: false })
            );

            expect(result.current.isEnabled).toBe(false);

            act(() => {
                result.current.enable();
            });

            expect(result.current.isEnabled).toBe(true);
        });

        it('should disable autoscroll', () => {
            const { result } = renderHook(() => useAutoScroll());

            expect(result.current.isEnabled).toBe(true);

            act(() => {
                result.current.disable();
            });

            expect(result.current.isEnabled).toBe(false);
        });

        it('should persist state to localStorage when storageKey is provided', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ storageKey: 'persist-test' })
            );

            act(() => {
                result.current.setEnabled(false);
            });

            expect(localStorageMock.getItem('saturn:autoscroll:persist-test')).toBe('false');

            act(() => {
                result.current.setEnabled(true);
            });

            expect(localStorageMock.getItem('saturn:autoscroll:persist-test')).toBe('true');
        });
    });

    describe('callbacks', () => {
        it('should call onAutoScrollChange when state changes', () => {
            const onAutoScrollChange = vi.fn();

            const { result } = renderHook(() =>
                useAutoScroll({ onAutoScrollChange })
            );

            act(() => {
                result.current.toggle();
            });

            expect(onAutoScrollChange).toHaveBeenCalledWith(false);

            act(() => {
                result.current.toggle();
            });

            expect(onAutoScrollChange).toHaveBeenCalledWith(true);
        });
    });

    describe('newItemsCount', () => {
        it('should start with newItemsCount at 0', () => {
            const { result } = renderHook(() => useAutoScroll());
            expect(result.current.newItemsCount).toBe(0);
        });

        it('should clear newItemsCount', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ defaultEnabled: false })
            );

            // Simulate new content while disabled
            act(() => {
                result.current.notifyNewContent(5);
            });

            expect(result.current.newItemsCount).toBe(5);

            act(() => {
                result.current.clearNewItemsCount();
            });

            expect(result.current.newItemsCount).toBe(0);
        });

        it('should increment newItemsCount when notifyNewContent is called while disabled', () => {
            const { result } = renderHook(() =>
                useAutoScroll({ defaultEnabled: false })
            );

            act(() => {
                result.current.notifyNewContent(3);
            });

            expect(result.current.newItemsCount).toBe(3);

            act(() => {
                result.current.notifyNewContent(2);
            });

            expect(result.current.newItemsCount).toBe(5);
        });

        it('should call onNewContentWhileScrolledUp callback', () => {
            const onNewContentWhileScrolledUp = vi.fn();

            const { result } = renderHook(() =>
                useAutoScroll({
                    defaultEnabled: false,
                    onNewContentWhileScrolledUp,
                })
            );

            act(() => {
                result.current.notifyNewContent(3);
            });

            expect(onNewContentWhileScrolledUp).toHaveBeenCalledWith(3);
        });
    });

    describe('refs', () => {
        it('should provide containerRef', () => {
            const { result } = renderHook(() => useAutoScroll());
            expect(result.current.containerRef).toBeDefined();
            expect(result.current.containerRef.current).toBeNull();
        });

        it('should provide bottomRef', () => {
            const { result } = renderHook(() => useAutoScroll());
            expect(result.current.bottomRef).toBeDefined();
            expect(result.current.bottomRef.current).toBeNull();
        });
    });

    describe('isAtBottom', () => {
        it('should start with isAtBottom=true', () => {
            const { result } = renderHook(() => useAutoScroll());
            expect(result.current.isAtBottom).toBe(true);
        });
    });
});
