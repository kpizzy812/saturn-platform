import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import {
    Search,
    Database,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Loader2,
} from 'lucide-react';

interface DatabaseInfo {
    id: number;
    name: string;
    uuid: string;
    database_type: string;
    status: string;
    description?: string;
    team_id?: number;
    team_name?: string;
    environment_id?: number;
    environment_name?: string;
    project_name?: string;
    created_at: string;
    updated_at?: string;
}

interface Props {
    databases: {
        data: DatabaseInfo[];
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    stats: {
        runningCount: number;
        stoppedCount: number;
        errorCount: number;
    };
    typeCounts: Record<string, number>;
    filters: {
        search?: string;
        status?: string;
        type?: string;
    };
}

const TYPE_LABELS: Record<string, string> = {
    postgresql: 'PostgreSQL',
    mysql: 'MySQL',
    mongodb: 'MongoDB',
    redis: 'Redis',
    mariadb: 'MariaDB',
    clickhouse: 'ClickHouse',
    dragonfly: 'Dragonfly',
    keydb: 'KeyDB',
};

function DatabaseTypeIcon({ type }: { type: string }) {
    const colors: Record<string, string> = {
        postgresql: 'text-blue-500',
        mysql: 'text-orange-500',
        mongodb: 'text-green-500',
        redis: 'text-red-500',
        mariadb: 'text-teal-500',
        clickhouse: 'text-yellow-500',
        dragonfly: 'text-purple-500',
        keydb: 'text-cyan-500',
    };

    return <Database className={`h-5 w-5 ${colors[type] || 'text-foreground-muted'}`} />;
}

function DatabaseRow({ database }: { database: DatabaseInfo }) {
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'danger' | 'warning'; label: string; icon: React.ReactNode }> = {
        running: { variant: 'success', label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default', label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        error: { variant: 'danger', label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
        exited: { variant: 'danger', label: 'Exited', icon: <XCircle className="h-3 w-3" /> },
    };

    const config = statusConfig[database.status] || { variant: 'default' as const, label: database.status || 'Unknown', icon: null };
    const typeLabel = TYPE_LABELS[database.database_type] || database.database_type || 'Unknown';

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <DatabaseTypeIcon type={database.database_type} />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/databases/${database.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {database.name}
                                </Link>
                                <Badge variant="default" size="sm">
                                    {typeLabel}
                                </Badge>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {database.team_name && <span>{database.team_name}</span>}
                                {database.project_name && (
                                    <>
                                        <span>·</span>
                                        <span>{database.project_name}</span>
                                    </>
                                )}
                                {database.environment_name && (
                                    <>
                                        <span>·</span>
                                        <span>{database.environment_name}</span>
                                    </>
                                )}
                            </div>
                            <div className="mt-1 text-xs text-foreground-subtle">
                                Created: {new Date(database.created_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminDatabasesIndex({ databases, stats, typeCounts = {}, filters = {} }: Props) {
    const items = databases?.data ?? [];
    const total = databases?.total ?? 0;
    const currentPage = databases?.current_page ?? 1;
    const lastPage = databases?.last_page ?? 1;

    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [typeFilter, setTypeFilter] = React.useState<string>(filters.type ?? 'all');
    const [statusFilter, setStatusFilter] = React.useState<string>(filters.status ?? 'all');
    const [isFiltering, setIsFiltering] = React.useState(false);

    const runningCount = stats?.runningCount ?? 0;
    const stoppedCount = stats?.stoppedCount ?? 0;
    const errorCount = stats?.errorCount ?? 0;

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onDatabaseStatusChange: () => {
            router.reload({ only: ['databases', 'stats', 'typeCounts'] });
        },
    });

    // Server-side search with debounce
    const searchTimerRef = React.useRef<ReturnType<typeof setTimeout>>(null);
    React.useEffect(() => {
        return () => {
            if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
        };
    }, []);

    const applyFilters = React.useCallback((search: string, status: string, type: string) => {
        setIsFiltering(true);
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (status && status !== 'all') params.status = status;
        if (type && type !== 'all') params.type = type;

        router.get('/admin/databases', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['databases', 'stats', 'typeCounts', 'filters'],
            onFinish: () => setIsFiltering(false),
        });
    }, []);

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
        searchTimerRef.current = setTimeout(() => {
            applyFilters(value, statusFilter, typeFilter);
        }, 300);
    };

    const handleStatusChange = (status: string) => {
        setStatusFilter(status);
        applyFilters(searchQuery, status, typeFilter);
    };

    const handleTypeChange = (type: string) => {
        setTypeFilter(type);
        applyFilters(searchQuery, statusFilter, type);
    };

    const handlePageChange = (page: number) => {
        setIsFiltering(true);
        const params: Record<string, string> = { page: String(page) };
        if (searchQuery) params.search = searchQuery;
        if (statusFilter && statusFilter !== 'all') params.status = statusFilter;
        if (typeFilter && typeFilter !== 'all') params.type = typeFilter;

        router.get('/admin/databases', params, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setIsFiltering(false),
        });
    };

    return (
        <AdminLayout
            title="Databases"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Databases' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Database Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all databases across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Running</p>
                                    <p className="text-2xl font-bold text-success">{runningCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Stopped</p>
                                    <p className="text-2xl font-bold text-foreground-muted">{stoppedCount}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Errors</p>
                                    <p className="text-2xl font-bold text-danger">{errorCount}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-4">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search databases by name or team..."
                                        value={searchQuery}
                                        onChange={(e) => handleSearchChange(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                {isFiltering && <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm text-foreground-subtle">Type:</span>
                                <Button
                                    variant={typeFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleTypeChange('all')}
                                >
                                    All
                                </Button>
                                {Object.entries(typeCounts).map(([type, count]) => (
                                    <Button
                                        key={type}
                                        variant={typeFilter === type ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleTypeChange(type)}
                                    >
                                        {TYPE_LABELS[type] || type} ({count})
                                    </Button>
                                ))}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-foreground-subtle">Status:</span>
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'running' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('running')}
                                >
                                    Running
                                </Button>
                                <Button
                                    variant={statusFilter === 'stopped' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('stopped')}
                                >
                                    Stopped
                                </Button>
                                <Button
                                    variant={statusFilter === 'error' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('error')}
                                >
                                    Errors
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Databases List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} databases
                                {currentPage > 1 && ` (page ${currentPage})`}
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Database className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No databases found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((db) => (
                                    <DatabaseRow key={`${db.database_type}-${db.id}`} database={db} />
                                ))}
                            </div>
                        )}

                        {/* Pagination */}
                        {lastPage > 1 && (
                            <div className="mt-6 flex items-center justify-center gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={currentPage <= 1 || isFiltering}
                                    onClick={() => handlePageChange(currentPage - 1)}
                                >
                                    Previous
                                </Button>
                                <span className="text-sm text-foreground-muted">
                                    Page {currentPage} of {lastPage}
                                </span>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={currentPage >= lastPage || isFiltering}
                                    onClick={() => handlePageChange(currentPage + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
