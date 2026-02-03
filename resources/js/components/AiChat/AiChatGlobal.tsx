import React, { lazy, Suspense, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

// Lazy load the widget to avoid initial bundle size impact
const AiChatWidget = lazy(() => import('./AiChatWidget'));

interface AiChatPageProps extends InertiaPageProps {
    auth?: {
        user?: {
            id: number;
        };
    };
    aiChat?: {
        enabled: boolean;
        available: boolean;
    };
    // For context-aware chat
    application?: { uuid: string; name: string; id: number };
    service?: { uuid: string; name: string; id: number };
    database?: { uuid: string; name: string; id: number };
    server?: { uuid: string; name: string; id: number };
    [key: string]: unknown;
}

/**
 * Global AI Chat component that renders the floating widget
 * based on authentication and feature availability.
 */
export function AiChatGlobal() {
    const { props } = usePage<AiChatPageProps>();

    // Only render if user is authenticated and AI chat is available
    const shouldRender = useMemo(() => {
        return props.auth?.user && props.aiChat?.available !== false;
    }, [props.auth?.user, props.aiChat?.available]);

    // Build context from page props
    const context = useMemo(() => {
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

    if (!shouldRender) {
        return null;
    }

    return (
        <Suspense fallback={null}>
            <AiChatWidget context={context} />
        </Suspense>
    );
}

export default AiChatGlobal;
