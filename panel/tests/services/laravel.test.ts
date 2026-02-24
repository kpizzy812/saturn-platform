import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Hoist mock functions so they are available inside vi.mock() factory (TDZ-safe)
// ---------------------------------------------------------------------------

const { mockExec } = vi.hoisted(() => {
  return {
    mockExec: vi.fn<() => Promise<string>>(),
  };
});

vi.mock('../../src/ssh/exec.js', () => ({
  exec: mockExec,
}));

import {
  migrate,
  migrateFresh,
  seed,
  clearCache,
  rebuildCache,
  migrationStatus,
  runCommand,
} from '../../src/services/laravel.js';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('laravel service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- artisan() helper (via each exported function) ----------------------

  describe('migrate()', () => {
    it('runs artisan migrate --force inside the app container', async () => {
      mockExec.mockResolvedValueOnce('Nothing to migrate.');

      const result = await migrate('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-dev php artisan migrate --force',
      );
      expect(result).toBe('Nothing to migrate.');
    });

    it('uses the correct container name for staging', async () => {
      mockExec.mockResolvedValueOnce('');

      await migrate('staging');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-staging php artisan migrate --force',
      );
    });

    it('uses the correct container name for production', async () => {
      mockExec.mockResolvedValueOnce('');

      await migrate('production');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-production php artisan migrate --force',
      );
    });

    it('propagates exec errors', async () => {
      mockExec.mockRejectedValueOnce(new Error('Container not found'));

      await expect(migrate('dev')).rejects.toThrow('Container not found');
    });
  });

  // ---- migrateFresh() ------------------------------------------------------

  describe('migrateFresh()', () => {
    it('runs artisan migrate:fresh --force inside the app container', async () => {
      mockExec.mockResolvedValueOnce('Database cleared. Running migrations.');

      const result = await migrateFresh('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-dev php artisan migrate:fresh --force',
      );
      expect(result).toContain('migrations');
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('Connection refused'));

      await expect(migrateFresh('staging')).rejects.toThrow('Connection refused');
    });
  });

  // ---- seed() --------------------------------------------------------------

  describe('seed()', () => {
    it('defaults to ProductionSeeder when no seeder is provided', async () => {
      mockExec.mockResolvedValueOnce('Seeding complete.');

      await seed('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-dev php artisan db:seed --class=ProductionSeeder --force',
      );
    });

    it('uses a custom seeder class when provided', async () => {
      mockExec.mockResolvedValueOnce('Done.');

      await seed('staging', 'DatabaseSeeder');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-staging php artisan db:seed --class=DatabaseSeeder --force',
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('Class not found'));

      await expect(seed('dev', 'MissingSeeder')).rejects.toThrow('Class not found');
    });
  });

  // ---- clearCache() --------------------------------------------------------

  describe('clearCache()', () => {
    it('runs all four cache:clear commands in sequence', async () => {
      mockExec
        .mockResolvedValueOnce('Cache cleared.')
        .mockResolvedValueOnce('Configuration cache cleared.')
        .mockResolvedValueOnce('Route cache cleared.')
        .mockResolvedValueOnce('Compiled views cleared.');

      const result = await clearCache('dev');

      expect(mockExec).toHaveBeenCalledTimes(4);
      expect(mockExec).toHaveBeenNthCalledWith(
        1,
        'docker exec saturn-dev php artisan cache:clear',
      );
      expect(mockExec).toHaveBeenNthCalledWith(
        2,
        'docker exec saturn-dev php artisan config:clear',
      );
      expect(mockExec).toHaveBeenNthCalledWith(
        3,
        'docker exec saturn-dev php artisan route:clear',
      );
      expect(mockExec).toHaveBeenNthCalledWith(
        4,
        'docker exec saturn-dev php artisan view:clear',
      );

      // All results should be joined with newlines
      expect(result).toContain('Cache cleared.');
      expect(result).toContain('Configuration cache cleared.');
    });

    it('joins outputs from all four commands with newlines', async () => {
      mockExec
        .mockResolvedValueOnce('A')
        .mockResolvedValueOnce('B')
        .mockResolvedValueOnce('C')
        .mockResolvedValueOnce('D');

      const result = await clearCache('production');

      expect(result).toBe('A\nB\nC\nD');
    });

    it('propagates errors if any command fails', async () => {
      mockExec
        .mockResolvedValueOnce('Cache cleared.')
        .mockRejectedValueOnce(new Error('config:clear failed'));

      await expect(clearCache('dev')).rejects.toThrow('config:clear failed');
    });

    it('uses the production container name for production env', async () => {
      mockExec.mockResolvedValue('ok');

      await clearCache('production');

      for (const call of mockExec.mock.calls) {
        expect(call[0]).toContain('saturn-production');
      }
    });
  });

  // ---- rebuildCache() ------------------------------------------------------

  describe('rebuildCache()', () => {
    it('runs config:cache, route:cache, and view:cache in sequence', async () => {
      mockExec
        .mockResolvedValueOnce('Configuration cached.')
        .mockResolvedValueOnce('Routes cached.')
        .mockResolvedValueOnce('Blade templates cached.');

      await rebuildCache('dev');

      expect(mockExec).toHaveBeenCalledTimes(3);
      expect(mockExec).toHaveBeenNthCalledWith(
        1,
        'docker exec saturn-dev php artisan config:cache',
      );
      expect(mockExec).toHaveBeenNthCalledWith(
        2,
        'docker exec saturn-dev php artisan route:cache',
      );
      expect(mockExec).toHaveBeenNthCalledWith(
        3,
        'docker exec saturn-dev php artisan view:cache',
      );
    });

    it('joins outputs from all three commands with newlines', async () => {
      mockExec
        .mockResolvedValueOnce('X')
        .mockResolvedValueOnce('Y')
        .mockResolvedValueOnce('Z');

      const result = await rebuildCache('staging');
      expect(result).toBe('X\nY\nZ');
    });

    it('propagates errors if any command fails', async () => {
      mockExec
        .mockResolvedValueOnce('ok')
        .mockRejectedValueOnce(new Error('route:cache failed'));

      await expect(rebuildCache('dev')).rejects.toThrow('route:cache failed');
    });
  });

  // ---- migrationStatus() ---------------------------------------------------

  describe('migrationStatus()', () => {
    it('runs artisan migrate:status and returns output', async () => {
      const statusOutput = '+--------+------+--------------------+-------+\n...';
      mockExec.mockResolvedValueOnce(statusOutput);

      const result = await migrationStatus('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-dev php artisan migrate:status',
      );
      expect(result).toBe(statusOutput);
    });
  });

  // ---- runCommand() --------------------------------------------------------

  describe('runCommand()', () => {
    it('passes arbitrary commands to artisan', async () => {
      mockExec.mockResolvedValueOnce('Broadcasting restarted.');

      const result = await runCommand('dev', 'queue:restart');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-dev php artisan queue:restart',
      );
      expect(result).toBe('Broadcasting restarted.');
    });

    it('passes commands with arguments verbatim', async () => {
      mockExec.mockResolvedValueOnce('');

      await runCommand('staging', 'tinker --execute="echo 1;"');

      expect(mockExec).toHaveBeenCalledWith(
        'docker exec saturn-staging php artisan tinker --execute="echo 1;"',
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('artisan failed'));

      await expect(runCommand('dev', 'bad:command')).rejects.toThrow('artisan failed');
    });
  });
});
