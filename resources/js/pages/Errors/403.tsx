import { Link } from '@inertiajs/react';
import { Home, ShieldAlert, Mail, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/Button';

interface ForbiddenProps {
    resource?: string;
    reason?: string;
}

export default function Forbidden({ resource, reason }: ForbiddenProps) {
    const displayResource = resource || 'this resource';
    const displayReason = reason || 'You do not have the necessary permissions to access this page.';

    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-6">
            <div className="w-full max-w-2xl">
                {/* Animated 403 Number */}
                <div className="relative mb-8 text-center">
                    <div className="relative inline-block">
                        {/* Gradient glow effect */}
                        <div className="absolute inset-0 animate-pulse blur-3xl">
                            <span className="bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 bg-clip-text text-[180px] font-bold leading-none opacity-20">
                                403
                            </span>
                        </div>
                        {/* Main 403 text */}
                        <h1 className="relative bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 bg-clip-text text-[180px] font-bold leading-none text-transparent">
                            403
                        </h1>
                    </div>
                </div>

                {/* Content */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8 text-center shadow-xl">
                    {/* Subtle gradient overlay */}
                    <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-100" />

                    <div className="relative">
                        {/* Icon */}
                        <div className="mb-6 flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/10">
                                <ShieldAlert className="h-8 w-8 text-amber-500" />
                            </div>
                        </div>

                        <h2 className="mb-3 text-2xl font-semibold text-foreground">
                            Access Denied
                        </h2>
                        <p className="mb-6 text-foreground-muted">
                            {displayReason}
                        </p>

                        {/* Info box */}
                        <div className="mb-8 rounded-lg border border-border/50 bg-background-tertiary/30 p-4 text-left">
                            <div className="mb-2 text-sm font-medium text-foreground">
                                Why am I seeing this?
                            </div>
                            <ul className="space-y-2 text-sm text-foreground-muted">
                                <li className="flex items-start gap-2">
                                    <span className="mt-1.5 h-1 w-1 flex-shrink-0 rounded-full bg-foreground-subtle" />
                                    <span>You may not have permission to view {displayResource}</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-1.5 h-1 w-1 flex-shrink-0 rounded-full bg-foreground-subtle" />
                                    <span>Your team role might not include this access level</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-1.5 h-1 w-1 flex-shrink-0 rounded-full bg-foreground-subtle" />
                                    <span>The resource may be restricted to certain team members</span>
                                </li>
                            </ul>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            <Link href="/settings/team">
                                <Button variant="default" className="w-full gap-2 sm:w-auto">
                                    <Mail className="h-4 w-4" />
                                    Request Access
                                </Button>
                            </Link>
                            <Link href="/">
                                <Button variant="secondary" className="w-full gap-2 sm:w-auto">
                                    <Home className="h-4 w-4" />
                                    Go to Dashboard
                                </Button>
                            </Link>
                        </div>

                        {/* Alternative action */}
                        <div className="mt-6">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex items-center gap-2 text-sm text-foreground-subtle transition-colors hover:text-foreground"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Go Back
                            </button>
                        </div>
                    </div>
                </div>

                {/* Contact help */}
                <div className="mt-6 text-center">
                    <p className="text-sm text-foreground-muted">
                        Need help?{' '}
                        <Link
                            href="/settings/team"
                            className="text-foreground-subtle hover:text-foreground"
                        >
                            Contact your team admin
                        </Link>{' '}
                        or{' '}
                        <Link
                            href="/support"
                            className="text-foreground-subtle hover:text-foreground"
                        >
                            reach out to support
                        </Link>
                        .
                    </p>
                </div>
            </div>
        </div>
    );
}
