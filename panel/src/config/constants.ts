import type { SaturnEnv } from './types.js';

export const VPS_HOST = '157.180.57.47';
export const VPS_USER = 'root';
export const DEFAULT_SSH_PORT = 22;

export const ENVIRONMENTS: SaturnEnv[] = ['dev', 'staging', 'production'];

export const ENV_DOMAINS: Record<SaturnEnv, string> = {
  dev: 'dev.saturn.ac',
  staging: 'uat.saturn.ac',
  production: 'saturn.ac',
};

export const DATA_DIR = '/data/saturn';

// Container name patterns from docker-compose.env.yml
export function containerName(service: 'app' | 'db' | 'redis' | 'realtime', env: SaturnEnv): string {
  const prefixes = { app: 'saturn', db: 'saturn-db', redis: 'saturn-redis', realtime: 'saturn-realtime' };
  return `${prefixes[service]}-${env}`;
}

export function allContainers(env: SaturnEnv): string[] {
  return ['app', 'db', 'redis', 'realtime'].map(s => containerName(s as 'app' | 'db' | 'redis' | 'realtime', env));
}

export const DEPLOY_SCRIPT = 'deploy/scripts/deploy.sh';
export const SOURCE_DIR = (env: SaturnEnv) => `${DATA_DIR}/${env}/source`;
export const BACKUP_DIR = (env: SaturnEnv) => `${DATA_DIR}/${env}/backups`;
export const ENV_FILE = (env: SaturnEnv) => `${DATA_DIR}/${env}/source/.env`;

export const DEFAULT_LOG_BUFFER_SIZE = 1000;
export const DEFAULT_POLL_INTERVAL_MS = 5000;
export const GITHUB_REPO = 'AgazadeAV/coolify-Saturn';

export const SCREEN_KEYS = {
  '1': 'dashboard',
  '2': 'git',
  '3': 'deploy',
  '4': 'logs',
  '5': 'containers',
  '6': 'database',
  '7': 'env',
} as const;

export type ScreenName = typeof SCREEN_KEYS[keyof typeof SCREEN_KEYS];
