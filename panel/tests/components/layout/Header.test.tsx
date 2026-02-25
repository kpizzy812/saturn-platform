import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Header } from '../../../src/components/layout/Header.js';

describe('Header', () => {
  it('renders the Saturn brand name', () => {
    const { lastFrame } = render(
      <Header screenName="Dashboard" sshConnected={false} />,
    );
    expect(lastFrame()).toContain('Saturn');
  });

  it('renders the screenName', () => {
    const { lastFrame } = render(
      <Header screenName="Dashboard" sshConnected={false} />,
    );
    expect(lastFrame()).toContain('Dashboard');
  });

  it('shows SSH connected indicator when connected', () => {
    const { lastFrame } = render(
      <Header screenName="Logs" sshConnected={true} />,
    );
    expect(lastFrame()).toContain('SSH');
    expect(lastFrame()).toContain('●');
  });

  it('shows SSH disconnected indicator when not connected', () => {
    const { lastFrame } = render(
      <Header screenName="Logs" sshConnected={false} />,
    );
    expect(lastFrame()).toContain('SSH');
    expect(lastFrame()).toContain('○');
  });

  it('shows the current environment when provided', () => {
    const { lastFrame } = render(
      <Header screenName="Deploy" sshConnected={true} currentEnv="staging" />,
    );
    expect(lastFrame()).toContain('staging');
  });

  it('does not render currentEnv separator when env is omitted', () => {
    const { lastFrame } = render(
      <Header screenName="Dashboard" sshConnected={false} />,
    );
    // The env breadcrumb separator and env label should not appear
    const frame = lastFrame() ?? '';
    // Only one "/" separator — between Saturn and screenName
    const slashCount = (frame.match(/\//g) ?? []).length;
    expect(slashCount).toBe(1);
  });

  it('renders without crashing when screenName is a long string', () => {
    expect(() =>
      render(<Header screenName="A Very Long Screen Name" sshConnected={true} />),
    ).not.toThrow();
  });
});
