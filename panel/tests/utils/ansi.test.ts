import { describe, it, expect } from 'vitest';
import {
  stripAnsiCodes,
  hasAnsiCodes,
  getContainerColor,
  CONTAINER_COLORS,
} from '../../src/utils/ansi.js';

// ---------------------------------------------------------------------------
// stripAnsiCodes
// ---------------------------------------------------------------------------
describe('stripAnsiCodes', () => {
  it('strips basic color codes', () => {
    expect(stripAnsiCodes('\x1b[32mhello\x1b[0m')).toBe('hello');
  });

  it('strips bold and reset sequences', () => {
    expect(stripAnsiCodes('\x1b[1mBold text\x1b[0m')).toBe('Bold text');
  });

  it('returns plain string unchanged', () => {
    expect(stripAnsiCodes('plain text')).toBe('plain text');
  });

  it('handles empty string', () => {
    expect(stripAnsiCodes('')).toBe('');
  });

  it('strips multiple sequences in one string', () => {
    const colored = '\x1b[31mred\x1b[0m and \x1b[34mblue\x1b[0m';
    expect(stripAnsiCodes(colored)).toBe('red and blue');
  });
});

// ---------------------------------------------------------------------------
// hasAnsiCodes
// ---------------------------------------------------------------------------
describe('hasAnsiCodes', () => {
  it('returns true for a string with color codes', () => {
    expect(hasAnsiCodes('\x1b[32mgreen\x1b[0m')).toBe(true);
  });

  it('returns true for a reset sequence', () => {
    expect(hasAnsiCodes('\x1b[0m')).toBe(true);
  });

  it('returns false for plain text', () => {
    expect(hasAnsiCodes('just plain text')).toBe(false);
  });

  it('returns false for empty string', () => {
    expect(hasAnsiCodes('')).toBe(false);
  });

  it('returns true for bold sequence', () => {
    expect(hasAnsiCodes('\x1b[1m')).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// CONTAINER_COLORS — sanity checks on exported map
// ---------------------------------------------------------------------------
describe('CONTAINER_COLORS', () => {
  it('has all four expected service types', () => {
    expect(CONTAINER_COLORS).toHaveProperty('app', 'cyan');
    expect(CONTAINER_COLORS).toHaveProperty('db', 'yellow');
    expect(CONTAINER_COLORS).toHaveProperty('redis', 'magenta');
    expect(CONTAINER_COLORS).toHaveProperty('realtime', 'green');
  });
});

// ---------------------------------------------------------------------------
// getContainerColor
// ---------------------------------------------------------------------------
describe('getContainerColor', () => {
  // App container: "saturn-{env}" — no service infix
  it('returns cyan for the app container (dev)', () => {
    expect(getContainerColor('saturn-dev')).toBe('cyan');
  });

  it('returns cyan for the app container (production)', () => {
    expect(getContainerColor('saturn-production')).toBe('cyan');
  });

  it('returns cyan for the app container (staging)', () => {
    expect(getContainerColor('saturn-staging')).toBe('cyan');
  });

  // DB container: "saturn-db-{env}"
  it('returns yellow for the db container (dev)', () => {
    expect(getContainerColor('saturn-db-dev')).toBe('yellow');
  });

  it('returns yellow for the db container (production)', () => {
    expect(getContainerColor('saturn-db-production')).toBe('yellow');
  });

  // Redis container: "saturn-redis-{env}"
  it('returns magenta for the redis container', () => {
    expect(getContainerColor('saturn-redis-dev')).toBe('magenta');
  });

  it('returns magenta for the redis container (staging)', () => {
    expect(getContainerColor('saturn-redis-staging')).toBe('magenta');
  });

  // Realtime container: "saturn-realtime-{env}"
  it('returns green for the realtime container', () => {
    expect(getContainerColor('saturn-realtime-dev')).toBe('green');
  });

  it('returns green for the realtime container (production)', () => {
    expect(getContainerColor('saturn-realtime-production')).toBe('green');
  });

  // DB must NOT be matched as app
  it('does not return cyan for the db container', () => {
    expect(getContainerColor('saturn-db-dev')).not.toBe('cyan');
  });

  // Redis must NOT be matched as app
  it('does not return cyan for the redis container', () => {
    expect(getContainerColor('saturn-redis-dev')).not.toBe('cyan');
  });

  // Unknown container
  it('returns white for unknown container names', () => {
    expect(getContainerColor('some-other-service-dev')).toBe('white');
  });

  it('returns white for empty string', () => {
    expect(getContainerColor('')).toBe('white');
  });
});
