import React from 'react';
import { Box, Text } from 'ink';
import type { SaturnEnv } from '../../config/types.js';
import { ENVIRONMENTS, ENV_DOMAINS } from '../../config/constants.js';

interface SidebarProps {
  activeEnv: SaturnEnv;
  onSelect: (env: SaturnEnv) => void;
  visible?: boolean;
}

export function Sidebar({ activeEnv, onSelect: _onSelect, visible = true }: SidebarProps) {
  if (!visible) return null;

  const envColors: Record<SaturnEnv, string> = {
    dev: 'green',
    staging: 'yellow',
    production: 'red',
  };

  return (
    <Box flexDirection="column" borderStyle="single" borderColor="gray" width={20} paddingX={1}>
      <Text bold underline>Environment</Text>
      {ENVIRONMENTS.map((env) => (
        <Box key={env}>
          <Text
            color={envColors[env]}
            bold={activeEnv === env}
            inverse={activeEnv === env}
          >
            {activeEnv === env ? ' ▸ ' : '   '}
            {env}
          </Text>
        </Box>
      ))}
      <Box marginTop={1}>
        <Text dimColor>{ENV_DOMAINS[activeEnv]}</Text>
      </Box>
    </Box>
  );
}
