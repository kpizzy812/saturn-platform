import { lazy, Suspense } from 'react';

// Lazy load the widget to avoid initial bundle size impact
const AiChatWidget = lazy(() => import('./AiChatWidget'));

interface AiChatGlobalProps {
    isAuthenticated?: boolean;
    isAvailable?: boolean;
}

/**
 * Global AI Chat component that renders the floating widget.
 */
export function AiChatGlobal({ isAuthenticated = false, isAvailable = true }: AiChatGlobalProps) {
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
