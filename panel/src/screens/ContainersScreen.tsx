import React, { useState, useCallback } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SaturnEnv, ContainerInfo } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge, statusColor } from '../components/shared/Badge.js';
import { Table } from '../components/shared/Table.js';
import { ConfirmDialog } from '../components/shared/ConfirmDialog.js';
import { useSSH } from '../ssh/context.js';
import { useContainers } from '../hooks/useContainers.js';
import { useSSHExec } from '../hooks/useSSHExec.js';
import { restartContainer, stopContainer, startContainer } from '../services/docker.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

type ContainerAction = 'restart' | 'stop' | 'start';

// How often to auto-refresh stats (ms)
const REFRESH_INTERVAL_MS = 5000;

export function ContainersScreen({ env }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();
  const { containers, loading, error, refresh } = useContainers(REFRESH_INTERVAL_MS);
  const { loading: actionLoading, execute } = useSSHExec();

  const [selectedRow, setSelectedRow] = useState(0);
  const [dialog, setDialog] = useState<{ action: ContainerAction; container: ContainerInfo } | null>(null);
  const [actionResult, setActionResult] = useState<{ success: boolean; message: string } | null>(null);

  const envContainers = containers[env];

  // Clamp selected row to valid range after refresh
  const clampedRow = Math.min(selectedRow, Math.max(0, envContainers.length - 1));

  // Execute a container action via SSH exec and refresh afterwards
  const runAction = useCallback(
    async (action: ContainerAction, name: string) => {
      setActionResult(null);
      try {
        let result: string;
        if (action === 'restart') {
          result = await restartContainer(name);
        } else if (action === 'stop') {
          result = await stopContainer(name);
        } else {
          result = await startContainer(name);
        }
        setActionResult({
          success: true,
          message: `${action}: ${name} — ${result.trim() || 'ok'}`,
        });
      } catch (err) {
        setActionResult({
          success: false,
          message: `${action} failed: ${err instanceof Error ? err.message : String(err)}`,
        });
      }
      // Refresh stats after action completes
      void refresh();
    },
    [refresh],
  );

  // Suppress unused-variable warning — execute is provided by useSSHExec but
  // here we delegate to service helpers that call exec internally.
  void execute;

  // Keyboard handling — suspended while a dialog is open
  useInput((_input, key) => {
    if (dialog !== null) return;

    if (key.upArrow) {
      setSelectedRow((r) => Math.max(0, r - 1));
      return;
    }
    if (key.downArrow) {
      setSelectedRow((r) => Math.min(envContainers.length - 1, r + 1));
      return;
    }

    const selected = envContainers[clampedRow];
    if (!selected) return;

    if (_input === 'r') {
      setDialog({ action: 'restart', container: selected });
      return;
    }
    if (_input === 's') {
      setDialog({ action: 'stop', container: selected });
      return;
    }
    if (_input === 'S') {
      setDialog({ action: 'start', container: selected });
      return;
    }
    if (_input === 'R') {
      void refresh();
      return;
    }
  });

  // SSH not ready
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

  // Confirm dialog renders in place
  if (dialog !== null) {
    const { action, container } = dialog;
    const isDestructive = action === 'stop';
    return (
      <Box flexDirection="column" padding={1}>
        <Box marginBottom={1}>
          <Text bold color="cyan">Containers — {env.toUpperCase()}</Text>
        </Box>
        <ConfirmDialog
          message={`${action.charAt(0).toUpperCase() + action.slice(1)} container ${container.name}?`}
          destructive={isDestructive}
          onConfirm={() => {
            const actionCopy = action;
            const nameCopy = container.name;
            setDialog(null);
            void runAction(actionCopy, nameCopy);
          }}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  // Build table data
  type ContainerRow = {
    name: string;
    status: string;
    cpu: string;
    memory: string;
    memPct: string;
    netIO: string;
  };

  const tableColumns = [
    { header: 'Name', key: 'name' as keyof ContainerRow, width: 26 },
    { header: 'Status', key: 'status' as keyof ContainerRow, width: 10 },
    { header: 'CPU%', key: 'cpu' as keyof ContainerRow, width: 7, align: 'right' as const },
    { header: 'Memory', key: 'memory' as keyof ContainerRow, width: 12 },
    { header: 'Mem%', key: 'memPct' as keyof ContainerRow, width: 6, align: 'right' as const },
    { header: 'Net I/O', key: 'netIO' as keyof ContainerRow, width: 18 },
  ];

  const tableData: ContainerRow[] = envContainers.map((c) => ({
    name: c.name,
    status: c.status.toLowerCase().startsWith('up') ? 'running' : c.status,
    cpu: c.cpu,
    memory: c.memory,
    memPct: c.memoryPercent,
    netIO: c.netIO,
  }));

  const selectedContainer = envContainers[clampedRow];
  const selectedRunning = selectedContainer
    ? selectedContainer.status.toLowerCase().startsWith('up') || selectedContainer.status === 'running'
    : false;

  return (
    <Box flexDirection="column" padding={1}>
      {/* Screen header */}
      <Box gap={2} marginBottom={1} alignItems="center">
        <Text bold color="cyan">Containers — {env.toUpperCase()}</Text>
        {loading && <Spinner label="Refreshing…" />}
        {actionLoading && <Spinner label="Running action…" />}
        <Text dimColor>Auto-refresh every 5s</Text>
      </Box>

      {/* Error from useContainers */}
      {error && (
        <Box marginBottom={1}>
          <Text color="red">Error: {error}</Text>
        </Box>
      )}

      {/* Action result banner */}
      {actionResult && (
        <Box marginBottom={1}>
          <Text bold color={actionResult.success ? 'green' : 'red'}>
            {actionResult.success ? '✔ ' : '✘ '}
            {actionResult.message}
          </Text>
        </Box>
      )}

      {/* Container table */}
      {envContainers.length === 0 && !loading ? (
        <Text dimColor>No containers tracked for {env}.</Text>
      ) : (
        <Table
          columns={tableColumns}
          data={tableData}
          highlightRow={clampedRow}
        />
      )}

      {/* Selected container info + status badge */}
      {selectedContainer && (
        <Box marginTop={1} gap={2}>
          <Text dimColor>Selected:</Text>
          <Text bold>{selectedContainer.name}</Text>
          <Badge
            label={selectedRunning ? 'running' : 'stopped'}
            color={statusColor(selectedRunning ? 'running' : 'stopped')}
          />
        </Box>
      )}

      {/* Key hints */}
      <Box marginTop={1} gap={3}>
        <Box>
          <Text bold color="cyan">↑↓</Text>
          <Text dimColor>:select</Text>
        </Box>
        <Box>
          <Text bold color="cyan">r</Text>
          <Text dimColor>:restart</Text>
        </Box>
        <Box>
          <Text bold color="cyan">s</Text>
          <Text dimColor>:stop</Text>
        </Box>
        <Box>
          <Text bold color="cyan">S</Text>
          <Text dimColor>:start</Text>
        </Box>
        <Box>
          <Text bold color="cyan">R</Text>
          <Text dimColor>:force refresh</Text>
        </Box>
      </Box>
    </Box>
  );
}
