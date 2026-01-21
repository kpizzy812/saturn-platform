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
    type: 'postgresql' | 'mysql' | 'mongodb' | 'redis' | 'mariadb';
    status: 'running' | 'stopped' | 'error';
    user: string;
    team: string;
    version?: string;
    size?: string;
    connections?: number;
    created_at: string;
}

interface Props {
    databases: DatabaseInfo[];
    total: number;
}

const defaultDatabases: DatabaseInfo[] = [
    {
        id: 1,
        name: 'production-postgres',
        uuid: 'db-1234-5678',
        type: 'postgresql',
        status: 'running',
        user: 'john.doe@example.com',
        team: 'Production Team',
        version: '15.2',
        size: '2.5 GB',
        connections: 45,
        created_at: '2024-01-15',
    },
    {
        id: 2,
        name: 'staging-mysql',
        uuid: 'db-2345-6789',
        type: 'mysql',
        status: 'running',
        user: 'jane.smith@example.com',
        team: 'Staging Team',
        version: '8.0',
        size: '512 MB',
        connections: 12,
        created_at: '2024-02-20',
    },
    {
        id: 3,
        name: 'cache-redis',
        uuid: 'db-3456-7890',
        type: 'redis',
        status: 'running',
        user: 'bob.wilson@example.com',
        team: 'Dev Team',
        version: '7.0',
        size: '128 MB',
        connections: 8,
        created_at: '2024-03-01',
    },
    {
        id: 4,
        name: 'analytics-mongodb',
        uuid: 'db-4567-8901',
        type: 'mongodb',
        status: 'stopped',
        user: 'admin@example.com',
        team: 'Infrastructure',
        version: '6.0',
        size: '1.2 GB',
        connections: 0,
        created_at: '2024-01-10',
    },
    {
        id: 5,
        name: 'legacy-mariadb',
        uuid: 'db-5678-9012',
        type: 'mariadb',
        status: 'error',
        user: 'admin@example.com',
        team: 'Infrastructure',
        version: '10.9',
        size: '890 MB',
        connections: 0,
        created_at: '2023-12-05',
    },
];

function DatabaseTypeIcon({ type }: { type: string }) {
    const colors = {
        postgresql: 'text-blue-500',
        mysql: 'text-orange-500',
        mongodb: 'text-green-500',
        redis: 'text-red-500',
        mariadb: 'text-teal-500',
    };

    return <Database className={`h-5 w-5 ${colors[type as keyof typeof colors] || 'text-foreground-muted'}`} />;
}

function DatabaseRow({ database }: { database: DatabaseInfo }) {
    const statusConfig = {
        running: { variant: 'success' as const, label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default' as const, label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        error: { variant: 'danger' as const, label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
    };

    const typeConfig = {
        postgresql: { label: 'PostgreSQL', variant: 'default' as const },
        mysql: { label: 'MySQL', variant: 'default' as const },
        mongodb: { label: 'MongoDB', variant: 'default' as const },
        redis: { label: 'Redis', variant: 'default' as const },
        mariadb: { label: 'MariaDB', variant: 'default' as const },
    };

    const config = statusConfig[database.status];
    const typeInfo = typeConfig[database.type];

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <DatabaseTypeIcon type={database.type} />
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
                                <span>{database.user}</span>
                                <span>·</span>
                                <span>{database.team}</span>
                                {database.version && (
                                    <>
                                        <span>·</span>
                                        <span>v{database.version}</span>
                                    </>
                                )}
                                {database.size && (
                                    <>
                                        <span>·</span>
                                        <span>{database.size}</span>
                                    </>
                                )}
                                {database.connections !== undefined && (
                                    <>
                                        <span>·</span>
                                        <span>{database.connections} connections</span>
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

export default function AdminDatabasesIndex({ databases = defaultDatabases, total = 5 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [typeFilter, setTypeFilter] = React.useState<'all' | 'postgresql' | 'mysql' | 'mongodb' | 'redis' | 'mariadb'>('all');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'running' | 'stopped' | 'error'>('all');

    const filteredDatabases = databases.filter((db) => {
        const matchesSearch =
            db.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            db.user.toLowerCase().includes(searchQuery.toLowerCase()) ||
            db.team.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesType = typeFilter === 'all' || db.type === typeFilter;
        const matchesStatus = statusFilter === 'all' || db.status === statusFilter;
        return matchesSearch && matchesType && matchesStatus;
    });

    const runningCount = databases.filter((d) => d.status === 'running').length;
    const stoppedCount = databases.filter((d) => d.status === 'stopped').length;
    const errorCount = databases.filter((d) => d.status === 'error').length;

    const typeCount = {
        postgresql: databases.filter((d) => d.type === 'postgresql').length,
        mysql: databases.filter((d) => d.type === 'mysql').length,
        mongodb: databases.filter((d) => d.type === 'mongodb').length,
        redis: databases.filter((d) => d.type === 'redis').length,
        mariadb: databases.filter((d) => d.type === 'mariadb').length,
    };

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
                                <Button
                                    variant={typeFilter === 'postgresql' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('postgresql')}
                                >
                                    PostgreSQL ({typeCount.postgresql})
                                </Button>
                                <Button
                                    variant={typeFilter === 'mysql' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('mysql')}
                                >
                                    MySQL ({typeCount.mysql})
                                </Button>
                                <Button
                                    variant={typeFilter === 'mongodb' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('mongodb')}
                                >
                                    MongoDB ({typeCount.mongodb})
                                </Button>
                                <Button
                                    variant={typeFilter === 'redis' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('redis')}
                                >
                                    Redis ({typeCount.redis})
                                </Button>
                                <Button
                                    variant={typeFilter === 'mariadb' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('mariadb')}
                                >
                                    MariaDB ({typeCount.mariadb})
                                </Button>
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
