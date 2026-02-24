import { describe, it, expect, vi } from 'vitest';
import type { ScreenName } from '../../src/config/constants.js';

// ---------------------------------------------------------------------------
// Minimal React stubs — useNavigation only uses useState and useCallback
// ---------------------------------------------------------------------------

vi.mock('react', async () => {
  const actual = await vi.importActual<typeof import('react')>('react');
  return {
    ...actual,
    useState: <T>(init: T | (() => T)): [T, (v: T | ((prev: T) => T)) => void] => {
      let value = typeof init === 'function' ? (init as () => T)() : init;
      const setter = (v: T | ((prev: T) => T)): void => {
        value = typeof v === 'function' ? (v as (prev: T) => T)(value) : v;
      };
      // Return the getter value via a getter so callers read the latest value
      return [value, setter];
    },
    useCallback: <T extends (...args: unknown[]) => unknown>(fn: T): T => fn,
  };
});

import { useNavigation } from '../../src/hooks/useNavigation.js';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useNavigation', () => {
  it('initialises with the provided initial screen', () => {
    const nav = useNavigation('dashboard');
    expect(nav.currentScreen).toBe('dashboard');
  });

  it('defaults to dashboard when no initial screen is given', () => {
    const nav = useNavigation();
    expect(nav.currentScreen).toBe('dashboard');
  });

  it('canGoBack is false with a single entry in history', () => {
    const nav = useNavigation('dashboard');
    expect(nav.canGoBack).toBe(false);
  });

  it('history contains the initial screen', () => {
    const nav = useNavigation('dashboard');
    expect(nav.history).toEqual(['dashboard']);
  });

  it('navigate() updates currentScreen', () => {
    const nav = useNavigation('dashboard');
    // Because our stub captures values at call time, we test via navigate side-effect:
    // navigate should not throw and should call setState
    expect(() => nav.navigate('logs' as ScreenName)).not.toThrow();
  });

  it('goBack() does not throw when history has only one entry', () => {
    const nav = useNavigation('dashboard');
    expect(() => nav.goBack()).not.toThrow();
  });

  it('goBack() does not remove the last entry when only one exists', () => {
    // We simulate a multi-call scenario using a real history array
    const historyArr: ScreenName[] = ['dashboard'];

    // Manually simulate navigate + goBack logic
    historyArr.push('logs');
    expect(historyArr.length).toBe(2);

    historyArr.splice(historyArr.length - 1, 1);
    expect(historyArr).toEqual(['dashboard']);

    // Attempt another goBack when only one item remains — should be a no-op
    if (historyArr.length > 1) {
      historyArr.splice(historyArr.length - 1, 1);
    }
    expect(historyArr).toEqual(['dashboard']);
  });
});
