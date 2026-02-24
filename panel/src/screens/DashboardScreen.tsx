import React, { useState, useCallback } from 'react';
import { Box, Text } from 'ink';
import type { SaturnEnv, ContainerInfo } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge, statusColor } from '../components/shared/Badge.js';
import { useSSH } from '../ssh/context.js';
import { useInterval } from '../hooks/useInterval.js';
import { useSSHExec } from '../hooks/useSSHExec.js';
import { ENVIRONMENTS, ENV_DOMAINS, allContainers, SOURCE_DIR } from '../config/constants.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

// Per-environment snapshot collected on every poll
interface EnvSnapshot {
  containers: ContainerInfo[];
  branch: string;
  commit: string;
  error: string | null;
}

type EnvData = Record<SaturnEnv, EnvSnapshot>;

const EMPTY_SNAPSHOT: EnvSnapshot = {
  containers: [],
  branch: '—',
  commit: '—',
  error: null,
};

// Parse tab-delimited docker stats --no-stream output
function parseStats(raw: string, env: SaturnEnv): ContainerInfo[] {
  const names = allContainers(env);
  const statsMap: Record<string, Partial<ContainerInfo>> = {};

  for (const line of raw.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed) continue;
    const parts = trimmed.split('\t');
    if (parts.length < 5) continue;
    const [name, cpu, memUsage, memPercent, netIO] = parts as [
      string,
      string,
      string,
      string,
      string,
    ];
    const memParts = memUsage.split(' / ');
    statsMap[name.trim()] = {
      cpu: cpu.trim(),
      memory: memParts[0]?.trim() ?? '0B',
      memoryLimit: memParts[1]?.trim() ?? '0B',
      memoryPercent: memPercent.trim(),
      netIO: netIO.trim(),
    };
  }

  return names.map((name) => {
    const s = statsMap[name];
    return {
      name,
      status: s ? 'running' : 'stopped',
      cpu: s?.cpu ?? '0.00%',
      memory: s?.memory ?? '0B',
      memoryLimit: s?.memoryLimit ?? '0B',
      memoryPercent: s?.memoryPercent ?? '0.00%',
      netIO: s?.netIO ?? '0B / 0B',
      blockIO: '0B / 0B',
    };
  });
}

// Parse docker ps --format output into a name→status map
function parsePsStatus(raw: string): Record<string, string> {
  const map: Record<string, string> = {};
  for (const line of raw.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed) continue;
    const tabIdx = trimmed.indexOf('\t');
    if (tabIdx === -1) continue;
    const name = trimmed.slice(0, tabIdx).trim();
    const status = trimmed.slice(tabIdx + 1).trim();
    if (name) map[name] = status;
  }
  return map;
}

export function DashboardScreen({ env }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();
  const { execute } = useSSHExec();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<EnvData>({
    dev: { ...EMPTY_SNAPSHOT },
    staging: { ...EMPTY_SNAPSHOT },
    production: { ...EMPTY_SNAPSHOT },
  });

  const refresh = useCallback(async () => {
    if (!connected) return;
    setLoading(true);

    const nextData: EnvData = {
      dev: { ...EMPTY_SNAPSHOT },
      staging: { ...EMPTY_SNAPSHOT },
      production: { ...EMPTY_SNAPSHOT },
    };

    await Promise.all(
      ENVIRONMENTS.map(async (e) => {
        try {
          const nameList = allContainers(e).join(' ');

          const [statsRaw, psRaw, branchRaw, commitRaw] = await Promise.all([
            execute(
              `docker stats --no-stream --format '{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}' ${nameList} 2>/dev/null || true`,
            ),
            execute(
              `docker ps --format '{{.Names}}\t{{.Status}}' --filter name=saturn-${e} 2>/dev/null || true`,
            ),
            execute(
              `cd ${SOURCE_DIR(e)} && git rev-parse --abbrev-ref HEAD 2>/dev/null || echo '—'`,
            ),
            execute(
              `cd ${SOURCE_DIR(e)} && git log --oneline -1 2>/dev/null || echo '—'`,
            ),
          ]);

          const containers = parseStats(statsRaw, e);
          const psMap = parsePsStatus(psRaw);

          // Merge live docker ps status into stats records
          const merged = containers.map((c) => ({
            ...c,
            status: psMap[c.name] ?? c.status,
          }));

          nextData[e] = {
            containers: merged,
            branch: branchRaw.trim() || '—',
            commit: commitRaw.trim() || '—',
            error: null,
          };
        } catch (err) {
          nextData[e] = {
            ...EMPTY_SNAPSHOT,
            error: err instanceof Error ? err.message : String(err),
          };
        }
      }),
    );

    setData(nextData);
    setLoading(false);
  }, [connected, execute]);

  // Initial load + auto-refresh every 5s
  useInterval(refresh, connected ? 5000 : null);

  if (connecting) {
    return (
      <Box flexDirection="column" padding={1}>
        <Spinner label="Connecting to SSH…" />
      </Box>
    );
  }

  if (!connected) {
    return (
      <Box flexDirection="column" padding={1}>
        <Text bold color="red">SSH Disconnected</Text>
        {sshError && <Text color="red">{sshError}</Text>}
        <Text dimColor>Configure SSH in the config file and restart the panel.</Text>
      </Box>
    );
  }

  return (
    <Box flexDirection="column" padding={1}>
      <Box gap={2} alignItems="center">
        <Text bold color="cyan">Dashboard — All Environments</Text>
        {loading && <Spinner label="Refreshing…" />}
      </Box>

      <Box flexDirection="column" marginTop={1} gap={1}>
        {ENVIRONMENTS.map((e) => {
          const snapshot = data[e];
          const isActive = e === env;
          const domain = ENV_DOMAINS[e];

          return (
            <Box
              key={e}
              flexDirection="column"
              borderStyle="single"
              borderColor={isActive ? 'cyan' : 'gray'}
              paddingX={1}
            >
              {/* Environment header */}
              <Box gap={2}>
                <Text bold color={isActive ? 'cyan' : 'white'}>
                  {e.toUpperCase()}
                </Text>
                <Text dimColor>{domain}</Text>
                <Text dimColor>branch:</Text>
                <Text color="yellow">{snapshot.branch}</Text>
                <Text dimColor>commit:</Text>
                <Text>
                  {snapshot.commit.length > 50
                    ? snapshot.commit.slice(0, 49) + '\u2026'
                    : snapshot.commit}
                </Text>
              </Box>

              {/* Error state */}
              {snapshot.error && (
                <Box marginTop={1}>
                  <Text color="red">Error: {snapshot.error}</Text>
                </Box>
              )}

              {/* Container rows */}
              <Box flexDirection="column" marginTop={snapshot.error ? 0 : 1}>
                {snapshot.containers.map((c) => {
                  const running =
                    c.status.toLowerCase().startsWith('up') || c.status === 'running';
                  const color = statusColor(running ? 'running' : 'stopped');
                  const cpuVal = parseFloat(c.cpu);
                  const cpuColor = cpuVal > 80 ? 'red' : cpuVal > 50 ? 'yellow' : 'green';

                  return (
                    <Box key={c.name} gap={2}>
                      <Text color={color}>{running ? '●' : '○'}</Text>
                      <Box width={24}>
                        <Text>{c.name}</Text>
                      </Box>
                      <Badge label={running ? 'running' : 'stopped'} color={color} />
                      <Text dimColor>CPU:</Text>
                      <Box width={7}>
                        <Text color={running ? cpuColor : 'gray'}>{c.cpu}</Text>
                      </Box>
                      <Text dimColor>MEM:</Text>
                      <Box width={12}>
                        <Text>{c.memory}</Text>
                      </Box>
                      <Text dimColor>NET:</Text>
                      <Text>{c.netIO}</Text>
                    </Box>
                  );
                })}

                {snapshot.containers.length === 0 && !snapshot.error && (
                  <Text dimColor>No containers found.</Text>
                )}
              </Box>
            </Box>
          );
        })}
      </Box>

      <Box marginTop={1}>
        <Text dimColor>Auto-refresh every 5s  |  1-7: switch screen  |  E: switch env</Text>
      </Box>
    </Box>
  );
}
