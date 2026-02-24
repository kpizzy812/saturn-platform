import { describe, it, expect, beforeEach, vi } from 'vitest';
import { homedir } from 'node:os';
import { join } from 'node:path';

// ---------------------------------------------------------------------------
// Hoist mutable state so it's available inside vi.mock factory (TDZ-safe)
// ---------------------------------------------------------------------------
const { mockStore, mockPath } = vi.hoisted(() => {
  const mockStore: Record<string, unknown> = {};
  const mockPath = { value: '/tmp/saturn-panel-test/config.json' };
  return { mockStore, mockPath };
});

vi.mock('conf', () => {
  return {
    default: class MockConf {
      private _defaults: Record<string, unknown>;

      constructor(opts: { defaults?: Record<string, unknown>; projectName?: string; configName?: string }) {
        this._defaults = opts.defaults ?? {};
        // Pre-populate store with defaults on first construction
        for (const [k, v] of Object.entries(this._defaults)) {
          if (!(k in mockStore)) {
            mockStore[k] = JSON.parse(JSON.stringify(v));
          }
        }
      }

      get path() {
        return mockPath.value;
      }

      get(key: string, fallback?: unknown): unknown {
        const parts = key.split('.');
        let val: unknown = mockStore;
        for (const part of parts) {
          if (val === null || typeof val !== 'object') return fallback;
          val = (val as Record<string, unknown>)[part];
        }
        return val !== undefined ? val : fallback;
      }

      set(key: string, value: unknown): void {
        const parts = key.split('.');
        let obj = mockStore as Record<string, unknown>;
        for (let i = 0; i < parts.length - 1; i++) {
          const part = parts[i]!;
          if (typeof obj[part] !== 'object' || obj[part] === null) {
            obj[part] = {};
          }
          obj = obj[part] as Record<string, unknown>;
        }
        obj[parts[parts.length - 1]!] = value;
      }
    },
  };
});

// Import after mock is in place
import {
  loadConfig,
  saveConfig,
  getConfigPath,
} from '../../src/config/loader.js';

import {
  VPS_HOST,
  VPS_USER,
  DEFAULT_SSH_PORT,
  DEFAULT_LOG_BUFFER_SIZE,
  DEFAULT_POLL_INTERVAL_MS,
  GITHUB_REPO,
  ENVIRONMENTS,
  ENV_DOMAINS,
  DATA_DIR,
  DEPLOY_SCRIPT,
  SCREEN_KEYS,
  containerName,
  allContainers,
  SOURCE_DIR,
  BACKUP_DIR,
  ENV_FILE,
} from '../../src/config/constants.js';

// Helper: reset store to known defaults before each loader test
function resetStore(): void {
  for (const key of Object.keys(mockStore)) {
    delete mockStore[key];
  }
  Object.assign(mockStore, {
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
  });
}

// ---------------------------------------------------------------------------
// constants.ts
// ---------------------------------------------------------------------------
describe('constants', () => {
  describe('VPS & SSH constants', () => {
    it('exports correct VPS host', () => {
      expect(VPS_HOST).toBe('157.180.57.47');
    });

    it('exports correct VPS user', () => {
      expect(VPS_USER).toBe('root');
    });

    it('exports default SSH port 22', () => {
      expect(DEFAULT_SSH_PORT).toBe(22);
    });
  });

  describe('ENVIRONMENTS', () => {
    it('contains all three environments in order', () => {
      expect(ENVIRONMENTS).toEqual(['dev', 'staging', 'production']);
    });
  });

  describe('ENV_DOMAINS', () => {
    it('maps dev to dev.saturn.ac', () => {
      expect(ENV_DOMAINS.dev).toBe('dev.saturn.ac');
    });

    it('maps staging to uat.saturn.ac', () => {
      expect(ENV_DOMAINS.staging).toBe('uat.saturn.ac');
    });

    it('maps production to saturn.ac', () => {
      expect(ENV_DOMAINS.production).toBe('saturn.ac');
    });
  });

  describe('DATA_DIR', () => {
    it('equals /data/saturn', () => {
      expect(DATA_DIR).toBe('/data/saturn');
    });
  });

  describe('DEPLOY_SCRIPT', () => {
    it('equals deploy/scripts/deploy.sh', () => {
      expect(DEPLOY_SCRIPT).toBe('deploy/scripts/deploy.sh');
    });
  });

  describe('containerName()', () => {
    it('returns saturn-dev for app/dev', () => {
      expect(containerName('app', 'dev')).toBe('saturn-dev');
    });

    it('returns saturn-db-staging for db/staging', () => {
      expect(containerName('db', 'staging')).toBe('saturn-db-staging');
    });

    it('returns saturn-redis-production for redis/production', () => {
      expect(containerName('redis', 'production')).toBe('saturn-redis-production');
    });

    it('returns saturn-realtime-dev for realtime/dev', () => {
      expect(containerName('realtime', 'dev')).toBe('saturn-realtime-dev');
    });

    it('returns saturn-production for app/production', () => {
      expect(containerName('app', 'production')).toBe('saturn-production');
    });
  });

  describe('allContainers()', () => {
    it('returns exactly 4 containers for dev', () => {
      const containers = allContainers('dev');
      expect(containers).toHaveLength(4);
    });

    it('returns correct dev container names', () => {
      const containers = allContainers('dev');
      expect(containers).toContain('saturn-dev');
      expect(containers).toContain('saturn-db-dev');
      expect(containers).toContain('saturn-redis-dev');
      expect(containers).toContain('saturn-realtime-dev');
    });

    it('returns correct production container names', () => {
      const containers = allContainers('production');
      expect(containers).toContain('saturn-production');
      expect(containers).toContain('saturn-db-production');
      expect(containers).toContain('saturn-redis-production');
      expect(containers).toContain('saturn-realtime-production');
    });

    it('returns correct staging container names', () => {
      const containers = allContainers('staging');
      expect(containers).toContain('saturn-staging');
      expect(containers).toContain('saturn-db-staging');
    });
  });

  describe('path helpers', () => {
    it('SOURCE_DIR returns correct path for dev', () => {
      expect(SOURCE_DIR('dev')).toBe('/data/saturn/dev/source');
    });

    it('SOURCE_DIR returns correct path for production', () => {
      expect(SOURCE_DIR('production')).toBe('/data/saturn/production/source');
    });

    it('BACKUP_DIR returns correct path for staging', () => {
      expect(BACKUP_DIR('staging')).toBe('/data/saturn/staging/backups');
    });

    it('BACKUP_DIR returns correct path for dev', () => {
      expect(BACKUP_DIR('dev')).toBe('/data/saturn/dev/backups');
    });

    it('ENV_FILE returns correct path for production', () => {
      expect(ENV_FILE('production')).toBe('/data/saturn/production/source/.env');
    });

    it('ENV_FILE returns correct path for dev', () => {
      expect(ENV_FILE('dev')).toBe('/data/saturn/dev/source/.env');
    });
  });

  describe('default values', () => {
    it('DEFAULT_LOG_BUFFER_SIZE is 1000', () => {
      expect(DEFAULT_LOG_BUFFER_SIZE).toBe(1000);
    });

    it('DEFAULT_POLL_INTERVAL_MS is 5000', () => {
      expect(DEFAULT_POLL_INTERVAL_MS).toBe(5000);
    });

    it('GITHUB_REPO is correct', () => {
      expect(GITHUB_REPO).toBe('AgazadeAV/coolify-Saturn');
    });
  });

  describe('SCREEN_KEYS', () => {
    it('maps 1 to dashboard', () => {
      expect(SCREEN_KEYS['1']).toBe('dashboard');
    });

    it('maps 2 to git', () => {
      expect(SCREEN_KEYS['2']).toBe('git');
    });

    it('maps 3 to deploy', () => {
      expect(SCREEN_KEYS['3']).toBe('deploy');
    });

    it('maps 4 to logs', () => {
      expect(SCREEN_KEYS['4']).toBe('logs');
    });

    it('maps 5 to containers', () => {
      expect(SCREEN_KEYS['5']).toBe('containers');
    });

    it('maps 6 to database', () => {
      expect(SCREEN_KEYS['6']).toBe('database');
    });

    it('maps 7 to env', () => {
      expect(SCREEN_KEYS['7']).toBe('env');
    });
  });
});

// ---------------------------------------------------------------------------
// loader.ts
// ---------------------------------------------------------------------------
describe('loader', () => {
  beforeEach(() => {
    resetStore();
  });

  describe('loadConfig()', () => {
    it('returns default SSH host', () => {
      const cfg = loadConfig();
      expect(cfg.ssh.host).toBe('157.180.57.47');
    });

    it('returns default SSH port', () => {
      const cfg = loadConfig();
      expect(cfg.ssh.port).toBe(22);
    });

    it('returns default SSH username root', () => {
      const cfg = loadConfig();
      expect(cfg.ssh.username).toBe('root');
    });

    it('returns privateKeyPath pointing to ~/.ssh/id_rsa', () => {
      const cfg = loadConfig();
      expect(cfg.ssh.privateKeyPath).toBe(join(homedir(), '.ssh', 'id_rsa'));
    });

    it('returns defaultEnv as dev', () => {
      const cfg = loadConfig();
      expect(cfg.defaultEnv).toBe('dev');
    });

    it('returns default logBufferSize of 1000', () => {
      const cfg = loadConfig();
      expect(cfg.logBufferSize).toBe(1000);
    });

    it('returns default pollIntervalMs of 5000', () => {
      const cfg = loadConfig();
      expect(cfg.pollIntervalMs).toBe(5000);
    });

    it('returns default githubRepo', () => {
      const cfg = loadConfig();
      expect(cfg.githubRepo).toBe('AgazadeAV/coolify-Saturn');
    });

    it('returns a complete PanelConfig shape', () => {
      const cfg = loadConfig();
      expect(cfg).toHaveProperty('ssh');
      expect(cfg).toHaveProperty('ssh.host');
      expect(cfg).toHaveProperty('ssh.port');
      expect(cfg).toHaveProperty('ssh.username');
      expect(cfg).toHaveProperty('ssh.privateKeyPath');
      expect(cfg).toHaveProperty('defaultEnv');
      expect(cfg).toHaveProperty('logBufferSize');
      expect(cfg).toHaveProperty('pollIntervalMs');
      expect(cfg).toHaveProperty('githubRepo');
    });
  });

  describe('saveConfig()', () => {
    it('persists a new defaultEnv to staging', () => {
      saveConfig({ defaultEnv: 'staging' });
      expect(loadConfig().defaultEnv).toBe('staging');
    });

    it('persists a new defaultEnv to production', () => {
      saveConfig({ defaultEnv: 'production' });
      expect(loadConfig().defaultEnv).toBe('production');
    });

    it('persists a new logBufferSize', () => {
      saveConfig({ logBufferSize: 500 });
      expect(loadConfig().logBufferSize).toBe(500);
    });

    it('persists a new pollIntervalMs', () => {
      saveConfig({ pollIntervalMs: 10000 });
      expect(loadConfig().pollIntervalMs).toBe(10000);
    });

    it('persists a new githubRepo', () => {
      saveConfig({ githubRepo: 'other/repo' });
      expect(loadConfig().githubRepo).toBe('other/repo');
    });

    it('merges partial SSH host without overwriting port', () => {
      saveConfig({ ssh: { host: '10.0.0.1' } as any });
      const cfg = loadConfig();
      expect(cfg.ssh.host).toBe('10.0.0.1');
      expect(cfg.ssh.port).toBe(22);
    });

    it('merges partial SSH port without overwriting host', () => {
      saveConfig({ ssh: { port: 2222 } as any });
      const cfg = loadConfig();
      expect(cfg.ssh.port).toBe(2222);
      expect(cfg.ssh.host).toBe('157.180.57.47');
    });

    it('saves multiple fields at once', () => {
      saveConfig({ defaultEnv: 'production', logBufferSize: 200, pollIntervalMs: 3000 });
      const cfg = loadConfig();
      expect(cfg.defaultEnv).toBe('production');
      expect(cfg.logBufferSize).toBe(200);
      expect(cfg.pollIntervalMs).toBe(3000);
    });

    it('handles empty partial without throwing', () => {
      expect(() => saveConfig({})).not.toThrow();
    });

    it('does not mutate unrelated keys on partial save', () => {
      saveConfig({ logBufferSize: 777 });
      const cfg = loadConfig();
      // githubRepo must remain unchanged
      expect(cfg.githubRepo).toBe('AgazadeAV/coolify-Saturn');
    });
  });

  describe('getConfigPath()', () => {
    it('returns a non-empty string', () => {
      const p = getConfigPath();
      expect(typeof p).toBe('string');
      expect(p.length).toBeGreaterThan(0);
    });

    it('returns the mocked path value', () => {
      expect(getConfigPath()).toBe('/tmp/saturn-panel-test/config.json');
    });
  });
});
