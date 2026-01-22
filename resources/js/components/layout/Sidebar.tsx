import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Server,
    FolderKanban,
    Database,
    Settings,
    Layers,
    Box,
} from 'lucide-react';
import { SaturnLogo } from '@/components/ui/SaturnLogo';
import { useSidebar } from '@/contexts/SidebarContext';

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
}

const navItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-5 w-5" /> },
    { label: 'Projects', href: '/projects', icon: <FolderKanban className="h-5 w-5" /> },
    { label: 'Applications', href: '/applications', icon: <Layers className="h-5 w-5" /> },
    { label: 'Services', href: '/services', icon: <Box className="h-5 w-5" /> },
    { label: 'Databases', href: '/databases', icon: <Database className="h-5 w-5" /> },
    { label: 'Servers', href: '/servers', icon: <Server className="h-5 w-5" /> },
    { label: 'Settings', href: '/settings', icon: <Settings className="h-5 w-5" /> },
];

export function Sidebar() {
    const { url } = usePage();
    const { isExpanded } = useSidebar();

    const isActive = (href: string) => url.startsWith(href);

    return (
        <aside
            className={cn(
                'flex h-screen flex-col border-r border-border bg-background transition-all duration-200',
                isExpanded ? 'w-52' : 'w-16'
            )}
        >
            {/* Logo */}
            <div className="flex h-14 items-center justify-center border-b border-border px-2">
                <Link href="/dashboard" className="flex items-center gap-2">
                    <SaturnLogo size="sm" />
                    {isExpanded && (
                        <span className="text-lg font-semibold text-foreground">Saturn</span>
                    )}
                </Link>
            </div>

            {/* Navigation */}
            <nav className="flex-1 space-y-1 p-2">
                {navItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex items-center gap-3 rounded-lg p-2.5 text-sm font-medium transition-colors',
                            isExpanded ? 'justify-start' : 'justify-center',
                            isActive(item.href)
                                ? 'bg-background-secondary text-foreground'
                                : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                        )}
                        title={!isExpanded ? item.label : undefined}
                    >
                        {item.icon}
                        {isExpanded && <span>{item.label}</span>}
                    </Link>
                ))}
            </nav>
        </aside>
    );
}
