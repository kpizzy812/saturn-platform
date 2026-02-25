import { exec, execStream } from '../ssh/exec.js';
import { allContainers } from '../config/constants.js';
import type { SaturnEnv, ContainerInfo } from '../config/types.js';

/**
 * Fetch live resource stats for all containers belonging to the given environment.
 * Uses docker stats --no-stream so it returns immediately instead of streaming.
 * Containers that are stopped will be caught and returned with placeholder values.
 */
export async function getContainerStats(env: SaturnEnv): Promise<ContainerInfo[]> {
  const containers = allContainers(env);

  const results: ContainerInfo[] = [];

  for (const name of containers) {
    try {
      // --no-stream: single snapshot, not a live stream
      const raw = await exec(
        `docker stats --no-stream --format '{{.Name}}\t{{.Status}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}' ${name}`,
      );

      const line = raw.trim();
      if (!line) {
        results.push(stoppedContainer(name));
        continue;
      }

      const parts = line.split('\t');
      // Guard: docker stats output must have exactly 7 tab-separated fields
      if (parts.length < 7) {
        results.push(stoppedContainer(name));
        continue;
      }

      const [, status, cpu, memUsage, memPercent, netIO, blockIO] = parts;

      // MemUsage is "used / limit" — split on the slash
      const memParts = (memUsage ?? '').split('/');
      const memory = memParts[0]?.trim() ?? '0B';
      const memoryLimit = memParts[1]?.trim() ?? '0B';

      results.push({
        name,
        status: status ?? 'unknown',
        cpu: cpu ?? '0.00%',
        memory,
        memoryLimit,
        memoryPercent: memPercent ?? '0.00%',
        netIO: netIO ?? '0B / 0B',
        blockIO: blockIO ?? '0B / 0B',
      });
    } catch {
      // Container is not running or does not exist — surface a safe placeholder
      results.push(stoppedContainer(name));
    }
  }

  return results;
}

/**
 * Build a ContainerInfo record representing a stopped/unreachable container.
 */
function stoppedContainer(name: string): ContainerInfo {
  return {
    name,
    status: 'stopped',
    cpu: '0.00%',
    memory: '0B',
    memoryLimit: '0B',
    memoryPercent: '0.00%',
    netIO: '0B / 0B',
    blockIO: '0B / 0B',
  };
}

/**
 * Return status and state for all saturn- containers that belong to this env.
 * Uses docker ps -a so stopped containers are included.
 */
export async function getContainerStatus(
  env: SaturnEnv,
): Promise<Array<{ name: string; status: string; health: string }>> {
  const raw = await exec(
    `docker ps -a --format '{{.Names}}\t{{.Status}}\t{{.State}}' --filter name=saturn-${env}`,
  );

  const envContainers = new Set(allContainers(env));
  const rows: Array<{ name: string; status: string; health: string }> = [];

  for (const line of raw.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed) continue;

    const parts = trimmed.split('\t');
    if (parts.length < 3) continue;

    const [name, status, state] = parts;

    // Only include containers that belong to this specific environment
    if (!name || !envContainers.has(name)) continue;

    rows.push({
      name,
      status: status ?? 'unknown',
      health: state ?? 'unknown',
    });
  }

  return rows;
}

/**
 * Restart a running or stopped container by name.
 * Returns raw docker output (typically the container name on success).
 */
export async function restartContainer(container: string): Promise<string> {
  return exec(`docker restart ${container}`);
}

/**
 * Stop a running container by name.
 * Returns raw docker output (typically the container name on success).
 */
export async function stopContainer(container: string): Promise<string> {
  return exec(`docker stop ${container}`);
}

/**
 * Start a stopped container by name.
 * Returns raw docker output (typically the container name on success).
 */
export async function startContainer(container: string): Promise<string> {
  return exec(`docker start ${container}`);
}

/**
 * Stream logs from a container in real time.
 * Merges stderr into stdout via 2>&1 so callers receive a single unified stream.
 *
 * @param container  Container name
 * @param tail       Number of historical lines to show before following
 */
export async function* streamLogs(
  container: string,
  tail: number = 200,
): AsyncGenerator<string> {
  yield* execStream(
    `docker logs -f --tail ${tail} --timestamps ${container} 2>&1`,
  );
}

/**
 * Fetch the last <tail> lines of container logs without following.
 * Merges stderr into stdout so the caller gets a single string.
 */
export async function getLogs(container: string, tail: number = 100): Promise<string> {
  return exec(`docker logs --tail ${tail} --timestamps ${container} 2>&1`);
}

/**
 * Run docker ps -a filtered to the given environment and return the formatted table.
 * The result includes container name, status, exposed ports, and image.
 */
export async function getDockerPS(env: SaturnEnv): Promise<string> {
  return exec(
    `docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}\t{{.Image}}' --filter name=saturn-${env}`,
  );
}
