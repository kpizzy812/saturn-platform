import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { FlashMessages } from '@/components/layout/FlashMessages';
import {
    LayoutDashboard,
    Users,
    Server,
    Users as Teams,
    Settings,
    FileText,
    Shield,
    LogOut,
    ChevronDown,
    FolderKanban,
    ListOrdered,
    HardDrive,
    MailPlus,
    Activity,
    CheckCircle,
    ClipboardList,
    LayoutTemplate,
    Bell,
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

const adminNavItems: NavItem[] = [
    { label: 'Dashboard', href: '/admin', icon: <LayoutDashboard className="h-4 w-4" /> },
    { label: 'Health', href: '/admin/health', icon: <Activity className="h-4 w-4" /> },
    { label: 'Notifications', href: '/admin/notifications', icon: <Bell className="h-4 w-4" /> },
    { label: 'Users', href: '/admin/users', icon: <Users className="h-4 w-4" /> },
    { label: 'Teams', href: '/admin/teams', icon: <Teams className="h-4 w-4" /> },
    { label: 'Projects', href: '/admin/projects', icon: <FolderKanban className="h-4 w-4" /> },
    { label: 'Servers', href: '/admin/servers', icon: <Server className="h-4 w-4" /> },
    { label: 'Approvals', href: '/admin/deployment-approvals', icon: <CheckCircle className="h-4 w-4" /> },
    { label: 'Templates', href: '/admin/templates', icon: <LayoutTemplate className="h-4 w-4" /> },
    { label: 'Queues', href: '/admin/queues', icon: <ListOrdered className="h-4 w-4" /> },
    { label: 'Backups', href: '/admin/backups', icon: <HardDrive className="h-4 w-4" /> },
    { label: 'Invitations', href: '/admin/invitations', icon: <MailPlus className="h-4 w-4" /> },
    { label: 'Settings', href: '/admin/settings', icon: <Settings className="h-4 w-4" /> },
    { label: 'System Logs', href: '/admin/logs', icon: <FileText className="h-4 w-4" /> },
    { label: 'Audit Logs', href: '/admin/audit-logs', icon: <ClipboardList className="h-4 w-4" /> },
];

function AdminSidebar() {
    const { url, props } = usePage();
    const user = (props as any).auth as { name?: string; email?: string; is_root_user?: boolean } | undefined;
    const systemNotifications = (props as any).systemNotifications as { unreadCount: number } | undefined;

    const isActive = (href: string) => {
        if (href === '/admin') {
            return url === href;
        }
        return url.startsWith(href);
    };

    return (
        <aside className="flex h-screen w-64 flex-col border-r border-primary/20 bg-gradient-to-b from-primary/5 via-background to-background">
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
            </div>

            {/* Admin Navigation */}
            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {adminNavItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200',
                            isActive(item.href)
                                ? 'bg-gradient-to-r from-primary/20 to-purple-500/10 text-primary border border-primary/30 shadow-sm shadow-primary/10'
                                : 'text-foreground-muted hover:bg-primary/10 hover:text-foreground hover:border-primary/20 border border-transparent'
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
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-purple-500 text-sm font-bold text-white shadow-lg shadow-primary/25">
                        {user?.name?.charAt(0).toUpperCase() || 'A'}
                    </div>
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
        </aside>
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

export function AdminLayout({ children, title, breadcrumbs }: AdminLayoutProps) {
    return (
        <>
            <Head title={title ? `${title} | Admin` : 'Admin Panel'} />
            <FlashMessages />
            <div className="flex h-screen bg-background">
                <AdminSidebar />
                <div className="flex flex-1 flex-col overflow-hidden">
                    {breadcrumbs && breadcrumbs.length > 0 && <AdminBreadcrumbs items={breadcrumbs} />}
                    <main className="flex-1 overflow-auto p-6 lg:p-8">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
