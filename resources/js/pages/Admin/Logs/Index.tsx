import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import {
    Search,
    Filter,
    Download,
    AlertTriangle,
    Info,
    XCircle,
    CheckCircle,
    Activity,
    Shield,
    User,
    Server,
} from 'lucide-react';

interface LogEntry {
    id: number;
    timestamp: string;
    level: 'info' | 'warning' | 'error' | 'debug' | 'critical';
    category: 'auth' | 'deployment' | 'server' | 'system' | 'api' | 'security';
    message: string;
    user?: string;
    ip_address?: string;
    metadata?: Record<string, any>;
}

interface Props {
    logs: LogEntry[];
    total: number;
    currentPage?: number;
    perPage?: number;
}

const defaultLogs: LogEntry[] = [];

// Format log message - extract text and JSON separately
function formatLogMessage(message: string): { text: string; jsonData: Record<string, unknown> | null } {
    // Check if message contains JSON object
    const jsonMatch = message.match(/^(.+?)\s*(\{.+\})\s*$/);
    if (jsonMatch) {
        try {
            const jsonData = JSON.parse(jsonMatch[2]);
            return { text: jsonMatch[1].trim(), jsonData };
        } catch {
            // Not valid JSON
        }
    }

    // Check if entire message is JSON
    if (message.startsWith('{') || message.startsWith('[')) {
        try {
            const jsonData = JSON.parse(message);
            // Extract meaningful text from common fields
            if (typeof jsonData === 'object' && jsonData !== null) {
                const textParts: string[] = [];
                if (jsonData.message) textParts.push(String(jsonData.message));
                if (jsonData.action) textParts.push(String(jsonData.action));
                if (jsonData.error) textParts.push(String(jsonData.error));

                if (textParts.length > 0) {
                    return { text: textParts.join(' - '), jsonData };
                }
                return { text: 'System event', jsonData };
            }
        } catch {
            // Not valid JSON
        }
    }

    return { text: message, jsonData: null };
}

function LogRow({ log }: { log: LogEntry }) {
    const levelConfig = {
        info: {
            variant: 'info' as const,
            icon: Info,
            iconColor: 'text-info',
        },
        warning: {
            variant: 'warning' as const,
            icon: AlertTriangle,
            iconColor: 'text-warning',
        },
        error: {
            variant: 'danger' as const,
            icon: XCircle,
            iconColor: 'text-danger',
        },
        debug: {
            variant: 'default' as const,
            icon: Activity,
            iconColor: 'text-foreground-muted',
        },
        critical: {
            variant: 'danger' as const,
            icon: AlertTriangle,
            iconColor: 'text-danger',
        },
    };

    const categoryIcons = {
        auth: User,
        deployment: Activity,
        server: Server,
        system: Activity,
        api: Activity,
        security: Shield,
    };

    const config = levelConfig[log.level];
    const Icon = config.icon;
    const CategoryIcon = categoryIcons[log.category];

    // Parse message for JSON content
    const { text: messageText, jsonData: parsedJson } = formatLogMessage(log.message);

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start gap-3">
                <div className={`mt-1 rounded-full bg-background-tertiary p-2 ${config.iconColor}`}>
                    <Icon className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <Badge variant={config.variant} size="sm">
                                    {log.level}
                                </Badge>
                                <Badge variant="default" size="sm" icon={<CategoryIcon className="h-3 w-3" />}>
                                    {log.category}
                                </Badge>
                                <span className="text-xs text-foreground-subtle">{log.timestamp}</span>
                            </div>
                            <p className="mt-2 text-sm text-foreground">{messageText}</p>
                            {parsedJson && (
                                <details className="mt-2">
                                    <summary className="cursor-pointer text-xs text-foreground-muted hover:text-foreground">
                                        View details
                                    </summary>
                                    <pre className="mt-1 rounded-md bg-background-tertiary p-2 text-xs text-foreground-muted overflow-x-auto">
                                        {JSON.stringify(parsedJson, null, 2)}
                                    </pre>
                                </details>
                            )}
                            {(log.user || log.ip_address) && (
                                <div className="mt-1 flex items-center gap-3 text-xs text-foreground-muted">
                                    {log.user && (
                                        <span className="flex items-center gap-1">
                                            <User className="h-3 w-3" />
                                            {log.user}
                                        </span>
                                    )}
                                    {log.ip_address && (
                                        <span className="flex items-center gap-1">
                                            <Activity className="h-3 w-3" />
                                            {log.ip_address}
                                        </span>
                                    )}
                                </div>
                            )}
                            {log.metadata && (
                                <details className="mt-2">
                                    <summary className="cursor-pointer text-xs text-foreground-muted hover:text-foreground">
                                        View metadata
                                    </summary>
                                    <pre className="mt-1 rounded-md bg-background-tertiary p-2 text-xs text-foreground-muted">
                                        {JSON.stringify(log.metadata, null, 2)}
                                    </pre>
                                </details>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminLogsIndex({ logs = defaultLogs, total = 6 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [levelFilter, setLevelFilter] = React.useState<'all' | 'info' | 'warning' | 'error' | 'debug' | 'critical'>('all');
    const [categoryFilter, setCategoryFilter] = React.useState<'all' | 'auth' | 'deployment' | 'server' | 'system' | 'api' | 'security'>('all');

    const filteredLogs = logs.filter((log) => {
        const matchesSearch =
            log.message.toLowerCase().includes(searchQuery.toLowerCase()) ||
            log.user?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            log.ip_address?.includes(searchQuery);
        const matchesLevel = levelFilter === 'all' || log.level === levelFilter;
        const matchesCategory = categoryFilter === 'all' || log.category === categoryFilter;
        return matchesSearch && matchesLevel && matchesCategory;
    });

    const handleExportLogs = () => {
        const data = JSON.stringify(filteredLogs, null, 2);
        const blob = new Blob([data], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `saturn-logs-${new Date().toISOString().slice(0, 10)}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <AdminLayout
            title="Logs"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Logs' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">System Logs</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            View and search system logs and audit trail
                        </p>
                    </div>
                    <Button onClick={handleExportLogs}>
                        <Download className="h-4 w-4" />
                        Export Logs
                    </Button>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search logs by message, user, or IP address..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex flex-wrap gap-4">
                                <div className="flex items-center gap-2">
                                    <Filter className="h-4 w-4 text-foreground-muted" />
                                    <span className="text-sm text-foreground-muted">Level:</span>
                                    <div className="flex gap-2">
                                        <Button
                                            variant={levelFilter === 'all' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setLevelFilter('all')}
                                        >
                                            All
                                        </Button>
                                        <Button
                                            variant={levelFilter === 'info' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setLevelFilter('info')}
                                        >
                                            Info
                                        </Button>
                                        <Button
                                            variant={levelFilter === 'warning' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setLevelFilter('warning')}
                                        >
                                            Warning
                                        </Button>
                                        <Button
                                            variant={levelFilter === 'error' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setLevelFilter('error')}
                                        >
                                            Error
                                        </Button>
                                        <Button
                                            variant={levelFilter === 'critical' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setLevelFilter('critical')}
                                        >
                                            Critical
                                        </Button>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Category:</span>
                                    <div className="flex gap-2">
                                        <Button
                                            variant={categoryFilter === 'all' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setCategoryFilter('all')}
                                        >
                                            All
                                        </Button>
                                        <Button
                                            variant={categoryFilter === 'auth' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setCategoryFilter('auth')}
                                        >
                                            Auth
                                        </Button>
                                        <Button
                                            variant={categoryFilter === 'deployment' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setCategoryFilter('deployment')}
                                        >
                                            Deployment
                                        </Button>
                                        <Button
                                            variant={categoryFilter === 'server' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setCategoryFilter('server')}
                                        >
                                            Server
                                        </Button>
                                        <Button
                                            variant={categoryFilter === 'security' ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setCategoryFilter('security')}
                                        >
                                            Security
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Logs List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredLogs.length} of {total} logs
                            </p>
                        </div>

                        {filteredLogs.length === 0 ? (
                            <div className="py-12 text-center">
                                <Activity className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No logs found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredLogs.map((log) => (
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
