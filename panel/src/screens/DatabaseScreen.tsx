import React, { useState, useCallback, useEffect } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SaturnEnv } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge } from '../components/shared/Badge.js';
import { ConfirmDialog } from '../components/shared/ConfirmDialog.js';
import { useSSH } from '../ssh/context.js';
import { useSSHExec } from '../hooks/useSSHExec.js';
import { useSSHStream } from '../hooks/useSSHStream.js';
import {
  migrate,
  migrateFresh,
  migrationStatus,
} from '../services/laravel.js';
import {
  createBackup,
  listBackups,
  restoreBackup,
  getBackupSize,
} from '../services/backup.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

type Tab = 'migrate' | 'backup' | 'restore';
const TABS: Tab[] = ['migrate', 'backup', 'restore'];
const TAB_LABELS: Record<Tab, string> = {
  migrate: 'Migrate',
  backup: 'Backup',
  restore: 'Restore',
};

// Maximum lines to keep in the streaming restore output
const MAX_STREAM_LINES = 300;

export function DatabaseScreen({ env }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();
  const { loading, error: execError } = useSSHExec();
  const {
    lines: restoreLines,
    streaming: restoreStreaming,
    error: restoreStreamError,
    start: startRestore,
    clear: clearRestoreLines,
  } = useSSHStream(MAX_STREAM_LINES);

  const [activeTab, setActiveTab] = useState<Tab>('migrate');

  // Operation result message
  const [opResult, setOpResult] = useState<{ success: boolean; message: string } | null>(null);

  // Migrate tab state
  const [migrateStatus, setMigrateStatus] = useState<string | null>(null);
  const [migrateLoading, setMigrateLoading] = useState(false);

  // Backup tab state
  const [backupList, setBackupList] = useState<string | null>(null);
  const [backupSize, setBackupSize] = useState<string | null>(null);
  const [backupLoading, setBackupLoading] = useState(false);

  // Restore tab state — selected backup filename (from the list)
  const [restoreList, setRestoreList] = useState<string[]>([]);
  const [selectedBackup, setSelectedBackup] = useState(0);
  const [restoreListLoading, setRestoreListLoading] = useState(false);
  const [restoreResult, setRestoreResult] = useState<{ success: boolean; message: string } | null>(null);

  // Confirm dialogs
  type Dialog =
    | 'migrate'
    | 'migrate-fresh'
    | 'restore'
    | null;
  const [dialog, setDialog] = useState<Dialog>(null);

  // Load migration status on mount and when env or tab changes to migrate
  const loadMigrateStatus = useCallback(async () => {
    setMigrateLoading(true);
    setMigrateStatus(null);
    try {
      const result = await migrationStatus(env);
      setMigrateStatus(result);
    } catch (err) {
      setMigrateStatus(`Error: ${err instanceof Error ? err.message : String(err)}`);
    } finally {
      setMigrateLoading(false);
    }
  }, [env]);

  // Load backup list and disk usage
  const loadBackups = useCallback(async () => {
    setBackupLoading(true);
    setBackupList(null);
    setBackupSize(null);
    try {
      const [list, size] = await Promise.all([
        listBackups(env),
        getBackupSize(env),
      ]);
      setBackupList(list);
      setBackupSize(size.trim());

      // Parse filenames for restore tab
      const filenames = list
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.endsWith('.sql'))
        .map((l) => {
          // ls -lth: last token is filename
          const parts = l.split(/\s+/);
          return parts[parts.length - 1] ?? '';
        })
        .filter(Boolean);
      setRestoreList(filenames);
      setSelectedBackup(0);
    } catch (err) {
      setBackupList(`Error: ${err instanceof Error ? err.message : String(err)}`);
    } finally {
      setBackupLoading(false);
    }
  }, [env]);

  // Load restore list separately when restore tab is active
  const loadRestoreList = useCallback(async () => {
    setRestoreListLoading(true);
    try {
      const list = await listBackups(env);
      const filenames = list
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.endsWith('.sql'))
        .map((l) => {
          const parts = l.split(/\s+/);
          return parts[parts.length - 1] ?? '';
        })
        .filter(Boolean);
      setRestoreList(filenames);
      setSelectedBackup(0);
    } catch {
      setRestoreList([]);
    } finally {
      setRestoreListLoading(false);
    }
  }, [env]);

  // Load data when tab changes
  useEffect(() => {
    if (!connected) return;
    if (activeTab === 'migrate') void loadMigrateStatus();
    if (activeTab === 'backup') void loadBackups();
    if (activeTab === 'restore') void loadRestoreList();
  }, [activeTab, env, connected, loadMigrateStatus, loadBackups, loadRestoreList]);

  // Keyboard handling — suspended while a dialog or streaming restore is active
  useInput((_input, key) => {
    if (dialog !== null || restoreStreaming) return;

    // Tab key cycles through tabs
    if (key.tab) {
      const idx = TABS.indexOf(activeTab);
      setActiveTab(TABS[(idx + 1) % TABS.length] ?? 'migrate');
      setOpResult(null);
      return;
    }

    if (activeTab === 'migrate') {
      if (_input === 'm') { setDialog('migrate'); return; }
      if (_input === 'f') { setDialog('migrate-fresh'); return; }
      if (_input === 'r') { void loadMigrateStatus(); return; }
    }

    if (activeTab === 'backup') {
      if (_input === 'b') {
        void handleCreateBackup();
        return;
      }
      if (_input === 'r') {
        void loadBackups();
        return;
      }
    }

    if (activeTab === 'restore') {
      if (key.upArrow) {
        setSelectedBackup((s) => Math.max(0, s - 1));
        return;
      }
      if (key.downArrow) {
        setSelectedBackup((s) => Math.min(restoreList.length - 1, s + 1));
        return;
      }
      if (_input === 'r' && restoreList.length > 0) {
        setDialog('restore');
        return;
      }
      if (_input === 'R') {
        void loadRestoreList();
        return;
      }
    }
  });

  // Create a new database backup
  async function handleCreateBackup() {
    setOpResult(null);
    setBackupLoading(true);
    try {
      const result = await createBackup(env);
      setOpResult({ success: true, message: result.trim() });
      await loadBackups();
    } catch (err) {
      setOpResult({
        success: false,
        message: `Backup failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    } finally {
      setBackupLoading(false);
    }
  }

  // Run migrate
  async function handleMigrate() {
    setDialog(null);
    setOpResult(null);
    setMigrateLoading(true);
    try {
      const result = await migrate(env);
      setOpResult({ success: true, message: result.trim() || 'Migrations ran successfully.' });
      await loadMigrateStatus();
    } catch (err) {
      setOpResult({
        success: false,
        message: `Migrate failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    } finally {
      setMigrateLoading(false);
    }
  }

  // Run migrate:fresh (destructive)
  async function handleMigrateFresh() {
    setDialog(null);
    setOpResult(null);
    setMigrateLoading(true);
    try {
      const result = await migrateFresh(env);
      setOpResult({ success: true, message: result.trim() || 'Fresh migration complete.' });
      await loadMigrateStatus();
    } catch (err) {
      setOpResult({
        success: false,
        message: `Fresh migrate failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    } finally {
      setMigrateLoading(false);
    }
  }

  // Run restore via streaming
  async function handleRestore() {
    setDialog(null);
    setRestoreResult(null);
    clearRestoreLines();
    const filename = restoreList[selectedBackup];
    if (!filename) return;

    try {
      // Use the equivalent shell command directly so useSSHStream handles the stream.
      // restoreBackup is kept as a service function for direct SSH exec usage
      // when not using the hook-based streaming.
      await startRestore(
        `cat /data/saturn/${env}/backups/${filename} | docker exec -i saturn-db-${env} psql -U saturn -d saturn 2>&1`,
      );
      setRestoreResult({ success: true, message: `Restore of ${filename} completed.` });
    } catch (err) {
      setRestoreResult({
        success: false,
        message: `Restore failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    }
  }

  // restoreBackup imported for direct SSH exec usage in other contexts
  void restoreBackup;

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

  // Tab bar (inline component)
  const TabBar = () => (
    <Box gap={2} marginBottom={1}>
      {TABS.map((tab) => (
        <Box key={tab}>
          {activeTab === tab ? (
            <Text bold color="cyan" inverse>{` ${TAB_LABELS[tab]} `}</Text>
          ) : (
            <Text dimColor>{` ${TAB_LABELS[tab]} `}</Text>
          )}
        </Box>
      ))}
      <Text dimColor>  Tab:switch</Text>
    </Box>
  );

  // Confirm dialogs render in place
  if (dialog === 'migrate') {
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message={`Run pending migrations for ${env}?`}
          onConfirm={() => void handleMigrate()}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  if (dialog === 'migrate-fresh') {
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message={`DANGER: Run migrate:fresh for ${env}? ALL DATA WILL BE LOST!`}
          destructive
          onConfirm={() => void handleMigrateFresh()}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  if (dialog === 'restore') {
    const filename = restoreList[selectedBackup] ?? '(none)';
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message={`DANGER: Restore backup "${filename}" into ${env}? Current data will be overwritten!`}
          destructive
          onConfirm={() => void handleRestore()}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  return (
    <Box flexDirection="column" padding={1}>
      {/* Screen header */}
      <Box gap={2} marginBottom={1} alignItems="center">
        <Text bold color="cyan">Database — {env.toUpperCase()}</Text>
        {restoreStreaming && <Badge label="restoring" color="yellow" />}
      </Box>

      <TabBar />

      {/* Global operation result */}
      {opResult && (
        <Box marginBottom={1}>
          <Text bold color={opResult.success ? 'green' : 'red'}>
            {opResult.success ? '✔ ' : '✘ '}
            {opResult.message}
          </Text>
        </Box>
      )}

      {/* MIGRATE TAB */}
      {activeTab === 'migrate' && (
        <Box flexDirection="column">
          <Box gap={3} marginBottom={1}>
            <Box>
              <Text bold color="cyan">m</Text>
              <Text dimColor>:migrate</Text>
            </Box>
            <Box>
              <Text bold color="red">f</Text>
              <Text dimColor>:migrate:fresh (DESTRUCTIVE)</Text>
            </Box>
            <Box>
              <Text bold color="cyan">r</Text>
              <Text dimColor>:refresh status</Text>
            </Box>
          </Box>

          {(migrateLoading || loading) && <Spinner label="Running migration…" />}
          {execError && <Text color="red">Error: {execError}</Text>}

          {migrateStatus && !migrateLoading && (
            <Box flexDirection="column" borderStyle="single" borderColor="gray">
              {migrateStatus.split('\n').map((line, i) => {
                const isRan = line.includes('Yes');
                const isPending = line.includes('No');
                return (
                  <Text
                    key={i}
                    color={isRan ? 'green' : isPending ? 'yellow' : undefined}
                    dimColor={line.startsWith('+') || line.startsWith('|+') || line.startsWith('+-')}
                  >
                    {line}
                  </Text>
                );
              })}
            </Box>
          )}

          {!migrateStatus && !migrateLoading && (
            <Text dimColor>Press r to load migration status.</Text>
          )}
        </Box>
      )}

      {/* BACKUP TAB */}
      {activeTab === 'backup' && (
        <Box flexDirection="column">
          <Box gap={3} marginBottom={1}>
            <Box>
              <Text bold color="cyan">b</Text>
              <Text dimColor>:create backup</Text>
            </Box>
            <Box>
              <Text bold color="cyan">r</Text>
              <Text dimColor>:refresh list</Text>
            </Box>
          </Box>

          {backupLoading && <Spinner label="Working…" />}

          {backupSize && (
            <Box marginBottom={1}>
              <Text dimColor>Backup directory size: </Text>
              <Text bold color="yellow">{backupSize}</Text>
            </Box>
          )}

          {backupList && !backupLoading && (
            <Box flexDirection="column" borderStyle="single" borderColor="gray">
              {backupList.split('\n').map((line, i) => (
                <Text key={i} dimColor={line.startsWith('total')}>
                  {line}
                </Text>
              ))}
            </Box>
          )}

          {!backupList && !backupLoading && (
            <Text dimColor>Press b to create a backup or r to list existing backups.</Text>
          )}
        </Box>
      )}

      {/* RESTORE TAB */}
      {activeTab === 'restore' && (
        <Box flexDirection="column">
          <Box gap={3} marginBottom={1}>
            <Box>
              <Text bold color="cyan">↑↓</Text>
              <Text dimColor>:select backup</Text>
            </Box>
            <Box>
              <Text bold color="red">r</Text>
              <Text dimColor>:restore selected (DESTRUCTIVE)</Text>
            </Box>
            <Box>
              <Text bold color="cyan">R</Text>
              <Text dimColor>:refresh list</Text>
            </Box>
          </Box>

          {restoreListLoading && <Spinner label="Loading backups…" />}

          {restoreResult && (
            <Box marginBottom={1}>
              <Text bold color={restoreResult.success ? 'green' : 'red'}>
                {restoreResult.success ? '✔ ' : '✘ '}
                {restoreResult.message}
              </Text>
            </Box>
          )}
          {restoreStreamError && (
            <Box marginBottom={1}>
              <Text color="red">Stream error: {restoreStreamError}</Text>
            </Box>
          )}

          {!restoreListLoading && restoreList.length === 0 && (
            <Text dimColor>No backups found. Create one in the Backup tab.</Text>
          )}

          {!restoreListLoading && restoreList.length > 0 && (
            <Box flexDirection="column" borderStyle="single" borderColor="gray">
              {restoreList.map((filename, i) => (
                <Box key={filename}>
                  {i === selectedBackup ? (
                    <Text bold color="cyan" inverse>{` > ${filename} `}</Text>
                  ) : (
                    <Text dimColor>{`   ${filename}`}</Text>
                  )}
                </Box>
              ))}
            </Box>
          )}

          {/* Streaming restore output */}
          {(restoreStreaming || restoreLines.length > 0) && (
            <Box flexDirection="column" marginTop={1}>
              {restoreStreaming && <Spinner label="Restoring…" />}
              <Box flexDirection="column" borderStyle="single" borderColor="gray">
                {restoreLines.slice(-20).map((line, i) => (
                  <Text key={i} wrap="truncate">
                    {line}
                  </Text>
                ))}
              </Box>
              {restoreLines.length > 20 && (
                <Text dimColor>… {restoreLines.length - 20} earlier lines hidden</Text>
              )}
            </Box>
          )}
        </Box>
      )}
    </Box>
  );
}
