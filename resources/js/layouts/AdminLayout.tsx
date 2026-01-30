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

    const isActive = (href: string) => {
        if (href === '/admin') {
            return url === href;
        }
        return url.startsWith(href);
    };

    return (
        <aside className="flex h-screen w-64 flex-col border-r border-red-500/20 bg-background">
            {/* Admin Logo */}
            <div className="flex h-14 items-center gap-2 border-b border-red-500/20 bg-gradient-to-r from-red-500/10 to-transparent px-4">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-600">
                    <Shield className="h-4 w-4 text-white" />
                </div>
                <div>
                    <span className="text-sm font-bold text-foreground">Admin Panel</span>
                    <div className="text-[10px] text-red-400">Super Admin</div>
                </div>
            </div>

            {/* Admin Navigation */}
            <nav className="flex-1 space-y-1 px-2 py-4">
                {adminNavItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isActive(item.href)
                                ? 'bg-red-500/10 text-red-400 border border-red-500/20'
                                : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                        )}
                    >
                        {item.icon}
                        {item.label}
                    </Link>
                ))}
            </nav>

            {/* Back to Main App */}
            <div className="border-t border-border px-2 py-4">
                <Link
                    href="/dashboard"
                    className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                >
                    <LayoutDashboard className="h-4 w-4" />
                    Back to App
                </Link>
            </div>

            {/* Admin User Section */}
            <div className="border-t border-red-500/20 bg-gradient-to-r from-red-500/5 to-transparent p-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-red-600 text-sm font-medium text-white">
                        {user?.name?.charAt(0).toUpperCase() || 'A'}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-foreground truncate">
                            {user?.name || 'Admin'}
                        </p>
                        <p className="text-xs text-red-400 truncate">
                            Super Administrator
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

function AdminBreadcrumbs({ items }: { items: AdminBreadcrumb[] }) {
    if (!items || items.length === 0) {
        return null;
    }

    return (
        <nav className="border-b border-border bg-background px-6 py-3">
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
                                <span className="text-foreground-muted">/</span>
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
                    <main className="flex-1 overflow-auto">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
