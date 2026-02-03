import React, { lazy, Suspense, useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import type { ChatContext } from '@/types/ai-chat';

// Lazy load the widget to avoid initial bundle size impact
const AiChatWidget = lazy(() => import('./AiChatWidget'));

interface PageProps {
    auth?: {
        user?: {
            id: number;
        };
    };
    aiChat?: {
        enabled: boolean;
        available: boolean;
    };
    application?: { uuid: string; name: string; id: number };
    service?: { uuid: string; name: string; id: number };
    database?: { uuid: string; name: string; id: number };
    server?: { uuid: string; name: string; id: number };
}

/**
 * Global AI Chat component that renders the floating widget
 * based on authentication and feature availability.
 *
 * Uses Inertia router events to stay in sync with page navigation,
 * avoiding usePage() hook which requires Inertia context.
 */
export function AiChatGlobal({ initialProps }: { initialProps?: PageProps }) {
    const [props, setProps] = useState<PageProps>(initialProps || {});

    // Update props on Inertia navigation
    useEffect(() => {
        const handleNavigate = (event: { detail: { page: { props: PageProps } } }) => {
            setProps(event.detail.page.props);
        };

        // Listen for successful navigation
        const removeListener = router.on('navigate', handleNavigate as unknown as () => void);

        return () => {
            removeListener();
        };
    }, []);

    // Build context from current page props
    const getContext = useCallback((): ChatContext | undefined => {
        if (props.application) {
            return {
                type: 'application',
                id: props.application.id,
                name: props.application.name,
                uuid: props.application.uuid,
            };
        }
        if (props.service) {
            return {
                type: 'service',
                id: props.service.id,
                name: props.service.name,
                uuid: props.service.uuid,
            };
        }
        if (props.database) {
            return {
                type: 'database',
                id: props.database.id,
                name: props.database.name,
                uuid: props.database.uuid,
            };
        }
        if (props.server) {
            return {
                type: 'server',
                id: props.server.id,
                name: props.server.name,
                uuid: props.server.uuid,
            };
        }
        return undefined;
    }, [props.application, props.service, props.database, props.server]);

    // Only render if user is authenticated and AI chat is available
    const shouldRender = props.auth?.user && props.aiChat?.available !== false;

    if (!shouldRender) {
        return null;
    }

    return (
        <Suspense fallback={null}>
            <AiChatWidget context={getContext()} />
        </Suspense>
    );
}

export default AiChatGlobal;
