import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Tabs } from '../../src/components/shared/Tabs.js';

// ---------------------------------------------------------------------------
// Helper: wait for ink effects to flush (useEffect runs after paint)
// ---------------------------------------------------------------------------
async function waitForEffects(): Promise<void> {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

const TABS = [
  { label: 'Overview', value: 'overview' },
  { label: 'Logs', value: 'logs' },
  { label: 'Settings', value: 'settings' },
];

describe('Tabs', () => {
  it('renders all tab labels', () => {
    const { lastFrame } = render(
      <Tabs tabs={TABS} activeTab="overview" onChange={vi.fn()} />,
    );
    const output = lastFrame() ?? '';
    expect(output).toContain('Overview');
    expect(output).toContain('Logs');
    expect(output).toContain('Settings');
  });

  it('renders without crashing', () => {
    expect(() =>
      render(<Tabs tabs={TABS} activeTab="logs" onChange={vi.fn()} />),
    ).not.toThrow();
  });

  it('calls onChange with next tab value when Tab is pressed', async () => {
    const onChange = vi.fn();
    const { stdin } = render(
      <Tabs tabs={TABS} activeTab="overview" onChange={onChange} />,
    );
    await waitForEffects();
    // Tab key
    stdin.write('\t');
    await waitForEffects();
    expect(onChange).toHaveBeenCalledWith('logs');
  });

  it('calls onChange with previous tab value when Shift+Tab is pressed', async () => {
    const onChange = vi.fn();
    const { stdin } = render(
      <Tabs tabs={TABS} activeTab="logs" onChange={onChange} />,
    );
    await waitForEffects();
    // Shift+Tab escape sequence
    stdin.write('\u001B[Z');
    await waitForEffects();
    expect(onChange).toHaveBeenCalledWith('overview');
  });

  it('wraps from last tab back to first on Tab press', async () => {
    const onChange = vi.fn();
    const { stdin } = render(
      <Tabs tabs={TABS} activeTab="settings" onChange={onChange} />,
    );
    await waitForEffects();
    stdin.write('\t');
    await waitForEffects();
    expect(onChange).toHaveBeenCalledWith('overview');
  });

  it('wraps from first tab to last on Shift+Tab press', async () => {
    const onChange = vi.fn();
    const { stdin } = render(
      <Tabs tabs={TABS} activeTab="overview" onChange={onChange} />,
    );
    await waitForEffects();
    stdin.write('\u001B[Z');
    await waitForEffects();
    expect(onChange).toHaveBeenCalledWith('settings');
  });

  it('renders with a single tab without crashing', () => {
    const singleTab = [{ label: 'Only', value: 'only' }];
    expect(() =>
      render(<Tabs tabs={singleTab} activeTab="only" onChange={vi.fn()} />),
    ).not.toThrow();
  });

  it('renders with empty tabs array without crashing', () => {
    expect(() =>
      render(<Tabs tabs={[]} activeTab="" onChange={vi.fn()} />),
    ).not.toThrow();
  });
});
