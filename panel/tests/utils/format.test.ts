import { describe, it, expect } from 'vitest';
import {
  formatBytes,
  formatDuration,
  formatRelativeTime,
  truncate,
  padRight,
  padLeft,
} from '../../src/utils/format.js';

// ---------------------------------------------------------------------------
// formatBytes
// ---------------------------------------------------------------------------
describe('formatBytes', () => {
  it('formats 0 bytes', () => {
    expect(formatBytes(0)).toBe('0 B');
  });

  it('formats bytes without decimal', () => {
    expect(formatBytes(512)).toBe('512 B');
  });

  it('formats exactly 1 KB', () => {
    expect(formatBytes(1024)).toBe('1.0 KB');
  });

  it('formats 1.5 KB', () => {
    expect(formatBytes(1536)).toBe('1.5 KB');
  });

  it('formats exactly 1 MB', () => {
    expect(formatBytes(1024 * 1024)).toBe('1.0 MB');
  });

  it('formats exactly 1 GB', () => {
    expect(formatBytes(1024 * 1024 * 1024)).toBe('1.0 GB');
  });

  it('formats exactly 1 TB', () => {
    expect(formatBytes(1024 ** 4)).toBe('1.0 TB');
  });

  it('formats 2.5 GB', () => {
    expect(formatBytes(2.5 * 1024 * 1024 * 1024)).toBe('2.5 GB');
  });

  it('returns 0 B for negative values', () => {
    expect(formatBytes(-1)).toBe('0 B');
  });

  it('returns 0 B for NaN', () => {
    expect(formatBytes(NaN)).toBe('0 B');
  });

  it('returns 0 B for Infinity', () => {
    expect(formatBytes(Infinity)).toBe('0 B');
  });
});

// ---------------------------------------------------------------------------
// formatDuration
// ---------------------------------------------------------------------------
describe('formatDuration', () => {
  it('formats 0 ms as 0s', () => {
    expect(formatDuration(0)).toBe('0s');
  });

  it('formats sub-second as 0s', () => {
    expect(formatDuration(999)).toBe('0s');
  });

  it('formats 2 seconds', () => {
    expect(formatDuration(2000)).toBe('2s');
  });

  it('formats exactly 1 minute', () => {
    expect(formatDuration(60_000)).toBe('1m');
  });

  it('formats 1 minute 30 seconds', () => {
    expect(formatDuration(90_000)).toBe('1m 30s');
  });

  it('formats exactly 1 hour', () => {
    expect(formatDuration(3_600_000)).toBe('1h');
  });

  it('formats 2 hours 15 minutes', () => {
    expect(formatDuration(2 * 3_600_000 + 15 * 60_000)).toBe('2h 15m');
  });

  it('formats 1 hour 0 minutes (omits 0m)', () => {
    expect(formatDuration(3_600_000)).toBe('1h');
  });

  it('formats 1 hour 1 minute', () => {
    expect(formatDuration(3_660_000)).toBe('1h 1m');
  });

  it('returns 0s for negative values', () => {
    expect(formatDuration(-500)).toBe('0s');
  });
});

// ---------------------------------------------------------------------------
// formatRelativeTime
// ---------------------------------------------------------------------------
describe('formatRelativeTime', () => {
  it('returns a non-empty string for a Date', () => {
    const result = formatRelativeTime(new Date());
    expect(typeof result).toBe('string');
    expect(result.length).toBeGreaterThan(0);
  });

  it('returns a non-empty string for a date string', () => {
    const result = formatRelativeTime('2020-01-01T00:00:00.000Z');
    expect(typeof result).toBe('string');
    expect(result.length).toBeGreaterThan(0);
  });

  it('includes "ago" for a past date', () => {
    const pastDate = new Date(Date.now() - 2 * 60 * 1000); // 2 minutes ago
    expect(formatRelativeTime(pastDate)).toMatch(/ago/);
  });
});

// ---------------------------------------------------------------------------
// truncate
// ---------------------------------------------------------------------------
describe('truncate', () => {
  it('returns original string if within limit', () => {
    expect(truncate('hello', 10)).toBe('hello');
  });

  it('returns original string if exactly at limit', () => {
    expect(truncate('hello', 5)).toBe('hello');
  });

  it('truncates and adds ellipsis when over limit', () => {
    expect(truncate('hello world', 8)).toBe('hello w\u2026');
  });

  it('handles limit of 1', () => {
    expect(truncate('ab', 1)).toBe('\u2026');
  });

  it('handles empty string', () => {
    expect(truncate('', 5)).toBe('');
  });
});

// ---------------------------------------------------------------------------
// padRight / padLeft
// ---------------------------------------------------------------------------
describe('padRight', () => {
  it('pads a short string to the right', () => {
    expect(padRight('hi', 5)).toBe('hi   ');
  });

  it('does not truncate a string longer than length', () => {
    expect(padRight('hello world', 5)).toBe('hello world');
  });

  it('returns unchanged string at exact length', () => {
    expect(padRight('abc', 3)).toBe('abc');
  });
});

describe('padLeft', () => {
  it('pads a short string to the left', () => {
    expect(padLeft('hi', 5)).toBe('   hi');
  });

  it('does not truncate a string longer than length', () => {
    expect(padLeft('hello world', 5)).toBe('hello world');
  });

  it('returns unchanged string at exact length', () => {
    expect(padLeft('abc', 3)).toBe('abc');
  });
});
