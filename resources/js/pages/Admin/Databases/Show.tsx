import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Database,
    Calendar,
    Users,
    Server,
    FolderKanban,
    Play,
    Square,
    RefreshCw,
    Trash2,
    CheckCircle,
    XCircle,
    Clock,
    Activity,
    HardDrive,
    Key,
    Network,
    FileArchive,
} from 'lucide-react';

interface BackupSchedule {
    id: number;
    uuid: string;
    frequency: string;
    enabled: boolean;
    last_execution_status?: string;
    last_execution_at?: string;
}

interface DatabaseDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    database_type: string;
    status: string;
    internal_db_url?: string;
    public_port?: number;
    is_public: boolean;
    team_id: number;
    team_name: string;
    project_id: number;
    project_name: string;
    environment_id: number;
    environment_name: string;
    server_id?: number;
    server_name?: string;
    server_uuid?: string;
    image?: string;
    limits_memory?: string;
    limits_cpus?: string;
    backups: BackupSchedule[];
    created_at: string;
    updated_at: string;
}

interface Props {
    database: DatabaseDetails;
}

function BackupRow({ backup }: { backup: BackupSchedule }) {
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'warning' | 'danger'; label: string }> = {
        success: { variant: 'success', label: 'Success' },
        failed: { variant: 'danger', label: 'Failed' },
        running: { variant: 'warning', label: 'Running' },
    };

    const config = backup.last_execution_status
        ? statusConfig[backup.last_execution_status] || { variant: 'default' as const, label: backup.last_execution_status }
        : null;

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <FileArchive className="h-5 w-5 text-foreground-muted" />
                <div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/admin/backups/${backup.uuid}`}
                            className="font-medium text-foreground hover:text-primary"
                        >
                            Backup Schedule
                        </Link>
                        <Badge variant={backup.enabled ? 'success' : 'default'} size="sm">
                            {backup.enabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                        {config && (
                            <Badge variant={config.variant} size="sm">
                                Last: {config.label}
                            </Badge>
                        )}
                    </div>
                    <p className="text-xs text-foreground-subtle">
                        Frequency: {backup.frequency}
                        {backup.last_execution_at && ` · Last run: ${new Date(backup.last_execution_at).toLocaleString()}`}
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function AdminDatabaseShow({ database }: Props) {
    const confirm = useConfirm();
    const [isRestarting, setIsRestarting] = React.useState(false);
    const [isStopping, setIsStopping] = React.useState(false);
    const [isStarting, setIsStarting] = React.useState(false);

    const getStatusConfig = (status: string) => {
        const statusLower = status?.toLowerCase() || 'unknown';
        if (statusLower.includes('running') || statusLower.includes('healthy')) {
            return { variant: 'success' as const, label: 'Running', icon: <CheckCircle className="h-4 w-4" /> };
        }
        if (statusLower.includes('stopped') || statusLower.includes('exited')) {
            return { variant: 'danger' as const, label: 'Stopped', icon: <XCircle className="h-4 w-4" /> };
        }
        if (statusLower.includes('starting') || statusLower.includes('restarting')) {
            return { variant: 'warning' as const, label: 'Starting', icon: <Clock className="h-4 w-4" /> };
        }
        return { variant: 'default' as const, label: status || 'Unknown', icon: null };
    };

    const config = getStatusConfig(database.status);

    const getDatabaseIcon = (type: string) => {
        const colors: Record<string, string> = {
            postgresql: 'from-blue-500 to-blue-600',
            mysql: 'from-orange-500 to-orange-600',
            mariadb: 'from-yellow-500 to-yellow-600',
            mongodb: 'from-green-500 to-green-600',
            redis: 'from-red-500 to-red-600',
            keydb: 'from-purple-500 to-purple-600',
            dragonfly: 'from-cyan-500 to-cyan-600',
            clickhouse: 'from-amber-500 to-amber-600',
        };
        return colors[type.toLowerCase()] || 'from-gray-500 to-gray-600';
    };

    const handleRestart = async () => {
        const confirmed = await confirm({
            title: 'Restart Database',
            description: `Are you sure you want to restart "${database.name}"? This will cause a brief downtime.`,
            confirmText: 'Restart',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRestarting(true);
            router.post(`/admin/databases/${database.uuid}/restart`, {}, {
                preserveScroll: true,
                onFinish: () => setIsRestarting(false),
            });
        }
    };

    const handleStop = async () => {
        const confirmed = await confirm({
            title: 'Stop Database',
            description: `Are you sure you want to stop "${database.name}"? The database will become unavailable.`,
            confirmText: 'Stop',
            variant: 'danger',
        });
        if (confirmed) {
            setIsStopping(true);
            router.post(`/admin/databases/${database.uuid}/stop`, {}, {
                preserveScroll: true,
                onFinish: () => setIsStopping(false),
            });
        }
    };

    const handleStart = async () => {
        setIsStarting(true);
        router.post(`/admin/databases/${database.uuid}/start`, {}, {
            preserveScroll: true,
            onFinish: () => setIsStarting(false),
        });
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Database',
            description: `Are you sure you want to delete "${database.name}"? This action cannot be undone and ALL DATA will be permanently lost!`,
            confirmText: 'Delete Database',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/databases/${database.uuid}`);
        }
    };

    const isStopped = database.status?.toLowerCase().includes('stopped') ||
                      database.status?.toLowerCase().includes('exited');

    return (
        <AdminLayout
            title={database.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Databases', href: '/admin/databases' },
                { label: database.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className={`flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br ${getDatabaseIcon(database.database_type)} text-white`}>
                                <Database className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{database.name}</h1>
                                    <Badge variant={config.variant} icon={config.icon}>
                                        {config.label}
                                    </Badge>
                                    <Badge variant="default">{database.database_type}</Badge>
                                    {database.is_public && (
                                        <Badge variant="warning">Public</Badge>
                                    )}
                                </div>
                                {database.description && (
                                    <p className="mt-1 text-sm text-foreground-subtle">{database.description}</p>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {isStopped ? (
                                <Button
                                    variant="success"
                                    onClick={handleStart}
                                    disabled={isStarting}
                                >
                                    <Play className={`h-4 w-4 ${isStarting ? 'animate-pulse' : ''}`} />
                                    {isStarting ? 'Starting...' : 'Start'}
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        variant="secondary"
                                        onClick={handleRestart}
                                        disabled={isRestarting}
                                    >
                                        <RefreshCw className={`h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                                        {isRestarting ? 'Restarting...' : 'Restart'}
                                    </Button>
                                    <Button
                                        variant="warning"
                                        onClick={handleStop}
                                        disabled={isStopping}
                                    >
                                        <Square className="h-4 w-4" />
                                        {isStopping ? 'Stopping...' : 'Stop'}
                                    </Button>
                                </>
                            )}
                            <Button variant="danger" onClick={handleDelete}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Status</p>
                                    <div className="flex items-center gap-2">
                                        {config.icon}
                                        <span className={`text-lg font-bold ${config.variant === 'success' ? 'text-success' : config.variant === 'danger' ? 'text-danger' : config.variant === 'warning' ? 'text-warning' : 'text-foreground'}`}>
                                            {config.label}
                                        </span>
                                    </div>
                                </div>
                                <Activity className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Team</p>
                                    <Link
                                        href={`/admin/teams/${database.team_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {database.team_name}
                                    </Link>
                                </div>
                                <Users className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Project</p>
                                    <Link
                                        href={`/admin/projects/${database.project_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {database.project_name}
                                    </Link>
                                </div>
                                <FolderKanban className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Server</p>
                                    {database.server_uuid ? (
                                        <Link
                                            href={`/admin/servers/${database.server_uuid}`}
                                            className="text-lg font-bold text-foreground hover:text-primary"
                                        >
                                            {database.server_name || 'Unknown'}
                                        </Link>
                                    ) : (
                                        <span className="text-lg font-bold text-foreground-muted">N/A</span>
                                    )}
                                </div>
                                <Server className="h-8 w-8 text-green-500/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Created</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(database.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Database Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Database Configuration</CardTitle>
                        <CardDescription>Connection details and resource limits</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-center gap-3">
                                <Database className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Database Type</p>
                                    <p className="font-medium text-foreground">{database.database_type}</p>
                                </div>
                            </div>
                            {database.image && (
                                <div className="flex items-center gap-3">
                                    <HardDrive className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Docker Image</p>
                                        <p className="font-medium text-foreground truncate max-w-xs">{database.image}</p>
                                    </div>
                                </div>
                            )}
                            {database.public_port && (
                                <div className="flex items-center gap-3">
                                    <Network className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Public Port</p>
                                        <p className="font-medium text-foreground">{database.public_port}</p>
                                    </div>
                                </div>
                            )}
                            {database.internal_db_url && (
                                <div className="flex items-center gap-3 sm:col-span-2 lg:col-span-3">
                                    <Key className="h-5 w-5 text-foreground-muted" />
                                    <div className="flex-1">
                                        <p className="text-xs text-foreground-subtle">Internal URL</p>
                                        <p className="font-medium font-mono text-foreground text-sm break-all">
                                            {database.internal_db_url}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {database.limits_memory && (
                                <div className="flex items-center gap-3">
                                    <HardDrive className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Memory Limit</p>
                                        <p className="font-medium text-foreground">{database.limits_memory}</p>
                                    </div>
                                </div>
                            )}
                            {database.limits_cpus && (
                                <div className="flex items-center gap-3">
                                    <Activity className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">CPU Limit</p>
                                        <p className="font-medium text-foreground">{database.limits_cpus}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <FolderKanban className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Environment</p>
                                    <p className="font-medium text-foreground">{database.environment_name}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Backups */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Backup Schedules ({database.backups?.length || 0})</CardTitle>
                        <CardDescription>Configured backup schedules for this database</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!database.backups || database.backups.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No backup schedules configured</p>
                        ) : (
                            database.backups.map((backup) => (
                                <BackupRow key={backup.id} backup={backup} />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
