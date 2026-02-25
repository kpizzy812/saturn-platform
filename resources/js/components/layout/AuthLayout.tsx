import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import { SaturnLogo } from '@/components/ui/SaturnLogo';
import { SaturnBackground } from '@/components/ui/SaturnBackground';
import { FlashMessages } from './FlashMessages';

interface AuthLayoutProps {
    children: ReactNode;
    title: string;
    subtitle?: string;
}

export function AuthLayout({ children, title, subtitle }: AuthLayoutProps) {
    return (
        <>
            <Head title={`${title} | Saturn`} />
            <FlashMessages />
            <SaturnBackground variant="prominent" />
            <div className="relative flex min-h-screen items-center justify-center p-4">
                <div className="w-full max-w-md">
                    {/* Logo */}
                    <div className="mb-8 flex flex-col items-center">
                        <SaturnLogo size="xl" />
                        <h1 className="mt-4 text-2xl font-bold text-foreground">Saturn</h1>
                        {subtitle && (
                            <p className="mt-2 text-center text-foreground-muted">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    {/* Content */}
                    <div className="rounded-lg border border-border bg-background-secondary p-6">
                        {children}
                    </div>

                    {/* Footer */}
                    <p className="mt-6 text-center text-sm text-foreground-muted">
                        Self-hosted platform for deploying applications.
                    </p>
                </div>
            </div>
        </>
    );
}
