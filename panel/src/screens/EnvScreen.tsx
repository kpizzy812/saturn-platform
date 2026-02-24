import React, { useState, useCallback, useEffect } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SaturnEnv } from '../config/types.js';
import { Spinner } from '../components/shared/Spinner.js';
import { Badge } from '../components/shared/Badge.js';
import { SearchInput } from '../components/shared/SearchInput.js';
import { useSSH } from '../ssh/context.js';
import { readEnvFile, diffEnvFiles, parseEnvString } from '../services/env-file.js';
import { ENVIRONMENTS } from '../config/constants.js';

interface ScreenProps {
  env: SaturnEnv;
  onEnvChange: (env: SaturnEnv) => void;
}

// Regex patterns for sensitive variable names — values are partially masked
const SENSITIVE_PATTERN =
  /secret|password|passwd|pwd|token|key|dsn|database_url|private|auth|api_key|webhook|salt|signing/i;

// Number of rows visible in the key-value viewport
const VIEWPORT_ROWS = 28;

// Mask a sensitive value: show first 4 chars + **** (or all **** if shorter)
function maskSensitiveValue(value: string): string {
  if (value.length <= 4) return '****';
  return value.slice(0, 4) + '****';
}

function isSensitive(key: string): boolean {
  return SENSITIVE_PATTERN.test(key);
}

export function EnvScreen({ env }: ScreenProps) {
  const { connected, connecting, error: sshError } = useSSH();

  // Loaded key-value pairs for the active env
  const [envVars, setEnvVars] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  // Mask toggle — sensitive values are masked by default
  const [masked, setMasked] = useState(true);

  // Search state
  const [searchActive, setSearchActive] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Scroll offset for the key-value viewport
  const [scrollOffset, setScrollOffset] = useState(0);

  // Diff mode — shows diff output between active env and another env
  const [diffMode, setDiffMode] = useState(false);
  const [diffTarget, setDiffTarget] = useState<SaturnEnv>('staging');
  const [diffOutput, setDiffOutput] = useState<string | null>(null);
  const [diffLoading, setDiffLoading] = useState(false);
  const [diffError, setDiffError] = useState<string | null>(null);

  // Load the .env file for the active environment
  const loadEnvFile = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    setLoaded(false);
    setScrollOffset(0);
    try {
      const raw = await readEnvFile(env);
      setEnvVars(parseEnvString(raw));
      setLoaded(true);
    } catch (err) {
      setLoadError(err instanceof Error ? err.message : String(err));
    } finally {
      setLoading(false);
    }
  }, [env]);

  // Auto-load when env changes and already loaded once
  useEffect(() => {
    if (!connected) return;
    if (loaded) {
      void loadEnvFile();
    }
  // Intentionally omitting loadEnvFile from deps — its identity is stable via useCallback
  }, [env, connected]);

  // Run a diff between env and diffTarget
  const runDiff = useCallback(async () => {
    setDiffLoading(true);
    setDiffError(null);
    setDiffOutput(null);
    try {
      const result = await diffEnvFiles(env, diffTarget);
      setDiffOutput(result);
    } catch (err) {
      setDiffError(err instanceof Error ? err.message : String(err));
    } finally {
      setDiffLoading(false);
    }
  }, [env, diffTarget]);

  // Keyboard handling
  useInput((_input, key) => {
    // Hand off all input to SearchInput when active
    if (searchActive) return;

    // Escape: exit diff mode or clear search
    if (key.escape) {
      if (diffMode) {
        setDiffMode(false);
        setDiffOutput(null);
        setDiffError(null);
        return;
      }
      if (searchQuery) {
        setSearchQuery('');
        return;
      }
      return;
    }

    // r: (re)load env file
    if (_input === 'r') {
      void loadEnvFile();
      return;
    }

    // m: toggle mask
    if (_input === 'm') {
      setMasked((v) => !v);
      return;
    }

    // /: open search
    if (_input === '/') {
      setSearchActive(true);
      return;
    }

    // d: toggle diff mode
    if (_input === 'd') {
      if (diffMode) {
        setDiffMode(false);
        setDiffOutput(null);
        setDiffError(null);
      } else {
        setDiffMode(true);
        void runDiff();
      }
      return;
    }

    // t: cycle diff target environment (when in diff mode)
    if (_input === 't' && diffMode) {
      const others = ENVIRONMENTS.filter((e) => e !== env);
      const idx = others.indexOf(diffTarget);
      const next = others[(idx + 1) % others.length] ?? others[0] ?? 'staging';
      setDiffTarget(next);
      return;
    }

    // Scroll in the key-value list
    if (key.upArrow) {
      setScrollOffset((o) => Math.max(0, o - 1));
      return;
    }
    if (key.downArrow) {
      setScrollOffset((o) => o + 1);
      return;
    }
  });

  function handleSearchChange(query: string) {
    setSearchQuery(query);
    setScrollOffset(0);
  }

  function handleSearchCancel() {
    setSearchActive(false);
    setSearchQuery('');
    setScrollOffset(0);
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

  // Build filtered, sorted entries
  const allEntries = Object.entries(envVars);
  const filtered = searchQuery.trim()
    ? allEntries.filter(
        ([k, v]) =>
          k.toLowerCase().includes(searchQuery.toLowerCase()) ||
          v.toLowerCase().includes(searchQuery.toLowerCase()),
      )
    : allEntries;
  const sorted = [...filtered].sort(([a], [b]) => a.localeCompare(b));

  // Clamp scroll offset
  const maxOffset = Math.max(0, sorted.length - VIEWPORT_ROWS);
  const clampedOffset = Math.min(scrollOffset, maxOffset);
  const visibleEntries = sorted.slice(clampedOffset, clampedOffset + VIEWPORT_ROWS);

  const sensitiveCount = sorted.filter(([k]) => isSensitive(k)).length;

  return (
    <Box flexDirection="column" padding={1}>
      {/* Screen header */}
      <Box gap={2} marginBottom={1} alignItems="center">
        <Text bold color="cyan">Env Vars — {env.toUpperCase()}</Text>
        {loaded && (
          <Badge
            label={`${sorted.length} vars`}
            color="cyan"
          />
        )}
        {masked && sensitiveCount > 0 && (
          <Badge label={`${sensitiveCount} masked`} color="yellow" />
        )}
        {diffMode && (
          <Badge label={`diff: ${env} ↔ ${diffTarget}`} color="magenta" />
        )}
      </Box>

      {/* Key hints */}
      <Box gap={3} marginBottom={1}>
        <Box>
          <Text bold color="cyan">r</Text>
          <Text dimColor>:reload</Text>
        </Box>
        <Box>
          <Text bold color="cyan">m</Text>
          <Text dimColor>:{masked ? 'unmask' : 'mask'}</Text>
        </Box>
        <Box>
          <Text bold color="cyan">/</Text>
          <Text dimColor>:search</Text>
        </Box>
        <Box>
          <Text bold color="cyan">d</Text>
          <Text dimColor>:{diffMode ? 'exit diff' : 'diff env'}</Text>
        </Box>
        {diffMode && (
          <Box>
            <Text bold color="cyan">t</Text>
            <Text dimColor>:diff target ({diffTarget})</Text>
          </Box>
        )}
        <Box>
          <Text bold color="cyan">↑↓</Text>
          <Text dimColor>:scroll</Text>
        </Box>
      </Box>

      {/* Search input */}
      {searchActive && (
        <Box marginBottom={1}>
          <SearchInput
            placeholder="filter by key or value…"
            onChange={handleSearchChange}
            onSubmit={() => setSearchActive(false)}
            onCancel={handleSearchCancel}
          />
          <Text dimColor>  {sorted.length} matches</Text>
        </Box>
      )}

      {/* Non-search query display */}
      {searchQuery && !searchActive && (
        <Box marginBottom={1} gap={1}>
          <Text dimColor>Filter:</Text>
          <Text color="cyan">{searchQuery}</Text>
          <Text dimColor>({sorted.length} results)  /: edit  Esc: clear</Text>
        </Box>
      )}

      {/* Loading / error states */}
      {loading && <Spinner label={`Reading .env for ${env}…`} />}
      {loadError && (
        <Box marginBottom={1}>
          <Text color="red">Error: {loadError}</Text>
        </Box>
      )}

      {/* Empty state */}
      {!loaded && !loading && !loadError && (
        <Box marginTop={1}>
          <Text dimColor>Press r to load .env file for {env}.</Text>
        </Box>
      )}

      {/* DIFF MODE */}
      {diffMode && (
        <Box flexDirection="column">
          {diffLoading && <Spinner label={`Diffing ${env} ↔ ${diffTarget}…`} />}
          {diffError && <Text color="red">Diff error: {diffError}</Text>}
          {diffOutput && !diffLoading && (
            <Box flexDirection="column" borderStyle="single" borderColor="gray">
              {diffOutput.split('\n').map((line, i) => {
                // Mark added/changed lines by checking the diff separator
                const hasLeft = !line.includes('>') && !line.startsWith(' ');
                const hasRight = line.includes('>');
                const color = hasLeft ? 'yellow' : hasRight ? 'green' : undefined;
                return (
                  <Text key={i} color={color} wrap="truncate">
                    {line}
                  </Text>
                );
              })}
            </Box>
          )}
        </Box>
      )}

      {/* KEY-VALUE TABLE */}
      {loaded && !loading && !diffMode && (
        <Box flexDirection="column">
          {/* Column header */}
          <Box gap={1} marginBottom={1}>
            <Box width={32}>
              <Text bold underline color="cyan">Key</Text>
            </Box>
            <Box width={4}>
              <Text bold underline color="cyan">S</Text>
            </Box>
            <Text bold underline color="cyan">Value</Text>
          </Box>

          {/* Data rows */}
          <Box flexDirection="column" borderStyle="single" borderColor="gray">
            {visibleEntries.length === 0 && (
              <Text dimColor>No variables match the current filter.</Text>
            )}
            {visibleEntries.map(([key, value]) => {
              const sensitive = isSensitive(key);
              const displayValue = masked && sensitive ? maskSensitiveValue(value) : value;
              // Truncate very long values
              const truncatedValue =
                displayValue.length > 80
                  ? displayValue.slice(0, 79) + '\u2026'
                  : displayValue;

              return (
                <Box key={key} gap={1}>
                  <Box width={32}>
                    <Text color={sensitive ? 'yellow' : 'white'}>
                      {key.length > 31 ? key.slice(0, 30) + '\u2026' : key}
                    </Text>
                  </Box>
                  <Box width={4}>
                    {sensitive && (
                      <Text color="yellow">S</Text>
                    )}
                  </Box>
                  <Text
                    dimColor={masked && sensitive}
                    color={masked && sensitive ? 'gray' : undefined}
                  >
                    {truncatedValue}
                  </Text>
                </Box>
              );
            })}
          </Box>

          {/* Scroll / count info */}
          <Box marginTop={1} gap={2}>
            {sorted.length > VIEWPORT_ROWS && (
              <Text dimColor>
                Rows {clampedOffset + 1}–{Math.min(clampedOffset + VIEWPORT_ROWS, sorted.length)} of {sorted.length}
              </Text>
            )}
            {sorted.length <= VIEWPORT_ROWS && (
              <Text dimColor>{sorted.length} variables</Text>
            )}
            {allEntries.length !== sorted.length && (
              <Text dimColor>(filtered from {allEntries.length} total)</Text>
            )}
          </Box>
        </Box>
      )}
    </Box>
  );
}
