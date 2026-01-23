import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { initializeEcho } from '@/lib/echo';
import { initializeSentry, setUser } from '@/lib/sentry';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { ConfirmationProvider } from '@/components/ui';

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
        const user = props.initialPage.props.auth?.user;
        if (user) {
            setUser({ id: user.id, email: user.email, name: user.name });
        }

        const root = createRoot(el);
        root.render(
            <ErrorBoundary>
                <ConfirmationProvider>
                    <App {...props} />
                </ConfirmationProvider>
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#10B981',
    },
});
