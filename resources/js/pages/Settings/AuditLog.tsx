import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Input, Select, Badge } from '@/components/ui';
import { FileText, Download, Filter, User, GitBranch, Settings, Trash2, Key, Shield } from 'lucide-react';

interface AuditLogEntry {
    id: number;
    user: string;
    action: string;
    resource: string;
    resourceType: 'project' | 'deployment' | 'team' | 'settings' | 'api_token';
    ip: string;
    timestamp: string;
    metadata?: Record<string, any>;
}

const mockAuditLogs: AuditLogEntry[] = [
    {
        id: 1,
        user: 'john@example.com',
        action: 'deployed',
        resource: 'Production API',
        resourceType: 'deployment',
        ip: '192.168.1.100',
        timestamp: '2024-03-28T14:30:00Z',
        metadata: { branch: 'main', commit: 'abc123' },
    },
    {
        id: 2,
        user: 'jane@example.com',
        action: 'created',
        resource: 'Marketing Site',
        resourceType: 'project',
        ip: '192.168.1.101',
        timestamp: '2024-03-28T12:15:00Z',
    },
    {
        id: 3,
        user: 'john@example.com',
        action: 'updated',
        resource: 'Workspace Settings',
        resourceType: 'settings',
        ip: '192.168.1.100',
        timestamp: '2024-03-28T10:45:00Z',
    },
    {
        id: 4,
        user: 'bob@example.com',
        action: 'deleted',
        resource: 'Staging Environment',
        resourceType: 'project',
        ip: '192.168.1.102',
        timestamp: '2024-03-27T16:20:00Z',
    },
    {
        id: 5,
        user: 'jane@example.com',
        action: 'invited',
        resource: 'alice@example.com',
        resourceType: 'team',
        ip: '192.168.1.101',
        timestamp: '2024-03-27T14:10:00Z',
    },
    {
        id: 6,
        user: 'john@example.com',
        action: 'revoked',
        resource: 'Production Deploy Token',
        resourceType: 'api_token',
        ip: '192.168.1.100',
        timestamp: '2024-03-27T11:30:00Z',
    },
    {
        id: 7,
        user: 'bob@example.com',
        action: 'deployed',
        resource: 'Documentation',
        resourceType: 'deployment',
        ip: '192.168.1.102',
        timestamp: '2024-03-26T09:15:00Z',
        metadata: { branch: 'develop', commit: 'def456' },
    },
    {
        id: 8,
        user: 'john@example.com',
        action: 'updated',
        resource: 'Team Permissions',
        resourceType: 'settings',
        ip: '192.168.1.100',
        timestamp: '2024-03-25T15:45:00Z',
    },
];

const actionTypes = ['all', 'deployed', 'created', 'updated', 'deleted', 'invited', 'revoked'];
const resourceTypes = ['all', 'project', 'deployment', 'team', 'settings', 'api_token'];

export default function AuditLogSettings() {
    const [logs, setLogs] = React.useState<AuditLogEntry[]>(mockAuditLogs);
    const [filters, setFilters] = React.useState({
        user: '',
        action: 'all',
        resourceType: 'all',
        dateFrom: '',
        dateTo: '',
    });
    const [currentPage, setCurrentPage] = React.useState(1);
    const logsPerPage = 10;

    const getActionIcon = (action: string) => {
        switch (action) {
            case 'deployed':
                return <GitBranch className="h-4 w-4" />;
            case 'created':
                return <FileText className="h-4 w-4" />;
            case 'updated':
                return <Settings className="h-4 w-4" />;
            case 'deleted':
                return <Trash2 className="h-4 w-4" />;
            case 'invited':
                return <User className="h-4 w-4" />;
            case 'revoked':
                return <Key className="h-4 w-4" />;
            default:
                return <FileText className="h-4 w-4" />;
        }
    };

    const getActionColor = (action: string) => {
        switch (action) {
            case 'deployed':
                return 'text-primary';
            case 'created':
                return 'text-success';
            case 'updated':
                return 'text-warning';
            case 'deleted':
                return 'text-danger';
            case 'invited':
                return 'text-info';
            case 'revoked':
                return 'text-danger';
            default:
                return 'text-foreground-muted';
        }
    };

    const getResourceTypeBadge = (type: string): 'default' | 'success' | 'warning' | 'danger' => {
        switch (type) {
            case 'deployment':
                return 'success';
            case 'project':
                return 'default';
            case 'team':
                return 'warning';
            case 'api_token':
                return 'danger';
            default:
                return 'default';
        }
    };

    const filteredLogs = logs.filter((log) => {
        if (filters.user && !log.user.toLowerCase().includes(filters.user.toLowerCase())) {
            return false;
        }
        if (filters.action !== 'all' && log.action !== filters.action) {
            return false;
        }
        if (filters.resourceType !== 'all' && log.resourceType !== filters.resourceType) {
            return false;
        }
        if (filters.dateFrom && new Date(log.timestamp) < new Date(filters.dateFrom)) {
            return false;
        }
        if (filters.dateTo && new Date(log.timestamp) > new Date(filters.dateTo)) {
            return false;
        }
        return true;
    });

    const totalPages = Math.ceil(filteredLogs.length / logsPerPage);
    const paginatedLogs = filteredLogs.slice(
        (currentPage - 1) * logsPerPage,
        currentPage * logsPerPage
    );

    const handleExport = () => {
        console.log('Exporting audit logs...');
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
                            <Button variant="secondary" onClick={handleExport}>
                                <Download className="mr-2 h-4 w-4" />
                                Export
                            </Button>
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
                        {paginatedLogs.length === 0 ? (
                            <div className="p-8 text-center">
                                <FileText className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No audit log entries found</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {paginatedLogs.map((log) => (
                                    <div key={log.id} className="p-4 transition-colors hover:bg-background-secondary">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex flex-1 items-start gap-3">
                                                <div className={`mt-0.5 ${getActionColor(log.action)}`}>
                                                    {getActionIcon(log.action)}
                                                </div>
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-foreground">{log.user}</span>
                                                        <span className="text-foreground-muted">{log.action}</span>
                                                        <Badge variant={getResourceTypeBadge(log.resourceType)}>
                                                            {log.resourceType.replace('_', ' ')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-foreground-muted">
                                                        {log.resource}
                                                    </p>
                                                    {log.metadata && (
                                                        <div className="flex gap-2 text-xs text-foreground-subtle">
                                                            {Object.entries(log.metadata).map(([key, value]) => (
                                                                <span key={key}>
                                                                    {key}: <code>{value}</code>
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex flex-col items-end gap-1 text-right">
                                                <p className="text-xs text-foreground-muted">
                                                    {formatTimestamp(log.timestamp)}
                                                </p>
                                                <p className="text-xs text-foreground-subtle">{log.ip}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-foreground-muted">
                            Showing {(currentPage - 1) * logsPerPage + 1} to{' '}
                            {Math.min(currentPage * logsPerPage, filteredLogs.length)} of {filteredLogs.length} entries
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                disabled={currentPage === 1}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages}
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
