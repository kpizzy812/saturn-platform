import * as Sentry from '@sentry/react';
import { AlertTriangle, RefreshCw, Home, Bug } from 'lucide-react';
import { Button } from '@/components/ui/Button';

interface FallbackProps {
    error: Error;
    componentStack: string | null;
    eventId: string | null;
    resetError: () => void;
}

/**
 * Error fallback UI shown when a React component crashes
 */
function ErrorFallback({ error, componentStack, eventId, resetError }: FallbackProps) {
    const isDev = import.meta.env.DEV;

    const handleReportFeedback = () => {
        if (eventId) {
            Sentry.showReportDialog({ eventId });
        }
    };

    return (
        <div className="flex min-h-[400px] flex-col items-center justify-center p-8">
            <div className="w-full max-w-md space-y-6 text-center">
                {/* Icon */}
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                    <AlertTriangle className="h-8 w-8 text-destructive" />
                </div>

                {/* Title */}
                <div className="space-y-2">
                    <h2 className="text-xl font-semibold">Something went wrong</h2>
                    <p className="text-sm text-muted-foreground">
                        An unexpected error occurred. Our team has been notified.
                    </p>
                </div>

                {/* Error details (dev only) */}
                {isDev && (
                    <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-left">
                        <p className="mb-2 text-sm font-medium text-destructive">
                            {error.name}: {error.message}
                        </p>
                        {componentStack && (
                            <pre className="max-h-32 overflow-auto text-xs text-muted-foreground">
                                {componentStack}
                            </pre>
                        )}
                    </div>
                )}

                {/* Event ID for support */}
                {eventId && (
                    <p className="text-xs text-muted-foreground">
                        Error ID: <code className="rounded bg-muted px-1">{eventId}</code>
                    </p>
                )}

                {/* Actions */}
                <div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
                    <Button onClick={resetError} variant="default" className="gap-2">
                        <RefreshCw className="h-4 w-4" />
                        Try Again
                    </Button>
                    <Button
                        onClick={() => (window.location.href = '/')}
                        variant="outline"
                        className="gap-2"
                    >
                        <Home className="h-4 w-4" />
                        Go Home
                    </Button>
                    {eventId && (
                        <Button onClick={handleReportFeedback} variant="ghost" className="gap-2">
                            <Bug className="h-4 w-4" />
                            Report Issue
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

interface ErrorBoundaryProps {
    children: React.ReactNode;
    fallback?: React.ReactNode;
}

/**
 * Error Boundary wrapper that catches React errors and reports to Sentry
 *
 * Usage:
 * ```tsx
 * <ErrorBoundary>
 *   <MyComponent />
 * </ErrorBoundary>
 * ```
 */
export function ErrorBoundary({ children, fallback }: ErrorBoundaryProps) {
    return (
        <Sentry.ErrorBoundary
            fallback={({ error, componentStack, eventId, resetError }) => (
                    fallback || (
                        <ErrorFallback
                            error={error instanceof Error ? error : new Error(String(error))}
                            componentStack={componentStack}
                            eventId={eventId}
                            resetError={resetError}
                        />
                    )
                ) as React.ReactElement
            }
            beforeCapture={(scope) => {
                scope.setTag('errorBoundary', 'true');
            }}
        >
            {children}
        </Sentry.ErrorBoundary>
    );
}

/**
 * HOC to wrap a component with ErrorBoundary
 */
export function withErrorBoundary<P extends object>(
    Component: React.ComponentType<P>,
    fallback?: React.ReactNode
) {
    return function WrappedComponent(props: P) {
        return (
            <ErrorBoundary fallback={fallback}>
                <Component {...props} />
            </ErrorBoundary>
        );
    };
}
