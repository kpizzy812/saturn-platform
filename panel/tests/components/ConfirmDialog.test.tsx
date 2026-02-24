import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { ConfirmDialog } from '../../src/components/shared/ConfirmDialog.js';

// ---------------------------------------------------------------------------
// Helper: wait for ink effects to flush (useEffect runs after paint)
// ---------------------------------------------------------------------------
async function waitForEffects(): Promise<void> {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

describe('ConfirmDialog', () => {
  it('renders the message', () => {
    const { lastFrame } = render(
      <ConfirmDialog
        message="Delete this item?"
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );
    expect(lastFrame()).toContain('Delete this item?');
  });

  it('renders the [y/N] prompt', () => {
    const { lastFrame } = render(
      <ConfirmDialog
        message="Are you sure?"
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );
    expect(lastFrame()).toContain('[y/N]');
  });

  it('calls onConfirm when "y" is pressed', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('y');
    await waitForEffects();
    expect(onConfirm).toHaveBeenCalledOnce();
    expect(onCancel).not.toHaveBeenCalled();
  });

  it('calls onConfirm when "Y" is pressed', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('Y');
    await waitForEffects();
    expect(onConfirm).toHaveBeenCalledOnce();
    expect(onCancel).not.toHaveBeenCalled();
  });

  it('calls onCancel when "n" is pressed', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('n');
    await waitForEffects();
    expect(onCancel).toHaveBeenCalledOnce();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  it('calls onCancel when "N" is pressed', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('N');
    await waitForEffects();
    expect(onCancel).toHaveBeenCalledOnce();
  });

  it('calls onCancel when Escape is pressed', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('\u001B');
    await waitForEffects();
    expect(onCancel).toHaveBeenCalledOnce();
  });

  it('renders without crashing when destructive=true', () => {
    expect(() =>
      render(
        <ConfirmDialog
          message="Delete ALL data?"
          onConfirm={vi.fn()}
          onCancel={vi.fn()}
          destructive={true}
        />,
      ),
    ).not.toThrow();
  });

  it('does not call handlers for unrelated key presses', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    const { stdin } = render(
      <ConfirmDialog message="Confirm?" onConfirm={onConfirm} onCancel={onCancel} />,
    );
    await waitForEffects();
    stdin.write('x');
    stdin.write('z');
    stdin.write('1');
    await waitForEffects();
    expect(onConfirm).not.toHaveBeenCalled();
    expect(onCancel).not.toHaveBeenCalled();
  });
});
