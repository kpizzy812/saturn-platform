import { useState, useCallback, useRef } from 'react';
import { useSSH } from '../ssh/context.js';

export interface UseSSHStreamResult {
  lines: string[];
  streaming: boolean;
  error: string | null;
  start: (command: string) => Promise<void>;
  stop: () => void;
  clear: () => void;
}

/**
 * Streaming SSH command hook.
 * Accumulates lines from a long-running remote command into a circular buffer.
 * The stop() call sets an abort flag that the async generator loop checks on
 * every iteration — no AbortController needed since the generator is pure async.
 */
export function useSSHStream(maxLines: number = 1000): UseSSHStreamResult {
  const { execStream } = useSSH();
  const [lines, setLines] = useState<string[]>([]);
  const [streaming, setStreaming] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Boolean flag ref — set to true by stop() to break the generator loop
  const abortedRef = useRef<boolean>(false);

  const start = useCallback(
    async (command: string): Promise<void> => {
      // Reset abort flag before starting a new stream
      abortedRef.current = false;
      setStreaming(true);
      setError(null);

      try {
        const generator = execStream(command);

        for await (const line of generator) {
          // Check abort flag on every line received
          if (abortedRef.current) break;

          setLines((prev) => {
            const next = [...prev, line];
            // Circular buffer: trim oldest entries when limit is exceeded
            return next.length > maxLines ? next.slice(next.length - maxLines) : next;
          });
        }
      } catch (err) {
        if (!abortedRef.current) {
          const message = err instanceof Error ? err.message : String(err);
          setError(message);
        }
      } finally {
        setStreaming(false);
      }
    },
    [execStream, maxLines],
  );

  const stop = useCallback((): void => {
    abortedRef.current = true;
  }, []);

  const clear = useCallback((): void => {
    setLines([]);
  }, []);

  return { lines, streaming, error, start, stop, clear };
}
