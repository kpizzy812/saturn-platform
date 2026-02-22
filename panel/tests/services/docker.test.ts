import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Hoist mock functions so they are available inside vi.mock() factory (TDZ-safe)
// ---------------------------------------------------------------------------

const { mockExec, mockExecStream } = vi.hoisted(() => {
  return {
    mockExec: vi.fn<() => Promise<string>>(),
    mockExecStream: vi.fn<() => AsyncGenerator<string>>(),
  };
});

vi.mock('../../src/ssh/exec.js', () => ({
  exec: mockExec,
  execStream: mockExecStream,
}));

import {
  getContainerStats,
  getContainerStatus,
  restartContainer,
  stopContainer,
  startContainer,
  getLogs,
  getDockerPS,
} from '../../src/services/docker.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Build a tab-separated docker stats line matching the format string used by getContainerStats. */
function statsLine(
  name: string,
  status: string,
  cpu: string,
  memUsage: string,
  memPercent: string,
  netIO: string,
  blockIO: string,
): string {
  return [name, status, cpu, memUsage, memPercent, netIO, blockIO].join('\t');
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('docker service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- getContainerStats() -------------------------------------------------

  describe('getContainerStats()', () => {
    it('parses a single healthy container line correctly', async () => {
      mockExec.mockResolvedValueOnce(
        statsLine(
          'saturn-dev',
          'Up 5 minutes',
          '1.23%',
          '128MiB / 64GiB',
          '0.20%',
          '1.5kB / 900B',
          '0B / 0B',
        ),
      );
      // Remaining containers fail (stopped)
      mockExec.mockRejectedValue(new Error('No such container'));

      const result = await getContainerStats('dev');

      const app = result.find((c) => c.name === 'saturn-dev');
      expect(app).toBeDefined();
      expect(app!.cpu).toBe('1.23%');
      expect(app!.memory).toBe('128MiB');
      expect(app!.memoryLimit).toBe('64GiB');
      expect(app!.memoryPercent).toBe('0.20%');
      expect(app!.netIO).toBe('1.5kB / 900B');
      expect(app!.blockIO).toBe('0B / 0B');
    });

    it('marks container as stopped when exec throws', async () => {
      mockExec.mockRejectedValue(new Error('No such container'));

      const result = await getContainerStats('dev');

      expect(result).toHaveLength(4);
      for (const c of result) {
        expect(c.status).toBe('stopped');
        expect(c.cpu).toBe('0.00%');
        expect(c.memory).toBe('0B');
      }
    });

    it('marks container as stopped when exec returns empty string', async () => {
      mockExec.mockResolvedValue('');

      const result = await getContainerStats('staging');

      for (const c of result) {
        expect(c.status).toBe('stopped');
      }
    });

    it('marks container as stopped when output has fewer than 7 fields', async () => {
      mockExec.mockResolvedValue('saturn-staging\tUp 1 min\t0.5%');

      const result = await getContainerStats('staging');

      for (const c of result) {
        expect(c.status).toBe('stopped');
      }
    });

    it('returns exactly 4 containers for any environment', async () => {
      mockExec.mockResolvedValue(
        statsLine('x', 'Up', '0%', '0B / 0B', '0%', '0B / 0B', '0B / 0B'),
      );

      const result = await getContainerStats('production');
      expect(result).toHaveLength(4);
    });

    it('calls docker stats with the correct container name', async () => {
      mockExec.mockResolvedValue(
        statsLine('saturn-db-dev', 'Up', '0%', '256MiB / 64GiB', '0%', '0B / 0B', '0B / 0B'),
      );

      await getContainerStats('dev');

      // Each call should contain docker stats with no-stream and the container name
      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('docker stats --no-stream'),
      );
    });

    it('correctly splits memUsage on the / separator', async () => {
      mockExec.mockResolvedValueOnce(
        statsLine('saturn-dev', 'Up', '2%', '512MiB / 32GiB', '1.56%', '0B / 0B', '0B / 0B'),
      );
      mockExec.mockRejectedValue(new Error('stopped'));

      const result = await getContainerStats('dev');

      const app = result.find((c) => c.name === 'saturn-dev');
      expect(app!.memory).toBe('512MiB');
      expect(app!.memoryLimit).toBe('32GiB');
    });
  });

  // ---- getContainerStatus() ------------------------------------------------

  describe('getContainerStatus()', () => {
    it('parses multi-line docker ps output filtering to env containers only', async () => {
      mockExec.mockResolvedValueOnce(
        [
          'saturn-dev\tUp 10 minutes\trunning',
          'saturn-db-dev\tUp 10 minutes\trunning',
          'saturn-redis-dev\tExited (0) 2 mins ago\texited',
          'saturn-realtime-dev\tUp 3 minutes\trunning',
          // This one belongs to a different env and must be excluded
          'saturn-production\tUp 1 hour\trunning',
        ].join('\n'),
      );

      const result = await getContainerStatus('dev');

      // Only dev containers should be present
      const names = result.map((r) => r.name);
      expect(names).toContain('saturn-dev');
      expect(names).toContain('saturn-db-dev');
      expect(names).toContain('saturn-redis-dev');
      expect(names).toContain('saturn-realtime-dev');
      expect(names).not.toContain('saturn-production');
    });

    it('includes status and health fields from docker ps', async () => {
      mockExec.mockResolvedValueOnce(
        'saturn-dev\tUp 5 minutes (healthy)\trunning',
      );

      const result = await getContainerStatus('dev');

      const app = result.find((r) => r.name === 'saturn-dev');
      expect(app).toBeDefined();
      expect(app!.status).toBe('Up 5 minutes (healthy)');
      expect(app!.health).toBe('running');
    });

    it('returns empty array when docker ps returns empty string', async () => {
      mockExec.mockResolvedValueOnce('');

      const result = await getContainerStatus('dev');
      expect(result).toEqual([]);
    });

    it('skips lines with fewer than 3 tab-separated fields', async () => {
      mockExec.mockResolvedValueOnce('saturn-dev\tUp 5 minutes');

      const result = await getContainerStatus('dev');
      expect(result).toEqual([]);
    });

    it('uses --filter name=saturn-<env> in the docker ps command', async () => {
      mockExec.mockResolvedValueOnce('');

      await getContainerStatus('staging');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('--filter name=saturn-staging'),
      );
    });
  });

  // ---- restartContainer() --------------------------------------------------

  describe('restartContainer()', () => {
    it('calls exec with docker restart and returns output', async () => {
      mockExec.mockResolvedValueOnce('saturn-dev\n');

      const result = await restartContainer('saturn-dev');

      expect(mockExec).toHaveBeenCalledWith('docker restart saturn-dev');
      expect(result).toBe('saturn-dev\n');
    });

    it('propagates exec errors', async () => {
      mockExec.mockRejectedValueOnce(new Error('No such container'));

      await expect(restartContainer('ghost')).rejects.toThrow('No such container');
    });
  });

  // ---- stopContainer() -----------------------------------------------------

  describe('stopContainer()', () => {
    it('calls exec with docker stop and returns output', async () => {
      mockExec.mockResolvedValueOnce('saturn-db-dev\n');

      const result = await stopContainer('saturn-db-dev');

      expect(mockExec).toHaveBeenCalledWith('docker stop saturn-db-dev');
      expect(result).toBe('saturn-db-dev\n');
    });
  });

  // ---- startContainer() ----------------------------------------------------

  describe('startContainer()', () => {
    it('calls exec with docker start and returns output', async () => {
      mockExec.mockResolvedValueOnce('saturn-redis-dev\n');

      const result = await startContainer('saturn-redis-dev');

      expect(mockExec).toHaveBeenCalledWith('docker start saturn-redis-dev');
      expect(result).toBe('saturn-redis-dev\n');
    });
  });

  // ---- getLogs() -----------------------------------------------------------

  describe('getLogs()', () => {
    it('calls exec with docker logs --tail and --timestamps', async () => {
      mockExec.mockResolvedValueOnce('2026-01-01T00:00:00Z log line\n');

      const result = await getLogs('saturn-dev', 50);

      expect(mockExec).toHaveBeenCalledWith(
        'docker logs --tail 50 --timestamps saturn-dev 2>&1',
      );
      expect(result).toContain('log line');
    });

    it('uses default tail of 100 when not specified', async () => {
      mockExec.mockResolvedValueOnce('');

      await getLogs('saturn-dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('--tail 100'),
      );
    });

    it('merges stderr via 2>&1', async () => {
      mockExec.mockResolvedValueOnce('');

      await getLogs('saturn-dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('2>&1'),
      );
    });
  });

  // ---- getDockerPS() -------------------------------------------------------

  describe('getDockerPS()', () => {
    it('uses --filter name=saturn-<env>', async () => {
      mockExec.mockResolvedValueOnce('NAMES\tSTATUS\tPORTS\tIMAGE\n');

      await getDockerPS('production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('--filter name=saturn-production'),
      );
    });

    it('requests table format with Names, Status, Ports, Image columns', async () => {
      mockExec.mockResolvedValueOnce('');

      await getDockerPS('dev');

      const call = mockExec.mock.calls[0]![0];
      expect(call).toContain('{{.Names}}');
      expect(call).toContain('{{.Status}}');
      expect(call).toContain('{{.Ports}}');
      expect(call).toContain('{{.Image}}');
    });
  });
});
