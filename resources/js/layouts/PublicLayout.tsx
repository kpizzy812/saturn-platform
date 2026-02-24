import * as React from 'react';
import { Head } from '@inertiajs/react';

interface PublicLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export function PublicLayout({ children, title }: PublicLayoutProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <main className="flex-1">
                    {children}
                </main>
                <footer className="border-t border-white/[0.06] py-6 text-center text-xs text-foreground-muted">
                    Powered by Saturn
                </footer>
            </div>
        </>
    );
}
