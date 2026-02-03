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
        // Set Sentry user context if authenticated
        const auth = props.initialPage.props.auth as { user?: { id: number; email: string; name: string } } | undefined;
        if (auth?.user) {
            setUser({ id: auth.user.id, email: auth.user.email, name: auth.user.name });
        }

        const root = createRoot(el);
        root.render(
            <ErrorBoundary>
                <ThemeProvider>
                    <ToastProvider>
                        <ConfirmationProvider>
                            <App {...props} />
                            <AiChatGlobal
                                isAuthenticated={!!auth?.user}
                                isAvailable={true}
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
