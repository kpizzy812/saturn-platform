import React from 'react';
import { Box, Text } from 'ink';

interface KeyHint {
  key: string;
  label: string;
}

interface FooterProps {
  hints: KeyHint[];
}

export function Footer({ hints }: FooterProps) {
  // Render hints horizontally: "1-7:Screens  q:Quit  ?:Help  Esc:Back"
  // Each hint: key in bold cyan, label in dim
  return (
    <Box borderStyle="single" borderColor="gray" paddingX={1} gap={2}>
      {hints.map((hint, i) => (
        <Box key={i}>
          <Text bold color="cyan">{hint.key}</Text>
          <Text dimColor>:{hint.label}</Text>
        </Box>
      ))}
    </Box>
  );
}

export const DEFAULT_HINTS: KeyHint[] = [
  { key: '1-7', label: 'Screens' },
  { key: 'q', label: 'Quit' },
  { key: '?', label: 'Help' },
  { key: 'Esc', label: 'Back' },
];
