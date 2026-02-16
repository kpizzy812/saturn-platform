import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import {
    Search,
    FileText,
    User,
    Download,
    Filter,
    X,
    ChevronDown,
    ChevronUp,
    Clock,
    Globe,
    Activity,
} from 'lucide-react';

interface AuditLogEntry {
    id: number;
    action: string;
    formatted_action: string;
    resource_type?: string;
    resource_id?: number;
    resource_name?: string;
    description?: string;
    metadata?: Record<string, unknown>;
    user_id?: number;
    user_name?: string;
    user_email?: string;
    team_id?: number;
    team_name?: string;
    ip_address?: string;
    user_agent?: string;
    created_at: string;
}

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface Props {
    logs: {
        data: AuditLogEntry[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    actions: string[];
    resourceTypes: string[];
    users: UserOption[];
    filters: {
        search?: string;
        action?: string;
        resource_type?: string;
        user_id?: string;
        date_from?: string;
        date_to?: string;
    };
}

function LogRow({ log }: { log: AuditLogEntry }) {
    const [isExpanded, setIsExpanded] = React.useState(false);

    const getActionColor = (action: string): 'success' | 'warning' | 'danger' | 'primary' | 'default' => {
        if (['create', 'deploy', 'start', 'login'].includes(action)) return 'success';
        if (['delete', 'stop', 'logout', 'user_suspended'].includes(action)) return 'danger';
        if (['update', 'restart', 'rollback'].includes(action)) return 'warning';
        if (['user_impersonated', 'user_activated'].includes(action)) return 'primary';
        return 'default';
    };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div
                className="flex cursor-pointer items-start justify-between"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Badge variant={getActionColor(log.action)} size="sm">
                            {log.formatted_action}
                        </Badge>
                        {log.resource_type && (
                            <Badge variant="secondary" size="sm">
                                {log.resource_type}
                            </Badge>
                        )}
                        {log.resource_name && (
                            <span className="text-sm font-medium text-foreground">
                                {log.resource_name}
                            </span>
                        )}
                    </div>
                    {log.description && (
                        <p className="mt-1 text-sm text-foreground-muted">{log.description}</p>
                    )}
                    <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-foreground-subtle">
                        {log.user_name && (
                            <span className="flex items-center gap-1">
                                <User className="h-3 w-3" />
                                {log.user_name}
                            </span>
                        )}
                        {log.team_name && (
                            <span className="flex items-center gap-1">
                                <Activity className="h-3 w-3" />
                                {log.team_name}
                            </span>
                        )}
                        {log.ip_address && (
                            <span className="flex items-center gap-1">
                                <Globe className="h-3 w-3" />
                                {log.ip_address}
                            </span>
                        )}
                        <span className="flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {new Date(log.created_at).toLocaleString()}
                        </span>
                    </div>
                </div>
                <div className="ml-4 flex items-center gap-2">
                    {(log.metadata && Object.keys(log.metadata).length > 0) && (
                        <Badge variant="outline" size="sm">
                            +metadata
                        </Badge>
                    )}
                    {isExpanded ? (
                        <ChevronUp className="h-4 w-4 text-foreground-muted" />
                    ) : (
                        <ChevronDown className="h-4 w-4 text-foreground-muted" />
                    )}
                </div>
            </div>

            {isExpanded && (
                <div className="mt-4 rounded-lg bg-background-subtle p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-medium text-foreground-subtle">Action</p>
                            <p className="text-sm text-foreground">{log.action}</p>
                        </div>
                        {log.resource_type && (
                            <div>
                                <p className="text-xs font-medium text-foreground-subtle">Resource Type</p>
                                <p className="text-sm text-foreground">{log.resource_type}</p>
                            </div>
                        )}
                        {log.resource_id && (
                            <div>
                                <p className="text-xs font-medium text-foreground-subtle">Resource ID</p>
                                <p className="text-sm text-foreground">{log.resource_id}</p>
                            </div>
                        )}
                        {log.user_email && (
                            <div>
                                <p className="text-xs font-medium text-foreground-subtle">User Email</p>
                                <p className="text-sm text-foreground">{log.user_email}</p>
                            </div>
                        )}
                        {log.user_agent && (
                            <div className="sm:col-span-2">
                                <p className="text-xs font-medium text-foreground-subtle">User Agent</p>
                                <p className="text-sm text-foreground break-all">{log.user_agent}</p>
                            </div>
                        )}
                    </div>
                    {log.metadata && Object.keys(log.metadata).length > 0 && (
                        <div className="mt-4">
                            <p className="mb-2 text-xs font-medium text-foreground-subtle">Metadata</p>
                            <pre className="overflow-x-auto rounded bg-background p-3 text-xs text-foreground">
                                {JSON.stringify(log.metadata, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function AdminAuditLogsIndex({
    logs,
    actions,
    resourceTypes,
    users,
    filters,
}: Props) {
    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [showFilters, setShowFilters] = React.useState(
        !!(filters.action || filters.resource_type || filters.user_id || filters.date_from || filters.date_to)
    );
    const [selectedAction, setSelectedAction] = React.useState(filters.action ?? '');
    const [selectedResourceType, setSelectedResourceType] = React.useState(filters.resource_type ?? '');
    const [selectedUserId, setSelectedUserId] = React.useState(filters.user_id ?? '');
    const [dateFrom, setDateFrom] = React.useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = React.useState(filters.date_to ?? '');

    // Debounced search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            search: filters.search,
            action: filters.action,
            resource_type: filters.resource_type,
            user_id: filters.user_id,
            date_from: filters.date_from,
            date_to: filters.date_to,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        router.get(`/admin/audit-logs?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleFilterChange = (key: string, value: string) => {
        switch (key) {
            case 'action':
                setSelectedAction(value);
                break;
            case 'resource_type':
                setSelectedResourceType(value);
                break;
            case 'user_id':
                setSelectedUserId(value);
                break;
            case 'date_from':
                setDateFrom(value);
                break;
            case 'date_to':
                setDateTo(value);
                break;
        }
        applyFilters({ [key]: value || undefined });
    };

    const clearFilters = () => {
        setSearchQuery('');
        setSelectedAction('');
        setSelectedResourceType('');
        setSelectedUserId('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/audit-logs');
    };

    const handleExport = (format: 'csv' | 'json') => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.action) params.set('action', filters.action);
        if (filters.resource_type) params.set('resource_type', filters.resource_type);
        if (filters.user_id) params.set('user_id', filters.user_id);
        if (filters.date_from) params.set('date_from', filters.date_from);
        if (filters.date_to) params.set('date_to', filters.date_to);
        params.set('format', format);

        window.location.href = `/admin/audit-logs/export?${params.toString()}`;
    };

    const hasActiveFilters = filters.search || filters.action || filters.resource_type || filters.user_id || filters.date_from || filters.date_to;

    return (
        <AdminLayout
            title="Audit Logs"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Audit Logs' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Audit Logs</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Track all user activities and system changes
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={() => handleExport('csv')}>
                            <Download className="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                        <Button variant="secondary" onClick={() => handleExport('json')}>
                            <Download className="mr-2 h-4 w-4" />
                            Export JSON
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Logs</p>
                                    <p className="text-2xl font-bold text-primary">{logs.total}</p>
                                </div>
                                <FileText className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Actions Types</p>
                                    <p className="text-2xl font-bold text-success">{actions.length}</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Resource Types</p>
                                    <p className="text-2xl font-bold text-warning">{resourceTypes.length}</p>
                                </div>
                                <FileText className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Active Users</p>
                                    <p className="text-2xl font-bold text-foreground">{users.length}</p>
                                </div>
                                <User className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search logs by description, resource, action..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <Button
                                    variant={showFilters ? 'primary' : 'secondary'}
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filters
                                    {hasActiveFilters && (
                                        <Badge variant="primary" size="sm" className="ml-2">
                                            Active
                                        </Badge>
                                    )}
                                </Button>
                            </div>

                            {showFilters && (
                                <div className="grid gap-4 border-t border-border/50 pt-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <Select
                                        label="Action"
                                        value={selectedAction}
                                        onChange={(e) => handleFilterChange('action', e.target.value)}
                                        options={[
                                            { value: '', label: 'All Actions' },
                                            ...actions.map((action) => ({ value: action, label: action })),
                                        ]}
                                    />

                                    <Select
                                        label="Resource Type"
                                        value={selectedResourceType}
                                        onChange={(e) => handleFilterChange('resource_type', e.target.value)}
                                        options={[
                                            { value: '', label: 'All Types' },
                                            ...resourceTypes.map((type) => ({ value: type, label: type })),
                                        ]}
                                    />

                                    <Select
                                        label="User"
                                        value={selectedUserId}
                                        onChange={(e) => handleFilterChange('user_id', e.target.value)}
                                        options={[
                                            { value: '', label: 'All Users' },
                                            ...users.map((user) => ({ value: user.id.toString(), label: user.name })),
                                        ]}
                                    />

                                    <Input
                                        type="date"
                                        label="Date From"
                                        value={dateFrom}
                                        onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                    />

                                    <Input
                                        type="date"
                                        label="Date To"
                                        value={dateTo}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                    />
                                </div>
                            )}

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2 border-t border-border/50 pt-4">
                                    <span className="text-sm text-foreground-muted">Active filters:</span>
                                    {filters.search && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Search: {filters.search}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => { setSearchQuery(''); applyFilters({ search: undefined }); }} />
                                        </Badge>
                                    )}
                                    {filters.action && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Action: {filters.action}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleFilterChange('action', '')} />
                                        </Badge>
                                    )}
                                    {filters.resource_type && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Type: {filters.resource_type}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleFilterChange('resource_type', '')} />
                                        </Badge>
                                    )}
                                    {filters.user_id && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            User: {users.find(u => u.id.toString() === filters.user_id)?.name}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleFilterChange('user_id', '')} />
                                        </Badge>
                                    )}
                                    {(filters.date_from || filters.date_to) && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Date: {filters.date_from || '...'} - {filters.date_to || '...'}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => { handleFilterChange('date_from', ''); handleFilterChange('date_to', ''); }} />
                                        </Badge>
                                    )}
                                    <Button variant="ghost" size="sm" onClick={clearFilters}>
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Logs List */}
                <Card variant="glass">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Activity Log</CardTitle>
                                <CardDescription>
                                    Showing {logs.data.length} of {logs.total} entries
                                </CardDescription>
                            </div>
                            {logs.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={logs.current_page === 1}
                                        onClick={() => {
                                            const params = new URLSearchParams(window.location.search);
                                            params.set('page', (logs.current_page - 1).toString());
                                            router.get(`/admin/audit-logs?${params.toString()}`);
                                        }}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-sm text-foreground-muted">
                                        Page {logs.current_page} of {logs.last_page}
                                    </span>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={logs.current_page === logs.last_page}
                                        onClick={() => {
                                            const params = new URLSearchParams(window.location.search);
                                            params.set('page', (logs.current_page + 1).toString());
                                            router.get(`/admin/audit-logs?${params.toString()}`);
                                        }}
                                    >
                                        Next
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {logs.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <FileText className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No audit logs found</p>
                                <p className="text-xs text-foreground-subtle">
                                    {hasActiveFilters
                                        ? 'Try adjusting your filters'
                                        : 'Activity will appear here as users perform actions'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {logs.data.map((log) => (
                                    <LogRow key={log.id} log={log} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
