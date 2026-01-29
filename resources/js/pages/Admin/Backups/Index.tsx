import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useConfirm } from '@/components/ui';
import {
    Dropdown,
    DropdownTrigger,
    DropdownContent,
    DropdownItem,
    DropdownDivider,
} from '@/components/ui/Dropdown';
import {
    HardDrive,
    Database,
    Clock,
    CheckCircle,
    XCircle,
    Play,
    Search,
    MoreHorizontal,
    Calendar,
    Cloud,
    Server,
    Eye,
    RefreshCw,
    Trash2,
    AlertTriangle,
} from 'lucide-react';

interface BackupExecution {
    id: number;
    uuid: string;
    status: 'success' | 'failed' | 'running';
    size?: number;
    filename?: string;
    message?: string;
    created_at: string;
}

interface BackupSchedule {
    id: number;
    uuid: string;
    database_id: number;
    database_uuid: string;
    database_name: string;
    database_type: string;
    team_id: number;
    team_name: string;
    frequency: string;
    enabled: boolean;
    save_s3: boolean;
    s3_storage_name?: string;
    last_execution?: BackupExecution;
    executions_count: number;
    created_at: string;
}

interface Props {
    backups: BackupSchedule[];
    stats: {
        total: number;
        enabled: number;
        with_s3: number;
        failed_last_24h: number;
    };
}

function BackupRow({ backup, onRunNow }: { backup: BackupSchedule; onRunNow: () => void }) {
    const confirm = useConfirm();
    const [isRunning, setIsRunning] = React.useState(false);

    const getStatusConfig = (status?: string) => {
        if (!status) return { variant: 'default' as const, label: 'No runs' };
        switch (status) {
            case 'success':
                return { variant: 'success' as const, label: 'Success' };
            case 'failed':
                return { variant: 'danger' as const, label: 'Failed' };
            case 'running':
                return { variant: 'warning' as const, label: 'Running' };
            default:
                return { variant: 'default' as const, label: status };
        }
    };

    const getDatabaseTypeConfig = (type: string) => {
        const configs: Record<string, { color: string; label: string }> = {
            postgresql: { color: 'text-blue-400', label: 'PostgreSQL' },
            mysql: { color: 'text-orange-400', label: 'MySQL' },
            mariadb: { color: 'text-teal-400', label: 'MariaDB' },
            mongodb: { color: 'text-green-400', label: 'MongoDB' },
            redis: { color: 'text-red-400', label: 'Redis' },
            keydb: { color: 'text-purple-400', label: 'KeyDB' },
            dragonfly: { color: 'text-pink-400', label: 'Dragonfly' },
            clickhouse: { color: 'text-yellow-400', label: 'ClickHouse' },
        };
        return configs[type.toLowerCase()] || { color: 'text-foreground-muted', label: type };
    };

    const formatSize = (bytes?: number) => {
        if (!bytes) return 'N/A';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        return `${size.toFixed(1)} ${units[unitIndex]}`;
    };

    const handleRunNow = async () => {
        const confirmed = await confirm({
            title: 'Run Backup Now',
            description: `Start a backup for "${backup.database_name}" immediately?`,
            confirmText: 'Run Backup',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRunning(true);
            onRunNow();
            // Note: The actual completion will reload the page
            setTimeout(() => setIsRunning(false), 5000);
        }
    };

    const statusConfig = getStatusConfig(backup.last_execution?.status);
    const dbConfig = getDatabaseTypeConfig(backup.database_type);

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Database className={`h-5 w-5 ${dbConfig.color}`} />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/backups/${backup.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {backup.database_name}
                                </Link>
                                <Badge variant="default" size="sm">{dbConfig.label}</Badge>
                                {backup.save_s3 && (
                                    <Badge variant="primary" size="sm" icon={<Cloud className="h-3 w-3" />}>
                                        S3
                                    </Badge>
                                )}
                                {!backup.enabled && (
                                    <Badge variant="danger" size="sm">Disabled</Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {backup.frequency}
                                </span>
                                <span>{backup.team_name}</span>
                                <span>{backup.executions_count} executions</span>
                            </div>
                        </div>
                    </div>

                    {/* Last execution info */}
                    {backup.last_execution && (
                        <div className="mt-2 flex items-center gap-4 text-xs text-foreground-muted">
                            <span>
                                Last run: {new Date(backup.last_execution.created_at).toLocaleString()}
                            </span>
                            {backup.last_execution.size && (
                                <span>Size: {formatSize(backup.last_execution.size)}</span>
                            )}
                            {backup.last_execution.status === 'failed' && backup.last_execution.message && (
                                <span className="text-danger">
                                    Error: {backup.last_execution.message.substring(0, 50)}...
                                </span>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Badge variant={statusConfig.variant} size="sm">
                        {statusConfig.label}
                    </Badge>
                    <Dropdown>
                        <DropdownTrigger>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownTrigger>
                        <DropdownContent align="right">
                            <DropdownItem onClick={() => router.visit(`/admin/backups/${backup.uuid}`)}>
                                <Eye className="h-4 w-4" />
                                View Details
                            </DropdownItem>
                            <DropdownDivider />
                            <DropdownItem onClick={handleRunNow} disabled={isRunning}>
                                <Play className="h-4 w-4" />
                                {isRunning ? 'Running...' : 'Run Now'}
                            </DropdownItem>
                        </DropdownContent>
                    </Dropdown>
                </div>
            </div>
        </div>
    );
}

export default function AdminBackupsIndex({ backups: initialBackups, stats }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [typeFilter, setTypeFilter] = React.useState<string>('all');

    const backups = initialBackups ?? [];

    const filteredBackups = backups.filter((backup) => {
        const matchesSearch =
            backup.database_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            backup.team_name.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesType = typeFilter === 'all' || backup.database_type.toLowerCase() === typeFilter;
        return matchesSearch && matchesType;
    });

    const handleRunBackup = (uuid: string) => {
        router.post(`/admin/backups/${uuid}/run`, {}, {
            preserveScroll: true,
        });
    };

    // Get unique database types for filter
    const databaseTypes = [...new Set(backups.map((b) => b.database_type.toLowerCase()))];

    return (
        <AdminLayout
            title="Backups"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Backups' },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Backup Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor and manage database backups across all teams
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Schedules</p>
                                    <p className="text-2xl font-bold text-primary">{stats?.total ?? 0}</p>
                                </div>
                                <HardDrive className="h-8 w-8 text-primary/50" />
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
                                    <p className="text-sm text-foreground-subtle">With S3</p>
                                    <p className="text-2xl font-bold text-info">{stats?.with_s3 ?? 0}</p>
                                </div>
                                <Cloud className="h-8 w-8 text-info/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed (24h)</p>
                                    <p className="text-2xl font-bold text-danger">{stats?.failed_last_24h ?? 0}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
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
                                    placeholder="Search by database name or team..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={typeFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setTypeFilter('all')}
                                >
                                    All
                                </Button>
                                {databaseTypes.map((type) => (
                                    <Button
                                        key={type}
                                        variant={typeFilter === type ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => setTypeFilter(type)}
                                    >
                                        {type.charAt(0).toUpperCase() + type.slice(1)}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Backups List */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Backup Schedules ({filteredBackups.length})</CardTitle>
                        <CardDescription>Scheduled database backups across all databases</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {filteredBackups.length === 0 ? (
                            <div className="py-12 text-center">
                                <HardDrive className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">
                                    {backups.length === 0 ? 'No backup schedules configured' : 'No matching backups'}
                                </p>
                                <p className="text-xs text-foreground-subtle">
                                    {backups.length === 0
                                        ? 'Configure backups for your databases to see them here'
                                        : 'Try adjusting your search or filters'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredBackups.map((backup) => (
                                    <BackupRow
                                        key={backup.id}
                                        backup={backup}
                                        onRunNow={() => handleRunBackup(backup.uuid)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Info */}
                <Card variant="glass" className="mt-6">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 text-warning" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Backup Information</p>
                                <p className="mt-1 text-xs text-foreground-muted">
                                    Backups are created according to their schedules and stored locally on the database server.
                                    Enable S3 storage to automatically upload backups to a remote location for disaster recovery.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
