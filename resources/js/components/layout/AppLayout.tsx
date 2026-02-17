import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Header } from './Header';
import { FlashMessages } from './FlashMessages';
import { CommandPalette, useCommandPalette } from '@/components/ui/CommandPalette';
import { PageTransition } from '@/components/animation';
import { useRecentResources, type RecentResource } from '@/hooks/useRecentResources';
import { useResourceFrequency } from '@/hooks/useResourceFrequency';
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

const RESOURCE_PATTERNS: Array<{
    pattern: RegExp;
    type: RecentResource['type'];
    propKey: string;
}> = [
    { pattern: /^\/projects\/([a-z0-9-]+)$/, type: 'project', propKey: 'project' },
    { pattern: /^\/servers\/([a-z0-9-]+)$/, type: 'server', propKey: 'server' },
    { pattern: /^\/applications\/([a-z0-9-]+)$/, type: 'application', propKey: 'application' },
    { pattern: /^\/databases\/([a-z0-9-]+)$/, type: 'database', propKey: 'database' },
    { pattern: /^\/services\/([a-z0-9-]+)$/, type: 'service', propKey: 'service' },
];

export function AppLayout({ children, title, showNewProject = true, breadcrumbs }: AppLayoutProps) {
    const commandPalette = useCommandPalette();
    const { recentItems, addRecent } = useRecentResources();
    const { addVisit, getFavorites } = useResourceFrequency();
    const page = usePage();
    const url = page.url;
    const favorites = React.useMemo(() => getFavorites(), [getFavorites]);

    // Track resource visits
    React.useEffect(() => {
        const pathname = url.split('?')[0];
        for (const { pattern, type, propKey } of RESOURCE_PATTERNS) {
            const match = pathname.match(pattern);
            if (match) {
                const resource = (page.props as Record<string, unknown>)[propKey] as
                    | { name?: string; uuid?: string }
                    | undefined;
                if (resource?.name && resource?.uuid) {
                    addRecent({
                        type,
                        name: resource.name,
                        uuid: resource.uuid,
                        href: pathname,
                    });
                    addVisit({
                        type,
                        id: resource.uuid,
                        name: resource.name,
                        href: pathname,
                    });
                }
                break;
            }
        }
    }, [url]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title={title ? `${title} | Saturn` : 'Saturn'} />
            <FlashMessages />
            <div className="flex h-screen flex-col bg-background">
                <Header showNewProject={showNewProject} onCommandPalette={commandPalette.open} />
                {breadcrumbs && breadcrumbs.length > 0 && <Breadcrumbs items={breadcrumbs} />}
                <main className="flex-1 overflow-auto px-6 py-8">
                    <PageTransition>{children}</PageTransition>
                </main>
            </div>
            <CommandPalette open={commandPalette.isOpen} onClose={commandPalette.close} recentItems={recentItems} favorites={favorites} />
        </>
    );
}
