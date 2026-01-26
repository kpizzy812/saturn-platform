import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Input, Select, Badge, Spinner } from '@/components/ui';
import { FileText, Download, Filter, User, GitBranch, Settings, Trash2, Key, Shield, RefreshCw, AlertCircle } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';

interface AuditLogEntry {
    id: string;
    action: string;
    description: string;
    user: {
        name: string;
        email: string;
        avatar: string | null;
    };
    resource: {
        type: string;
        name: string;
        id: string;
    } | null;
    timestamp: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const actionTypes = ['all', 'created', 'updated', 'deleted', 'deployed', 'started', 'stopped', 'restarted'];
const resourceTypes = ['all', 'application', 'service', 'database', 'server', 'team', 'project', 'environment'];

export default function AuditLogSettings() {
    const { toast } = useToast();
    const [logs, setLogs] = React.useState<AuditLogEntry[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [isExporting, setIsExporting] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);
    const [meta, setMeta] = React.useState<PaginationMeta | null>(null);
    const [filters, setFilters] = React.useState({
        user: '',
        action: 'all',
        resourceType: 'all',
        dateFrom: '',
        dateTo: '',
    });
    const [currentPage, setCurrentPage] = React.useState(1);
    const logsPerPage = 20;

    // Fetch audit logs from API
    const fetchLogs = React.useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            params.append('per_page', logsPerPage.toString());
            params.append('page', currentPage.toString());

            if (filters.action !== 'all') {
                params.append('action', filters.action);
            }
            if (filters.user) {
                params.append('member', filters.user);
            }
            if (filters.dateFrom) {
                params.append('date_from', filters.dateFrom);
            }
            if (filters.dateTo) {
                params.append('date_to', filters.dateTo);
            }

            const response = await fetch(`/api/v1/teams/current/activities?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error('Failed to fetch audit logs');
            }

            const data = await response.json();
            setLogs(data.data || []);
            setMeta(data.meta || null);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to load audit logs';
            setError(message);
            toast({
                title: 'Error',
                description: message,
                variant: 'destructive',
            });
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, filters, toast]);

    // Initial fetch and refetch on filter/page change
    React.useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    const getActionIcon = (action: string) => {
        if (action.includes('deploy') || action.includes('started')) {
            return <GitBranch className="h-4 w-4" />;
        }
        if (action.includes('created') || action.includes('added')) {
            return <FileText className="h-4 w-4" />;
        }
        if (action.includes('updated') || action.includes('settings')) {
            return <Settings className="h-4 w-4" />;
        }
        if (action.includes('deleted') || action.includes('removed')) {
            return <Trash2 className="h-4 w-4" />;
        }
        if (action.includes('member') || action.includes('invited')) {
            return <User className="h-4 w-4" />;
        }
        if (action.includes('token') || action.includes('revoked')) {
            return <Key className="h-4 w-4" />;
        }
        return <FileText className="h-4 w-4" />;
    };

    const getActionColor = (action: string) => {
        if (action.includes('deploy') || action.includes('started')) {
            return 'text-primary';
        }
        if (action.includes('created') || action.includes('added')) {
            return 'text-success';
        }
        if (action.includes('updated') || action.includes('settings')) {
            return 'text-warning';
        }
        if (action.includes('deleted') || action.includes('removed') || action.includes('failed')) {
            return 'text-danger';
        }
        if (action.includes('stopped')) {
            return 'text-danger';
        }
        return 'text-foreground-muted';
    };

    const getResourceTypeBadge = (type: string | undefined): 'default' | 'success' | 'warning' | 'danger' => {
        switch (type) {
            case 'application':
            case 'service':
                return 'success';
            case 'database':
                return 'warning';
            case 'server':
                return 'danger';
            case 'project':
            case 'environment':
                return 'default';
            case 'team':
                return 'warning';
            default:
                return 'default';
        }
    };

    const totalPages = meta?.last_page || 1;

    const handleExport = async (format: 'csv' | 'json' = 'csv') => {
        setIsExporting(true);

        try {
            const params = new URLSearchParams();
            params.append('format', format);

            if (filters.action !== 'all') {
                params.append('action', filters.action);
            }
            if (filters.user) {
                params.append('member', filters.user);
            }
            if (filters.dateFrom) {
                params.append('date_from', filters.dateFrom);
            }
            if (filters.dateTo) {
                params.append('date_to', filters.dateTo);
            }

            const response = await fetch(`/api/v1/teams/current/activities/export?${params.toString()}`, {
                headers: {
                    'Accept': format === 'json' ? 'application/json' : 'text/csv',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error('Failed to export audit logs');
            }

            // Get filename from Content-Disposition header or generate default
            const contentDisposition = response.headers.get('Content-Disposition');
            let filename = `audit-log-${new Date().toISOString().split('T')[0]}.${format}`;
            if (contentDisposition) {
                const match = contentDisposition.match(/filename="(.+)"/);
                if (match) {
                    filename = match[1];
                }
            }

            // Download file
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            toast({
                title: 'Export Successful',
                description: `Audit logs exported to ${filename}`,
            });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to export audit logs';
            toast({
                title: 'Export Failed',
                description: message,
                variant: 'destructive',
            });
        } finally {
            setIsExporting(false);
        }
    };

    const handleResetFilters = () => {
        setFilters({
            user: '',
            action: 'all',
            resourceType: 'all',
            dateFrom: '',
            dateTo: '',
        });
        setCurrentPage(1);
    };

    const formatTimestamp = (timestamp: string) => {
        const date = new Date(timestamp);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    return (
        <SettingsLayout activeSection="audit-log">
            <div className="space-y-6">
                {/* Filters */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Audit Log</CardTitle>
                                <CardDescription>
                                    View and filter all activity in your workspace
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={fetchLogs}
                                    disabled={isLoading}
                                >
                                    <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                                </Button>
                                <Select
                                    value=""
                                    onChange={(e) => {
                                        if (e.target.value) {
                                            handleExport(e.target.value as 'csv' | 'json');
                                        }
                                    }}
                                    disabled={isExporting}
                                    className="w-[130px]"
                                >
                                    <option value="" disabled>
                                        {isExporting ? 'Exporting...' : 'Export'}
                                    </option>
                                    <option value="csv">Export CSV</option>
                                    <option value="json">Export JSON</option>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                <Input
                                    label="User Email"
                                    placeholder="Filter by user..."
                                    value={filters.user}
                                    onChange={(e) => setFilters({ ...filters, user: e.target.value })}
                                />
                                <Select
                                    label="Action"
                                    value={filters.action}
                                    onChange={(e) => setFilters({ ...filters, action: e.target.value })}
                                >
                                    {actionTypes.map((action) => (
                                        <option key={action} value={action}>
                                            {action.charAt(0).toUpperCase() + action.slice(1)}
                                        </option>
                                    ))}
                                </Select>
                                <Select
                                    label="Resource Type"
                                    value={filters.resourceType}
                                    onChange={(e) => setFilters({ ...filters, resourceType: e.target.value })}
                                >
                                    {resourceTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {type === 'all' ? 'All' : type.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                        </option>
                                    ))}
                                </Select>
                                <div className="flex items-end">
                                    <Button variant="secondary" onClick={handleResetFilters} className="w-full">
                                        <Filter className="mr-2 h-4 w-4" />
                                        Reset
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Audit Log Entries */}
                <Card>
                    <CardContent className="p-0">
                        {isLoading ? (
                            <div className="flex items-center justify-center p-8">
                                <Spinner className="h-8 w-8" />
                            </div>
                        ) : error ? (
                            <div className="p-8 text-center">
                                <AlertCircle className="mx-auto h-12 w-12 text-danger" />
                                <p className="mt-4 text-sm text-danger">{error}</p>
                                <Button variant="secondary" onClick={fetchLogs} className="mt-4">
                                    Try Again
                                </Button>
                            </div>
                        ) : logs.length === 0 ? (
                            <div className="p-8 text-center">
                                <FileText className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No audit log entries found</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {logs.map((log) => (
                                    <div key={log.id} className="p-4 transition-colors hover:bg-background-secondary">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex flex-1 items-start gap-3">
                                                <div className={`mt-0.5 ${getActionColor(log.action)}`}>
                                                    {getActionIcon(log.action)}
                                                </div>
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium text-foreground">{log.user.name}</span>
                                                        <span className="text-foreground-muted">{log.action.replace(/_/g, ' ')}</span>
                                                        {log.resource && (
                                                            <Badge variant={getResourceTypeBadge(log.resource.type)}>
                                                                {log.resource.type}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-foreground-muted">
                                                        {log.description || log.resource?.name || 'No description'}
                                                    </p>
                                                    <p className="text-xs text-foreground-subtle">
                                                        {log.user.email}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex flex-col items-end gap-1 text-right">
                                                <p className="text-xs text-foreground-muted">
                                                    {formatTimestamp(log.timestamp)}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {meta && totalPages > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-foreground-muted">
                            Page {meta.current_page} of {meta.last_page} ({meta.total} total entries)
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                disabled={currentPage === 1 || isLoading}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages || isLoading}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
