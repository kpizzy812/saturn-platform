import React from 'react';
import { Box, Text, useInput } from 'ink';

interface Tab {
  label: string;
  value: string;
}

interface TabsProps {
  tabs: Tab[];
  activeTab: string;
  onChange: (value: string) => void;
}

export function Tabs({ tabs, activeTab, onChange }: TabsProps) {
  useInput((_input, key) => {
    const currentIndex = tabs.findIndex((t) => t.value === activeTab);

    if (key.tab) {
      if (key.shift) {
        // Shift+Tab: go to previous tab (wrapping around)
        const prevIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        const prev = tabs[prevIndex];
        if (prev) onChange(prev.value);
      } else {
        // Tab: go to next tab (wrapping around)
        const nextIndex = (currentIndex + 1) % tabs.length;
        const next = tabs[nextIndex];
        if (next) onChange(next.value);
      }
    }
  });

  return (
    <Box>
      {tabs.map((tab, index) => {
        const isActive = tab.value === activeTab;
        const isLast = index === tabs.length - 1;

        return (
          <Box key={tab.value} marginRight={isLast ? 0 : 2}>
            <Text
              color={isActive ? 'cyan' : undefined}
              bold={isActive}
              underline={isActive}
              dimColor={!isActive}
            >
              {tab.label}
            </Text>
          </Box>
        );
      })}
    </Box>
  );
}
