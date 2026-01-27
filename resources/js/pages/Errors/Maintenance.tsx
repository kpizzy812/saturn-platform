import { Settings, Clock, Bell, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useState } from 'react';

interface MaintenanceProps {
    estimatedReturn?: string;
    message?: string;
    statusUrl?: string;
}

export default function Maintenance({
    estimatedReturn,
    message,
    statusUrl
}: MaintenanceProps) {
    const [email, setEmail] = useState('');
    const [subscribed, setSubscribed] = useState(false);
    const [loading, setLoading] = useState(false);

    const displayMessage = message ||
        'We are currently performing scheduled maintenance to improve your experience.';

    const handleSubscribe = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));

        setSubscribed(true);
        setLoading(false);
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-6">
            <div className="w-full max-w-2xl">
                {/* Animated Icon */}
                <div className="relative mb-8 flex justify-center">
                    <div className="relative">
                        {/* Rotating glow effect */}
                        <div className="absolute inset-0 animate-spin-slow blur-2xl">
                            <div className="h-32 w-32 rounded-full bg-gradient-to-r from-primary via-purple-500 to-primary opacity-20" />
                        </div>
                        {/* Icon container */}
                        <div className="relative flex h-32 w-32 items-center justify-center rounded-full border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50">
                            <Settings className="h-16 w-16 animate-spin-slow text-primary" />
                        </div>
                    </div>
                </div>

                {/* Content */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8 text-center shadow-xl">
                    {/* Subtle gradient overlay */}
                    <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-100" />

                    <div className="relative">
                        <h1 className="mb-3 text-3xl font-bold text-foreground">
                            We'll Be Back Soon
                        </h1>
                        <p className="mb-6 text-foreground-muted">
                            {displayMessage}
                        </p>

                        {/* Estimated return time */}
                        {estimatedReturn && (
                            <div className="mb-8 rounded-lg border border-border/50 bg-background-tertiary/30 p-4">
                                <div className="flex items-center justify-center gap-2 text-foreground">
                                    <Clock className="h-5 w-5 text-primary" />
                                    <div className="text-left">
                                        <div className="text-xs font-medium uppercase tracking-wide text-foreground-muted">
                                            Estimated Return
                                        </div>
                                        <div className="text-lg font-semibold">
                                            {estimatedReturn}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Status page link */}
                        {statusUrl && (
                            <div className="mb-8">
                                <a
                                    href={statusUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 transition-all hover:border-primary/50 hover:bg-primary/10"
                                >
                                    <ExternalLink className="h-4 w-4" />
                                    <span className="text-sm font-medium">Check Status Page</span>
                                </a>
                            </div>
                        )}

                        {/* Email subscription */}
                        <div className="rounded-lg border border-border/50 bg-background-tertiary/30 p-6">
                            <div className="mb-4 flex items-center justify-center gap-2">
                                <Bell className="h-5 w-5 text-foreground-muted" />
                                <h3 className="text-sm font-medium text-foreground">
                                    Get Notified When We're Back
                                </h3>
                            </div>

                            {subscribed ? (
                                <div className="rounded-lg bg-emerald-500/10 p-4 text-emerald-500">
                                    <p className="text-sm font-medium">
                                        Thanks! We'll notify you at {email} when we're back online.
                                    </p>
                                </div>
                            ) : (
                                <form onSubmit={handleSubscribe} className="flex flex-col gap-3 sm:flex-row">
                                    <Input
                                        type="email"
                                        placeholder="Enter your email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                        className="flex-1"
                                    />
                                    <Button
                                        type="submit"
                                        variant="default"
                                        loading={loading}
                                        className="sm:w-auto"
                                    >
                                        Notify Me
                                    </Button>
                                </form>
                            )}
                        </div>

                        {/* Additional info */}
                        <div className="mt-6 text-sm text-foreground-subtle">
                            <p>
                                Your services are still running and deployments will resume automatically
                                once maintenance is complete.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="mt-6 text-center">
                    <p className="text-sm text-foreground-subtle">
                        Your services are still running. This page will refresh automatically.
                    </p>
                </div>
            </div>
        </div>
    );
}
