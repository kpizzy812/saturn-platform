import type { LogEntry } from '../config/types.js';

// Docker log format: "2026-02-22T10:30:45.123456789Z message..."
// Laravel log format: "[2026-02-22 10:30:45] production.ERROR: message..."

let logIdCounter = 0;

// Regex for Docker ISO 8601 timestamp with nanoseconds
const DOCKER_TIMESTAMP_RE = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z)\s+(.*)/s;

// Regex for Laravel log timestamp: [YYYY-MM-DD HH:MM:SS]
const LARAVEL_TIMESTAMP_RE = /^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(.*)/s;

// Regex for Laravel channel.LEVEL prefix: "production.ERROR:", "local.WARNING:", etc.
const LARAVEL_LEVEL_RE = /\b(?:production|local|staging|testing)\.(ERROR|WARN|WARNING|DEBUG|INFO|CRITICAL|NOTICE)\b/i;

// Generic level keyword regex — checked against the whole message
const LEVEL_KEYWORD_RE = /\b(ERROR|CRITICAL|EXCEPTION|WARN|WARNING|DEBUG)\b/i;

export function parseDockerLog(line: string, source: string): LogEntry {
  const id = String(++logIdCounter);
  const { timestamp, message } = parseTimestamp(line);
  const level = detectLevel(message);

  return { id, timestamp, message, level, source };
}

export function detectLevel(message: string): LogEntry['level'] {
  // 1. Laravel channel.LEVEL format has highest priority
  const laravelMatch = LARAVEL_LEVEL_RE.exec(message);
  if (laravelMatch) {
    const keyword = laravelMatch[1].toUpperCase();
    if (keyword === 'ERROR' || keyword === 'CRITICAL') return 'error';
    if (keyword === 'WARN' || keyword === 'WARNING') return 'warning';
    if (keyword === 'DEBUG') return 'debug';
    // INFO, NOTICE — fall through to default
    return 'info';
  }

  // 2. Generic keyword scan (ERROR, WARN, DEBUG, etc.)
  const genericMatch = LEVEL_KEYWORD_RE.exec(message);
  if (genericMatch) {
    const keyword = genericMatch[1].toUpperCase();
    if (keyword === 'ERROR' || keyword === 'CRITICAL' || keyword === 'EXCEPTION') return 'error';
    if (keyword === 'WARN' || keyword === 'WARNING') return 'warning';
    if (keyword === 'DEBUG') return 'debug';
  }

  return 'info';
}

export function parseTimestamp(line: string): { timestamp: string; message: string } {
  // Try Docker ISO format first: 2026-02-22T10:30:45.123456789Z
  const dockerMatch = DOCKER_TIMESTAMP_RE.exec(line);
  if (dockerMatch) {
    const [, rawTs, message] = dockerMatch;
    // Normalize to a readable ISO string; trim nanoseconds to milliseconds
    const timestamp = new Date(rawTs).toISOString();
    return { timestamp, message: message.trimEnd() };
  }

  // Try Laravel format: [2026-02-22 10:30:45]
  const laravelMatch = LARAVEL_TIMESTAMP_RE.exec(line);
  if (laravelMatch) {
    const [, rawTs, message] = laravelMatch;
    // Laravel uses local time — parse as-is and convert to ISO
    const timestamp = new Date(rawTs.replace(' ', 'T')).toISOString();
    return { timestamp, message: message.trimEnd() };
  }

  // Fallback: no recognizable timestamp — use current time, full line as message
  return {
    timestamp: new Date().toISOString(),
    message: line.trimEnd(),
  };
}

export function resetIdCounter(): void {
  logIdCounter = 0;
}
