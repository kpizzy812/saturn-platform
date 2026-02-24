import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Badge, statusColor } from '../../src/components/shared/Badge.js';

// ---------------------------------------------------------------------------
// statusColor helper
// ---------------------------------------------------------------------------
describe('statusColor', () => {
  it('returns green for "running"', () => {
    expect(statusColor('running')).toBe('green');
  });

  it('returns green for "healthy"', () => {
    expect(statusColor('healthy')).toBe('green');
  });

  it('returns red for "stopped"', () => {
    expect(statusColor('stopped')).toBe('red');
  });

  it('returns red for "exited"', () => {
    expect(statusColor('exited')).toBe('red');
  });

  it('returns yellow for "starting"', () => {
    expect(statusColor('starting')).toBe('yellow');
  });

  it('returns yellow for "restarting"', () => {
    expect(statusColor('restarting')).toBe('yellow');
  });

  it('returns blue for "paused"', () => {
    expect(statusColor('paused')).toBe('blue');
  });

  it('returns gray for unknown status', () => {
    expect(statusColor('unknown')).toBe('gray');
  });

  it('returns gray for empty string', () => {
    expect(statusColor('')).toBe('gray');
  });

  it('is case-insensitive', () => {
    expect(statusColor('RUNNING')).toBe('green');
    expect(statusColor('Stopped')).toBe('red');
    expect(statusColor('PAUSED')).toBe('blue');
  });

  it('trims surrounding whitespace', () => {
    expect(statusColor('  running  ')).toBe('green');
  });
});

// ---------------------------------------------------------------------------
// Badge component rendering
// ---------------------------------------------------------------------------
describe('Badge', () => {
  it('renders the label in uppercase', () => {
    const { lastFrame } = render(<Badge label="running" color="green" />);
    expect(lastFrame()).toContain('RUNNING');
  });

  it('renders the label text content (terminal may strip trailing spaces)', () => {
    const { lastFrame } = render(<Badge label="ok" color="green" />);
    // The badge renders " OK " but terminal output may trim trailing whitespace
    // Verify the text content is present
    expect(lastFrame()).toContain('OK');
  });

  it('renders with leading space before label', () => {
    const { lastFrame } = render(<Badge label="ok" color="green" />);
    // Should contain " OK" (space before the text)
    expect(lastFrame()).toContain(' OK');
  });

  it('renders without crashing for any color string', () => {
    expect(() => render(<Badge label="test" color="magenta" />)).not.toThrow();
  });

  it('renders with inverse=true without crashing', () => {
    expect(() => render(<Badge label="test" color="green" inverse={true} />)).not.toThrow();
  });

  it('renders with inverse=false by default without crashing', () => {
    expect(() => render(<Badge label="test" color="red" />)).not.toThrow();
  });

  it('uppercases multi-word labels', () => {
    const { lastFrame } = render(<Badge label="in progress" color="yellow" />);
    expect(lastFrame()).toContain('IN PROGRESS');
  });
});
