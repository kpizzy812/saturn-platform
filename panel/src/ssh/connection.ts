import { readFileSync } from 'fs';
import { Client, type ConnectConfig, type ClientChannel } from 'ssh2';
import type { SSHConfig } from '../config/types.js';

// Backoff delays in milliseconds: 1s, 2s, 4s, 8s, 16s, 30s (capped)
const BACKOFF_DELAYS_MS = [1000, 2000, 4000, 8000, 16000, 30000];

// Connection timeout in milliseconds
const CONNECT_TIMEOUT_MS = 20000;

export class SSHConnectionManager {
  private static instance: SSHConnectionManager | null = null;

  private client: Client | null = null;
  private config: ConnectConfig | null = null;
  private connected = false;
  private reconnecting = false;
  private destroyed = false;
  private reconnectAttempt = 0;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private listeners: Set<(connected: boolean) => void> = new Set();

  // Private constructor enforces singleton usage
  private constructor() {}

  static getInstance(): SSHConnectionManager {
    if (!SSHConnectionManager.instance) {
      SSHConnectionManager.instance = new SSHConnectionManager();
    }
    return SSHConnectionManager.instance;
  }

  // Reset singleton — intended for use in tests only
  static resetInstance(): void {
    if (SSHConnectionManager.instance) {
      SSHConnectionManager.instance.destroyed = true;
      SSHConnectionManager.instance.clearReconnectTimer();
      const client = SSHConnectionManager.instance.client;
      if (client) {
        client.removeAllListeners();
        try {
          client.end();
        } catch {
          // Ignore errors during forced teardown
        }
      }
    }
    SSHConnectionManager.instance = null;
  }

  async connect(config: SSHConfig): Promise<void> {
    this.destroyed = false;
    this.reconnectAttempt = 0;
    this.reconnecting = false;

    const connectConfig: ConnectConfig = {
      host: config.host,
      port: config.port,
      username: config.username,
      privateKey: readFileSync(config.privateKeyPath),
      readyTimeout: CONNECT_TIMEOUT_MS,
    };

    this.config = connectConfig;
    await this.createAndConnect();
  }

  async disconnect(): Promise<void> {
    this.destroyed = true;
    this.reconnecting = false;
    this.clearReconnectTimer();

    if (this.client) {
      this.client.removeAllListeners();
      try {
        this.client.end();
      } catch {
        // Ignore errors during intentional disconnect
      }
      this.client = null;
    }

    if (this.connected) {
      this.connected = false;
      this.notifyListeners();
    }
  }

  isConnected(): boolean {
    return this.connected;
  }

  // Returns an unsubscribe function
  onStatusChange(listener: (connected: boolean) => void): () => void {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  }

  async exec(command: string): Promise<string> {
    const stream = await this.getChannel(command);

    return new Promise<string>((resolve, reject) => {
      let stdout = '';
      let stderr = '';

      stream.on('data', (chunk: Buffer) => {
        stdout += chunk.toString('utf8');
      });

      stream.stderr.on('data', (chunk: Buffer) => {
        stderr += chunk.toString('utf8');
      });

      stream.on('close', (code: number | null) => {
        if (code !== 0 && stderr) {
          reject(new Error(`Command failed (exit ${code ?? 'null'}): ${stderr.trim()}`));
        } else {
          resolve(stdout);
        }
      });

      stream.on('error', (err: Error) => {
        reject(err);
      });
    });
  }

  async *execStream(command: string): AsyncGenerator<string, void, unknown> {
    const stream = await this.getChannel(command);

    // Buffer for partial lines that span chunk boundaries
    let lineBuffer = '';

    const queue: string[] = [];
    let done = false;
    let error: Error | null = null;

    // Resolve/reject handle for the consumer waiting on the next item
    let notify: (() => void) | null = null;

    const wake = (): void => {
      if (notify) {
        const fn = notify;
        notify = null;
        fn();
      }
    };

    stream.on('data', (chunk: Buffer) => {
      const text = lineBuffer + chunk.toString('utf8');
      const lines = text.split('\n');
      // Keep the last (potentially incomplete) segment in the buffer
      lineBuffer = lines.pop() ?? '';
      for (const line of lines) {
        queue.push(line);
      }
      wake();
    });

    stream.stderr.on('data', (_chunk: Buffer) => {
      // stderr output is silently discarded in streaming mode;
      // callers can catch errors via the close event exit code
    });

    stream.on('close', () => {
      // Flush any remaining buffered content that had no trailing newline
      if (lineBuffer.length > 0) {
        queue.push(lineBuffer);
        lineBuffer = '';
      }
      done = true;
      wake();
    });

    stream.on('error', (err: Error) => {
      error = err;
      done = true;
      wake();
    });

    // Yield lines as they arrive
    while (true) {
      if (queue.length > 0) {
        yield queue.shift()!;
        continue;
      }

      if (done) {
        if (error) throw error;
        break;
      }

      // Wait until data or close fires
      await new Promise<void>((resolve) => {
        notify = resolve;
      });
    }
  }

  private getChannel(command: string): Promise<ClientChannel> {
    if (!this.client || !this.connected) {
      return Promise.reject(new Error('SSH client is not connected'));
    }

    return new Promise<ClientChannel>((resolve, reject) => {
      this.client!.exec(command, (err, stream) => {
        if (err) {
          reject(err);
          return;
        }
        resolve(stream);
      });
    });
  }

  private async createAndConnect(): Promise<void> {
    if (!this.config) {
      throw new Error('No SSH config provided — call connect() first');
    }

    // Tear down any previous client before creating a new one
    if (this.client) {
      this.client.removeAllListeners();
      try {
        this.client.end();
      } catch {
        // Ignore
      }
      this.client = null;
    }

    const client = new Client();
    this.client = client;

    return new Promise<void>((resolve, reject) => {
      let settled = false;

      const settle = (err?: Error): void => {
        if (settled) return;
        settled = true;
        if (err) {
          reject(err);
        } else {
          resolve();
        }
      };

      client.once('ready', () => {
        this.connected = true;
        this.reconnectAttempt = 0;
        this.notifyListeners();
        settle();
      });

      client.on('error', (err: Error) => {
        // Provide descriptive error context based on ssh2 level property
        const sshErr = err as Error & { level?: string };
        const level = sshErr.level ?? 'unknown';
        const message = `SSH error [${level}]: ${err.message}`;
        settle(new Error(message));
      });

      client.on('close', () => {
        if (this.connected) {
          this.connected = false;
          this.notifyListeners();
        }
        // settle() is a no-op here if 'ready' already fired
        settle(new Error('SSH connection closed before becoming ready'));

        // Initiate auto-reconnect unless we were intentionally destroyed
        if (!this.destroyed && !this.reconnecting) {
          void this.reconnect();
        }
      });

      client.connect(this.config!);
    });
  }

  private async reconnect(): Promise<void> {
    if (this.destroyed || !this.config) return;

    this.reconnecting = true;

    const delayMs = BACKOFF_DELAYS_MS[
      Math.min(this.reconnectAttempt, BACKOFF_DELAYS_MS.length - 1)
    ] ?? 30000;

    this.reconnectAttempt++;

    await new Promise<void>((resolve) => {
      this.reconnectTimer = setTimeout(resolve, delayMs);
    });

    if (this.destroyed) return;

    try {
      await this.createAndConnect();
      this.reconnecting = false;
    } catch {
      // Connection attempt failed — schedule another attempt
      if (!this.destroyed) {
        void this.reconnect();
      } else {
        this.reconnecting = false;
      }
    }
  }

  private clearReconnectTimer(): void {
    if (this.reconnectTimer !== null) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  private notifyListeners(): void {
    for (const listener of this.listeners) {
      try {
        listener(this.connected);
      } catch {
        // Prevent a broken listener from disrupting other listeners
      }
    }
  }
}
