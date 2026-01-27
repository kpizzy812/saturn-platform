import { useCallback, useEffect, useRef, useState, useMemo } from 'react';

/**
 * Storage key prefix for autoscroll preferences
 */
const STORAGE_KEY_PREFIX = 'saturn:autoscroll:';

/**
 * Configuration options for useAutoScroll hook
 */
export interface UseAutoScrollOptions {
    /**
     * Unique key for persisting autoscroll preference
     * If provided, the preference will be saved to localStorage
     */
    storageKey?: string;

    /**
     * Initial autoscroll state (default: true)
     * Only used if no persisted value exists
     */
    defaultEnabled?: boolean;

    /**
     * Threshold in pixels from bottom to consider "at bottom" (default: 50)
     */
    threshold?: number;

    /**
     * Whether to use smooth scrolling (default: true)
     */
    smooth?: boolean;

    /**
     * Callback when autoscroll state changes
     */
    onAutoScrollChange?: (enabled: boolean) => void;

    /**
     * Callback when new content arrives while scrolled up
     */
    onNewContentWhileScrolledUp?: (count: number) => void;

    /**
     * Debounce scroll events (default: 50ms)
     */
    scrollDebounceMs?: number;
}

export interface UseAutoScrollReturn {
    /**
     * Ref to attach to the scrollable container
     */
    containerRef: React.RefObject<HTMLDivElement | null>;

    /**
     * Ref to attach to the bottom anchor element
     */
    bottomRef: React.RefObject<HTMLDivElement | null>;

    /**
     * Whether autoscroll is currently enabled
     */
    isEnabled: boolean;

    /**
     * Whether the container is currently at the bottom
     */
    isAtBottom: boolean;

    /**
     * Number of new items that arrived while scrolled up
     */
    newItemsCount: number;

    /**
     * Enable autoscroll
     */
    enable: () => void;

    /**
     * Disable autoscroll
     */
    disable: () => void;

    /**
     * Toggle autoscroll state
     */
    toggle: () => void;

    /**
     * Set autoscroll state directly
     */
    setEnabled: (enabled: boolean) => void;

    /**
     * Scroll to bottom immediately
     */
    scrollToBottom: (options?: { smooth?: boolean }) => void;

    /**
     * Scroll to top immediately
     */
    scrollToTop: (options?: { smooth?: boolean }) => void;

    /**
     * Notify the hook that new content was added
     * This triggers autoscroll if enabled, or increments newItemsCount if disabled
     */
    notifyNewContent: (count?: number) => void;

    /**
     * Clear the new items counter
     */
    clearNewItemsCount: () => void;

    /**
     * Handle scroll event - attach to onScroll of container
     * Usually not needed if containerRef is used, but available for custom implementations
     */
    handleScroll: () => void;
}

/**
 * Get persisted autoscroll preference from localStorage
 */
function getPersistedValue(key: string | undefined): boolean | null {
    if (!key || typeof window === 'undefined') return null;
    try {
        const stored = localStorage.getItem(STORAGE_KEY_PREFIX + key);
        if (stored !== null) {
            return stored === 'true';
        }
    } catch (e) {
        // localStorage might be disabled
    }
    return null;
}

/**
 * Persist autoscroll preference to localStorage
 */
function setPersistedValue(key: string | undefined, value: boolean): void {
    if (!key || typeof window === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY_PREFIX + key, String(value));
    } catch (e) {
        // localStorage might be disabled or full
    }
}

/**
 * Custom hook for managing autoscroll behavior in log viewers and similar components
 *
 * Features:
 * - Persists user preference to localStorage
 * - Automatically disables when user scrolls up
 * - Automatically re-enables when user scrolls to bottom
 * - Tracks new content count while scrolled up
 * - Keyboard shortcuts support (via separate integration)
 * - Debounced scroll handling for performance
 *
 * @example
 * ```tsx
 * function LogViewer({ logs }: { logs: string[] }) {
 *   const {
 *     containerRef,
 *     bottomRef,
 *     isEnabled,
 *     isAtBottom,
 *     newItemsCount,
 *     toggle,
 *     scrollToBottom,
 *     notifyNewContent,
 *   } = useAutoScroll({
 *     storageKey: 'deployment-logs',
 *     defaultEnabled: true,
 *   });
 *
 *   // Notify hook when new logs arrive
 *   useEffect(() => {
 *     notifyNewContent(1);
 *   }, [logs.length]);
 *
 *   return (
 *     <div>
 *       <button onClick={toggle}>
 *         Auto-scroll: {isEnabled ? 'ON' : 'OFF'}
 *       </button>
 *       {newItemsCount > 0 && (
 *         <button onClick={() => scrollToBottom()}>
 *           {newItemsCount} new logs ↓
 *         </button>
 *       )}
 *       <div ref={containerRef} style={{ overflow: 'auto', height: 400 }}>
 *         {logs.map((log, i) => <div key={i}>{log}</div>)}
 *         <div ref={bottomRef} />
 *       </div>
 *     </div>
 *   );
 * }
 * ```
 */
export function useAutoScroll(options: UseAutoScrollOptions = {}): UseAutoScrollReturn {
    const {
        storageKey,
        defaultEnabled = true,
        threshold = 50,
        smooth = true,
        onAutoScrollChange,
        onNewContentWhileScrolledUp,
        scrollDebounceMs = 50,
    } = options;

    // Initialize state from localStorage or default
    const [isEnabled, setIsEnabledInternal] = useState<boolean>(() => {
        const persisted = getPersistedValue(storageKey);
        return persisted !== null ? persisted : defaultEnabled;
    });

    const [isAtBottom, setIsAtBottom] = useState(true);
    const [newItemsCount, setNewItemsCount] = useState(0);

    const containerRef = useRef<HTMLDivElement>(null);
    const bottomRef = useRef<HTMLDivElement>(null);
    const scrollTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const isScrollingProgrammatically = useRef(false);
    const lastScrollTop = useRef(0);

    /**
     * Check if container is scrolled to bottom
     */
    const checkIsAtBottom = useCallback((): boolean => {
        const container = containerRef.current;
        if (!container) return true;

        const { scrollTop, scrollHeight, clientHeight } = container;
        return scrollHeight - scrollTop - clientHeight <= threshold;
    }, [threshold]);

    /**
     * Set enabled state and persist to localStorage
     */
    const setEnabled = useCallback((enabled: boolean) => {
        setIsEnabledInternal(enabled);
        setPersistedValue(storageKey, enabled);
        onAutoScrollChange?.(enabled);

        // If enabling and at bottom, clear new items count
        if (enabled && checkIsAtBottom()) {
            setNewItemsCount(0);
        }
    }, [storageKey, onAutoScrollChange, checkIsAtBottom]);

    const enable = useCallback(() => setEnabled(true), [setEnabled]);
    const disable = useCallback(() => setEnabled(false), [setEnabled]);
    const toggle = useCallback(() => setEnabled(!isEnabled), [setEnabled, isEnabled]);

    /**
     * Scroll to bottom of container
     */
    const scrollToBottom = useCallback((scrollOptions?: { smooth?: boolean }) => {
        const container = containerRef.current;
        const bottom = bottomRef.current;

        if (bottom) {
            isScrollingProgrammatically.current = true;
            bottom.scrollIntoView({
                behavior: (scrollOptions?.smooth ?? smooth) ? 'smooth' : 'instant',
                block: 'end',
            });
            // Reset flag after animation
            setTimeout(() => {
                isScrollingProgrammatically.current = false;
            }, 500);
        } else if (container) {
            isScrollingProgrammatically.current = true;
            container.scrollTo({
                top: container.scrollHeight,
                behavior: (scrollOptions?.smooth ?? smooth) ? 'smooth' : 'instant',
            });
            setTimeout(() => {
                isScrollingProgrammatically.current = false;
            }, 500);
        }

        setNewItemsCount(0);
        setIsAtBottom(true);
    }, [smooth]);

    /**
     * Scroll to top of container
     */
    const scrollToTop = useCallback((scrollOptions?: { smooth?: boolean }) => {
        const container = containerRef.current;
        if (!container) return;

        isScrollingProgrammatically.current = true;
        container.scrollTo({
            top: 0,
            behavior: (scrollOptions?.smooth ?? smooth) ? 'smooth' : 'instant',
        });
        setTimeout(() => {
            isScrollingProgrammatically.current = false;
        }, 500);
    }, [smooth]);

    /**
     * Handle scroll events with debouncing
     */
    const handleScroll = useCallback(() => {
        // Clear existing timeout
        if (scrollTimeoutRef.current) {
            clearTimeout(scrollTimeoutRef.current);
        }

        // Debounce scroll handling
        scrollTimeoutRef.current = setTimeout(() => {
            const container = containerRef.current;
            if (!container) return;

            const currentScrollTop = container.scrollTop;
            const atBottom = checkIsAtBottom();

            setIsAtBottom(atBottom);

            // Only process user-initiated scrolls
            if (!isScrollingProgrammatically.current) {
                // User scrolled up - disable autoscroll
                if (currentScrollTop < lastScrollTop.current && !atBottom && isEnabled) {
                    setEnabled(false);
                }
                // User scrolled to bottom - enable autoscroll
                else if (atBottom && !isEnabled) {
                    setEnabled(true);
                    setNewItemsCount(0);
                }
            }

            lastScrollTop.current = currentScrollTop;
        }, scrollDebounceMs);
    }, [checkIsAtBottom, isEnabled, setEnabled, scrollDebounceMs]);

    /**
     * Notify that new content was added
     */
    const notifyNewContent = useCallback((count: number = 1) => {
        if (isEnabled && checkIsAtBottom()) {
            // Autoscroll is enabled and we're at bottom - scroll to new content
            requestAnimationFrame(() => {
                scrollToBottom({ smooth: true });
            });
        } else if (!isEnabled || !checkIsAtBottom()) {
            // User is scrolled up - increment counter
            setNewItemsCount(prev => {
                const newCount = prev + count;
                onNewContentWhileScrolledUp?.(newCount);
                return newCount;
            });
        }
    }, [isEnabled, checkIsAtBottom, scrollToBottom, onNewContentWhileScrolledUp]);

    /**
     * Clear new items count
     */
    const clearNewItemsCount = useCallback(() => {
        setNewItemsCount(0);
    }, []);

    // Attach scroll listener to container
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        container.addEventListener('scroll', handleScroll, { passive: true });
        return () => {
            container.removeEventListener('scroll', handleScroll);
            if (scrollTimeoutRef.current) {
                clearTimeout(scrollTimeoutRef.current);
            }
        };
    }, [handleScroll]);

    // Initial scroll to bottom if enabled
    useEffect(() => {
        if (isEnabled) {
            // Delay to ensure content is rendered
            const timer = setTimeout(() => {
                scrollToBottom({ smooth: false });
            }, 100);
            return () => clearTimeout(timer);
        }
    }, []); // Only on mount

    return useMemo(() => ({
        containerRef,
        bottomRef,
        isEnabled,
        isAtBottom,
        newItemsCount,
        enable,
        disable,
        toggle,
        setEnabled,
        scrollToBottom,
        scrollToTop,
        notifyNewContent,
        clearNewItemsCount,
        handleScroll,
    }), [
        isEnabled,
        isAtBottom,
        newItemsCount,
        enable,
        disable,
        toggle,
        setEnabled,
        scrollToBottom,
        scrollToTop,
        notifyNewContent,
        clearNewItemsCount,
        handleScroll,
    ]);
}

export default useAutoScroll;
