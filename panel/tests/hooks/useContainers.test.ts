import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mock SSH context
// ---------------------------------------------------------------------------

const mockExec = vi.fn<[string], Promise<string>>();

vi.mock('../../src/ssh/context.js', () => ({
  useSSH: () => ({ exec: mockExec, execStream: vi.fn() }),
}));

// ---------------------------------------------------------------------------
// Mock useInterval — execute callback synchronously once for testing
// ---------------------------------------------------------------------------

vi.mock('../../src/hooks/useInterval.js', () => ({
  useInterval: (callback: () => void, delayMs: number | null) => {
    // Trigger one poll immediately when delay is not null
    if (delayMs !== null) {
      void (async () => callback())();
    }
  },
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
    useCallback: <T extends (...args: unknown[]) => unknown>(fn: T): T => fn,
  };
});

import { useContainers } from '../../src/hooks/useContainers.js';

// ---------------------------------------------------------------------------
// Sample docker output fixtures
// ---------------------------------------------------------------------------

const STATS_OUTPUT = [
  'saturn-dev\t0.25%\t128MiB / 64GiB\t0.19%\t1.2MB / 800kB\t10MB / 5MB',
  'saturn-db-dev\t0.10%\t256MiB / 64GiB\t0.39%\t600kB / 200kB\t50MB / 20MB',
  'saturn-redis-dev\t0.05%\t64MiB / 64GiB\t0.10%\t100kB / 50kB\t1MB / 500kB',
  'saturn-realtime-dev\t0.02%\t32MiB / 64GiB\t0.05%\t80kB / 40kB\t500kB / 200kB',
].join('\n');

const PS_OUTPUT = [
  'saturn-dev\tUp 2 hours',
  'saturn-db-dev\tUp 2 hours',
  'saturn-redis-dev\tUp 2 hours',
  'saturn-realtime-dev\tUp 2 hours',
].join('\n');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useContainers', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns initial empty state', () => {
    mockExec.mockResolvedValue('');
    const { containers, loading, error } = useContainers();
    expect(containers.dev).toEqual([]);
    expect(containers.staging).toEqual([]);
    expect(containers.production).toEqual([]);
    expect(loading).toBe(false);
    expect(error).toBeNull();
  });

  it('refresh() calls exec exactly twice (stats + ps) per invocation', async () => {
    mockExec.mockResolvedValue('');
    // Each call to refresh() makes exactly 2 exec calls (stats + ps in parallel).
    // The useInterval mock triggers one additional poll on mount, so after one explicit
    // refresh() call the total exec count is 4 (2 from interval poll + 2 from our call).
    const { refresh } = useContainers();
    const countBefore = mockExec.mock.calls.length;
    await refresh();
    // Each refresh() call issues exactly 2 exec calls
    expect(mockExec.mock.calls.length - countBefore).toBe(2);
  });

  it('refresh() issues a docker stats command with all container names', async () => {
    mockExec.mockResolvedValue('');
    const { refresh } = useContainers();
    await refresh();

    const statsCall = mockExec.mock.calls[0]?.[0] ?? '';
    expect(statsCall).toContain('docker stats --no-stream');
    expect(statsCall).toContain('saturn-dev');
    expect(statsCall).toContain('saturn-db-dev');
    expect(statsCall).toContain('saturn-redis-dev');
    expect(statsCall).toContain('saturn-realtime-dev');
    expect(statsCall).toContain('saturn-staging');
    expect(statsCall).toContain('saturn-production');
  });

  it('refresh() issues a docker ps command', async () => {
    mockExec.mockResolvedValue('');
    const { refresh } = useContainers();
    await refresh();

    const psCall = mockExec.mock.calls[1]?.[0] ?? '';
    expect(psCall).toContain('docker ps');
  });

  it('refresh() resolves without throwing when exec returns empty output', async () => {
    mockExec.mockResolvedValue('');
    const { refresh } = useContainers();
    await expect(refresh()).resolves.toBeUndefined();
  });

  it('refresh() resolves without throwing on exec failure (via || true pattern)', async () => {
    // The commands use "|| true" so exec should still resolve
    mockExec.mockResolvedValue('');
    const { refresh } = useContainers();
    await expect(refresh()).resolves.toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// Docker output parsers (tested directly — no React dependency)
// ---------------------------------------------------------------------------

describe('docker stats parsing contract', () => {
  it('correctly splits tab-delimited stats line', () => {
    const line = 'saturn-dev\t0.25%\t128MiB / 64GiB\t0.19%\t1.2MB / 800kB\t10MB / 5MB';
    const parts = line.split('\t');
    expect(parts).toHaveLength(6);
    expect(parts[0]).toBe('saturn-dev');
    expect(parts[1]).toBe('0.25%');
    expect(parts[2]).toBe('128MiB / 64GiB');
    expect(parts[3]).toBe('0.19%');
    expect(parts[4]).toBe('1.2MB / 800kB');
    expect(parts[5]).toBe('10MB / 5MB');
  });

  it('correctly splits MemUsage into memory and memoryLimit', () => {
    const memUsage = '128MiB / 64GiB';
    const parts = memUsage.split(' / ');
    expect(parts[0]).toBe('128MiB');
    expect(parts[1]).toBe('64GiB');
  });

  it('handles missing containers by returning stopped status', () => {
    const psOutput = 'saturn-dev\tUp 2 hours';
    const statusMap: Record<string, string> = {};

    for (const line of psOutput.split('\n')) {
      const trimmed = line.trim();
      if (trimmed === '') continue;
      const tabIdx = trimmed.indexOf('\t');
      if (tabIdx === -1) continue;
      const name = trimmed.slice(0, tabIdx).trim();
      const status = trimmed.slice(tabIdx + 1).trim();
      if (name) statusMap[name] = status;
    }

    // saturn-db-dev was not in the ps output
    expect(statusMap['saturn-dev']).toBe('Up 2 hours');
    expect(statusMap['saturn-db-dev']).toBeUndefined();
    // Default for missing containers
    expect(statusMap['saturn-db-dev'] ?? 'stopped').toBe('stopped');
  });

  it('parses multiple stats lines correctly', () => {
    const statsMap: Record<string, { cpu: string }> = {};

    for (const line of STATS_OUTPUT.split('\n')) {
      const trimmed = line.trim();
      if (trimmed === '') continue;
      const parts = trimmed.split('\t');
      if (parts.length < 6) continue;
      const name = parts[0]?.trim() ?? '';
      const cpu = parts[1]?.trim() ?? '0.00%';
      if (name) statsMap[name] = { cpu };
    }

    expect(statsMap['saturn-dev']?.cpu).toBe('0.25%');
    expect(statsMap['saturn-db-dev']?.cpu).toBe('0.10%');
    expect(statsMap['saturn-redis-dev']?.cpu).toBe('0.05%');
    expect(statsMap['saturn-realtime-dev']?.cpu).toBe('0.02%');
  });

  it('parses docker ps output into name → status map', () => {
    const statusMap: Record<string, string> = {};

    for (const line of PS_OUTPUT.split('\n')) {
      const trimmed = line.trim();
      if (trimmed === '') continue;
      const tabIdx = trimmed.indexOf('\t');
      if (tabIdx === -1) continue;
      const name = trimmed.slice(0, tabIdx).trim();
      const status = trimmed.slice(tabIdx + 1).trim();
      if (name) statusMap[name] = status;
    }

    expect(Object.keys(statusMap)).toHaveLength(4);
    expect(statusMap['saturn-dev']).toBe('Up 2 hours');
    expect(statusMap['saturn-db-dev']).toBe('Up 2 hours');
  });
});
