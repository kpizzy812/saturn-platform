import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mock useSSH — must be declared before importing the hook
// ---------------------------------------------------------------------------

const mockExec = vi.fn<[string], Promise<string>>();

vi.mock('../../src/ssh/context.js', () => ({
  useSSH: () => ({ exec: mockExec, execStream: vi.fn() }),
}));

// We test the hook logic directly by importing the module and calling the
// internal logic via a thin wrapper that bypasses React rendering.
// Because vitest runs in Node (not jsdom), we mock React hooks with their
// functional equivalents so the hook can be exercised synchronously.

// Minimal React state/callback stubs
vi.mock('react', async () => {
  const actual = await vi.importActual<typeof import('react')>('react');
  return {
    ...actual,
    useState: <T>(init: T | (() => T)): [T, (v: T | ((prev: T) => T)) => void] => {
      let value = typeof init === 'function' ? (init as () => T)() : init;
      const setter = (v: T | ((prev: T) => T)): void => {
        value = typeof v === 'function' ? (v as (prev: T) => T)(value) : v;
      };
      return [value, setter];
    },
    useCallback: <T extends (...args: unknown[]) => unknown>(fn: T): T => fn,
  };
});

import { useSSHExec } from '../../src/hooks/useSSHExec.js';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useSSHExec', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns initial state with empty output and no loading/error', () => {
    const result = useSSHExec();
    expect(result.output).toBe('');
    expect(result.loading).toBe(false);
    expect(result.error).toBeNull();
  });

  it('execute() calls exec with the provided command', async () => {
    mockExec.mockResolvedValueOnce('hello\n');
    const { execute } = useSSHExec();
    await execute('uptime');
    expect(mockExec).toHaveBeenCalledWith('uptime');
  });

  it('execute() returns the raw exec output string', async () => {
    mockExec.mockResolvedValueOnce('12:00 up 1 day\n');
    const { execute } = useSSHExec();
    const result = await execute('uptime');
    expect(result).toBe('12:00 up 1 day\n');
  });

  it('execute() returns empty string and does not throw on exec failure', async () => {
    mockExec.mockRejectedValueOnce(new Error('Connection refused'));
    const { execute } = useSSHExec();
    const result = await execute('ls');
    expect(result).toBe('');
  });

  it('execute() handles non-Error rejections gracefully', async () => {
    mockExec.mockRejectedValueOnce('string error');
    const { execute } = useSSHExec();
    const result = await execute('ls');
    expect(result).toBe('');
  });
});
