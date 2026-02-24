export type SaturnEnv = 'dev' | 'staging' | 'production';

export interface SSHConfig {
  host: string;
  port: number;
  username: string;
  privateKeyPath: string;
}

export interface PanelConfig {
  ssh: SSHConfig;
  defaultEnv: SaturnEnv;
  logBufferSize: number;
  pollIntervalMs: number;
  githubRepo: string; // owner/repo format
}

export interface ContainerInfo {
  name: string;
  status: string;
  cpu: string;
  memory: string;
  memoryLimit: string;
  memoryPercent: string;
  netIO: string;
  blockIO: string;
}

export interface LogEntry {
  id: string;
  timestamp: string;
  message: string;
  level: 'info' | 'error' | 'warning' | 'debug';
  source: string;
}
