import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ---------------------------------------------------------------------------
// useInterval uses useEffect and useRef from React.
// We use real React hooks via @testing-library/react is not available here,
// so we test the hook's internal contract by directly simulating its behaviour
// using vitest fake timers and a plain function that mirrors the hook logic.
// ---------------------------------------------------------------------------

// Minimal stub for useRef that works in Node context
const refs: Map<unknown, { current: unknown }> = new Map();

vi.mock('react', async () => {
  const actual = await vi.importActual<typeof import('react')>('react');
  return {
    ...actual,
    useRef: <T>(init: T) => {
      const ref = { current: init };
      return ref;
    },
    useEffect: (fn: () => (() => void) | void, _deps?: unknown[]) => {
      // In our stub, useEffect runs synchronously once and stores the cleanup
      const cleanup = fn();
      if (cleanup) {
        refs.set(fn, { current: cleanup });
      }
    },
  };
});

import { useInterval } from '../../src/hooks/useInterval.js';

// ---------------------------------------------------------------------------
// Tests using fake timers
// ---------------------------------------------------------------------------

describe('useInterval', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    refs.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
  });

  it('does not invoke callback when delayMs is null (paused)', () => {
    const callback = vi.fn();
    useInterval(callback, null);
    vi.advanceTimersByTime(10_000);
    expect(callback).not.toHaveBeenCalled();
  });

  it('invokes callback after the specified delay', () => {
    const callback = vi.fn();
    useInterval(callback, 1000);
    vi.advanceTimersByTime(1000);
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('invokes callback multiple times over multiple intervals', () => {
    const callback = vi.fn();
    useInterval(callback, 500);
    vi.advanceTimersByTime(2500);
    // 500ms × 5 = 2500ms → 5 calls
    expect(callback).toHaveBeenCalledTimes(5);
  });

  it('does not throw when callback is called', () => {
    const callback = vi.fn(() => {
      /* intentionally empty */
    });
    expect(() => {
      useInterval(callback, 100);
      vi.advanceTimersByTime(300);
    }).not.toThrow();
  });
});

// ---------------------------------------------------------------------------
// Direct logic tests (not dependent on React hook lifecycle)
// These verify the stale-closure-safe ref pattern by simulating it manually.
// ---------------------------------------------------------------------------

describe('useInterval — stale closure prevention contract', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('always calls the latest callback version via ref pattern', () => {
    // Simulate how the hook prevents stale closures:
    // The interval function reads from callbackRef.current, not the original closure
    const callbackRef = { current: vi.fn().mockReturnValue('v1') };

    const id = setInterval(() => {
      callbackRef.current();
    }, 100);

    vi.advanceTimersByTime(100);
    expect(callbackRef.current).toHaveBeenCalledTimes(1);

    // Simulate callback update (useEffect would do this)
    const newCallback = vi.fn().mockReturnValue('v2');
    callbackRef.current = newCallback;

    vi.advanceTimersByTime(100);
    expect(newCallback).toHaveBeenCalledTimes(1);

    clearInterval(id);
  });
});
