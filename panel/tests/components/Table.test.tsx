import { describe, it, expect } from 'vitest';
import React from 'react';
import { render } from 'ink-testing-library';
import { Table } from '../../src/components/shared/Table.js';

interface Person {
  name: string;
  role: string;
  age: number;
}

const COLUMNS = [
  { header: 'Name', key: 'name' as keyof Person, width: 10 },
  { header: 'Role', key: 'role' as keyof Person, width: 12 },
  { header: 'Age', key: 'age' as keyof Person, width: 5, align: 'right' as const },
];

const DATA: Person[] = [
  { name: 'Alice', role: 'Engineer', age: 30 },
  { name: 'Bob', role: 'Designer', age: 25 },
];

describe('Table', () => {
  it('renders column headers', () => {
    const { lastFrame } = render(<Table columns={COLUMNS} data={DATA} />);
    const output = lastFrame() ?? '';
    expect(output).toContain('Name');
    expect(output).toContain('Role');
    expect(output).toContain('Age');
  });

  it('renders data row values', () => {
    const { lastFrame } = render(<Table columns={COLUMNS} data={DATA} />);
    const output = lastFrame() ?? '';
    expect(output).toContain('Alice');
    expect(output).toContain('Bob');
    expect(output).toContain('Engineer');
    expect(output).toContain('Designer');
  });

  it('renders a separator line between header and data', () => {
    const { lastFrame } = render(<Table columns={COLUMNS} data={DATA} />);
    // Separator is built from ─ characters
    expect(lastFrame()).toContain('\u2500');
  });

  it('renders numeric values as strings', () => {
    const { lastFrame } = render(<Table columns={COLUMNS} data={DATA} />);
    expect(lastFrame()).toContain('30');
    expect(lastFrame()).toContain('25');
  });

  it('renders an empty table without data rows (only header)', () => {
    const { lastFrame } = render(<Table columns={COLUMNS} data={[]} />);
    const output = lastFrame() ?? '';
    expect(output).toContain('Name');
    expect(output).not.toContain('Alice');
  });

  it('supports a getter function as key', () => {
    const cols = [
      { header: 'Info', key: (row: Person) => `${row.name}/${row.age}` },
    ];
    const { lastFrame } = render(<Table columns={cols} data={DATA} />);
    expect(lastFrame()).toContain('Alice/30');
  });

  it('auto-sizes column widths when width is not specified', () => {
    const cols = [
      { header: 'Name', key: 'name' as keyof Person },
    ];
    const longData: Person[] = [{ name: 'Alexander Hamilton', role: 'Founder', age: 49 }];
    const { lastFrame } = render(<Table columns={cols} data={longData} />);
    expect(lastFrame()).toContain('Alexander Hamilton');
  });

  it('truncates cell values that exceed the fixed column width', () => {
    const narrowCols = [
      { header: 'Name', key: 'name' as keyof Person, width: 4 },
    ];
    const { lastFrame } = render(<Table columns={narrowCols} data={DATA} />);
    // "Alice" should be truncated with ellipsis within 4 chars
    const output = lastFrame() ?? '';
    // "Ali…" is what we expect (3 chars + ellipsis)
    expect(output).toContain('Ali\u2026');
  });

  it('renders without crashing when highlightRow is set', () => {
    expect(() => render(<Table columns={COLUMNS} data={DATA} highlightRow={0} />)).not.toThrow();
  });

  it('renders without crashing when highlightRow is out of bounds', () => {
    expect(() => render(<Table columns={COLUMNS} data={DATA} highlightRow={99} />)).not.toThrow();
  });
});
