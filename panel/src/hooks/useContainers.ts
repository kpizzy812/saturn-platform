import { useState, useCallback } from 'react';
import { useSSH } from '../ssh/context.js';
import { useInterval } from './useInterval.js';
import type { SaturnEnv, ContainerInfo } from '../config/types.js';
import { ENVIRONMENTS, DEFAULT_POLL_INTERVAL_MS, allContainers } from '../config/constants.js';

export interface UseContainersResult {
  containers: Record<SaturnEnv, ContainerInfo[]>;
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
}

// Initial empty state — one empty array per environment
const EMPTY_CONTAINERS: Record<SaturnEnv, ContainerInfo[]> = {
  dev: [],
  staging: [],
  production: [],
};

/**
 * Poll docker stats and docker ps output for all three Saturn environments.
 *
 * Stats command format (tab-delimited):
 *   Name \t CPUPerc \t MemUsage \t MemPerc \t NetIO \t BlockIO
 *
 * Status is fetched separately via docker ps --filter and then merged by name.
 */
export function useContainers(
  pollIntervalMs: number = DEFAULT_POLL_INTERVAL_MS,
): UseContainersResult {
  const { exec } = useSSH();
  const [containers, setContainers] = useState<Record<SaturnEnv, ContainerInfo[]>>(
    EMPTY_CONTAINERS,
  );
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async (): Promise<void> => {
    setLoading(true);
    setError(null);

    try {
      // Collect all container names for every environment
      const allNames = ENVIRONMENTS.flatMap((env) => allContainers(env));

      // Run docker stats (--no-stream for a one-shot snapshot) and docker ps in parallel
      const [statsOutput, psOutput] = await Promise.all([
        exec(
          `docker stats --no-stream --format '{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}' ${allNames.join(' ')} 2>/dev/null || true`,
        ),
        exec(
          `docker ps --no-trunc --format '{{.Names}}\t{{.Status}}' 2>/dev/null || true`,
        ),
      ]);

      // Parse docker ps into a name → status map
      const statusMap = parseDockerPs(psOutput);

      // Parse stats lines into a name → partial ContainerInfo map
      const statsMap = parseDockerStats(statsOutput);

      // Build the final record grouped by environment
      const result: Record<SaturnEnv, ContainerInfo[]> = {
        dev: [],
        staging: [],
        production: [],
      };

      for (const env of ENVIRONMENTS) {
        result[env] = allContainers(env).map((name) => {
          const stats = statsMap[name];
          const status = statusMap[name] ?? 'stopped';

          return {
            name,
            status,
            cpu: stats?.cpu ?? '0.00%',
            memory: stats?.memory ?? '0B',
            memoryLimit: stats?.memoryLimit ?? '0B',
            memoryPercent: stats?.memoryPercent ?? '0.00%',
            netIO: stats?.netIO ?? '0B / 0B',
            blockIO: stats?.blockIO ?? '0B / 0B',
          };
        });
      }

      setContainers(result);
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [exec]);

  // Poll on the given interval; null would pause it (we always poll here)
  useInterval(refresh, pollIntervalMs);

  return { containers, loading, error, refresh };
}

// ---------------------------------------------------------------------------
// Parsers (module-private)
// ---------------------------------------------------------------------------

interface StatsEntry {
  cpu: string;
  memory: string;
  memoryLimit: string;
  memoryPercent: string;
  netIO: string;
  blockIO: string;
}

/**
 * Parse the tab-delimited output of docker stats --no-stream.
 * Format: Name \t CPUPerc \t MemUsage/MemLimit \t MemPerc \t NetIO \t BlockIO
 */
function parseDockerStats(output: string): Record<string, StatsEntry> {
  const map: Record<string, StatsEntry> = {};

  for (const line of output.split('\n')) {
    const trimmed = line.trim();
    if (trimmed === '') continue;

    const parts = trimmed.split('\t');
    if (parts.length < 6) continue;

    const [name, cpu, memUsage, memPercent, netIO, blockIO] = parts as [
      string,
      string,
      string,
      string,
      string,
      string,
    ];

    // MemUsage is "123MiB / 1GiB" — split on " / "
    const memParts = memUsage.split(' / ');
    const memory = memParts[0]?.trim() ?? '0B';
    const memoryLimit = memParts[1]?.trim() ?? '0B';

    map[name.trim()] = {
      cpu: cpu.trim(),
      memory,
      memoryLimit,
      memoryPercent: memPercent.trim(),
      netIO: netIO.trim(),
      blockIO: blockIO.trim(),
    };
  }

  return map;
}

/**
 * Parse the tab-delimited output of docker ps.
 * Format: Names \t Status
 * Returns a name → status string map.
 */
function parseDockerPs(output: string): Record<string, string> {
  const map: Record<string, string> = {};

  for (const line of output.split('\n')) {
    const trimmed = line.trim();
    if (trimmed === '') continue;

    const tabIdx = trimmed.indexOf('\t');
    if (tabIdx === -1) continue;

    const name = trimmed.slice(0, tabIdx).trim();
    const status = trimmed.slice(tabIdx + 1).trim();

    if (name) {
      map[name] = status;
    }
  }

  return map;
}
