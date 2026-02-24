import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from 'react';
import { SSHConnectionManager } from './connection.js';
import { exec as sshExec, execStream as sshExecStream } from './exec.js';
import type { SSHConfig } from '../config/types.js';

interface SSHContextValue {
  connected: boolean;
  connecting: boolean;
  error: string | null;
  connect: (config: SSHConfig) => Promise<void>;
  disconnect: () => Promise<void>;
  exec: (cmd: string) => Promise<string>;
  execStream: (cmd: string) => AsyncGenerator<string, void, unknown>;
}

const SSHContext = createContext<SSHContextValue | null>(null);

interface SSHProviderProps {
  children: ReactNode;
  /**
   * Optional initial config — when provided the provider will attempt to
   * connect automatically on mount (e.g. from a stored config).
   */
  initialConfig?: SSHConfig;
}

export function SSHProvider({ children, initialConfig }: SSHProviderProps): React.JSX.Element {
  const [connected, setConnected] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Mirror live connection state changes coming from the manager
  useEffect(() => {
    const manager = SSHConnectionManager.getInstance();

    // Sync initial state in case the manager was already connected
    setConnected(manager.isConnected());

    const unsubscribe = manager.onStatusChange((isConnected) => {
      setConnected(isConnected);
      if (isConnected) {
        setConnecting(false);
        setError(null);
      }
    });

    return unsubscribe;
  }, []);

  // Attempt connection with stored/initial config on mount
  useEffect(() => {
    if (!initialConfig) return;

    let cancelled = false;

    const attemptConnect = async (): Promise<void> => {
      if (cancelled) return;
      setConnecting(true);
      setError(null);
      try {
        await SSHConnectionManager.getInstance().connect(initialConfig);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : String(err));
          setConnecting(false);
        }
      }
    };

    void attemptConnect();

    return () => {
      cancelled = true;
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const connect = useCallback(async (config: SSHConfig): Promise<void> => {
    setConnecting(true);
    setError(null);
    try {
      await SSHConnectionManager.getInstance().connect(config);
      // connected state is updated by the onStatusChange listener above
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      setError(message);
      setConnecting(false);
      throw err;
    }
  }, []);

  const disconnect = useCallback(async (): Promise<void> => {
    await SSHConnectionManager.getInstance().disconnect();
    setConnected(false);
    setConnecting(false);
    setError(null);
  }, []);

  const exec = useCallback((cmd: string): Promise<string> => {
    return sshExec(cmd);
  }, []);

  // Returns a fresh generator each call — no caching needed
  const execStream = useCallback(
    (cmd: string): AsyncGenerator<string, void, unknown> => {
      return sshExecStream(cmd);
    },
    [],
  );

  const value: SSHContextValue = {
    connected,
    connecting,
    error,
    connect,
    disconnect,
    exec,
    execStream,
  };

  return <SSHContext.Provider value={value}>{children}</SSHContext.Provider>;
}

export function useSSH(): SSHContextValue {
  const ctx = useContext(SSHContext);
  if (!ctx) {
    throw new Error('useSSH must be used within SSHProvider');
  }
  return ctx;
}
