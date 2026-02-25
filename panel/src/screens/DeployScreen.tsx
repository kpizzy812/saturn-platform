import React, { useState, useCallback, useEffect } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SaturnEnv } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge } from '../components/shared/Badge.js';
import { ConfirmDialog } from '../components/shared/ConfirmDialog.js';
import { useSSH } from '../ssh/context.js';
import { useSSHExec } from '../hooks/useSSHExec.js';
import { useSSHStream } from '../hooks/useSSHStream.js';
import { ENVIRONMENTS, ENV_DOMAINS, SOURCE_DIR, DEPLOY_SCRIPT } from '../config/constants.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

interface EnvStatus {
  branch: string;
  commit: string;
  loading: boolean;
  error: string | null;
}

type AllEnvStatus = Record<SaturnEnv, EnvStatus>;

type DeployOp = 'deploy' | 'rollback';

// Maximum lines to keep in the streaming output buffer
const MAX_STREAM_LINES = 300;

export function DeployScreen({ env, onEnvChange }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();
  const { execute } = useSSHExec();
  const { lines, streaming, error: streamError, start, stop, clear } = useSSHStream(MAX_STREAM_LINES);

  const [statuses, setStatuses] = useState<AllEnvStatus>({
    dev: { branch: '—', commit: '—', loading: true, error: null },
    staging: { branch: '—', commit: '—', loading: true, error: null },
    production: { branch: '—', commit: '—', loading: true, error: null },
  });

  // { op, target } when the confirm dialog is showing; null otherwise
  const [dialog, setDialog] = useState<{ op: DeployOp; target: SaturnEnv } | null>(null);

  // Result message shown after the stream ends
  const [deployResult, setDeployResult] = useState<{ success: boolean; message: string } | null>(null);
  // Which env we are currently deploying to (used in the streaming header)
  const [activeDeployEnv, setActiveDeployEnv] = useState<SaturnEnv | null>(null);

  // Load git branch/commit for all environments
  const loadStatuses = useCallback(async () => {
    if (!connected) return;

    setStatuses((prev) => {
      const next = { ...prev };
      for (const e of ENVIRONMENTS) {
        next[e] = { ...prev[e], loading: true };
      }
      return next;
    });

    await Promise.all(
      ENVIRONMENTS.map(async (e) => {
        try {
          const [branchRaw, commitRaw] = await Promise.all([
            execute(
              `cd ${SOURCE_DIR(e)} && git rev-parse --abbrev-ref HEAD 2>/dev/null || echo '—'`,
            ),
            execute(
              `cd ${SOURCE_DIR(e)} && git log --oneline -1 2>/dev/null || echo '—'`,
            ),
          ]);
          setStatuses((prev) => ({
            ...prev,
            [e]: {
              branch: branchRaw.trim() || '—',
              commit: commitRaw.trim() || '—',
              loading: false,
              error: null,
            },
          }));
        } catch (err) {
          setStatuses((prev) => ({
            ...prev,
            [e]: {
              branch: '—',
              commit: '—',
              loading: false,
              error: err instanceof Error ? err.message : String(err),
            },
          }));
        }
      }),
    );
  }, [connected, execute]);

  useEffect(() => {
    void loadStatuses();
  }, [loadStatuses]);

  // Start a deploy or rollback streaming operation
  async function startOperation(op: DeployOp, target: SaturnEnv) {
    clear();
    setDeployResult(null);
    setActiveDeployEnv(target);

    const flag = op === 'rollback' ? ' --rollback' : '';
    const command = `cd ${SOURCE_DIR(target)} && SATURN_ENV=${target} ./${DEPLOY_SCRIPT}${flag} 2>&1`;

    try {
      await start(command);
      setDeployResult({ success: true, message: `${op} to ${target} completed successfully.` });
    } catch (err) {
      setDeployResult({
        success: false,
        message: `${op} failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    }

    // Refresh statuses after deploy finishes
    void loadStatuses();
  }

  // Keyboard handling
  useInput((_input, key) => {
    // Block input while a dialog is open or a deploy is streaming
    if (dialog !== null || streaming) return;

    if (_input === 'd') {
      setDialog({ op: 'deploy', target: env });
      return;
    }
    if (_input === 'r') {
      setDialog({ op: 'rollback', target: env });
      return;
    }
    if (_input === 'e') {
      // Cycle through environments
      const idx = ENVIRONMENTS.indexOf(env);
      const next = ENVIRONMENTS[(idx + 1) % ENVIRONMENTS.length] ?? 'dev';
      onEnvChange(next);
      return;
    }
    if (_input === 'R') {
      void loadStatuses();
      return;
    }
    if (_input === 'c') {
      clear();
      setDeployResult(null);
      return;
    }
    if ((_input === 's' || key.escape) && streaming) {
      stop();
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

  // Confirm dialog renders in place of the main view
  if (dialog !== null) {
    const { op, target } = dialog;
    const isDestructive = op === 'rollback' || target === 'production';
    return (
      <Box flexDirection="column" padding={1}>
        <Box marginBottom={1}>
          <Text bold color="cyan">Deploy — {env.toUpperCase()}</Text>
        </Box>
        <ConfirmDialog
          message={`${op === 'deploy' ? 'Deploy' : 'Rollback'} environment ${target} (${ENV_DOMAINS[target]})?`}
          destructive={isDestructive}
          onConfirm={() => {
            const opCopy = op;
            const targetCopy = target;
            setDialog(null);
            void startOperation(opCopy, targetCopy);
          }}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  // Visible lines — always show the last 35 lines of the stream
  const visibleLines = lines.slice(-35);

  return (
    <Box flexDirection="column" padding={1}>
      {/* Screen header */}
      <Box gap={2} marginBottom={1} alignItems="center">
        <Text bold color="cyan">Deploy — {env.toUpperCase()}</Text>
        <Text dimColor>{ENV_DOMAINS[env]}</Text>
        {streaming && <Badge label="deploying" color="yellow" />}
      </Box>

      {/* Environment status cards (hidden while streaming to preserve space) */}
      {!streaming && lines.length === 0 && (
        <Box flexDirection="column" gap={1} marginBottom={1}>
          {ENVIRONMENTS.map((e) => {
            const s = statuses[e];
            const isActive = e === env;
            return (
              <Box
                key={e}
                flexDirection="column"
                borderStyle="single"
                borderColor={isActive ? 'cyan' : 'gray'}
                paddingX={1}
              >
                <Box gap={2}>
                  <Text bold color={isActive ? 'cyan' : 'white'}>{e.toUpperCase()}</Text>
                  <Text dimColor>{ENV_DOMAINS[e]}</Text>
                  {s.loading && <Spinner label="loading…" />}
                </Box>
                {!s.loading && s.error && (
                  <Text color="red">{s.error}</Text>
                )}
                {!s.loading && !s.error && (
                  <Box gap={2}>
                    <Text dimColor>branch:</Text>
                    <Text color="yellow">{s.branch}</Text>
                    <Text dimColor>commit:</Text>
                    <Text>
                      {s.commit.length > 55 ? s.commit.slice(0, 54) + '\u2026' : s.commit}
                    </Text>
                  </Box>
                )}
              </Box>
            );
          })}
        </Box>
      )}

      {/* Key hints (hidden while streaming) */}
      {!streaming && (
        <Box marginBottom={1} gap={3}>
          <Box>
            <Text bold color="cyan">d</Text>
            <Text dimColor>:deploy</Text>
          </Box>
          <Box>
            <Text bold color="cyan">r</Text>
            <Text dimColor>:rollback</Text>
          </Box>
          <Box>
            <Text bold color="cyan">e</Text>
            <Text dimColor>:cycle env</Text>
          </Box>
          <Box>
            <Text bold color="cyan">R</Text>
            <Text dimColor>:refresh statuses</Text>
          </Box>
          {lines.length > 0 && (
            <Box>
              <Text bold color="cyan">c</Text>
              <Text dimColor>:clear output</Text>
            </Box>
          )}
        </Box>
      )}

      {/* Stop hint while streaming */}
      {streaming && (
        <Box marginBottom={1} gap={3}>
          <Spinner label={`Deploying to ${activeDeployEnv ?? env}…`} />
          <Text dimColor>  s / Esc: stop</Text>
        </Box>
      )}

      {/* Deploy result banner */}
      {deployResult && !streaming && (
        <Box marginBottom={1}>
          <Text bold color={deployResult.success ? 'green' : 'red'}>
            {deployResult.success ? '✔ ' : '✘ '}
            {deployResult.message}
          </Text>
        </Box>
      )}

      {/* Stream error */}
      {streamError && (
        <Box marginBottom={1}>
          <Text color="red">Stream error: {streamError}</Text>
        </Box>
      )}

      {/* Streaming / historical output */}
      {(streaming || lines.length > 0) && (
        <Box flexDirection="column">
          <Box marginBottom={1}>
            <Text bold>Output</Text>
            <Text dimColor> ({lines.length} lines{lines.length > 35 ? ', showing last 35' : ''})</Text>
          </Box>

          <Box flexDirection="column" borderStyle="single" borderColor="gray">
            {visibleLines.map((line, i) => {
              const lower = line.toLowerCase();
              let color: string | undefined;
              if (lower.includes('error') || lower.includes('failed') || lower.includes('fatal')) {
                color = 'red';
              } else if (
                lower.includes('success') ||
                lower.includes('done') ||
                lower.includes('complete') ||
                lower.includes('finished')
              ) {
                color = 'green';
              } else if (lower.includes('warning') || lower.includes('warn')) {
                color = 'yellow';
              }

              return (
                <Text key={i} color={color} wrap="truncate">
                  {line}
                </Text>
              );
            })}
          </Box>

          {lines.length > 35 && (
            <Text dimColor>… {lines.length - 35} earlier lines hidden</Text>
          )}
        </Box>
      )}

      {/* Empty state */}
      {!streaming && lines.length === 0 && (
        <Box marginTop={1}>
          <Text dimColor>Press d to deploy or r to rollback {env}.</Text>
        </Box>
      )}
    </Box>
  );
}
