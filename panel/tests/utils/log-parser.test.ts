import { describe, it, expect, beforeEach } from 'vitest';
import {
  parseDockerLog,
  detectLevel,
  parseTimestamp,
  resetIdCounter,
} from '../../src/utils/log-parser.js';

beforeEach(() => {
  resetIdCounter();
});

// ---------------------------------------------------------------------------
// detectLevel
// ---------------------------------------------------------------------------
describe('detectLevel', () => {
  it('returns info by default', () => {
    expect(detectLevel('Server started successfully')).toBe('info');
  });

  it('detects ERROR keyword (uppercase)', () => {
    expect(detectLevel('ERROR: something went wrong')).toBe('error');
  });

  it('detects error keyword (lowercase)', () => {
    expect(detectLevel('An error occurred')).toBe('error');
  });

  it('detects CRITICAL keyword', () => {
    expect(detectLevel('CRITICAL: disk full')).toBe('error');
  });

  it('detects EXCEPTION keyword', () => {
    expect(detectLevel('Unhandled EXCEPTION in handler')).toBe('error');
  });

  it('detects WARNING keyword', () => {
    expect(detectLevel('WARNING: deprecated call')).toBe('warning');
  });

  it('detects WARN keyword', () => {
    expect(detectLevel('WARN high memory usage')).toBe('warning');
  });

  it('detects DEBUG keyword', () => {
    expect(detectLevel('DEBUG queue dispatched')).toBe('debug');
  });

  it('handles Laravel production.ERROR format', () => {
    expect(detectLevel('[2026-02-22 10:30:45] production.ERROR: SQLSTATE[23000] ...')).toBe('error');
  });

  it('handles Laravel local.WARNING format', () => {
    expect(detectLevel('[2026-02-22 10:30:45] local.WARNING: Deprecated usage')).toBe('warning');
  });

  it('handles Laravel staging.DEBUG format', () => {
    expect(detectLevel('[2026-02-22 10:30:45] staging.DEBUG: query took 50ms')).toBe('debug');
  });

  it('handles Laravel production.INFO format', () => {
    expect(detectLevel('[2026-02-22 10:30:45] production.INFO: Scheduled job done')).toBe('info');
  });

  it('handles Laravel production.NOTICE format', () => {
    expect(detectLevel('[2026-02-22 10:30:45] production.NOTICE: Notice message')).toBe('info');
  });

  it('is case-insensitive for generic keywords', () => {
    expect(detectLevel('warn: low disk space')).toBe('warning');
    expect(detectLevel('debug mode active')).toBe('debug');
  });
});

// ---------------------------------------------------------------------------
// parseTimestamp
// ---------------------------------------------------------------------------
describe('parseTimestamp', () => {
  it('parses Docker ISO timestamp with nanoseconds', () => {
    const line = '2026-02-22T10:30:45.123456789Z Started app container';
    const result = parseTimestamp(line);
    // Should produce a valid ISO string
    expect(result.timestamp).toBe(new Date('2026-02-22T10:30:45.123456789Z').toISOString());
    expect(result.message).toBe('Started app container');
  });

  it('parses Docker ISO timestamp with zero nanoseconds', () => {
    const line = '2026-02-22T00:00:00.000000000Z boot message';
    const result = parseTimestamp(line);
    expect(result.timestamp).toBe('2026-02-22T00:00:00.000Z');
    expect(result.message).toBe('boot message');
  });

  it('parses Laravel timestamp format', () => {
    const line = '[2026-02-22 10:30:45] production.ERROR: DB connection failed';
    const result = parseTimestamp(line);
    // Laravel time parsed as local ISO
    expect(result.timestamp).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/);
    expect(result.message).toBe('production.ERROR: DB connection failed');
  });

  it('falls back to current time for plain lines', () => {
    const before = Date.now();
    const result = parseTimestamp('plain log line without timestamp');
    const after = Date.now();
    const ts = new Date(result.timestamp).getTime();
    expect(ts).toBeGreaterThanOrEqual(before);
    expect(ts).toBeLessThanOrEqual(after);
    expect(result.message).toBe('plain log line without timestamp');
  });

  it('trims trailing whitespace from message', () => {
    const line = '2026-02-22T10:30:45.000000000Z message with spaces   ';
    const result = parseTimestamp(line);
    expect(result.message).toBe('message with spaces');
  });

  it('handles multi-line Docker log message (dotall)', () => {
    const line = '2026-02-22T10:30:45.000000000Z line1\nline2';
    const result = parseTimestamp(line);
    expect(result.message).toBe('line1\nline2');
  });
});

// ---------------------------------------------------------------------------
// parseDockerLog
// ---------------------------------------------------------------------------
describe('parseDockerLog', () => {
  it('returns a LogEntry with auto-incremented numeric id', () => {
    const entry = parseDockerLog('plain message', 'app');
    expect(entry.id).toBe('1');
  });

  it('increments id on each call', () => {
    const a = parseDockerLog('msg1', 'app');
    const b = parseDockerLog('msg2', 'app');
    expect(a.id).toBe('1');
    expect(b.id).toBe('2');
  });

  it('sets source from second argument', () => {
    const entry = parseDockerLog('some line', 'saturn-db-dev');
    expect(entry.source).toBe('saturn-db-dev');
  });

  it('parses Docker timestamp and sets correct level', () => {
    const line = '2026-02-22T10:30:45.000000000Z ERROR: container crashed';
    const entry = parseDockerLog(line, 'app');
    expect(entry.timestamp).toBe('2026-02-22T10:30:45.000Z');
    expect(entry.message).toBe('ERROR: container crashed');
    expect(entry.level).toBe('error');
  });

  it('parses Laravel log line correctly', () => {
    const line = '[2026-02-22 10:30:45] production.WARNING: Slow query detected';
    const entry = parseDockerLog(line, 'saturn-dev');
    expect(entry.level).toBe('warning');
    expect(entry.message).toBe('production.WARNING: Slow query detected');
  });

  it('defaults level to info for a plain line', () => {
    const entry = parseDockerLog('Application ready.', 'app');
    expect(entry.level).toBe('info');
  });
});

// ---------------------------------------------------------------------------
// resetIdCounter
// ---------------------------------------------------------------------------
describe('resetIdCounter', () => {
  it('resets id sequence back to 1', () => {
    parseDockerLog('a', 'x');
    parseDockerLog('b', 'x');
    resetIdCounter();
    const entry = parseDockerLog('c', 'x');
    expect(entry.id).toBe('1');
  });
});
