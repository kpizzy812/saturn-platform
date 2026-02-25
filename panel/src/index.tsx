#!/usr/bin/env node
import React from 'react';
import { render } from 'ink';
import { App } from './app.js';

// Simple CLI args
const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
  console.log(`
Saturn Panel — Terminal UI for Saturn Platform

Usage:
  saturn-panel              Launch the panel
  saturn-panel --help       Show this help

Keyboard:
  1-7    Switch screens (Dashboard, Git, Deploy, Logs, Containers, Database, Env)
  e      Cycle environment (dev -> staging -> production)
  q      Quit
  ?      Help
  Esc    Go back
`);
  process.exit(0);
}

if (args.includes('--version') || args.includes('-v')) {
  console.log('saturn-panel v0.1.0');
  process.exit(0);
}

// Launch the TUI
const { waitUntilExit } = render(<App />);
waitUntilExit().then(() => {
  process.exit(0);
});
