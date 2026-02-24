import Conf from 'conf';
import { homedir } from 'node:os';
import { join } from 'node:path';
import type { PanelConfig } from './types.js';
import {
  VPS_HOST,
  VPS_USER,
  DEFAULT_SSH_PORT,
  DEFAULT_LOG_BUFFER_SIZE,
  DEFAULT_POLL_INTERVAL_MS,
  GITHUB_REPO,
} from './constants.js';

// Default config values — mirrors CLAUDE.md VPS and SSH settings
const DEFAULT_CONFIG: PanelConfig = {
  ssh: {
    host: VPS_HOST,
    port: DEFAULT_SSH_PORT,
    username: VPS_USER,
    privateKeyPath: join(homedir(), '.ssh', 'id_rsa'),
  },
  defaultEnv: 'dev',
  logBufferSize: DEFAULT_LOG_BUFFER_SIZE,
  pollIntervalMs: DEFAULT_POLL_INTERVAL_MS,
  githubRepo: GITHUB_REPO,
};

// Conf instance — stores to ~/.config/saturn-panel/config.json on macOS/Linux
const store = new Conf<PanelConfig>({
  projectName: 'saturn-panel',
  configName: 'config',
  defaults: DEFAULT_CONFIG,
});

/**
 * Load the full panel config. Falls back to DEFAULT_CONFIG for any missing keys.
 */
export function loadConfig(): PanelConfig {
  return {
    ssh: {
      host: store.get('ssh.host', DEFAULT_CONFIG.ssh.host) as string,
      port: store.get('ssh.port', DEFAULT_CONFIG.ssh.port) as number,
      username: store.get('ssh.username', DEFAULT_CONFIG.ssh.username) as string,
      privateKeyPath: store.get('ssh.privateKeyPath', DEFAULT_CONFIG.ssh.privateKeyPath) as string,
    },
    defaultEnv: store.get('defaultEnv', DEFAULT_CONFIG.defaultEnv),
    logBufferSize: store.get('logBufferSize', DEFAULT_CONFIG.logBufferSize) as number,
    pollIntervalMs: store.get('pollIntervalMs', DEFAULT_CONFIG.pollIntervalMs) as number,
    githubRepo: store.get('githubRepo', DEFAULT_CONFIG.githubRepo) as string,
  };
}

/**
 * Persist a partial or full config update to disk atomically.
 */
export function saveConfig(partial: Partial<PanelConfig>): void {
  if (partial.ssh !== undefined) {
    const current = loadConfig();
    const merged = { ...current.ssh, ...partial.ssh };
    store.set('ssh', merged);
  }
  if (partial.defaultEnv !== undefined) {
    store.set('defaultEnv', partial.defaultEnv);
  }
  if (partial.logBufferSize !== undefined) {
    store.set('logBufferSize', partial.logBufferSize);
  }
  if (partial.pollIntervalMs !== undefined) {
    store.set('pollIntervalMs', partial.pollIntervalMs);
  }
  if (partial.githubRepo !== undefined) {
    store.set('githubRepo', partial.githubRepo);
  }
}

/**
 * Return the absolute path to the config file on disk.
 */
export function getConfigPath(): string {
  return store.path;
}
