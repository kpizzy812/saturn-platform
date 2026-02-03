import React, { lazy, Suspense } from 'react';

// Lazy load the widget to avoid initial bundle size impact
const AiChatWidget = lazy(() => import('./AiChatWidget'));

interface AiChatGlobalProps {
    isAuthenticated?: boolean;
    isAvailable?: boolean;
}

/**
 * Global AI Chat component that renders the floating widget.
 *
 * Simplified version - no context awareness, just renders the widget
 * if user is authenticated and AI chat is available.
 *
 * Context-aware features can be added later via the chat itself.
 */
export function AiChatGlobal({ isAuthenticated = false, isAvailable = true }: AiChatGlobalProps) {
    // Only render if user is authenticated and AI chat is available
    if (!isAuthenticated || !isAvailable) {
        return null;
    }

    return (
        <Suspense fallback={null}>
            <AiChatWidget />
        </Suspense>
    );
}

export default AiChatGlobal;
