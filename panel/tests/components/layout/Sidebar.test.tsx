import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Sidebar } from '../../../src/components/layout/Sidebar.js';

describe('Sidebar', () => {
  it('renders without crashing', () => {
    expect(() =>
      render(<Sidebar activeEnv="dev" onSelect={vi.fn()} />),
    ).not.toThrow();
  });

  it('renders all three environment labels', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="dev" onSelect={vi.fn()} />,
    );
    const frame = lastFrame() ?? '';
    expect(frame).toContain('dev');
    expect(frame).toContain('staging');
    expect(frame).toContain('production');
  });

  it('renders the active environment with the arrow indicator', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="staging" onSelect={vi.fn()} />,
    );
    expect(lastFrame()).toContain('▸');
  });

  it('renders the domain for the active environment', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="dev" onSelect={vi.fn()} />,
    );
    expect(lastFrame()).toContain('dev.saturn.ac');
  });

  it('renders staging domain when staging is active', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="staging" onSelect={vi.fn()} />,
    );
    expect(lastFrame()).toContain('uat.saturn.ac');
  });

  it('renders production domain when production is active', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="production" onSelect={vi.fn()} />,
    );
    expect(lastFrame()).toContain('saturn.ac');
  });

  it('renders nothing when visible=false', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="dev" onSelect={vi.fn()} visible={false} />,
    );
    // When not visible the component returns null — frame is empty
    expect(lastFrame()).toBe('');
  });

  it('renders the Environment heading', () => {
    const { lastFrame } = render(
      <Sidebar activeEnv="dev" onSelect={vi.fn()} />,
    );
    expect(lastFrame()).toContain('Environment');
  });
});
