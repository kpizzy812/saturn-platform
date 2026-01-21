import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Server,
    FolderKanban,
    Database,
    Settings,
    Users,
    Activity,
    ChevronDown,
    Plus,
    LogOut,
} from 'lucide-react';
import { SaturnLogo } from '@/components/ui/SaturnLogo';

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    active?: boolean;
}

const mainNavItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-4 w-4" /> },
    { label: 'Projects', href: '/projects', icon: <FolderKanban className="h-4 w-4" /> },
    { label: 'Servers', href: '/servers', icon: <Server className="h-4 w-4" /> },
    { label: 'Databases', href: '/databases', icon: <Database className="h-4 w-4" /> },
];

const bottomNavItems: NavItem[] = [
    { label: 'Activity', href: '/activity', icon: <Activity className="h-4 w-4" /> },
    { label: 'Team', href: '/settings/team', icon: <Users className="h-4 w-4" /> },
    { label: 'Settings', href: '/settings', icon: <Settings className="h-4 w-4" /> },
];

export function Sidebar() {
    const { url, props } = usePage();
    const user = props.auth?.user as { name?: string; email?: string } | undefined;

    const isActive = (href: string) => url.startsWith(href);

    return (
        <aside className="flex h-screen w-64 flex-col border-r border-border bg-background">
            {/* Logo */}
            <div className="flex h-14 items-center gap-2 border-b border-border px-4">
                <SaturnLogo size="sm" />
                <span className="text-lg font-semibold text-foreground">Saturn</span>
            </div>

            {/* New Project Button */}
            <div className="p-4">
                <Link
                    href="/projects/create"
                    className="flex w-full items-center justify-center gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                >
                    <Plus className="h-4 w-4" />
                    New Project
                </Link>
            </div>

            {/* Main Navigation */}
            <nav className="flex-1 space-y-1 px-2">
                {mainNavItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isActive(item.href)
                                ? 'bg-background-secondary text-foreground'
                                : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                        )}
                    >
                        {item.icon}
                        {item.label}
                    </Link>
                ))}
            </nav>

            {/* Bottom Navigation */}
            <nav className="space-y-1 border-t border-border px-2 py-4">
                {bottomNavItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isActive(item.href)
                                ? 'bg-background-secondary text-foreground'
                                : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                        )}
                    >
                        {item.icon}
                        {item.label}
                    </Link>
                ))}
            </nav>

            {/* User Section */}
            <div className="border-t border-border p-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white">
                        {user?.name?.charAt(0).toUpperCase() || 'U'}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-foreground truncate">
                            {user?.name || 'User'}
                        </p>
                        <p className="text-xs text-foreground-muted truncate">
                            {user?.email || 'user@example.com'}
                        </p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="text-foreground-muted hover:text-foreground"
                    >
                        <LogOut className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </aside>
    );
}
