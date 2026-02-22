import { useState, useCallback } from 'react';
import { useSSH } from '../ssh/context.js';

export interface UseSSHExecResult {
  output: string;
  loading: boolean;
  error: string | null;
  execute: (command: string) => Promise<string>;
}

/**
 * One-shot SSH command execution hook.
 * Returns the last output, loading state, error state, and an execute function.
 * The execute function returns the result string for use in call chains.
 */
export function useSSHExec(): UseSSHExecResult {
  const { exec } = useSSH();
  const [output, setOutput] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const execute = useCallback(
    async (command: string): Promise<string> => {
      setLoading(true);
      setError(null);
      try {
        const result = await exec(command);
        setOutput(result);
        return result;
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        setError(message);
        return '';
      } finally {
        setLoading(false);
      }
    },
    [exec],
  );

  return { output, loading, error, execute };
}
