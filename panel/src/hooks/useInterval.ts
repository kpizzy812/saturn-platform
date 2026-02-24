import { useEffect, useRef } from 'react';

/**
 * Declarative setInterval hook with automatic cleanup.
 *
 * - Stores the latest callback in a ref so the interval never captures a stale closure.
 * - Pass null as delayMs to pause the interval without unmounting the hook.
 * - The interval is recreated whenever delayMs changes.
 */
export function useInterval(callback: () => void, delayMs: number | null): void {
  // Always keep the ref pointing at the most recent callback
  const callbackRef = useRef<() => void>(callback);

  useEffect(() => {
    callbackRef.current = callback;
  }, [callback]);

  useEffect(() => {
    // null means the caller wants the interval paused
    if (delayMs === null) return;

    const id = setInterval(() => {
      callbackRef.current();
    }, delayMs);

    return () => {
      clearInterval(id);
    };
  }, [delayMs]);
}
