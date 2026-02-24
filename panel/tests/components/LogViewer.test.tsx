import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { LogViewer } from '../../src/components/shared/LogViewer.js';
import type { LogEntry } from '../../src/config/types.js';

// ---------------------------------------------------------------------------
// Helper: wait for ink effects to flush (useEffect runs after paint)
// ---------------------------------------------------------------------------
async function waitForEffects(): Promise<void> {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

// Helpers to build log entries
let idSeq = 0;
function makeEntry(overrides: Partial<LogEntry> = {}): LogEntry {
  return {
    id: String(++idSeq),
    timestamp: '2026-02-22T10:30:45.000Z',
    message: 'Test message',
    level: 'info',
    source: 'saturn-dev',
    ...overrides,
  };
}

function makeEntries(count: number): LogEntry[] {
  return Array.from({ length: count }, (_, i) =>
    makeEntry({ id: String(i + 1), message: `Line ${i + 1}` }),
  );
}

// Extract HH:MM:SS from an ISO timestamp in local time (same as LogViewer does)
function formatTimeLocal(iso: string): string {
  const d = new Date(iso);
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

describe('LogViewer', () => {
  it('renders without crashing with empty logs', () => {
    expect(() => render(<LogViewer logs={[]} height={10} />)).not.toThrow();
  });

  it('renders log messages', () => {
    const logs = [makeEntry({ message: 'Hello world' })];
    const { lastFrame } = render(<LogViewer logs={logs} height={10} />);
    expect(lastFrame()).toContain('Hello world');
  });

  it('shows timestamp when showTimestamp=true', () => {
    const iso = '2026-02-22T10:30:45.000Z';
    const logs = [makeEntry({ timestamp: iso })];
    const { lastFrame } = render(
      <LogViewer logs={logs} height={10} showTimestamp={true} />,
    );
    // Use local time formatting consistent with LogViewer implementation
    const expectedTime = formatTimeLocal(iso);
    expect(lastFrame()).toContain(expectedTime);
  });

  it('hides timestamp when showTimestamp=false', () => {
    const iso = '2026-02-22T10:30:45.000Z';
    const expectedTime = formatTimeLocal(iso);
    const logs = [makeEntry({ timestamp: iso })]
    const { lastFrame } = render(
      <LogViewer logs={logs} height={10} showTimestamp={false} />,
    );
    expect(lastFrame()).not.toContain(expectedTime);
  });

  it('shows source when showSource=true', () => {
    const logs = [makeEntry({ source: 'saturn-db-dev' })];
    const { lastFrame } = render(
      <LogViewer logs={logs} height={10} showSource={true} />,
    );
    expect(lastFrame()).toContain('saturn-db-dev');
  });

  it('hides source when showSource=false', () => {
    const logs = [makeEntry({ source: 'saturn-db-dev' })];
    const { lastFrame } = render(
      <LogViewer logs={logs} height={10} showSource={false} />,
    );
    expect(lastFrame()).not.toContain('saturn-db-dev');
  });

  it('shows line count in the status bar', () => {
    const logs = makeEntries(5);
    const { lastFrame } = render(<LogViewer logs={logs} height={20} />);
    expect(lastFrame()).toContain('Lines:');
  });

  it('renders the most recent lines (bottom of viewport) by default', () => {
    // 15 entries, viewport of 5 — should show entries 11-15, NOT entries 1-5
    const logs = makeEntries(15);
    const { lastFrame } = render(<LogViewer logs={logs} height={5} />);
    const output = lastFrame() ?? '';
    expect(output).toContain('Line 15');
    // "Line 1" with trailing space should NOT be in the viewport
    // but "Line 1" is a prefix of "Line 10", "Line 11" etc, so check "Line 1 " or end of string
    expect(output).not.toMatch(/Line 1\b(?![\d])/);
  });

  it('scrolls up when Up arrow is pressed', async () => {
    const logs = makeEntries(20);
    const { lastFrame, stdin } = render(<LogViewer logs={logs} height={5} />);
    await waitForEffects();

    // Press up arrow — scroll back one line
    stdin.write('\u001B[A');
    await waitForEffects();

    const output = lastFrame() ?? '';
    // After scrolling up by 1, viewport shows lines 15-19 instead of 16-20
    expect(output).toContain('Line 19');
    expect(output).not.toContain('Line 20');
  });

  it('scrolls back to bottom when G is pressed', async () => {
    const logs = makeEntries(20);
    const { lastFrame, stdin } = render(<LogViewer logs={logs} height={5} />);
    await waitForEffects();

    // Scroll up several lines
    stdin.write('\u001B[A');
    stdin.write('\u001B[A');
    stdin.write('\u001B[A');
    await waitForEffects();

    // Press G to return to bottom
    stdin.write('G');
    await waitForEffects();

    const output = lastFrame() ?? '';
    expect(output).toContain('Line 20');
  });

  it('renders without crashing for 1000+ entries', () => {
    const logs = makeEntries(1000);
    expect(() => render(<LogViewer logs={logs} height={20} />)).not.toThrow();
  });

  it('only renders viewHeight lines (not all 1000)', () => {
    const logs = makeEntries(1000);
    const { lastFrame } = render(<LogViewer logs={logs} height={10} />);
    const output = lastFrame() ?? '';
    // Viewport shows lines 991-1000; line 1 is not visible
    expect(output).not.toMatch(/\bLine 1\b(?![\d])/);
    expect(output).toContain('Line 1000');
  });

  it('renders without crashing with searchQuery provided', () => {
    const logs = [makeEntry({ message: 'ERROR something broke' })];
    expect(() =>
      render(<LogViewer logs={logs} height={10} searchQuery="ERROR" />),
    ).not.toThrow();
  });

  it('uses provided height prop over default', () => {
    const logs = makeEntries(50);
    // With height=5 we see only 5 lines; Line 46-50
    const { lastFrame } = render(<LogViewer logs={logs} height={5} />);
    const output = lastFrame() ?? '';
    expect(output).toContain('Line 50');
    expect(output).not.toContain('Line 45');
  });

  it('shows scroll indicator text when scrolled up', async () => {
    const logs = makeEntries(20);
    const { lastFrame, stdin } = render(<LogViewer logs={logs} height={5} />);
    await waitForEffects();

    stdin.write('\u001B[A');
    await waitForEffects();

    // Scrolling up should show the scroll indicator message
    expect(lastFrame()).toContain('Scrolling');
  });

  it('scrolls down when Down arrow is pressed after scrolling up', async () => {
    const logs = makeEntries(20);
    const { lastFrame, stdin } = render(<LogViewer logs={logs} height={5} />);
    await waitForEffects();

    // Scroll up 2 lines
    stdin.write('\u001B[A');
    stdin.write('\u001B[A');
    await waitForEffects();

    // Scroll down 1 line
    stdin.write('\u001B[B');
    await waitForEffects();

    // Net offset = 1 line from bottom, so line 20 should NOT be visible
    const output = lastFrame() ?? '';
    expect(output).toContain('Line 19');
    expect(output).not.toContain('Line 20');
  });
});
