import { Link } from '@inertiajs/react';
import { Home, Search, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/Button';

export default function NotFound() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-6">
            <div className="w-full max-w-2xl">
                {/* Animated 404 Number */}
                <div className="relative mb-8 text-center">
                    <div className="relative inline-block">
                        {/* Gradient glow effect */}
                        <div className="absolute inset-0 animate-pulse blur-3xl">
                            <span className="bg-gradient-to-r from-primary via-purple-500 to-pink-500 bg-clip-text text-[180px] font-bold leading-none opacity-20">
                                404
                            </span>
                        </div>
                        {/* Main 404 text */}
                        <h1 className="relative bg-gradient-to-r from-primary via-purple-500 to-pink-500 bg-clip-text text-[180px] font-bold leading-none text-transparent">
                            404
                        </h1>
                    </div>
                </div>

                {/* Content */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8 text-center shadow-xl">
                    {/* Subtle gradient overlay */}
                    <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-100" />

                    <div className="relative">
                        <h2 className="mb-3 text-2xl font-semibold text-foreground">
                            Page Not Found
                        </h2>
                        <p className="mb-8 text-foreground-muted">
                            The page you're looking for doesn't exist or has been moved.
                            Try searching or go back to the dashboard.
                        </p>

                        {/* Actions */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            <Link href="/">
                                <Button variant="default" className="w-full gap-2 sm:w-auto">
                                    <Home className="h-4 w-4" />
                                    Go to Dashboard
                                </Button>
                            </Link>
                            <Link href="javascript:history.back()">
                                <Button variant="secondary" className="w-full gap-2 sm:w-auto">
                                    <ArrowLeft className="h-4 w-4" />
                                    Go Back
                                </Button>
                            </Link>
                        </div>

                        {/* Search suggestion */}
                        <div className="mt-8 rounded-lg border border-border/50 bg-background-tertiary/30 p-4">
                            <div className="flex items-center justify-center gap-2 text-sm text-foreground-muted">
                                <Search className="h-4 w-4" />
                                <span>
                                    Try using{' '}
                                    <kbd className="rounded border border-border bg-background px-2 py-1 font-mono text-xs text-foreground">
                                        Cmd+K
                                    </kbd>{' '}
                                    to search
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Quick links */}
                <div className="mt-6 text-center">
                    <p className="mb-3 text-sm text-foreground-muted">Quick links:</p>
                    <div className="flex flex-wrap justify-center gap-3">
                        <Link
                            href="/projects"
                            className="text-sm text-foreground-subtle transition-colors hover:text-foreground"
                        >
                            Projects
                        </Link>
                        <span className="text-foreground-subtle">·</span>
                        <Link
                            href="/services"
                            className="text-sm text-foreground-subtle transition-colors hover:text-foreground"
                        >
                            Services
                        </Link>
                        <span className="text-foreground-subtle">·</span>
                        <Link
                            href="/databases"
                            className="text-sm text-foreground-subtle transition-colors hover:text-foreground"
                        >
                            Databases
                        </Link>
                        <span className="text-foreground-subtle">·</span>
                        <Link
                            href="/settings"
                            className="text-sm text-foreground-subtle transition-colors hover:text-foreground"
                        >
                            Settings
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
