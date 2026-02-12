import '@testing-library/jest-dom';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

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
