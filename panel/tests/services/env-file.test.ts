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
  readEnvFile,
  getEnvValue,
  diffEnvFiles,
  parseEnvString,
} from '../../src/services/env-file.js';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('env-file service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---- readEnvFile() -------------------------------------------------------

  describe('readEnvFile()', () => {
    it('calls exec with cat on the correct env file path for dev', async () => {
      mockExec.mockResolvedValueOnce('APP_ENV=local\nAPP_KEY=base64:abc\n');

      const result = await readEnvFile('dev');

      expect(mockExec).toHaveBeenCalledWith(
        'cat /data/saturn/dev/source/.env',
      );
      expect(result).toContain('APP_ENV=local');
    });

    it('calls exec with cat on the correct env file path for production', async () => {
      mockExec.mockResolvedValueOnce('APP_ENV=production\n');

      await readEnvFile('production');

      expect(mockExec).toHaveBeenCalledWith(
        'cat /data/saturn/production/source/.env',
      );
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('No such file or directory'));

      await expect(readEnvFile('dev')).rejects.toThrow('No such file or directory');
    });
  });

  // ---- getEnvValue() -------------------------------------------------------

  describe('getEnvValue()', () => {
    it('builds a grep | cut command to extract the value', async () => {
      mockExec.mockResolvedValueOnce('production\n');

      const result = await getEnvValue('dev', 'APP_ENV');

      expect(mockExec).toHaveBeenCalledWith(
        "grep -E '^APP_ENV=' /data/saturn/dev/source/.env | cut -d'=' -f2-",
      );
      expect(result).toBe('production\n');
    });

    it('accepts lowercase keys', async () => {
      mockExec.mockResolvedValueOnce('value\n');

      await getEnvValue('dev', 'database_url');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining("'^database_url='"),
      );
    });

    it('accepts keys with numbers', async () => {
      mockExec.mockResolvedValueOnce('1234\n');

      await getEnvValue('staging', 'REDIS_PORT_6379');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining("'^REDIS_PORT_6379='"),
      );
    });

    it('throws for keys with spaces', async () => {
      await expect(getEnvValue('dev', 'APP ENV')).rejects.toThrow('Invalid env key');
    });

    it('throws for keys with special shell characters', async () => {
      await expect(getEnvValue('dev', 'APP$KEY')).rejects.toThrow('Invalid env key');
    });

    it('throws for keys with semicolons (command injection guard)', async () => {
      await expect(getEnvValue('dev', 'APP;rm -rf')).rejects.toThrow('Invalid env key');
    });

    it('throws for keys starting with a digit', async () => {
      await expect(getEnvValue('dev', '1APP_KEY')).rejects.toThrow('Invalid env key');
    });

    it('does NOT call exec when key validation fails', async () => {
      await expect(getEnvValue('dev', 'bad key')).rejects.toThrow();
      expect(mockExec).not.toHaveBeenCalled();
    });

    it('propagates errors from exec', async () => {
      mockExec.mockRejectedValueOnce(new Error('File not found'));

      await expect(getEnvValue('dev', 'APP_ENV')).rejects.toThrow('File not found');
    });
  });

  // ---- diffEnvFiles() ------------------------------------------------------

  describe('diffEnvFiles()', () => {
    it('calls exec with diff --side-by-side on both env files', async () => {
      mockExec.mockResolvedValueOnce('APP_ENV=local   | APP_ENV=production\n');

      const result = await diffEnvFiles('dev', 'production');

      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('diff --side-by-side'),
      );
      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/dev/source/.env'),
      );
      expect(mockExec).toHaveBeenCalledWith(
        expect.stringContaining('/data/saturn/production/source/.env'),
      );
      expect(result).toContain('APP_ENV');
    });

    it('returns "Files are identical" when diff output is empty', async () => {
      // When files are identical, diff returns exit code 0 and empty stdout.
      // The || true approach means exec resolves with empty string.
      mockExec.mockResolvedValueOnce('');

      const result = await diffEnvFiles('dev', 'staging');

      expect(result).toBe('Files are identical');
    });

    it('returns "Files are identical" when diff output is only whitespace', async () => {
      mockExec.mockResolvedValueOnce('   \n\t\n');

      const result = await diffEnvFiles('dev', 'staging');

      expect(result).toBe('Files are identical');
    });

    it('uses || true to prevent exit-code 1 from diff causing a throw', async () => {
      mockExec.mockResolvedValueOnce('APP_KEY=abc  | APP_KEY=xyz\n');

      await diffEnvFiles('dev', 'staging');

      const cmd: string = mockExec.mock.calls[0]![0] as string;
      expect(cmd).toContain('|| true');
    });

    it('propagates unexpected exec errors (exit 2 from diff = error)', async () => {
      mockExec.mockRejectedValueOnce(new Error('diff: file not found'));

      await expect(diffEnvFiles('dev', 'production')).rejects.toThrow('diff: file not found');
    });
  });

  // ---- parseEnvString() — pure function, no mocking needed -----------------

  describe('parseEnvString()', () => {
    it('parses a simple key=value pair', () => {
      const result = parseEnvString('APP_ENV=production');
      expect(result).toEqual({ APP_ENV: 'production' });
    });

    it('parses multiple key=value pairs', () => {
      const result = parseEnvString('A=1\nB=2\nC=3');
      expect(result).toEqual({ A: '1', B: '2', C: '3' });
    });

    it('skips blank lines', () => {
      const result = parseEnvString('A=1\n\nB=2');
      expect(result).toEqual({ A: '1', B: '2' });
    });

    it('skips comment lines starting with #', () => {
      const result = parseEnvString('# This is a comment\nA=1');
      expect(result).toEqual({ A: '1' });
    });

    it('skips lines without an = sign', () => {
      const result = parseEnvString('INVALID_LINE\nA=1');
      expect(result).toEqual({ A: '1' });
    });

    it('strips surrounding double quotes from values', () => {
      const result = parseEnvString('DB_PASSWORD="secret123"');
      expect(result).toEqual({ DB_PASSWORD: 'secret123' });
    });

    it('strips surrounding single quotes from values', () => {
      const result = parseEnvString("DB_PASSWORD='secret123'");
      expect(result).toEqual({ DB_PASSWORD: 'secret123' });
    });

    it('preserves = signs within values (cuts only on first =)', () => {
      const result = parseEnvString('DB_URL=postgres://user:pass@host/db?sslmode=require');
      expect(result).toEqual({
        DB_URL: 'postgres://user:pass@host/db?sslmode=require',
      });
    });

    it('preserves internal = in a quoted value', () => {
      const result = parseEnvString('APP_KEY="base64:abc==def"');
      expect(result).toEqual({ APP_KEY: 'base64:abc==def' });
    });

    it('returns an empty object for an empty string', () => {
      const result = parseEnvString('');
      expect(result).toEqual({});
    });

    it('returns an empty object for a string with only comments and blank lines', () => {
      const result = parseEnvString('# comment\n\n# another\n');
      expect(result).toEqual({});
    });

    it('trims whitespace around lines before parsing', () => {
      const result = parseEnvString('  APP_ENV=local  ');
      expect(result).toEqual({ APP_ENV: 'local' });
    });

    it('handles a realistic .env file', () => {
      const content = [
        '# Application',
        'APP_NAME=Saturn',
        'APP_ENV=production',
        'APP_KEY="base64:RjDlk5NUkpC8xEZqnH2m4ug=="',
        'APP_DEBUG=false',
        '',
        '# Database',
        'DB_CONNECTION=pgsql',
        'DB_HOST=saturn-db-production',
        'DB_PORT=5432',
        'DB_DATABASE=saturn',
        "DB_PASSWORD='super_secret'",
      ].join('\n');

      const result = parseEnvString(content);

      expect(result['APP_NAME']).toBe('Saturn');
      expect(result['APP_ENV']).toBe('production');
      expect(result['APP_KEY']).toBe('base64:RjDlk5NUkpC8xEZqnH2m4ug==');
      expect(result['APP_DEBUG']).toBe('false');
      expect(result['DB_CONNECTION']).toBe('pgsql');
      expect(result['DB_HOST']).toBe('saturn-db-production');
      expect(result['DB_PORT']).toBe('5432');
      expect(result['DB_PASSWORD']).toBe('super_secret');
      // Comments must not appear as keys
      expect(result['# Application']).toBeUndefined();
    });
  });
});
