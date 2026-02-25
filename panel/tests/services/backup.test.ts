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
  createBackup,
  listBackups,
  restoreBackup,
  getBackupSize,
} from '../../src/services/backup.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function* fakeStream(lines: string[]): AsyncGenerator<string> {
  for (const line of lines) {
    yield line;
  }
}

async function* failingStream(message: string): AsyncGenerator<string> {
  yield 'Starting restore...';
  throw new Error(message);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('backup service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- createBackup() ------------------------------------------------------

  describe('createBackup()', () => {
    it('runs pg_dump inside the db container and writes to backup dir', async () => {
      mockExec.mockResolvedValueOnce('Backup created: backup_2026-01-01T00-00-00-000Z.sql');

      const result = await createBackup('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('docker exec saturn-db-dev pg_dump'),
      );
      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('-U saturn -d saturn'),
      );
      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/dev/backups/'),
      );
      expect(result).toContain('Backup created');
    });

    it('uses the correct db container name for staging', async () => {
      mockExec.mockResolvedValueOnce('Backup created: x.sql');

      await createBackup('staging');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('saturn-db-staging'),
      );
    });

    it('uses the correct db container name for production', async () => {
      mockExec.mockResolvedValueOnce('Backup created: x.sql');

      await createBackup('production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('saturn-db-production'),
      );
    });

    it('generates a filename starting with backup_', async () => {
      mockExec.mockResolvedValueOnce('ok');

      await createBackup('dev');

      const cmd: string = mockExec.mock.calls[0]![0] as string;
      // Filename in the command should match backup_<timestamp>.sql
      expect(cmd).toMatch(/backup_[\d\-T]+Z?\.sql/);
    });

    it('writes to the dev backup directory', async () => {
      mockExec.mockResolvedValueOnce('ok');

      await createBackup('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/dev/backups/'),
      );
    });

    it('uses && echo to confirm success (and only succeeds when pg_dump does)', async () => {
      mockExec.mockResolvedValueOnce('');

      await createBackup('dev');

      const cmd: string = mockExec.mock.calls[0]![0] as string;
      expect(cmd).toContain('&&');
      expect(cmd).toContain('echo');
    });

    it('propagates errors from exec (e.g. pg_dump fails)', async () => {
      mockExec.mockRejectedValueOnce(new Error('pg_dump: connection refused'));

      await expect(createBackup('dev')).rejects.toThrow('pg_dump: connection refused');
    });
  });

  // ---- listBackups() -------------------------------------------------------

  describe('listBackups()', () => {
    it('calls exec with ls -lth on the correct backup dir', async () => {
      mockExec.mockResolvedValueOnce(
        '-rw-r--r-- 1 root root 1.2M Jan 1 00:00 backup_2026.sql\n',
      );

      const result = await listBackups('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('ls -lth /data/saturn/dev/backups/*.sql'),
      );
      expect(result).toContain('backup_2026.sql');
    });

    it('returns "No backups found" fallback when directory is empty or missing', async () => {
      mockExec.mockResolvedValueOnce('No backups found');

      const result = await listBackups('staging');

      expect(result).toBe('No backups found');
    });

    it('suppresses errors via 2>/dev/null || echo fallback', async () => {
      mockExec.mockResolvedValueOnce('');

      await listBackups('production');

      const cmd: string = mockExec.mock.calls[0]![0] as string;
      expect(cmd).toContain('2>/dev/null');
      expect(cmd).toContain('|| echo');
    });

    it('uses the production backup directory path', async () => {
      mockExec.mockResolvedValueOnce('');

      await listBackups('production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/production/backups/'),
      );
    });
  });

  // ---- restoreBackup() -----------------------------------------------------

  describe('restoreBackup()', () => {
    it('streams psql output for a valid backup filename', async () => {
      const lines = ['BEGIN', 'INSERT 0 100', 'COMMIT'];
      mockExecStream.mockReturnValueOnce(fakeStream(lines));

      const collected: string[] = [];
      for await (const line of restoreBackup('dev', 'backup_2026-01-01.sql')) {
        collected.push(line);
      }

      expect(collected).toEqual(lines);
    });

    it('builds a cat | docker exec -i psql command', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of restoreBackup('dev', 'backup_2026-01-01.sql')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('cat /data/saturn/dev/backups/backup_2026-01-01.sql'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('docker exec -i saturn-db-dev psql'),
      );
      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('-U saturn -d saturn'),
      );
    });

    it('merges stderr via 2>&1 in the restore command', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of restoreBackup('dev', 'backup_2026-01-01.sql')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('2>&1'),
      );
    });

    it('uses the correct db container for staging', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      for await (const _ of restoreBackup('staging', 'backup_test.sql')) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalledWith(
        expect.stringContaining('saturn-db-staging'),
      );
    });

    it('throws immediately for filenames with path traversal (../)', async () => {
      await expect(async () => {
        for await (const _ of restoreBackup('dev', '../etc/passwd')) { /* drain */ }
      }).rejects.toThrow('Invalid backup filename');
    });

    it('throws immediately for filenames with slashes', async () => {
      await expect(async () => {
        for await (const _ of restoreBackup('dev', 'subdir/backup.sql')) { /* drain */ }
      }).rejects.toThrow('Invalid backup filename');
    });

    it('throws immediately for filenames that do not end in .sql', async () => {
      await expect(async () => {
        for await (const _ of restoreBackup('dev', 'backup.tar.gz')) { /* drain */ }
      }).rejects.toThrow('Invalid backup filename');
    });

    it('throws immediately for filenames with shell metacharacters', async () => {
      await expect(async () => {
        for await (const _ of restoreBackup('dev', 'backup;rm -rf /.sql')) { /* drain */ }
      }).rejects.toThrow('Invalid backup filename');
    });

    it('does NOT call execStream when filename validation fails', async () => {
      await expect(async () => {
        for await (const _ of restoreBackup('dev', '../bad')) { /* drain */ }
      }).rejects.toThrow();

      expect(mockExecStream).not.toHaveBeenCalled();
    });

    it('accepts filenames with hyphens, underscores, and dots', async () => {
      mockExecStream.mockReturnValueOnce(fakeStream([]));

      // Should not throw
      const gen = restoreBackup('dev', 'backup_2026-01-01T00-00-00-000Z.sql');
      for await (const _ of gen) { /* drain */ }

      expect(mockExecStream).toHaveBeenCalled();
    });

    it('propagates streaming errors from execStream', async () => {
      mockExecStream.mockReturnValueOnce(failingStream('psql: connection error'));

      await expect(async () => {
        for await (const _ of restoreBackup('dev', 'backup_2026.sql')) { /* drain */ }
      }).rejects.toThrow('psql: connection error');
    });
  });

  // ---- getBackupSize() -----------------------------------------------------

  describe('getBackupSize()', () => {
    it('calls exec with du -sh on the backup directory', async () => {
      mockExec.mockResolvedValueOnce('4.2G\t/data/saturn/dev/backups\n');

      const result = await getBackupSize('dev');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('du -sh /data/saturn/dev/backups'),
      );
      expect(result).toContain('4.2G');
    });

    it('falls back to "0B" when directory does not exist', async () => {
      mockExec.mockResolvedValueOnce('0B');

      const result = await getBackupSize('staging');

      expect(result).toBe('0B');
    });

    it('uses 2>/dev/null || echo "0B" to handle missing directory gracefully', async () => {
      mockExec.mockResolvedValueOnce('0B');

      await getBackupSize('production');

      const cmd: string = mockExec.mock.calls[0]![0] as string;
      expect(cmd).toContain('2>/dev/null');
      expect(cmd).toContain('|| echo');
    });

    it('uses the correct backup dir for production', async () => {
      mockExec.mockResolvedValueOnce('1.1G\t/data/saturn/production/backups\n');

      await getBackupSize('production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/production/backups'),
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('SSH timeout'));

      await expect(getBackupSize('dev')).rejects.toThrow('SSH timeout');
    });
  });
});
