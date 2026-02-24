import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Footer, DEFAULT_HINTS } from '../../../src/components/layout/Footer.js';

describe('Footer', () => {
  it('renders without crashing with empty hints', () => {
    expect(() => render(<Footer hints={[]} />)).not.toThrow();
  });

  it('renders key text for each hint', () => {
    const { lastFrame } = render(
      <Footer hints={[{ key: 'q', label: 'Quit' }]} />,
    );
    expect(lastFrame()).toContain('q');
  });

  it('renders label text for each hint', () => {
    const { lastFrame } = render(
      <Footer hints={[{ key: 'q', label: 'Quit' }]} />,
    );
    expect(lastFrame()).toContain('Quit');
  });

  it('renders multiple hints', () => {
    const { lastFrame } = render(
      <Footer
        hints={[
          { key: '1-7', label: 'Screens' },
          { key: 'q', label: 'Quit' },
        ]}
      />,
    );
    const frame = lastFrame() ?? '';
    expect(frame).toContain('Screens');
    expect(frame).toContain('Quit');
  });

  it('renders the colon separator between key and label', () => {
    const { lastFrame } = render(
      <Footer hints={[{ key: 'q', label: 'Quit' }]} />,
    );
    // key:label format
    expect(lastFrame()).toContain(':Quit');
  });
});

describe('DEFAULT_HINTS', () => {
  it('exports an array with at least 4 entries', () => {
    expect(DEFAULT_HINTS.length).toBeGreaterThanOrEqual(4);
  });

  it('includes a quit hint', () => {
    const quitHint = DEFAULT_HINTS.find((h) => h.key === 'q');
    expect(quitHint).toBeDefined();
  });

  it('includes a screens navigation hint', () => {
    const screensHint = DEFAULT_HINTS.find((h) => h.key === '1-7');
    expect(screensHint).toBeDefined();
  });

  it('includes a help hint', () => {
    const helpHint = DEFAULT_HINTS.find((h) => h.key === '?');
    expect(helpHint).toBeDefined();
  });

  it('includes an Esc/back hint', () => {
    const escHint = DEFAULT_HINTS.find((h) => h.key === 'Esc');
    expect(escHint).toBeDefined();
  });

  it('renders without crashing with DEFAULT_HINTS', () => {
    expect(() => render(<Footer hints={DEFAULT_HINTS} />)).not.toThrow();
  });
});
