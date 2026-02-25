import { useInput } from 'ink';
import { useCallback } from 'react';

export interface KeyBinding {
  /** Display label shown in the status bar, e.g. '1-7', 'q', 'Esc', '/' */
  key: string;
  /** Human-readable description of what this binding does */
  description: string;
  /** Called when the binding matches the current input event */
  handler: () => void;
}

/**
 * Per-screen keybinding registry.
 * Iterates through the provided bindings in order and calls the first match.
 * When active=false all input is silently ignored (useful when a modal is open).
 */
export function useKeyBindings(bindings: KeyBinding[], active: boolean = true): void {
  useInput(
    useCallback(
      (input: string, key: Parameters<Parameters<typeof useInput>[0]>[1]) => {
        if (!active) return;

        for (const binding of bindings) {
          if (matchKey(input, key, binding.key)) {
            binding.handler();
            return;
          }
        }
      },
      // eslint-disable-next-line react-hooks/exhaustive-deps
      [bindings, active],
    ),
  );
}

/**
 * Match a raw ink input event against a binding key label.
 *
 * Supported labels:
 *   'Esc'   — key.escape
 *   'Enter' — key.return
 *   'Tab'   — key.tab
 *   'up'    — key.upArrow
 *   'down'  — key.downArrow
 *   Any other string (e.g. 'q', '1'-'9', '/', '?') — direct input character match
 */
function matchKey(
  input: string,
  key: { escape: boolean; return: boolean; tab: boolean; upArrow: boolean; downArrow: boolean },
  bindingKey: string,
): boolean {
  switch (bindingKey) {
    case 'Esc':
      return key.escape;
    case 'Enter':
      return key.return;
    case 'Tab':
      return key.tab;
    case 'up':
      return key.upArrow;
    case 'down':
      return key.downArrow;
    default:
      return input === bindingKey;
  }
}
