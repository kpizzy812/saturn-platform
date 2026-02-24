import React, { useState } from 'react';
import { Box, Text, useInput, useApp } from 'ink';
import { SSHProvider, useSSH } from './ssh/context.js';
import { Header } from './components/layout/Header.js';
import { Footer, DEFAULT_HINTS } from './components/layout/Footer.js';
import { Sidebar } from './components/layout/Sidebar.js';
import { useNavigation } from './hooks/useNavigation.js';
import { SCREEN_KEYS, type ScreenName } from './config/constants.js';
import type { SaturnEnv } from './config/types.js';

// Import all screens
import { DashboardScreen } from './screens/DashboardScreen.js';
import { GitScreen } from './screens/GitScreen.js';
import { DeployScreen } from './screens/DeployScreen.js';
import { LogsScreen } from './screens/LogsScreen.js';
import { ContainersScreen } from './screens/ContainersScreen.js';
import { DatabaseScreen } from './screens/DatabaseScreen.js';
import { EnvScreen } from './screens/EnvScreen.js';

const SCREEN_LABELS: Record<ScreenName, string> = {
  dashboard: 'Dashboard',
  git: 'Git & CI/CD',
  deploy: 'Deploy',
  logs: 'Logs',
  containers: 'Containers',
  database: 'Database',
  env: 'Environment',
};

function AppContent() {
  const { exit } = useApp();
  const { connected } = useSSH();
  const { currentScreen, navigate, goBack } = useNavigation();
  const [activeEnv, setActiveEnv] = useState<SaturnEnv>('dev');
  const [showHelp, setShowHelp] = useState(false);

  useInput((input, key) => {
    // Number keys 1-7 switch screens
    if (input in SCREEN_KEYS) {
      navigate(SCREEN_KEYS[input as keyof typeof SCREEN_KEYS]);
      return;
    }
    // q quits
    if (input === 'q' && !key.ctrl) {
      exit();
      return;
    }
    // Escape goes back
    if (key.escape) {
      if (showHelp) {
        setShowHelp(false);
      } else {
        goBack();
      }
      return;
    }
    // ? shows help
    if (input === '?') {
      setShowHelp(!showHelp);
      return;
    }
    // e cycles environment (dev -> staging -> production -> dev)
    if (input === 'e') {
      const envs: SaturnEnv[] = ['dev', 'staging', 'production'];
      const idx = envs.indexOf(activeEnv);
      setActiveEnv(envs[(idx + 1) % envs.length] as SaturnEnv);
      return;
    }
  });

  // Determine which screen to render
  function renderScreen() {
    const screenProps = { env: activeEnv, onEnvChange: setActiveEnv };
    switch (currentScreen) {
      case 'dashboard': return <DashboardScreen {...screenProps} />;
      case 'git': return <GitScreen />;
      case 'deploy': return <DeployScreen {...screenProps} />;
      case 'logs': return <LogsScreen {...screenProps} />;
      case 'containers': return <ContainersScreen {...screenProps} />;
      case 'database': return <DatabaseScreen {...screenProps} />;
      case 'env': return <EnvScreen {...screenProps} />;
    }
  }

  // Help overlay
  if (showHelp) {
    return (
      <Box flexDirection="column" padding={1}>
        <Text bold color="cyan">Saturn Panel — Help</Text>
        <Text/>
        <Text bold>Navigation:</Text>
        <Text>  <Text bold color="cyan">1</Text> Dashboard    <Text bold color="cyan">2</Text> Git & CI/CD    <Text bold color="cyan">3</Text> Deploy</Text>
        <Text>  <Text bold color="cyan">4</Text> Logs         <Text bold color="cyan">5</Text> Containers     <Text bold color="cyan">6</Text> Database</Text>
        <Text>  <Text bold color="cyan">7</Text> Environment</Text>
        <Text/>
        <Text bold>Controls:</Text>
        <Text>  <Text bold color="cyan">e</Text>   Cycle environment (dev/staging/prod)</Text>
        <Text>  <Text bold color="cyan">Esc</Text> Go back</Text>
        <Text>  <Text bold color="cyan">q</Text>   Quit</Text>
        <Text>  <Text bold color="cyan">?</Text>   Toggle this help</Text>
        <Text/>
        <Text dimColor>Press ? or Esc to close</Text>
      </Box>
    );
  }

  const hints = [
    ...DEFAULT_HINTS,
    { key: 'e', label: activeEnv },
  ];

  return (
    <Box flexDirection="column" height={process.stdout.rows || 24}>
      <Header
        screenName={SCREEN_LABELS[currentScreen]}
        sshConnected={connected}
        currentEnv={activeEnv}
      />
      <Box flexGrow={1}>
        <Sidebar activeEnv={activeEnv} onSelect={setActiveEnv} />
        <Box flexDirection="column" flexGrow={1} paddingX={1}>
          {renderScreen()}
        </Box>
      </Box>
      <Footer hints={hints} />
    </Box>
  );
}

export function App() {
  return (
    <SSHProvider>
      <AppContent />
    </SSHProvider>
  );
}
