import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { initializeEcho } from '@/lib/echo';
import { initializeSentry, setUser } from '@/lib/sentry';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { ConfirmationProvider, ToastProvider, ThemeProvider } from '@/components/ui';
import { AiChatGlobal } from '@/components/AiChat';

const appName = import.meta.env.VITE_APP_NAME || 'Saturn Platform';

// Initialize error tracking (Sentry)
initializeSentry();

// Initialize Laravel Echo for WebSocket support
initializeEcho();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        // Auth is directly on props, not nested under 'user'
        const auth = props.initialPage.props.auth as { id?: number; email?: string; name?: string } | undefined;

        // Check if user is authenticated (id can be 0, so check for undefined/null explicitly)
        const isAuthenticated = auth?.id !== undefined && auth?.id !== null;

        // Check if AI Chat is enabled in instance settings
        const aiChatEnabled = props.initialPage.props.aiChatEnabled as boolean ?? true;

        // Set Sentry user context if authenticated
        if (isAuthenticated) {
            setUser({ id: auth.id!, email: auth.email, name: auth.name });
        }

        const root = createRoot(el);
        root.render(
            <ErrorBoundary>
                <ThemeProvider>
                    <ToastProvider>
                        <ConfirmationProvider>
                            <App {...props} />
                            <AiChatGlobal
                                isAuthenticated={isAuthenticated}
                                isAvailable={aiChatEnabled}
                            />
                        </ConfirmationProvider>
                    </ToastProvider>
                </ThemeProvider>
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#10B981',
    },
});
