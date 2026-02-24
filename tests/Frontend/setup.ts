import '@testing-library/jest-dom';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';
import React from 'react';

// Mock motion/react so m.div renders as plain div in tests
// IMPORTANT: Cache components so React sees stable references across re-renders
// (without cache, Proxy creates a new component on every m.div access, causing
// React to unmount/remount the tree and invalidating DOM node references)
vi.mock('motion/react', () => {
    const cache = new Map<string, React.ComponentType<any>>();

    const getMotionComponent = (tag: string) => {
        if (!cache.has(tag)) {
            const Component = React.forwardRef((props: any, ref: any) => {
                const {
                    initial, animate, exit, variants, transition,
                    whileHover, whileTap, whileFocus, whileInView,
                    onAnimationStart, onAnimationComplete,
                    layout, layoutId,
                    ...rest
                } = props;
                return React.createElement(tag, { ...rest, ref });
            });
            Component.displayName = `motion.${tag}`;
            cache.set(tag, Component);
        }
        return cache.get(tag)!;
    };

    return {
        m: new Proxy({}, {
            get: (_target, prop: string) => getMotionComponent(prop),
        }),
        motion: new Proxy({}, {
            get: (_target, prop: string) => getMotionComponent(prop),
        }),
        AnimatePresence: ({ children }: any) => children,
        LazyMotion: ({ children }: any) => children,
        domAnimation: {},
        domMax: {},
    };
});

// Cleanup after each test
afterEach(() => {
    cleanup();
});

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

// Mock IntersectionObserver
class MockIntersectionObserver {
    observe = vi.fn();
    disconnect = vi.fn();
    unobserve = vi.fn();
}

Object.defineProperty(window, 'IntersectionObserver', {
    writable: true,
    value: MockIntersectionObserver,
});

// Mock ResizeObserver
class MockResizeObserver {
    observe = vi.fn();
    disconnect = vi.fn();
    unobserve = vi.fn();
}

Object.defineProperty(window, 'ResizeObserver', {
    writable: true,
    value: MockResizeObserver,
});

// Add CSRF meta tag for hooks that read it
const meta = document.createElement('meta');
meta.name = 'csrf-token';
meta.content = 'test-csrf-token';
document.head.appendChild(meta);

// Mock usePermissions to allow all permissions by default in tests
vi.mock('@/hooks/usePermissions', () => ({
    usePermissions: () => ({
        can: () => true,
    }),
}));

// Mock clipboard API (make it configurable so userEvent can also configure it)
if (!navigator.clipboard) {
    Object.defineProperty(navigator, 'clipboard', {
        writable: true,
        configurable: true,
        value: {
            writeText: vi.fn(() => Promise.resolve()),
            readText: vi.fn(() => Promise.resolve('')),
        },
    });
}
