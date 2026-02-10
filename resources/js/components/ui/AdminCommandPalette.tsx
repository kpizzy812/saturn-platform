import * as React from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    Search,
    LayoutDashboard,
    Users,
    Server,
    Settings,
    FileText,
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
    Rocket,
    BarChart3,
    Eye,
    LogOut,
    ArrowLeft,
} from 'lucide-react';

interface CommandItem {
    id: string;
    name: string;
    description?: string;
    icon: React.ReactNode;
    href?: string;
    action?: () => void;
    group: 'overview' | 'people' | 'resources' | 'deployments' | 'monitoring' | 'system' | 'actions';
}

const commands: CommandItem[] = [
    // Overview
    { id: 'admin-dashboard', name: 'Dashboard', icon: <LayoutDashboard className="h-4 w-4" />, href: '/admin', group: 'overview' },
    { id: 'admin-health', name: 'Health', icon: <Activity className="h-4 w-4" />, href: '/admin/health', group: 'overview' },
    { id: 'admin-metrics', name: 'Metrics', icon: <BarChart3 className="h-4 w-4" />, href: '/admin/metrics', group: 'overview' },
    { id: 'admin-notifications', name: 'Notifications', icon: <Bell className="h-4 w-4" />, href: '/admin/notifications', group: 'overview' },

    // People
    { id: 'admin-users', name: 'Users', description: 'Manage platform users', icon: <Users className="h-4 w-4" />, href: '/admin/users', group: 'people' },
    { id: 'admin-teams', name: 'Teams', description: 'Manage teams', icon: <Users className="h-4 w-4" />, href: '/admin/teams', group: 'people' },
    { id: 'admin-invitations', name: 'Invitations', description: 'Pending invitations', icon: <MailPlus className="h-4 w-4" />, href: '/admin/invitations', group: 'people' },
    { id: 'admin-login-history', name: 'Login History', description: 'User login activity', icon: <History className="h-4 w-4" />, href: '/admin/login-history', group: 'people' },

    // Resources
    { id: 'admin-servers', name: 'Servers', description: 'Manage all servers', icon: <Server className="h-4 w-4" />, href: '/admin/servers', group: 'resources' },
    { id: 'admin-projects', name: 'Projects', description: 'Manage all projects', icon: <FolderKanban className="h-4 w-4" />, href: '/admin/projects', group: 'resources' },
    { id: 'admin-ssh-keys', name: 'SSH Keys', icon: <KeyRound className="h-4 w-4" />, href: '/admin/ssh-keys', group: 'resources' },
    { id: 'admin-templates', name: 'Templates', icon: <LayoutTemplate className="h-4 w-4" />, href: '/admin/templates', group: 'resources' },

    // Deployments
    { id: 'admin-deployments', name: 'Deployments', description: 'All deployments', icon: <Rocket className="h-4 w-4" />, href: '/admin/deployments', group: 'deployments' },
    { id: 'admin-approvals', name: 'Approvals', description: 'Pending deployment approvals', icon: <CheckCircle className="h-4 w-4" />, href: '/admin/deployment-approvals', group: 'deployments' },
    { id: 'admin-queues', name: 'Queues', description: 'Job queues', icon: <ListOrdered className="h-4 w-4" />, href: '/admin/queues', group: 'deployments' },
    { id: 'admin-scheduled-tasks', name: 'Scheduled Tasks', icon: <Clock className="h-4 w-4" />, href: '/admin/scheduled-tasks', group: 'deployments' },

    // Monitoring
    { id: 'admin-audit-logs', name: 'Audit Logs', description: 'Activity audit trail', icon: <ClipboardList className="h-4 w-4" />, href: '/admin/audit-logs', group: 'monitoring' },
    { id: 'admin-system-logs', name: 'System Logs', icon: <FileText className="h-4 w-4" />, href: '/admin/logs', group: 'monitoring' },
    { id: 'admin-webhooks', name: 'Webhooks', description: 'Webhook deliveries', icon: <Webhook className="h-4 w-4" />, href: '/admin/webhook-deliveries', group: 'monitoring' },
    { id: 'admin-ai-usage', name: 'AI Usage', icon: <Brain className="h-4 w-4" />, href: '/admin/ai-usage', group: 'monitoring' },

    // System
    { id: 'admin-settings', name: 'Settings', description: 'Platform settings', icon: <Settings className="h-4 w-4" />, href: '/admin/settings', group: 'system' },
    { id: 'admin-oauth', name: 'OAuth / SSO', icon: <KeyRound className="h-4 w-4" />, href: '/admin/settings/oauth', group: 'system' },
    { id: 'admin-backups', name: 'Backups', icon: <HardDrive className="h-4 w-4" />, href: '/admin/backups', group: 'system' },
    { id: 'admin-docker-cleanups', name: 'Docker Cleanups', icon: <Container className="h-4 w-4" />, href: '/admin/docker-cleanups', group: 'system' },
    { id: 'admin-ssl', name: 'SSL Certificates', icon: <Lock className="h-4 w-4" />, href: '/admin/ssl-certificates', group: 'system' },
    { id: 'admin-transfers', name: 'Transfers', icon: <ArrowRightLeft className="h-4 w-4" />, href: '/admin/transfers', group: 'system' },

    // Actions
    { id: 'back-to-app', name: 'Back to App', description: 'Return to main dashboard', icon: <ArrowLeft className="h-4 w-4" />, href: '/dashboard', group: 'actions' },
    { id: 'logout', name: 'Logout', description: 'Sign out of your account', icon: <LogOut className="h-4 w-4" />, action: () => router.post('/logout'), group: 'actions' },
];

const groupLabels: Record<string, string> = {
    overview: 'Overview',
    people: 'People',
    resources: 'Resources',
    deployments: 'Deployments',
    monitoring: 'Monitoring',
    system: 'System',
    actions: 'Actions',
};

const groupOrder: Array<CommandItem['group']> = ['overview', 'people', 'resources', 'deployments', 'monitoring', 'system', 'actions'];

interface AdminCommandPaletteProps {
    open: boolean;
    onClose: () => void;
}

export function AdminCommandPalette({ open, onClose }: AdminCommandPaletteProps) {
    const [query, setQuery] = React.useState('');
    const [selectedIndex, setSelectedIndex] = React.useState(0);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const listRef = React.useRef<HTMLDivElement>(null);

    const filteredCommands = query === ''
        ? commands
        : commands.filter((command) =>
            command.name.toLowerCase().includes(query.toLowerCase()) ||
            command.description?.toLowerCase().includes(query.toLowerCase())
        );

    // Build grouped commands maintaining order
    const groupedCommands = groupOrder.reduce((acc, group) => {
        const items = filteredCommands.filter((c) => c.group === group);
        if (items.length > 0) {
            acc.push({ group, items });
        }
        return acc;
    }, [] as Array<{ group: string; items: CommandItem[] }>);

    // Flat list for keyboard navigation
    const flatItems = groupedCommands.flatMap((g) => g.items);

    const executeCommand = React.useCallback((command: CommandItem) => {
        if (command.action) {
            command.action();
        }
        if (command.href) {
            router.visit(command.href);
        }
        onClose();
        setQuery('');
        setSelectedIndex(0);
    }, [onClose]);

    // Reset selected index when query changes
    React.useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    // Focus input when opened, reset state
    React.useEffect(() => {
        if (open) {
            setQuery('');
            setSelectedIndex(0);
            requestAnimationFrame(() => {
                inputRef.current?.focus();
            });
        }
    }, [open]);

    // Scroll selected item into view
    React.useEffect(() => {
        if (listRef.current) {
            const el = listRef.current.querySelector(`[data-index="${selectedIndex}"]`);
            if (el) {
                el.scrollIntoView({ block: 'nearest' });
            }
        }
    }, [selectedIndex]);

    // Handle keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.min(prev + 1, flatItems.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.max(prev - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const selected = flatItems[selectedIndex];
            if (selected) {
                executeCommand(selected);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
        }
    };

    if (!open) return null;

    let globalIndex = 0;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 z-50 bg-black/50"
                onClick={onClose}
            />

            {/* Palette */}
            <div className="fixed inset-0 z-50 overflow-y-auto pt-[20vh]" onClick={onClose}>
                <div
                    className="mx-auto max-w-xl overflow-hidden rounded-xl border border-primary/30 bg-background-secondary shadow-2xl shadow-primary/10"
                    onClick={(e) => e.stopPropagation()}
                >
                    {/* Search Input */}
                    <div className="flex items-center gap-3 border-b border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-1">
                        <Search className="h-5 w-5 text-primary/60" />
                        <input
                            ref={inputRef}
                            type="text"
                            className="h-12 w-full bg-transparent text-foreground placeholder-foreground-muted focus:outline-none"
                            placeholder="Search admin commands..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                        />
                        <kbd className="rounded-md bg-background-tertiary px-2.5 py-1 text-xs font-medium text-foreground-muted">
                            ESC
                        </kbd>
                    </div>

                    {/* Results */}
                    <div ref={listRef} className="max-h-80 overflow-y-auto p-2">
                        {flatItems.length === 0 && query !== '' ? (
                            <div className="px-4 py-10 text-center text-foreground-muted">
                                No commands found for &ldquo;{query}&rdquo;
                            </div>
                        ) : (
                            groupedCommands.map(({ group, items }) => {
                                const startIndex = globalIndex;
                                const rendered = (
                                    <div key={group} className="mb-2">
                                        <div className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-primary/60">
                                            {groupLabels[group]}
                                        </div>
                                        {items.map((command, idx) => {
                                            const itemIndex = startIndex + idx;
                                            return (
                                                <button
                                                    key={command.id}
                                                    data-index={itemIndex}
                                                    onClick={() => executeCommand(command)}
                                                    onMouseEnter={() => setSelectedIndex(itemIndex)}
                                                    className={cn(
                                                        'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors duration-100',
                                                        itemIndex === selectedIndex
                                                            ? 'bg-gradient-to-r from-primary/15 to-purple-500/10 border border-primary/20'
                                                            : 'border border-transparent'
                                                    )}
                                                >
                                                    <span className={cn(
                                                        'transition-colors',
                                                        itemIndex === selectedIndex ? 'text-primary' : 'text-foreground-muted'
                                                    )}>
                                                        {command.icon}
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="text-sm text-foreground">{command.name}</div>
                                                        {command.description && (
                                                            <div className="text-xs text-foreground-muted">
                                                                {command.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                                globalIndex += items.length;
                                return rendered;
                            })
                        )}
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-between border-t border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-5 py-3 text-xs text-foreground-muted">
                        <div className="flex items-center gap-5">
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&#8593;&#8595;</kbd>
                                <span>navigate</span>
                            </span>
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&#8629;</kbd>
                                <span>select</span>
                            </span>
                        </div>
                        <span className="text-primary/60">Saturn Admin</span>
                    </div>
                </div>
            </div>
        </>
    );
}
