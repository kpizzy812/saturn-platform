import React, { memo } from 'react';
import { Box, Text } from 'ink';

interface Column<T> {
  header: string;
  /** Field key or a custom getter function */
  key: keyof T | ((row: T) => string);
  /** Fixed column width. When omitted the column auto-sizes to its content. */
  width?: number;
  color?: string;
  align?: 'left' | 'right';
}

interface TableProps<T> {
  columns: Column<T>[];
  data: T[];
  /** Zero-based index of the currently highlighted row */
  highlightRow?: number;
}

// Resolve a cell value from a row using the column key (field name or getter)
function getCellValue<T>(row: T, key: Column<T>['key']): string {
  if (typeof key === 'function') {
    return key(row);
  }
  const value = row[key];
  return value == null ? '' : String(value);
}

// Pad a string to the given width, respecting alignment
function pad(text: string, width: number, align: 'left' | 'right' = 'left'): string {
  const truncated = text.length > width ? text.slice(0, width - 1) + '\u2026' : text;
  return align === 'right' ? truncated.padStart(width) : truncated.padEnd(width);
}

// Build a separator line matching total column widths + spacing
function buildSeparator(colWidths: number[]): string {
  return colWidths.map((w) => '\u2500'.repeat(w)).join('  ');
}

function TableInner<T>({ columns, data, highlightRow }: TableProps<T>) {
  // Determine effective column widths
  const colWidths = columns.map((col) => {
    if (col.width != null) return col.width;

    // Auto-size: max of header length and all cell values
    const headerLen = col.header.length;
    const maxData = data.reduce((max, row) => {
      const cell = getCellValue(row, col.key);
      return Math.max(max, cell.length);
    }, 0);

    return Math.max(headerLen, maxData);
  });

  const separator = buildSeparator(colWidths);

  return (
    <Box flexDirection="column">
      {/* Header row */}
      <Box>
        {columns.map((col, ci) => (
          <Box key={col.header} marginRight={ci < columns.length - 1 ? 2 : 0}>
            <Text bold>
              {pad(col.header, colWidths[ci] ?? col.header.length, col.align)}
            </Text>
          </Box>
        ))}
      </Box>

      {/* Separator */}
      <Text dimColor>{separator}</Text>

      {/* Data rows */}
      {data.map((row, ri) => {
        const isHighlighted = ri === highlightRow;
        return (
          <Box key={ri}>
            {columns.map((col, ci) => {
              const cell = getCellValue(row, col.key);
              const width = colWidths[ci] ?? cell.length;
              const padded = pad(cell, width, col.align);

              return (
                <Box key={String(col.key instanceof Function ? ci : col.key)} marginRight={ci < columns.length - 1 ? 2 : 0}>
                  <Text
                    color={isHighlighted ? undefined : col.color}
                    inverse={isHighlighted}
                  >
                    {padded}
                  </Text>
                </Box>
              );
            })}
          </Box>
        );
      })}
    </Box>
  );
}

// memo wrapper — generic components cannot be wrapped directly, use cast
export const Table = memo(TableInner) as typeof TableInner;
