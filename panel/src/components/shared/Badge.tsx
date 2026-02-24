import React from 'react';
import { Text } from 'ink';

interface BadgeProps {
  label: string;
  color: string;
  inverse?: boolean;
}

export function Badge({ label, color, inverse = false }: BadgeProps) {
  return (
    <Text color={color} inverse={inverse}>
      {` ${label.toUpperCase()} `}
    </Text>
  );
}

/**
 * Map a container status string to a chalk/ink color name.
 * Used to colorize status badges consistently throughout the TUI.
 */
export function statusColor(status: string): string {
  const normalized = status.toLowerCase().trim();

  if (normalized === 'running' || normalized === 'healthy') return 'green';
  if (normalized === 'stopped' || normalized === 'exited') return 'red';
  if (normalized === 'starting' || normalized === 'restarting') return 'yellow';
  if (normalized === 'paused') return 'blue';

  return 'gray';
}
