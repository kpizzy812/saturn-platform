import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Database,
    CheckCircle,
    XCircle,
    AlertTriangle,
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

interface PaginatedDatabases {
    data: DatabaseInfo[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface Props {
    databases: PaginatedDatabases;
}

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

    const typeConfig: Record<string, { label: string; variant: 'default' }> = {
        postgresql: { label: 'PostgreSQL', variant: 'default' },
        mysql: { label: 'MySQL', variant: 'default' },
        mongodb: { label: 'MongoDB', variant: 'default' },
        redis: { label: 'Redis', variant: 'default' },
        mariadb: { label: 'MariaDB', variant: 'default' },
        clickhouse: { label: 'ClickHouse', variant: 'default' },
        dragonfly: { label: 'Dragonfly', variant: 'default' },
        keydb: { label: 'KeyDB', variant: 'default' },
    };

    const config = statusConfig[database.status] || { variant: 'default' as const, label: database.status || 'Unknown', icon: null };
    const typeInfo = typeConfig[database.database_type] || { label: database.database_type || 'Unknown', variant: 'default' as const };

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
                                <Badge variant={typeInfo.variant} size="sm">
                                    {typeInfo.label}
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

export default function AdminDatabasesIndex({ databases }: Props) {
    const items = databases?.data ?? [];
    const total = databases?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');
    const [typeFilter, setTypeFilter] = React.useState<string>('all');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');

    const filteredDatabases = items.filter((db) => {
        const matchesSearch =
            db.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (db.team_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (db.project_name || '').toLowerCase().includes(searchQuery.toLowerCase());
        const matchesType = typeFilter === 'all' || db.database_type === typeFilter;
        const matchesStatus = statusFilter === 'all' || db.status === statusFilter;
        return matchesSearch && matchesType && matchesStatus;
    });

    const runningCount = items.filter((d) => d.status?.startsWith('running')).length;
    const stoppedCount = items.filter((d) => d.status?.startsWith('stopped') || d.status?.startsWith('exited')).length;
    const errorCount = items.filter((d) => d.status?.startsWith('error') || d.status?.startsWith('degraded')).length;

    const typeCount: Record<string, number> = {};
    for (const db of items) {
        typeCount[db.database_type] = (typeCount[db.database_type] || 0) + 1;
    }

    return (
        <AdminLayout
            title="Databases"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Databases' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
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
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search databases by name, user, or team..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <span className="text-sm text-foreground-subtle">Type:</span>
                                <Button
                                    variant={typeFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('all')}
                                >
                                    All
                                </Button>
                                {Object.entries(typeCount).map(([type, count]) => {
                                    const labels: Record<string, string> = {
                                        postgresql: 'PostgreSQL', mysql: 'MySQL', mongodb: 'MongoDB',
                                        redis: 'Redis', mariadb: 'MariaDB', clickhouse: 'ClickHouse',
                                        dragonfly: 'Dragonfly', keydb: 'KeyDB',
                                    };
                                    return (
                                        <Button
                                            key={type}
                                            variant={typeFilter === type ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => setTypeFilter(type)}
                                        >
                                            {labels[type] || type} ({count})
                                        </Button>
                                    );
                                })}
                            </div>
                            <div className="flex gap-2">
                                <span className="text-sm text-foreground-subtle">Status:</span>
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'running' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('running')}
                                >
                                    Running
                                </Button>
                                <Button
                                    variant={statusFilter === 'stopped' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('stopped')}
                                >
                                    Stopped
                                </Button>
                                <Button
                                    variant={statusFilter === 'error' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('error')}
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
                                Showing {filteredDatabases.length} of {total} databases
                            </p>
                        </div>

                        {filteredDatabases.length === 0 ? (
                            <div className="py-12 text-center">
                                <Database className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No databases found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredDatabases.map((db) => (
                                    <DatabaseRow key={db.id} database={db} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
