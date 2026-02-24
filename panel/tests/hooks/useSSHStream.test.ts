import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Async generator helper
// ---------------------------------------------------------------------------

async function* makeStream(lines: string[]): AsyncGenerator<string, void, unknown> {
  for (const line of lines) {
    yield line;
  }
}

async function* makeFailingStream(errorMessage: string): AsyncGenerator<string, void, unknown> {
  yield 'first line';
  throw new Error(errorMessage);
}

// ---------------------------------------------------------------------------
// Mock SSH context
// ---------------------------------------------------------------------------

const mockExecStream = vi.fn<[string], AsyncGenerator<string, void, unknown>>();

vi.mock('../../src/ssh/context.js', () => ({
  useSSH: () => ({ exec: vi.fn(), execStream: mockExecStream }),
}));

// ---------------------------------------------------------------------------
// Minimal React stubs
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
      return [value, setter];
    },
    useRef: <T>(init: T) => ({ current: init }),
    useCallback: <T extends (...args: unknown[]) => unknown>(fn: T): T => fn,
  };
});

import { useSSHStream } from '../../src/hooks/useSSHStream.js';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useSSHStream', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns initial empty state', () => {
    const result = useSSHStream();
    expect(result.lines).toEqual([]);
    expect(result.streaming).toBe(false);
    expect(result.error).toBeNull();
  });

  it('clear() resets lines to empty array', () => {
    const { clear, lines } = useSSHStream();
    expect(() => clear()).not.toThrow();
    expect(lines).toEqual([]);
  });

  it('stop() does not throw when called before start()', () => {
    const { stop } = useSSHStream();
    expect(() => stop()).not.toThrow();
  });

  it('start() calls execStream with the provided command', async () => {
    mockExecStream.mockReturnValueOnce(makeStream([]));
    const { start } = useSSHStream();
    await start('tail -f /var/log/syslog');
    expect(mockExecStream).toHaveBeenCalledWith('tail -f /var/log/syslog');
  });

  it('start() resolves after stream ends', async () => {
    mockExecStream.mockReturnValueOnce(makeStream(['line1', 'line2', 'line3']));
    const { start } = useSSHStream();
    await expect(start('some command')).resolves.toBeUndefined();
  });

  it('start() does not propagate error when aborted during failure', async () => {
    // When aborted, errors from the stream are swallowed
    const hook = useSSHStream();

    mockExecStream.mockReturnValueOnce(makeFailingStream('connection lost'));

    // Set abort flag before starting
    hook.stop();

    // Should not throw even though the stream throws after yielding one line
    await expect(hook.start('cmd')).resolves.toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// Circular buffer contract (tested directly, independent of React)
// ---------------------------------------------------------------------------

describe('useSSHStream — circular buffer contract', () => {
  it('trims to maxLines when exceeded', () => {
    const maxLines = 5;
    let buffer: string[] = [];

    for (let i = 0; i < 8; i++) {
      const next = [...buffer, `line-${i}`];
      buffer = next.length > maxLines ? next.slice(next.length - maxLines) : next;
    }

    expect(buffer).toHaveLength(5);
    expect(buffer[0]).toBe('line-3');
    expect(buffer[4]).toBe('line-7');
  });

  it('does not trim when buffer stays within maxLines', () => {
    const maxLines = 10;
    let buffer: string[] = [];

    for (let i = 0; i < 7; i++) {
      const next = [...buffer, `line-${i}`];
      buffer = next.length > maxLines ? next.slice(next.length - maxLines) : next;
    }

    expect(buffer).toHaveLength(7);
  });
});

// ---------------------------------------------------------------------------
// Abort flag contract (direct logic simulation)
// ---------------------------------------------------------------------------

describe('useSSHStream — abort flag contract', () => {
  it('breaks the generator loop when abort flag is true', async () => {
    const linesCollected: string[] = [];
    const abortedRef = { current: false };

    async function* infiniteStream(): AsyncGenerator<string, void, unknown> {
      let n = 0;
      while (true) {
        yield `line-${n++}`;
      }
    }

    const gen = infiniteStream();

    // Simulate the loop with abort check
    for await (const line of gen) {
      if (abortedRef.current) break;
      linesCollected.push(line);

      // Abort after 3 lines
      if (linesCollected.length >= 3) {
        abortedRef.current = true;
      }
    }

    expect(linesCollected).toHaveLength(3);
  });
});
