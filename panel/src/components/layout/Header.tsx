import React from 'react';
import { Box, Text } from 'ink';

interface HeaderProps {
  screenName: string;
  sshConnected: boolean;
  currentEnv?: string;
}

export function Header({ screenName, sshConnected, currentEnv }: HeaderProps) {
  return (
    <Box borderStyle="single" borderColor="cyan" paddingX={1} justifyContent="space-between">
      <Box>
        <Text bold color="cyan">Saturn</Text>
        <Text dimColor> / </Text>
        <Text bold>{screenName}</Text>
        {currentEnv && (
          <>
            <Text dimColor> / </Text>
            <Text color="yellow">{currentEnv}</Text>
          </>
        )}
      </Box>
      <Box>
        <Text color={sshConnected ? 'green' : 'red'}>
          {sshConnected ? '● SSH' : '○ SSH'}
        </Text>
      </Box>
    </Box>
  );
}
