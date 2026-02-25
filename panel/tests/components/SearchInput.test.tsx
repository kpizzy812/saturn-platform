import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { SearchInput } from '../../src/components/shared/SearchInput.js';

// ---------------------------------------------------------------------------
// Helper: wait for ink effects to flush (useEffect runs after paint)
// ---------------------------------------------------------------------------
async function waitForEffects(): Promise<void> {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

describe('SearchInput', () => {
  it('renders without crashing', () => {
    expect(() =>
      render(<SearchInput onChange={vi.fn()} />),
    ).not.toThrow();
  });

  it('renders the "/" search prefix indicator', () => {
    const { lastFrame } = render(<SearchInput onChange={vi.fn()} />);
    expect(lastFrame()).toContain('/');
  });

  it('calls onCancel when Escape is pressed', async () => {
    const onCancel = vi.fn();
    const { stdin } = render(
      <SearchInput onChange={vi.fn()} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('\u001B');
    await waitForEffects();
    expect(onCancel).toHaveBeenCalledOnce();
  });

  it('does not throw when onCancel is not provided and Escape is pressed', async () => {
    const { stdin } = render(<SearchInput onChange={vi.fn()} />);
    await waitForEffects();
    expect(() => stdin.write('\u001B')).not.toThrow();
  });

  it('renders with custom placeholder', () => {
    const { lastFrame } = render(
      <SearchInput placeholder="Find container..." onChange={vi.fn()} />,
    );
    // Placeholder text appears when input is empty
    expect(lastFrame()).toContain('Find container...');
  });

  it('renders with default placeholder when none provided', () => {
    const { lastFrame } = render(<SearchInput onChange={vi.fn()} />);
    expect(lastFrame()).toContain('Search...');
  });
});
