import { Link, router } from '@inertiajs/react';
import { Home, RefreshCw, MessageCircle, Copy, Check } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { useState } from 'react';

interface ServerErrorProps {
    errorId?: string;
    message?: string;
}

export default function ServerError({ errorId, message }: ServerErrorProps) {
    const [copied, setCopied] = useState(false);
    const displayErrorId = errorId || `ERR-${Date.now().toString(36).toUpperCase()}`;
    const displayMessage = message || 'An unexpected error occurred on our servers.';

    const handleCopyErrorId = () => {
        navigator.clipboard.writeText(displayErrorId);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleRetry = () => {
        router.reload();
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-6">
            <div className="w-full max-w-2xl">
                {/* Animated 500 Number */}
                <div className="relative mb-8 text-center">
                    <div className="relative inline-block">
                        {/* Gradient glow effect */}
                        <div className="absolute inset-0 animate-pulse blur-3xl">
                            <span className="bg-gradient-to-r from-danger via-orange-500 to-amber-500 bg-clip-text text-[180px] font-bold leading-none opacity-20">
                                500
                            </span>
                        </div>
                        {/* Main 500 text */}
                        <h1 className="relative bg-gradient-to-r from-danger via-orange-500 to-amber-500 bg-clip-text text-[180px] font-bold leading-none text-transparent">
                            500
                        </h1>
                    </div>
                </div>

                {/* Content */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8 text-center shadow-xl">
                    {/* Subtle gradient overlay */}
                    <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-100" />

                    <div className="relative">
                        <h2 className="mb-3 text-2xl font-semibold text-foreground">
                            Something Went Wrong
                        </h2>
                        <p className="mb-6 text-foreground-muted">
                            {displayMessage}
                        </p>

                        {/* Error Reference ID */}
                        <div className="mb-8 rounded-lg border border-border/50 bg-background-tertiary/30 p-4">
                            <div className="mb-2 text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                Error Reference
                            </div>
                            <div className="flex items-center justify-center gap-2">
                                <code className="rounded bg-background px-3 py-1.5 font-mono text-sm text-foreground">
                                    {displayErrorId}
                                </code>
                                <button
                                    onClick={handleCopyErrorId}
                                    className="rounded-md p-1.5 transition-colors hover:bg-white/10"
                                    title="Copy error ID"
                                >
                                    {copied ? (
                                        <Check className="h-4 w-4 text-emerald-500" />
                                    ) : (
                                        <Copy className="h-4 w-4 text-foreground-muted" />
                                    )}
                                </button>
                            </div>
                            <p className="mt-2 text-xs text-foreground-subtle">
                                Share this ID with support for faster assistance
                            </p>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            <Button
                                variant="default"
                                className="w-full gap-2 sm:w-auto"
                                onClick={handleRetry}
                            >
                                <RefreshCw className="h-4 w-4" />
                                Try Again
                            </Button>
                            <Link href="/">
                                <Button variant="secondary" className="w-full gap-2 sm:w-auto">
                                    <Home className="h-4 w-4" />
                                    Go to Dashboard
                                </Button>
                            </Link>
                        </div>

                        {/* Support link */}
                        <div className="mt-6">
                            <Link
                                href="/support"
                                className="inline-flex items-center gap-2 text-sm text-foreground-subtle transition-colors hover:text-foreground"
                            >
                                <MessageCircle className="h-4 w-4" />
                                Contact Support
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Additional help */}
                <div className="mt-6 text-center">
                    <p className="text-sm text-foreground-muted">
                        If this issue persists, please contact your system administrator.
                    </p>
                </div>
            </div>
        </div>
    );
}
