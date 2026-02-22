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
  deploy,
  rollback,
  getDeployHistory,
  getGitLog,
  getCurrentBranch,
  getCurrentCommit,
} from '../../src/services/deploy.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Async generator factory — returns lines from the provided array
async function* fakeStream(lines: string[]): AsyncGenerator<string> {
  for (const line of lines) {
    yield line;
  }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('deploy service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- deploy() ------------------------------------------------------------

  describe('deploy()', () => {
    it('streams lines from execStream for deploy command', async () => {
      const lines = ['[1/3] Pulling image...', '[2/3] Starting containers...', '[3/3] Done.'];
      mockExecStream.mockReturnValueOnce(fakeStream(lines));

      const collected: string[] = [];
      for await (const line of deploy('dev')) {
        collected.push(line);
      }

      expect(collected).toEqual(lines);
    });

    it('calls execStream with the correct deploy script command for dev', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      // Drain the generator
      for await (const _ of deploy('dev')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/dev/source'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('SATURN_ENV=dev'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('deploy/scripts/deploy.sh'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('2>&1'),
      );
    });

    it('calls execStream with the correct deploy script command for production', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of deploy('production')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('SATURN_ENV=production'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/production/source'),
      );
    });

    it('propagates errors from execStream', async () => {
      async function* failingStream(): AsyncGenerator<string> {
        yield 'Starting...';
        throw new Error('SSH connection lost');
      }
      mockExecStream.mockReturnValueOnce(failingStream());

      await expect(async () => {
        for await (const _ of deploy('dev')) { /* drain */ }
      }).rejects.toThrow('SSH connection lost');
    });
  });

  // ---- rollback() ----------------------------------------------------------

  describe('rollback()', () => {
    it('streams lines from execStream for rollback command', async () => {
      const lines = ['Rolling back...', 'Done.'];
      mockExecStream.mockReturnValueOnce(fakeStream(lines));

      const collected: string[] = [];
      for await (const line of rollback('staging')) {
        collected.push(line);
      }

      expect(collected).toEqual(lines);
    });

    it('passes --rollback flag to the deploy script', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of rollback('dev')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('--rollback'),
      );
    });

    it('includes SATURN_ENV and source dir in the rollback command', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of rollback('staging')) { /* drain */ }

      const cmd: string = mockExecStream.mock.calls[0]![0] as string;
      expect(cmd).toContain('SATURN_ENV=staging');
      expect(cmd).toContain('/data/saturn/staging/source');
    });
  });

  // ---- getDeployHistory() --------------------------------------------------

  describe('getDeployHistory()', () => {
    it('calls exec with ls -lt on the backups directory', async () => {
      mockExec.mockResolvedValueOnce('total 4\n-rw-r--r-- 1 root root 0 Jan 1 00:00 backup.tar');

      await getDeployHistory('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/dev/source/deploy/backups/'),
      );
    });

    it('requests limit + 1 lines via head to account for the ls header', async () => {
      mockExec.mockResolvedValueOnce('');

      await getDeployHistory('dev', 5);

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('head -6'),
      );
    });

    it('uses default limit of 10 when not specified', async () => {
      mockExec.mockResolvedValueOnce('');

      await getDeployHistory('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('head -11'),
      );
    });

    it('uses 2>/dev/null to suppress errors when backup dir is missing', async () => {
      mockExec.mockResolvedValueOnce('');

      await getDeployHistory('production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('2>/dev/null'),
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('Permission denied'));

      await expect(getDeployHistory('dev')).rejects.toThrow('Permission denied');
    });
  });

  // ---- getGitLog() ---------------------------------------------------------

  describe('getGitLog()', () => {
    it('calls exec with git log --oneline and the correct limit', async () => {
      mockExec.mockResolvedValueOnce('f7e237f feat: something\n49d581a fix: something else\n');

      const result = await getGitLog('dev', 2);

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/dev/source && git log --oneline -2',
      );
      expect(result).toContain('f7e237f');
    });

    it('uses default limit of 10 when not specified', async () => {
      mockExec.mockResolvedValueOnce('');

      await getGitLog('staging');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('git log --oneline -10'),
      );
    });

    it('uses the correct source dir for the environment', async () => {
      mockExec.mockResolvedValueOnce('');

      await getGitLog('production', 5);

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/production/source && git log --oneline -5',
      );
    });
  });

  // ---- getCurrentBranch() --------------------------------------------------

  describe('getCurrentBranch()', () => {
    it('calls exec with git rev-parse --abbrev-ref HEAD', async () => {
      mockExec.mockResolvedValueOnce('dev\n');

      const result = await getCurrentBranch('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/dev/source && git rev-parse --abbrev-ref HEAD',
      );
      expect(result).toBe('dev\n');
    });

    it('works for all environments', async () => {
      mockExec.mockResolvedValueOnce('main\n');

      await getCurrentBranch('production');

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/production/source && git rev-parse --abbrev-ref HEAD',
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('not a git repository'));

      await expect(getCurrentBranch('dev')).rejects.toThrow('not a git repository');
    });
  });

  // ---- getCurrentCommit() --------------------------------------------------

  describe('getCurrentCommit()', () => {
    it('calls exec with git log --oneline -1', async () => {
      mockExec.mockResolvedValueOnce('f7e237f feat: auto-populated status page\n');

      const result = await getCurrentCommit('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/dev/source && git log --oneline -1',
      );
      expect(result).toContain('f7e237f');
    });

    it('uses the correct source dir for staging', async () => {
      mockExec.mockResolvedValueOnce('abc1234 fix: something\n');

      await getCurrentCommit('staging');

      expect(mockExec).toHaveBeenCalledWith(
        'cd /data/saturn/staging/source && git log --oneline -1',
      );
    });
  });
});
