import { useState, useCallback, useRef, useEffect } from 'react';
import { useSSH } from '../ssh/context.js';
import { parseDockerLog } from '../utils/log-parser.js';
import type { LogEntry } from '../config/types.js';
import { DEFAULT_LOG_BUFFER_SIZE } from '../config/constants.js';

export interface UseLogsResult {
  logs: LogEntry[];
  streaming: boolean;
  error: string | null;
  startStreaming: (container: string) => void;
  stopStreaming: () => void;
  clearLogs: () => void;
  filterByLevel: (level: LogEntry['level'] | null) => void;
  searchLogs: (query: string) => LogEntry[];
  activeLevel: LogEntry['level'] | null;
}

/**
 * Log streaming hook with circular buffer and per-level filtering.
 *
 * allLogsRef  — mutable circular buffer of ALL parsed entries (never triggers re-render).
 * logs state  — the currently visible subset after applying activeLevel filter.
 * streaming   — true while the execStream generator is running.
 * abortedRef  — boolean flag; set by stopStreaming() to break the generator loop.
 */
export function useLogs(bufferSize: number = DEFAULT_LOG_BUFFER_SIZE): UseLogsResult {
  const { execStream } = useSSH();

  // All parsed log entries — kept as a mutable ref to avoid re-renders on every append
  const allLogsRef = useRef<LogEntry[]>([]);

  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [streaming, setStreaming] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [activeLevel, setActiveLevel] = useState<LogEntry['level'] | null>(null);

  // Abort flag — checked by the streaming loop on each line
  const abortedRef = useRef<boolean>(false);

  // Keep activeLevel in a ref so the streaming closure always reads the latest value
  const activeLevelRef = useRef<LogEntry['level'] | null>(null);

  useEffect(() => {
    activeLevelRef.current = activeLevel;
  }, [activeLevel]);

  /** Append a new entry to the circular buffer and refresh the visible state. */
  const appendEntry = useCallback((entry: LogEntry): void => {
    const all = allLogsRef.current;
    all.push(entry);

    // Trim oldest entries when the buffer overflows
    if (all.length > bufferSize) {
      allLogsRef.current = all.slice(all.length - bufferSize);
    }

    setLogs(
      activeLevelRef.current === null
        ? [...allLogsRef.current]
        : allLogsRef.current.filter((e) => e.level === activeLevelRef.current),
    );
  }, [bufferSize]);

  const startStreaming = useCallback(
    (container: string): void => {
      abortedRef.current = false;
      setStreaming(true);
      setError(null);

      // Run the async generator in a detached promise so React state updates work normally
      void (async () => {
        try {
          const command = `docker logs -f --tail 200 --timestamps ${container} 2>&1`;
          const generator = execStream(command);

          for await (const line of generator) {
            if (abortedRef.current) break;
            if (line.trim() === '') continue;

            const entry = parseDockerLog(line, container);
            appendEntry(entry);
          }
        } catch (err) {
          if (!abortedRef.current) {
            const message = err instanceof Error ? err.message : String(err);
            setError(message);
          }
        } finally {
          setStreaming(false);
        }
      })();
    },
    [execStream, appendEntry],
  );

  const stopStreaming = useCallback((): void => {
    abortedRef.current = true;
  }, []);

  const clearLogs = useCallback((): void => {
    allLogsRef.current = [];
    setLogs([]);
  }, []);

  const filterByLevel = useCallback((level: LogEntry['level'] | null): void => {
    setActiveLevel(level);
    activeLevelRef.current = level;

    const all = allLogsRef.current;
    setLogs(level === null ? [...all] : all.filter((e) => e.level === level));
  }, []);

  const searchLogs = useCallback((query: string): LogEntry[] => {
    if (query.trim() === '') return [...allLogsRef.current];

    const lower = query.toLowerCase();
    return allLogsRef.current.filter((e) => e.message.toLowerCase().includes(lower));
  }, []);

  return {
    logs,
    streaming,
    error,
    startStreaming,
    stopStreaming,
    clearLogs,
    filterByLevel,
    searchLogs,
    activeLevel,
  };
}
