import * as React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Header } from './Header';
import { FlashMessages } from './FlashMessages';
import { CommandPalette, useCommandPalette } from '@/components/ui/CommandPalette';
import { SaturnBackground } from '@/components/ui/SaturnBackground';
import { PageTransition } from '@/components/animation';
import { useFavorites } from '@/hooks/useFavorites';
import { ChevronRight } from 'lucide-react';

export interface Breadcrumb {
    label: string;
    href?: string;
}

interface AppLayoutProps {
    children: React.ReactNode;
    title?: string;
    showNewProject?: boolean;
    breadcrumbs?: Breadcrumb[];
}

function Breadcrumbs({ items }: { items: Breadcrumb[] }) {
    if (!items || items.length === 0) {
        return null;
    }

    return (
        <nav className="border-b border-border bg-background px-4 py-3">
            <ol className="flex items-center gap-2 text-sm">
                {items.map((breadcrumb, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={index} className="flex items-center gap-2">
                            {breadcrumb.href && !isLast ? (
                                <Link
                                    href={breadcrumb.href}
                                    className="text-foreground-muted transition-colors duration-200 hover:text-foreground"
                                >
                                    {breadcrumb.label}
                                </Link>
                            ) : (
                                <span className={isLast ? 'text-foreground' : 'text-foreground-muted'}>
                                    {breadcrumb.label}
                                </span>
                            )}
                            {!isLast && (
                                <ChevronRight className="h-4 w-4 text-foreground-muted" />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

export function AppLayout({ children, title, showNewProject = true, breadcrumbs }: AppLayoutProps) {
    const commandPalette = useCommandPalette();
    const { favorites, isFavorite, toggleFavorite } = useFavorites();

    return (
        <>
            <Head title={title ? `${title} | Saturn` : 'Saturn'} />
            <FlashMessages />
            <SaturnBackground variant="subtle" />
            <div className="relative z-10 flex h-screen flex-col">
                <Header showNewProject={showNewProject} onCommandPalette={commandPalette.open} />
                {breadcrumbs && breadcrumbs.length > 0 && <Breadcrumbs items={breadcrumbs} />}
                <main className="flex-1 overflow-auto px-6 py-8">
                    <PageTransition>{children}</PageTransition>
                </main>
            </div>
            <CommandPalette open={commandPalette.isOpen} onClose={commandPalette.close} favorites={favorites} onToggleFavorite={toggleFavorite} isFavorite={isFavorite} />
        </>
    );
}
