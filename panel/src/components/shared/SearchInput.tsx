import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import TextInput from 'ink-text-input';

interface SearchInputProps {
  placeholder?: string;
  onChange: (value: string) => void;
  onSubmit?: (value: string) => void;
  onCancel?: () => void;
}

export function SearchInput({ placeholder = 'Search...', onChange, onSubmit, onCancel }: SearchInputProps) {
  const [value, setValue] = useState<string>('');

  useInput((_input, key) => {
    if (key.escape) {
      onCancel?.();
    }
  });

  function handleChange(newValue: string) {
    setValue(newValue);
    onChange(newValue);
  }

  function handleSubmit(submittedValue: string) {
    onSubmit?.(submittedValue);
  }

  return (
    <Box>
      <Text color="cyan">/</Text>
      <TextInput
        value={value}
        placeholder={placeholder}
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </Box>
  );
}
