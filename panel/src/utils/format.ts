import { formatDistanceToNow } from 'date-fns';

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'] as const;

export function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) return '0 B';

  if (bytes === 0) return '0 B';

  const exponent = Math.min(
    Math.floor(Math.log(bytes) / Math.log(1024)),
    BYTE_UNITS.length - 1,
  );

  const value = bytes / Math.pow(1024, exponent);
  const unit = BYTE_UNITS[exponent];

  // Use one decimal place for KB and above; whole number for bytes
  return exponent === 0 ? `${value} ${unit}` : `${value.toFixed(1)} ${unit}`;
}

export function formatDuration(ms: number): string {
  if (!Number.isFinite(ms) || ms < 0) return '0s';

  const totalSeconds = Math.floor(ms / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) {
    return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
  }

  if (minutes > 0) {
    return seconds > 0 ? `${minutes}m ${seconds}s` : `${minutes}m`;
  }

  return `${seconds}s`;
}

export function formatRelativeTime(date: Date | string): string {
  const parsed = typeof date === 'string' ? new Date(date) : date;
  return formatDistanceToNow(parsed, { addSuffix: true });
}

export function truncate(str: string, maxLength: number): string {
  if (str.length <= maxLength) return str;
  return str.slice(0, maxLength - 1) + '\u2026'; // …
}

export function padRight(str: string, length: number): string {
  return str.padEnd(length);
}

export function padLeft(str: string, length: number): string {
  return str.padStart(length);
}
