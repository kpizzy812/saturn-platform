import React, { memo, useState, useEffect, useCallback } from 'react';
import { Box, Text, useInput } from 'ink';
import type { LogEntry } from '../../config/types.js';
import { getContainerColor } from '../../utils/ansi.js';

interface LogViewerProps {
  logs: LogEntry[];
  /** Viewport height in terminal lines. Defaults to process.stdout.rows - 8, min 10. */
  height?: number;
  showTimestamp?: boolean;
  showSource?: boolean;
  colorBySource?: boolean;
  searchQuery?: string;
  autoScroll?: boolean;
}

// Map log level to ink color name
function levelColor(level: LogEntry['level']): string {
  switch (level) {
    case 'error':
      return 'red';
    case 'warning':
      return 'yellow';
    case 'debug':
      return 'gray';
    default:
      return 'white';
  }
}

// Format an ISO timestamp to HH:MM:SS
function formatTime(iso: string): string {
  try {
    const d = new Date(iso);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
  } catch {
    return iso.slice(11, 19);
  }
}

// Resolve effective viewport height
function resolveHeight(height?: number): number {
  if (height != null && height > 0) return height;
  const rows = process.stdout.rows ?? 30;
  return Math.max(10, rows - 8);
}

interface HighlightSegment {
  text: string;
  highlighted: boolean;
}

// Split a message string into plain/highlighted segments for the search query
function buildSegments(message: string, query: string): HighlightSegment[] {
  if (!query) return [{ text: message, highlighted: false }];

  const segments: HighlightSegment[] = [];
  const lowerMessage = message.toLowerCase();
  const lowerQuery = query.toLowerCase();
  let cursor = 0;

  while (cursor < message.length) {
    const matchIndex = lowerMessage.indexOf(lowerQuery, cursor);
    if (matchIndex === -1) {
      segments.push({ text: message.slice(cursor), highlighted: false });
      break;
    }

    if (matchIndex > cursor) {
      segments.push({ text: message.slice(cursor, matchIndex), highlighted: false });
    }

    segments.push({ text: message.slice(matchIndex, matchIndex + query.length), highlighted: true });
    cursor = matchIndex + query.length;
  }

  return segments;
}

function LogViewerInner({
  logs,
  height,
  showTimestamp = true,
  showSource = false,
  colorBySource = false,
  searchQuery = '',
  autoScroll = true,
}: LogViewerProps) {
  const viewHeight = resolveHeight(height);

  /**
   * scrollOffset: number of lines from the BOTTOM that are hidden.
   * 0 = showing the most recent lines (default, auto-scroll position).
   * Increasing this value scrolls UP through older log lines.
   */
  const [scrollOffset, setScrollOffset] = useState<number>(0);

  // When new logs arrive and the user has not scrolled up, stay at bottom
  useEffect(() => {
    if (autoScroll && scrollOffset === 0) {
      // Nothing to do — offset 0 already means bottom; render takes care of it
    }
  }, [logs.length, autoScroll, scrollOffset]);

  const scrollUp = useCallback((lines: number) => {
    setScrollOffset((prev) => {
      const maxOffset = Math.max(0, logs.length - viewHeight);
      return Math.min(prev + lines, maxOffset);
    });
  }, [logs.length, viewHeight]);

  const scrollDown = useCallback((lines: number) => {
    setScrollOffset((prev) => Math.max(0, prev - lines));
  }, []);

  const scrollToBottom = useCallback(() => {
    setScrollOffset(0);
  }, []);

  useInput((_input, key) => {
    if (key.upArrow) {
      scrollUp(1);
    } else if (key.downArrow) {
      scrollDown(1);
    } else if (key.pageUp) {
      scrollUp(viewHeight);
    } else if (key.pageDown) {
      scrollDown(viewHeight);
    } else if (_input === 'G') {
      // 'G' (vim-style) resumes auto-scroll at bottom
      scrollToBottom();
    }
  });

  // Compute visible slice
  // logs[0] is oldest, logs[logs.length-1] is newest
  const total = logs.length;
  const endIndex = Math.max(0, total - scrollOffset);
  const startIndex = Math.max(0, endIndex - viewHeight);
  const visibleLogs = logs.slice(startIndex, endIndex);

  const isAtBottom = scrollOffset === 0;
  const lineInfo = `Lines: ${endIndex}/${total}`;

  return (
    <Box flexDirection="column">
      {/* Log lines viewport */}
      <Box flexDirection="column" height={viewHeight}>
        {visibleLogs.map((entry) => {
          const color = colorBySource
            ? getContainerColor(entry.source)
            : levelColor(entry.level);

          const segments = buildSegments(entry.message, searchQuery);

          return (
            <Box key={entry.id} flexShrink={0}>
              {showTimestamp && (
                <Text dimColor>{formatTime(entry.timestamp)} </Text>
              )}
              {showSource && (
                <Text color={getContainerColor(entry.source)}>
                  [{entry.source}]{' '}
                </Text>
              )}
              {segments.map((seg, si) => (
                <Text
                  key={si}
                  color={color}
                  inverse={seg.highlighted}
                >
                  {seg.text}
                </Text>
              ))}
            </Box>
          );
        })}
      </Box>

      {/* Status bar */}
      <Box justifyContent="space-between">
        <Text dimColor>
          {isAtBottom ? '' : 'Scrolling \u2191 — press G or End to resume auto-scroll'}
        </Text>
        <Text dimColor>{lineInfo}</Text>
      </Box>
    </Box>
  );
}

export const LogViewer = memo(LogViewerInner);
