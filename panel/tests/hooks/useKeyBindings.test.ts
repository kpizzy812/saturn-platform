import { describe, it, expect, vi } from 'vitest';

// ---------------------------------------------------------------------------
// Mock ink's useInput — we capture the registered handler so we can invoke it
// ---------------------------------------------------------------------------

type InkInputHandler = (input: string, key: Record<string, boolean>) => void;
let capturedHandler: InkInputHandler | null = null;

vi.mock('ink', () => ({
  useInput: (handler: InkInputHandler) => {
    capturedHandler = handler;
  },
}));

vi.mock('react', async () => {
  const actual = await vi.importActual<typeof import('react')>('react');
  return {
    ...actual,
    useCallback: <T extends (...args: unknown[]) => unknown>(fn: T): T => fn,
  };
});

import { useKeyBindings } from '../../src/hooks/useKeyBindings.js';
import type { KeyBinding } from '../../src/hooks/useKeyBindings.js';

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

const blankKey: Record<string, boolean> = {
  escape: false,
  return: false,
  tab: false,
  upArrow: false,
  downArrow: false,
};

function fire(input: string, keyOverrides: Partial<typeof blankKey> = {}): void {
  capturedHandler?.(input, { ...blankKey, ...keyOverrides });
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useKeyBindings', () => {
  it('calls the matching handler for a character key', () => {
    const handler = vi.fn();
    const bindings: KeyBinding[] = [{ key: 'q', description: 'Quit', handler }];
    useKeyBindings(bindings, true);
    fire('q');
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('does not call handler when active=false', () => {
    const handler = vi.fn();
    const bindings: KeyBinding[] = [{ key: 'q', description: 'Quit', handler }];
    useKeyBindings(bindings, false);
    fire('q');
    expect(handler).not.toHaveBeenCalled();
  });

  it('calls handler for Esc key', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'Esc', description: 'Back', handler }], true);
    fire('', { escape: true });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('calls handler for Enter key', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'Enter', description: 'Confirm', handler }], true);
    fire('', { return: true });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('calls handler for Tab key', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'Tab', description: 'Tab', handler }], true);
    fire('', { tab: true });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('calls handler for up arrow key', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'up', description: 'Up', handler }], true);
    fire('', { upArrow: true });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('calls handler for down arrow key', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'down', description: 'Down', handler }], true);
    fire('', { downArrow: true });
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('calls handler for / character', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: '/', description: 'Search', handler }], true);
    fire('/');
    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('only calls the first matching handler — stops after first match', () => {
    const first = vi.fn();
    const second = vi.fn();
    const bindings: KeyBinding[] = [
      { key: 'q', description: 'First', handler: first },
      { key: 'q', description: 'Second (duplicate)', handler: second },
    ];
    useKeyBindings(bindings, true);
    fire('q');
    expect(first).toHaveBeenCalledTimes(1);
    expect(second).not.toHaveBeenCalled();
  });

  it('does not call any handler when no binding matches', () => {
    const handler = vi.fn();
    useKeyBindings([{ key: 'q', description: 'Quit', handler }], true);
    fire('x');
    expect(handler).not.toHaveBeenCalled();
  });

  it('handles digit key bindings (1-7)', () => {
    const handlers = Array.from({ length: 7 }, () => vi.fn());
    const bindings: KeyBinding[] = handlers.map((h, i) => ({
      key: String(i + 1),
      description: `Screen ${i + 1}`,
      handler: h,
    }));
    useKeyBindings(bindings, true);

    fire('3');
    expect(handlers[2]).toHaveBeenCalledTimes(1);
    for (let i = 0; i < 7; i++) {
      if (i !== 2) expect(handlers[i]).not.toHaveBeenCalled();
    }
  });
});
