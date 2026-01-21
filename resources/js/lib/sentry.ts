import * as Sentry from '@sentry/react';

/**
 * Initialize Sentry for error tracking
 *
 * Captures:
 * - JavaScript errors (frontend)
 * - React component errors
 * - Unhandled promise rejections
 * - Console errors
 */
export function initializeSentry(): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN;

    // Skip if no DSN configured
    if (!dsn) {
        console.debug('Sentry initialization skipped: VITE_SENTRY_DSN not configured');
        return;
    }

    // Skip in development unless explicitly enabled
    const isDev = import.meta.env.DEV;
    const enableInDev = import.meta.env.VITE_SENTRY_ENABLE_DEV === 'true';
    if (isDev && !enableInDev) {
        console.debug('Sentry initialization skipped: Development mode (set VITE_SENTRY_ENABLE_DEV=true to enable)');
        return;
    }

    try {
        Sentry.init({
            dsn,
            environment: import.meta.env.VITE_APP_ENV || 'production',
            release: import.meta.env.VITE_APP_VERSION || 'unknown',

            // Sample rate for error events (1.0 = 100%)
            sampleRate: 1.0,

            // Performance monitoring sample rate
            tracesSampleRate: isDev ? 1.0 : 0.2,

            // Session replay for debugging (optional)
            replaysSessionSampleRate: 0,
            replaysOnErrorSampleRate: isDev ? 0 : 0.1,

            integrations: [
                // Browser tracing for performance
                Sentry.browserTracingIntegration(),
                // Capture React component errors
                Sentry.replayIntegration({
                    maskAllText: false,
                    blockAllMedia: false,
                }),
            ],

            // Filter out known non-critical errors
            beforeSend(event, hint) {
                const error = hint.originalException;

                // Ignore ResizeObserver errors (browser quirk)
                if (error instanceof Error && error.message.includes('ResizeObserver')) {
                    return null;
                }

                // Ignore network errors that are expected
                if (error instanceof TypeError && error.message === 'Failed to fetch') {
                    return null;
                }

                return event;
            },

            // Don't send PII by default
            sendDefaultPii: false,
        });

        console.debug('Sentry initialized successfully');
    } catch (error) {
        console.error('Failed to initialize Sentry:', error);
    }
}

/**
 * Capture a custom error with context
 */
export function captureError(error: Error, context?: Record<string, unknown>): void {
    if (context) {
        Sentry.withScope((scope) => {
            Object.entries(context).forEach(([key, value]) => {
                scope.setExtra(key, value);
            });
            Sentry.captureException(error);
        });
    } else {
        Sentry.captureException(error);
    }
}

/**
 * Capture a custom message
 */
export function captureMessage(message: string, level: Sentry.SeverityLevel = 'info'): void {
    Sentry.captureMessage(message, level);
}

/**
 * Set user context for error tracking
 */
export function setUser(user: { id: string | number; email?: string; name?: string } | null): void {
    if (user) {
        Sentry.setUser({
            id: String(user.id),
            email: user.email,
            username: user.name,
        });
    } else {
        Sentry.setUser(null);
    }
}

/**
 * Add breadcrumb for debugging
 */
export function addBreadcrumb(
    message: string,
    category: string = 'custom',
    level: Sentry.SeverityLevel = 'info',
    data?: Record<string, unknown>
): void {
    Sentry.addBreadcrumb({
        message,
        category,
        level,
        data,
    });
}

// Re-export Sentry's ErrorBoundary for convenience
export { ErrorBoundary } from '@sentry/react';
