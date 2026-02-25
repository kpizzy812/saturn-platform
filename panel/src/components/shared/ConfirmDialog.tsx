import React from 'react';
import { Box, Text, useInput } from 'ink';

interface ConfirmDialogProps {
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  /** When true the message is rendered in red to signal a destructive action */
  destructive?: boolean;
}

export function ConfirmDialog({ message, onConfirm, onCancel, destructive = false }: ConfirmDialogProps) {
  useInput((input, key) => {
    if (input === 'y' || input === 'Y') {
      onConfirm();
      return;
    }

    if (input === 'n' || input === 'N' || key.escape) {
      onCancel();
    }
  });

  return (
    <Box>
      <Text color={destructive ? 'red' : undefined}>{message}</Text>
      <Text> </Text>
      <Text dimColor>[y/N]: </Text>
    </Box>
  );
}
