import { useState, useCallback } from 'react';
import type { ScreenName } from '../config/constants.js';

export interface UseNavigationResult {
  currentScreen: ScreenName;
  history: ScreenName[];
  navigate: (screen: ScreenName) => void;
  goBack: () => void;
  canGoBack: boolean;
}

/**
 * Screen navigation stack hook.
 * Maintains a history array where the last item is always the current screen.
 * goBack() pops the stack but never removes the root item (dashboard).
 */
export function useNavigation(initialScreen: ScreenName = 'dashboard'): UseNavigationResult {
  // History is the full stack; the last element is the current screen
  const [history, setHistory] = useState<ScreenName[]>([initialScreen]);

  const currentScreen = history[history.length - 1] ?? initialScreen;
  const canGoBack = history.length > 1;

  const navigate = useCallback((screen: ScreenName): void => {
    setHistory((prev) => [...prev, screen]);
  }, []);

  const goBack = useCallback((): void => {
    setHistory((prev) => {
      // Never remove the root entry — keep at least one item
      if (prev.length <= 1) return prev;
      return prev.slice(0, prev.length - 1);
    });
  }, []);

  return { currentScreen, history, navigate, goBack, canGoBack };
}
