import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import {
    Clock,
    Search,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Activity,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface ScheduledTask {
    id: number;
    uuid: string;
    name: string;
    command: string;
    frequency: string;
    enabled: boolean;
    timeout: number | null;
    container: string | null;
    resource_type: string;
    resource_name: string;
    team_name: string;
    last_execution: {
        status: string;
        started_at: string | null;
        finished_at: string | null;
        duration: number | null;
    } | null;
    updated_at: string;
}

interface Props {
    tasks: {
        data: ScheduledTask[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total: number;
        enabled: number;
        disabled: number;
        failedLast24h: number;
    };
    filters: {
        enabled?: string;
        search?: string;
    };
}

function TaskRow({ task }: { task: ScheduledTask }) {
    // Status badges configuration
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'warning' | 'danger'; label: string; icon: React.ReactNode }> = {
        success: { variant: 'success', label: 'Success', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger', label: 'Failed', icon: <XCircle className="h-3 w-3" /> },
        running: { variant: 'warning', label: 'Running', icon: <Activity className="h-3 w-3" /> },
    };

    const lastStatus = task.last_execution?.status || '';
    const statusInfo = statusConfig[lastStatus] || null;

    // Format last run time
    const formatLastRun = () => {
        if (!task.last_execution?.started_at) {
            return <span className="text-foreground-subtle">Never</span>;
        }
        const date = new Date(task.last_execution.started_at);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        let timeAgo = '';
        if (diffMins < 60) {
            timeAgo = `${diffMins}m ago`;
        } else if (diffHours < 24) {
            timeAgo = `${diffHours}h ago`;
        } else {
            timeAgo = `${diffDays}d ago`;
        }

        return (
            <div className="flex items-center gap-2">
                {statusInfo && (
                    <Badge variant={statusInfo.variant} size="sm" icon={statusInfo.icon}>
                        {statusInfo.label}
                    </Badge>
                )}
                <span className="text-xs text-foreground-muted">{timeAgo}</span>
            </div>
        );
    };

    // Truncate command to 50 chars
    const truncatedCommand = task.command.length > 50
        ? task.command.substring(0, 50) + '...'
        : task.command;

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between gap-4">
                <div className="flex-1 space-y-2">
                    {/* Name and enabled status */}
                    <div className="flex items-center gap-2">
                        <Clock className="h-4 w-4 text-foreground-muted" />
                        <span className="font-medium text-foreground">{task.name}</span>
                        <Badge
                            variant={task.enabled ? 'success' : 'default'}
                            size="sm"
                        >
                            {task.enabled ? 'Active' : 'Disabled'}
                        </Badge>
                    </div>

                    {/* Command */}
                    <div className="flex items-center gap-2 text-xs">
                        <span className="text-foreground-subtle">Command:</span>
                        <code className="rounded bg-background-secondary px-2 py-0.5 font-mono text-foreground">
                            {truncatedCommand}
                        </code>
                    </div>

                    {/* Frequency */}
                    <div className="flex items-center gap-2 text-xs">
                        <span className="text-foreground-subtle">Frequency:</span>
                        <code className="rounded bg-background-secondary px-2 py-0.5 font-mono text-foreground-muted">
                            {task.frequency}
                        </code>
                    </div>

                    {/* Resource and Team */}
                    <div className="flex items-center gap-3 text-xs text-foreground-subtle">
                        <span>
                            {task.resource_type}: <span className="text-foreground">{task.resource_name}</span>
                        </span>
                        <span>·</span>
                        <span>
                            Team: <span className="text-foreground">{task.team_name}</span>
                        </span>
                    </div>
                </div>

                {/* Last Run */}
                <div className="flex flex-col items-end justify-start gap-1">
                    <span className="text-xs text-foreground-subtle">Last Run</span>
                    {formatLastRun()}
                </div>
            </div>
        </div>
    );
}

export default function AdminScheduledTasksIndex({ tasks: tasksData, stats, filters }: Props) {
    const tasks = tasksData?.data ?? [];
    const currentPage = tasksData?.current_page ?? 1;
    const lastPage = tasksData?.last_page ?? 1;
    const total = tasksData?.total ?? 0;

    const [searchQuery, setSearchQuery] = React.useState(filters?.search || '');
    const [enabledFilter, setEnabledFilter] = React.useState(filters?.enabled || 'all');

    // Apply filters via Inertia router
    const applyFilters = React.useCallback(() => {
        const params: Record<string, string> = {};
        if (searchQuery) params.search = searchQuery;
        if (enabledFilter !== 'all') params.enabled = enabledFilter;

        router.get('/admin/scheduled-tasks', params, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [searchQuery, enabledFilter]);

    // Debounced search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            applyFilters();
        }, 300);

        return () => clearTimeout(timer);
    }, [searchQuery, applyFilters]);

    // Handle enabled filter change
    const handleEnabledFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setEnabledFilter(e.target.value);
        const params: Record<string, string> = {};
        if (searchQuery) params.search = searchQuery;
        if (e.target.value !== 'all') params.enabled = e.target.value;

        router.get('/admin/scheduled-tasks', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Pagination
    const goToPage = (page: number) => {
        const params: Record<string, string> = { page: page.toString() };
        if (searchQuery) params.search = searchQuery;
        if (enabledFilter !== 'all') params.enabled = enabledFilter;

        router.get('/admin/scheduled-tasks', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Scheduled Tasks"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Scheduled Tasks' },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Scheduled Tasks</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        All cron jobs across all teams
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total</p>
                                    <p className="text-2xl font-bold text-foreground">{stats?.total ?? 0}</p>
                                </div>
                                <Clock className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Enabled</p>
                                    <p className="text-2xl font-bold text-success">{stats?.enabled ?? 0}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Disabled</p>
                                    <p className="text-2xl font-bold text-foreground-muted">{stats?.disabled ?? 0}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed (24h)</p>
                                    <p className="text-2xl font-bold text-danger">{stats?.failedLast24h ?? 0}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search by name or command..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="w-full sm:w-48">
                                <Select
                                    value={enabledFilter}
                                    onChange={handleEnabledFilterChange}
                                >
                                    <option value="all">All Tasks</option>
                                    <option value="enabled">Enabled Only</option>
                                    <option value="disabled">Disabled Only</option>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tasks Table */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {tasks.length} of {total} scheduled tasks
                            </p>
                        </div>

                        {tasks.length === 0 ? (
                            <div className="py-12 text-center">
                                <Clock className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No scheduled tasks found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {tasks.map((task) => (
                                    <TaskRow key={task.id} task={task} />
                                ))}
                            </div>
                        )}

                        {/* Pagination */}
                        {lastPage > 1 && (
                            <div className="mt-6 flex items-center justify-between border-t border-border/50 pt-4">
                                <p className="text-xs text-foreground-subtle">
                                    Page {currentPage} of {lastPage}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => goToPage(currentPage - 1)}
                                        disabled={currentPage === 1}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => goToPage(currentPage + 1)}
                                        disabled={currentPage === lastPage}
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
