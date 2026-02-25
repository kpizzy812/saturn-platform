import React, { useState, useEffect, useCallback } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SaturnEnv } from '../config/types.js';
import type { LogEntry } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge } from '../components/shared/Badge.js';
import { SearchInput } from '../components/shared/SearchInput.js';
import { useSSH } from '../ssh/context.js';
import { useLogs } from '../hooks/useLogs.js';
import { useSSHStream } from '../hooks/useSSHStream.js';
import { containerName, allContainers } from '../config/constants.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

type ServiceKey = 'app' | 'db' | 'redis' | 'realtime';
const SERVICES: ServiceKey[] = ['app', 'db', 'redis', 'realtime'];

// Log level cycle order: null = all, then each level
const LEVEL_CYCLE: Array<LogEntry['level'] | null> = [null, 'info', 'error', 'warning', 'debug'];

// Number of log lines to show in the viewport
const VIEWPORT_LINES = 25;

// Color mapping for log levels
function levelColor(level: LogEntry['level']): string {
  if (level === 'error') return 'red';
  if (level === 'warning') return 'yellow';
  if (level === 'debug') return 'gray';
  return 'white';
}

export function LogsScreen({ env }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();

  const [selectedService, setSelectedService] = useState<ServiceKey>('app');
  const [multiMode, setMultiMode] = useState(false); // 'a' streams all containers interleaved

  // Single-container log hook
  const {
    logs,
    streaming,
    error,
    startStreaming,
    stopStreaming,
    clearLogs,
    filterByLevel,
    searchLogs,
    activeLevel,
  } = useLogs();

  // Multi-container raw stream hook (for 'a' mode)
  const {
    lines: multiLines,
    streaming: multiStreaming,
    error: multiError,
    start: startMulti,
    stop: stopMulti,
    clear: clearMulti,
  } = useSSHStream(2000);

  // Search / filter UI state
  const [searchActive, setSearchActive] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<LogEntry[] | null>(null);

  // Auto-start streaming when env or service changes (single mode)
  useEffect(() => {
    if (!connected || multiMode) return;
    stopStreaming();
    clearLogs();
    setSearchResults(null);
    setSearchQuery('');
    startStreaming(containerName(selectedService, env));
  }, [env, selectedService, connected, multiMode]);

  // Stop everything when unmounting or switching away
  useEffect(() => {
    return () => {
      stopStreaming();
      stopMulti();
    };
  }, []);

  // Start multi-container stream
  const startMultiStream = useCallback(() => {
    clearMulti();
    const containers = allContainers(env).join(' ');
    // Interleave all container logs — use docker logs with timestamps
    void startMulti(
      `docker logs -f --tail 50 --timestamps ${containers} 2>&1`,
    );
  }, [env, clearMulti, startMulti]);

  // Keyboard handling — suspended while search input is active
  useInput((_input, key) => {
    if (searchActive) return;

    // Tab: cycle through service containers
    if (key.tab) {
      if (multiMode) return;
      const idx = SERVICES.indexOf(selectedService);
      const next = SERVICES[(idx + 1) % SERVICES.length] ?? 'app';
      setSelectedService(next);
      return;
    }

    // s: toggle streaming on/off
    if (_input === 's') {
      if (multiMode) {
        if (multiStreaming) stopMulti();
        else startMultiStream();
      } else {
        if (streaming) {
          stopStreaming();
        } else {
          startStreaming(containerName(selectedService, env));
        }
      }
      return;
    }

    // c: clear logs
    if (_input === 'c') {
      clearLogs();
      clearMulti();
      setSearchResults(null);
      setSearchQuery('');
      return;
    }

    // /: open search
    if (_input === '/') {
      setSearchActive(true);
      return;
    }

    // f: cycle log level filter
    if (_input === 'f') {
      const currentIdx = LEVEL_CYCLE.indexOf(activeLevel);
      const nextLevel = LEVEL_CYCLE[(currentIdx + 1) % LEVEL_CYCLE.length] ?? null;
      filterByLevel(nextLevel);
      return;
    }

    // a: toggle multi-container mode
    if (_input === 'a') {
      if (multiMode) {
        stopMulti();
        clearMulti();
        setMultiMode(false);
        // Resume single-container stream
        startStreaming(containerName(selectedService, env));
      } else {
        stopStreaming();
        clearLogs();
        setMultiMode(true);
        startMultiStream();
      }
      return;
    }

    // Escape: clear search
    if (key.escape && searchQuery) {
      setSearchQuery('');
      setSearchResults(null);
      return;
    }
  });

  // Handle search submission
  function handleSearchChange(query: string) {
    setSearchQuery(query);
    if (query.trim()) {
      setSearchResults(searchLogs(query));
    } else {
      setSearchResults(null);
    }
  }

  function handleSearchCancel() {
    setSearchActive(false);
    setSearchQuery('');
    setSearchResults(null);
  }

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

  // Decide which log entries to display
  const displayLogs: LogEntry[] = searchResults ?? logs;
  const visibleLogs = displayLogs.slice(-VIEWPORT_LINES);

  // Current streaming/active container label
  const currentLabel = multiMode
    ? `all containers (${env})`
    : `${containerName(selectedService, env)}`;

  const isStreaming = multiMode ? multiStreaming : streaming;
  const currentError = multiMode ? multiError : error;

  return (
    <Box flexDirection="column" padding={1}>
      {/* Screen header */}
      <Box gap={2} marginBottom={1} alignItems="center">
        <Text bold color="cyan">Logs — {env.toUpperCase()}</Text>
        {isStreaming && <Badge label="live" color="green" />}
        {activeLevel && !multiMode && (
          <Badge label={`filter:${activeLevel}`} color="yellow" />
        )}
        {searchQuery && <Badge label={`search:${searchQuery}`} color="cyan" />}
      </Box>

      {/* Container selector (single mode only) */}
      {!multiMode && (
        <Box gap={1} marginBottom={1}>
          {SERVICES.map((svc) => (
            <Box key={svc} marginRight={1}>
              {selectedService === svc ? (
                <Text bold color="cyan" inverse>{` ${svc} `}</Text>
              ) : (
                <Text dimColor>{` ${svc} `}</Text>
              )}
            </Box>
          ))}
        </Box>
      )}

      {/* Multi mode indicator */}
      {multiMode && (
        <Box marginBottom={1}>
          <Badge label="ALL CONTAINERS" color="yellow" />
          <Text dimColor>  (interleaved stream)</Text>
        </Box>
      )}

      {/* Key hints */}
      <Box gap={3} marginBottom={1}>
        <Box>
          <Text bold color="cyan">Tab</Text>
          <Text dimColor>:next container</Text>
        </Box>
        <Box>
          <Text bold color="cyan">s</Text>
          <Text dimColor>:{isStreaming ? 'stop' : 'start'}</Text>
        </Box>
        <Box>
          <Text bold color="cyan">c</Text>
          <Text dimColor>:clear</Text>
        </Box>
        <Box>
          <Text bold color="cyan">/</Text>
          <Text dimColor>:search</Text>
        </Box>
        <Box>
          <Text bold color="cyan">f</Text>
          <Text dimColor>:filter({activeLevel ?? 'all'})</Text>
        </Box>
        <Box>
          <Text bold color="cyan">a</Text>
          <Text dimColor>:{multiMode ? 'single' : 'all containers'}</Text>
        </Box>
      </Box>

      {/* Search input */}
      {searchActive && (
        <Box marginBottom={1}>
          <SearchInput
            placeholder="search logs…"
            onChange={handleSearchChange}
            onSubmit={() => setSearchActive(false)}
            onCancel={handleSearchCancel}
          />
          {searchResults !== null && (
            <Text dimColor>  {searchResults.length} matches</Text>
          )}
        </Box>
      )}

      {/* Streaming indicator */}
      {isStreaming && (
        <Box marginBottom={1}>
          <Spinner label={`Streaming ${currentLabel}…`} />
        </Box>
      )}

      {/* Error display */}
      {currentError && (
        <Box marginBottom={1}>
          <Text color="red">Error: {currentError}</Text>
        </Box>
      )}

      {/* Multi-container raw lines */}
      {multiMode && (
        <Box flexDirection="column" borderStyle="single" borderColor="gray">
          {multiLines.slice(-VIEWPORT_LINES).map((line, i) => (
            <Text key={i} wrap="truncate" dimColor={!multiStreaming}>
              {line}
            </Text>
          ))}
          {multiLines.length === 0 && !multiStreaming && (
            <Text dimColor>No lines. Press s to start streaming all containers.</Text>
          )}
        </Box>
      )}

      {/* Single-container parsed log entries */}
      {!multiMode && (
        <Box flexDirection="column" borderStyle="single" borderColor="gray">
          {visibleLogs.length === 0 && !isStreaming && (
            <Text dimColor>No logs. Press s to start streaming.</Text>
          )}
          {visibleLogs.map((entry) => (
            <Box key={entry.id} gap={1}>
              <Box width={8}>
                <Text dimColor>{entry.timestamp.slice(11, 19)}</Text>
              </Box>
              <Box width={9}>
                <Text color={levelColor(entry.level)}>
                  {`[${entry.level.toUpperCase().slice(0, 4)}]`}
                </Text>
              </Box>
              <Text wrap="truncate">{entry.message}</Text>
            </Box>
          ))}
        </Box>
      )}

      {/* Log count / viewport info */}
      {!multiMode && displayLogs.length > VIEWPORT_LINES && (
        <Text dimColor>
          … {displayLogs.length - VIEWPORT_LINES} earlier entries hidden
          (total: {displayLogs.length})
        </Text>
      )}
    </Box>
  );
}
