import stripAnsi from 'strip-ansi';

// eslint-disable-next-line no-control-regex
const ANSI_RE = /\x1b\[[0-9;]*m/;

export function stripAnsiCodes(str: string): string {
  return stripAnsi(str);
}

export function hasAnsiCodes(str: string): boolean {
  return ANSI_RE.test(str);
}

// Map service names to chalk color names for multi-container log view.
// Key = service type substring present in the container name.
export const CONTAINER_COLORS: Record<string, string> = {
  app: 'cyan',
  db: 'yellow',
  redis: 'magenta',
  realtime: 'green',
};

// Container names follow the pattern: saturn-{env}, saturn-db-{env},
// saturn-redis-{env}, saturn-realtime-{env}.
// The "app" container is named "saturn-{env}" without a service infix.
export function getContainerColor(containerName: string): string {
  for (const [service, color] of Object.entries(CONTAINER_COLORS)) {
    if (service === 'app') {
      // The app container does NOT contain -db-, -redis-, or -realtime-
      const isAppContainer =
        containerName.startsWith('saturn-') &&
        !containerName.includes('-db-') &&
        !containerName.includes('-redis-') &&
        !containerName.includes('-realtime-');

      if (isAppContainer) return color;
      continue;
    }

    // Non-app services: container name must contain the exact infix, e.g. "-db-"
    if (containerName.includes(`-${service}-`)) {
      return color;
    }
  }

  return 'white';
}
