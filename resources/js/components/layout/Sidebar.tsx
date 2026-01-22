import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Server,
    FolderKanban,
    Database,
    Settings,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { SaturnLogo } from '@/components/ui/SaturnLogo';

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
}

const navItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: <LayoutDashboard className="h-5 w-5" /> },
    { label: 'Projects', href: '/projects', icon: <FolderKanban className="h-5 w-5" /> },
    { label: 'Servers', href: '/servers', icon: <Server className="h-5 w-5" /> },
    { label: 'Databases', href: '/databases', icon: <Database className="h-5 w-5" /> },
    { label: 'Settings', href: '/settings', icon: <Settings className="h-5 w-5" /> },
];

export function Sidebar() {
    const { url } = usePage();
    const [isExpanded, setIsExpanded] = React.useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('sidebar-expanded') === 'true';
        }
        return false;
    });

    const toggleSidebar = () => {
        const newValue = !isExpanded;
        setIsExpanded(newValue);
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar-expanded', String(newValue));
        }
    };

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

            {/* Toggle Button */}
            <div className="border-t border-border p-2">
                <button
                    onClick={toggleSidebar}
                    className="flex w-full items-center justify-center rounded-lg p-2.5 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                    title={isExpanded ? 'Collapse sidebar' : 'Expand sidebar'}
                >
                    {isExpanded ? (
                        <ChevronLeft className="h-5 w-5" />
                    ) : (
                        <ChevronRight className="h-5 w-5" />
                    )}
                </button>
            </div>
        </aside>
    );
}
