import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Spinner } from '../../src/components/shared/Spinner.js';

describe('Spinner', () => {
  it('renders without crashing', () => {
    expect(() => render(<Spinner />)).not.toThrow();
  });

  it('renders the default label', () => {
    const { lastFrame } = render(<Spinner />);
    expect(lastFrame()).toContain('Loading...');
  });

  it('renders a custom label', () => {
    const { lastFrame } = render(<Spinner label="Connecting to SSH..." />);
    expect(lastFrame()).toContain('Connecting to SSH...');
  });

  it('renders some content (spinner character + label)', () => {
    const { lastFrame } = render(<Spinner label="Please wait" />);
    const output = lastFrame() ?? '';
    expect(output.length).toBeGreaterThan(0);
    expect(output).toContain('Please wait');
  });
});
