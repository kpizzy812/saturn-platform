import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { FlashMessages } from '@/components/layout/FlashMessages';
import { CardThemeContext } from '@/components/ui/Card';
import { useCommandPalette } from '@/components/ui/CommandPalette';
import { AdminCommandPalette } from '@/components/ui/AdminCommandPalette';
import { useTheme } from '@/components/ui/ThemeProvider';
import { PageTransition, CollapseSection } from '@/components/animation';
import {
    LayoutDashboard,
    Users,
    Server,
    Settings,
    FileText,
    Shield,
    LogOut,
    ChevronDown,
    ChevronRight,
    FolderKanban,
    ListOrdered,
    HardDrive,
    MailPlus,
    Activity,
    CheckCircle,
    ClipboardList,
    LayoutTemplate,
    Bell,
    Brain,
    KeyRound,
    History,
    Webhook,
    Clock,
    Container,
    Lock,
    ArrowRightLeft,
    Menu,
    X,
    Rocket,
    BarChart3,
    Eye,
    Search,
    Command,
    Moon,
    Sun,
    ArrowLeft,
} from 'lucide-react';

export interface AdminBreadcrumb {
    label: string;
    href?: string;
}

interface AdminLayoutProps {
    children: React.ReactNode;
    title?: string;
    breadcrumbs?: AdminBreadcrumb[];
}

interface NavItem {
    label: string;
    href: string;
    icon: React.ReactNode;
}

interface NavGroup {
    label: string;
    icon: React.ReactNode;
    items: NavItem[];
}

const adminNavGroups: NavGroup[] = [
    {
        label: 'Overview',
        icon: <LayoutDashboard className="h-4 w-4" />,
        items: [
            { label: 'Dashboard', href: '/admin', icon: <LayoutDashboard className="h-4 w-4" /> },
            { label: 'Health', href: '/admin/health', icon: <Activity className="h-4 w-4" /> },
            { label: 'Metrics', href: '/admin/metrics', icon: <BarChart3 className="h-4 w-4" /> },
            { label: 'Notifications', href: '/admin/notifications', icon: <Bell className="h-4 w-4" /> },
        ],
    },
    {
        label: 'People',
        icon: <Users className="h-4 w-4" />,
        items: [
            { label: 'Users', href: '/admin/users', icon: <Users className="h-4 w-4" /> },
            { label: 'Teams', href: '/admin/teams', icon: <Users className="h-4 w-4" /> },
            { label: 'Invitations', href: '/admin/invitations', icon: <MailPlus className="h-4 w-4" /> },
            { label: 'Login History', href: '/admin/login-history', icon: <History className="h-4 w-4" /> },
        ],
    },
    {
        label: 'Resources',
        icon: <Server className="h-4 w-4" />,
        items: [
            { label: 'Servers', href: '/admin/servers', icon: <Server className="h-4 w-4" /> },
            { label: 'Projects', href: '/admin/projects', icon: <FolderKanban className="h-4 w-4" /> },
            { label: 'SSH Keys', href: '/admin/ssh-keys', icon: <KeyRound className="h-4 w-4" /> },
            { label: 'Templates', href: '/admin/templates', icon: <LayoutTemplate className="h-4 w-4" /> },
        ],
    },
    {
        label: 'Deployments',
        icon: <Rocket className="h-4 w-4" />,
        items: [
            { label: 'Deployments', href: '/admin/deployments', icon: <Rocket className="h-4 w-4" /> },
            { label: 'Approvals', href: '/admin/deployment-approvals', icon: <CheckCircle className="h-4 w-4" /> },
            { label: 'Queues', href: '/admin/queues', icon: <ListOrdered className="h-4 w-4" /> },
            { label: 'Scheduled Tasks', href: '/admin/scheduled-tasks', icon: <Clock className="h-4 w-4" /> },
        ],
    },
    {
        label: 'Monitoring',
        icon: <Eye className="h-4 w-4" />,
        items: [
            { label: 'Audit Logs', href: '/admin/audit-logs', icon: <ClipboardList className="h-4 w-4" /> },
            { label: 'System Logs', href: '/admin/logs', icon: <FileText className="h-4 w-4" /> },
            { label: 'Webhooks', href: '/admin/webhook-deliveries', icon: <Webhook className="h-4 w-4" /> },
            { label: 'AI Usage', href: '/admin/ai-usage', icon: <Brain className="h-4 w-4" /> },
        ],
    },
    {
        label: 'System',
        icon: <Settings className="h-4 w-4" />,
        items: [
            { label: 'Settings', href: '/admin/settings', icon: <Settings className="h-4 w-4" /> },
            { label: 'OAuth / SSO', href: '/admin/settings/oauth', icon: <KeyRound className="h-4 w-4" /> },
            { label: 'Backups', href: '/admin/backups', icon: <HardDrive className="h-4 w-4" /> },
            { label: 'Docker Cleanups', href: '/admin/docker-cleanups', icon: <Container className="h-4 w-4" /> },
            { label: 'SSL Certificates', href: '/admin/ssl-certificates', icon: <Lock className="h-4 w-4" /> },
            { label: 'Transfers', href: '/admin/transfers', icon: <ArrowRightLeft className="h-4 w-4" /> },
        ],
    },
];

// Get stored expanded groups from localStorage
function getStoredExpandedGroups(): Record<string, boolean> {
    if (typeof window === 'undefined') return {};
    try {
        const stored = localStorage.getItem('admin-nav-groups');
        return stored ? JSON.parse(stored) : {};
    } catch {
        return {};
    }
}

function storeExpandedGroups(groups: Record<string, boolean>) {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem('admin-nav-groups', JSON.stringify(groups));
    } catch {
        // Ignore storage errors
    }
}

function NavGroupSection({
    group,
    isExpanded,
    onToggle,
    isActive,
}: {
    group: NavGroup;
    isExpanded: boolean;
    onToggle: () => void;
    isActive: (href: string) => boolean;
}) {
    const hasActiveItem = group.items.some(item => isActive(item.href));

    return (
        <div className="mb-1">
            <button
                onClick={onToggle}
                className={cn(
                    'flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider transition-colors',
                    hasActiveItem
                        ? 'text-primary'
                        : 'text-foreground-muted/70 hover:text-foreground-muted'
                )}
            >
                <span className="flex items-center gap-2">
                    {group.icon}
                    {group.label}
                </span>
                {isExpanded ? (
                    <ChevronDown className="h-3 w-3" />
                ) : (
                    <ChevronRight className="h-3 w-3" />
                )}
            </button>

            <CollapseSection isOpen={isExpanded}>
                <div className="mt-0.5 ml-2 space-y-0.5 border-l border-primary/10 pl-2">
                    {group.items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
                                isActive(item.href)
                                    ? 'bg-gradient-to-r from-primary/20 to-purple-500/10 text-primary border border-primary/30 shadow-sm shadow-primary/10'
                                    : 'text-foreground-muted hover:bg-primary/10 hover:text-foreground border border-transparent'
                            )}
                        >
                            <span className={cn(
                                'transition-colors',
                                isActive(item.href) ? 'text-primary' : ''
                            )}>
                                {item.icon}
                            </span>
                            {item.label}
                        </Link>
                    ))}
                </div>
            </CollapseSection>
        </div>
    );
}

function AdminSidebar({ isMobileOpen, onMobileClose }: { isMobileOpen: boolean; onMobileClose: () => void }) {
    const { url, props } = usePage();
    const user = (props as any).auth as { name?: string; email?: string; avatar?: string | null; is_root_user?: boolean } | undefined;
    const systemNotifications = (props as any).systemNotifications as { unreadCount: number } | undefined;

    // Initialize expanded groups: all expanded by default, or use stored state
    const [expandedGroups, setExpandedGroups] = React.useState<Record<string, boolean>>(() => {
        const stored = getStoredExpandedGroups();
        if (Object.keys(stored).length > 0) return stored;
        // Default: all groups expanded
        const defaults: Record<string, boolean> = {};
        adminNavGroups.forEach(g => { defaults[g.label] = true; });
        return defaults;
    });

    const isActive = (href: string) => {
        if (href === '/admin') {
            return url === href;
        }
        return url.startsWith(href);
    };

    const toggleGroup = (label: string) => {
        setExpandedGroups(prev => {
            const next = { ...prev, [label]: !prev[label] };
            storeExpandedGroups(next);
            return next;
        });
    };

    const sidebarContent = (
        <>
            {/* Admin Logo with Notification Bell */}
            <div className="flex h-16 items-center justify-between border-b border-primary/20 bg-gradient-to-r from-primary/10 to-purple-500/5 px-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-purple-500 shadow-lg shadow-primary/25">
                        <Shield className="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <span className="text-base font-bold text-foreground">Admin Panel</span>
                        <div className="text-[11px] font-medium text-primary">Saturn Platform</div>
                    </div>
                </div>
                <div className="flex items-center gap-1">
                    {/* Notification Bell */}
                    <Link
                        href="/admin/notifications"
                        className="relative rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary"
                    >
                        <Bell className="h-5 w-5" />
                        {(systemNotifications?.unreadCount ?? 0) > 0 && (
                            <span className="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-danger text-[10px] font-bold text-white">
                                {systemNotifications!.unreadCount > 9 ? '9+' : systemNotifications!.unreadCount}
                            </span>
                        )}
                    </Link>
                    {/* Mobile close button */}
                    <button
                        onClick={onMobileClose}
                        className="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary lg:hidden"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
            </div>

            {/* Admin Navigation - Grouped */}
            <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                {adminNavGroups.map((group) => (
                    <NavGroupSection
                        key={group.label}
                        group={group}
                        isExpanded={expandedGroups[group.label] ?? true}
                        onToggle={() => toggleGroup(group.label)}
                        isActive={isActive}
                    />
                ))}
            </nav>

            {/* Back to Main App */}
            <div className="border-t border-primary/20 px-3 py-3">
                <Link
                    href="/dashboard"
                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-foreground-muted transition-all duration-200 hover:bg-primary/10 hover:text-primary border border-transparent hover:border-primary/20"
                >
                    <LayoutDashboard className="h-4 w-4" />
                    Back to App
                </Link>
            </div>

            {/* Admin User Section */}
            <div className="border-t border-primary/20 bg-gradient-to-r from-primary/10 to-transparent p-4">
                <div className="flex items-center gap-3">
                    {user?.avatar ? (
                        <img
                            src={user.avatar}
                            alt={user.name || 'Admin'}
                            className="h-10 w-10 rounded-xl object-cover shadow-lg shadow-primary/25"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-purple-500 text-sm font-bold text-white shadow-lg shadow-primary/25">
                            {user?.name?.charAt(0).toUpperCase() || 'A'}
                        </div>
                    )}
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-foreground truncate">
                            {user?.name || 'Admin'}
                        </p>
                        <p className="text-xs font-medium text-primary truncate">
                            Super Administrator
                        </p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary"
                    >
                        <LogOut className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </>
    );

    return (
        <>
            {/* Desktop sidebar */}
            <aside className="hidden lg:flex h-screen w-64 flex-col border-r border-primary/20 bg-gradient-to-b from-primary/5 via-background to-background">
                {sidebarContent}
            </aside>

            {/* Mobile sidebar overlay */}
            {isMobileOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={onMobileClose}
                />
            )}

            {/* Mobile sidebar drawer */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-primary/20 bg-background bg-gradient-to-b from-primary/5 via-background to-background transition-transform duration-300 lg:hidden',
                    isMobileOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                {sidebarContent}
            </aside>
        </>
    );
}

function AdminBreadcrumbs({ items }: { items: AdminBreadcrumb[] }) {
    if (!items || items.length === 0) {
        return null;
    }

    return (
        <nav className="border-b border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-6 lg:px-8 py-3">
            <ol className="flex items-center gap-2 text-sm">
                {items.map((breadcrumb, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={index} className="flex items-center gap-2">
                            {breadcrumb.href && !isLast ? (
                                <Link
                                    href={breadcrumb.href}
                                    className="text-foreground-muted transition-colors duration-200 hover:text-primary"
                                >
                                    {breadcrumb.label}
                                </Link>
                            ) : (
                                <span className={isLast ? 'text-foreground font-medium' : 'text-foreground-muted'}>
                                    {breadcrumb.label}
                                </span>
                            )}
                            {!isLast && (
                                <span className="text-primary/50">/</span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function AdminHeader({ title, onCommandPalette }: { title?: string; onCommandPalette: () => void }) {
    const { isDark, toggleTheme } = useTheme();
    const isMac = typeof window !== 'undefined' && navigator.platform.toUpperCase().indexOf('MAC') >= 0;

    return (
        <header className="hidden lg:flex h-14 items-center justify-between border-b border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-6">
            {/* Left: Page title */}
            <div className="flex items-center gap-4">
                {title && (
                    <h1 className="text-lg font-semibold text-foreground">{title}</h1>
                )}
            </div>

            {/* Center: Search trigger */}
            <button
                onClick={onCommandPalette}
                className="flex items-center gap-3 rounded-lg border border-primary/20 bg-background/50 px-4 py-2 text-sm text-foreground-muted transition-all duration-200 hover:border-primary/40 hover:bg-primary/5 hover:text-foreground"
            >
                <Search className="h-4 w-4" />
                <span>Search...</span>
                <kbd className="ml-6 flex items-center gap-1 rounded-md bg-background-tertiary px-2 py-1 text-xs font-medium">
                    {isMac ? <Command className="h-3 w-3" /> : <span>Ctrl</span>}
                    <span>K</span>
                </kbd>
            </button>

            {/* Right: Actions */}
            <div className="flex items-center gap-2">
                {/* Theme toggle */}
                <button
                    onClick={toggleTheme}
                    className="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary"
                    title={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
                >
                    {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                </button>

                {/* Back to App */}
                <Link
                    href="/dashboard"
                    className="flex items-center gap-2 rounded-lg border border-primary/20 px-3 py-1.5 text-sm font-medium text-foreground-muted transition-all duration-200 hover:border-primary/40 hover:bg-primary/10 hover:text-primary"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    <span>Back to App</span>
                </Link>
            </div>
        </header>
    );
}

export function AdminLayout({ children, title, breadcrumbs }: AdminLayoutProps) {
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = React.useState(false);
    const commandPalette = useCommandPalette();

    return (
        <>
            <Head title={title ? `${title} | Admin` : 'Admin Panel'} />
            <FlashMessages />
            <div className="flex h-screen bg-background">
                <AdminSidebar
                    isMobileOpen={isMobileSidebarOpen}
                    onMobileClose={() => setIsMobileSidebarOpen(false)}
                />
                <div className="flex flex-1 flex-col overflow-hidden">
                    {/* Desktop header */}
                    <AdminHeader title={title} onCommandPalette={commandPalette.toggle} />

                    {/* Mobile header with hamburger */}
                    <div className="flex items-center justify-between border-b border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-4 py-3 lg:hidden">
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => setIsMobileSidebarOpen(true)}
                                className="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary"
                            >
                                <Menu className="h-5 w-5" />
                            </button>
                            <div className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-primary" />
                                <span className="font-semibold text-foreground">Admin</span>
                            </div>
                        </div>
                        <button
                            onClick={commandPalette.toggle}
                            className="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-primary/10 hover:text-primary"
                        >
                            <Search className="h-5 w-5" />
                        </button>
                    </div>

                    {breadcrumbs && breadcrumbs.length > 0 && <AdminBreadcrumbs items={breadcrumbs} />}
                    <CardThemeContext.Provider value="admin">
                        <main className="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
                            <PageTransition>{children}</PageTransition>
                        </main>
                    </CardThemeContext.Provider>
                </div>
            </div>

            {/* Admin Command Palette */}
            <AdminCommandPalette open={commandPalette.isOpen} onClose={commandPalette.close} />
        </>
    );
}
