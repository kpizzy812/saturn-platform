import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
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
    MoreHorizontal,
    Calendar,
    Cloud,
    Server,
    RefreshCw,
    Trash2,
    RotateCcw,
    FileArchive,
    AlertTriangle,
    Shield,
    ShieldCheck,
    ShieldX,
    TestTube,
} from 'lucide-react';

interface BackupExecution {
    id: number;
    uuid: string;
    status: 'success' | 'failed' | 'running';
    size?: number;
    filename?: string;
    message?: string;
    s3_uploaded: boolean;
    local_storage_deleted: boolean;
    verification_status?: 'pending' | 'verified' | 'failed' | 'skipped';
    checksum?: string;
    verified_at?: string;
    restore_test_status?: 'pending' | 'success' | 'failed' | 'skipped';
    restore_test_at?: string;
    restore_test_duration_seconds?: number;
    s3_integrity_status?: 'pending' | 'verified' | 'failed';
    s3_file_size?: number;
    created_at: string;
    finished_at?: string;
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
    server_name: string;
    frequency: string;
    enabled: boolean;
    save_s3: boolean;
    s3_storage_name?: string;
    number_of_backups_locally: number;
    verify_after_backup: boolean;
    restore_test_enabled: boolean;
    restore_test_frequency?: string;
    executions: BackupExecution[];
    created_at: string;
    updated_at: string;
}

interface Props {
    backup: BackupSchedule;
}

function ExecutionRow({
    execution,
    backupUuid: _backupUuid,
    onRestore,
    onDelete,
}: {
    execution: BackupExecution;
    backupUuid: string;
    onRestore: () => void;
    onDelete: () => void;
}) {
    const confirm = useConfirm();

    const getStatusConfig = (status: string) => {
        switch (status) {
            case 'success':
                return { variant: 'success' as const, label: 'Success', icon: <CheckCircle className="h-4 w-4" /> };
            case 'failed':
                return { variant: 'danger' as const, label: 'Failed', icon: <XCircle className="h-4 w-4" /> };
            case 'running':
                return { variant: 'warning' as const, label: 'Running', icon: <RefreshCw className="h-4 w-4 animate-spin" /> };
            default:
                return { variant: 'default' as const, label: status, icon: null };
        }
    };

    const getVerificationBadge = () => {
        switch (execution.verification_status) {
            case 'verified':
                return <Badge variant="success" size="sm"><ShieldCheck className="mr-1 h-3 w-3" />Verified</Badge>;
            case 'failed':
                return <Badge variant="danger" size="sm"><ShieldX className="mr-1 h-3 w-3" />Verify Failed</Badge>;
            case 'pending':
                return <Badge variant="warning" size="sm"><Shield className="mr-1 h-3 w-3" />Verifying...</Badge>;
            default:
                return null;
        }
    };

    const getRestoreTestBadge = () => {
        switch (execution.restore_test_status) {
            case 'success':
                return <Badge variant="success" size="sm"><TestTube className="mr-1 h-3 w-3" />Restore OK</Badge>;
            case 'failed':
                return <Badge variant="danger" size="sm"><TestTube className="mr-1 h-3 w-3" />Restore Failed</Badge>;
            case 'pending':
                return <Badge variant="warning" size="sm"><TestTube className="mr-1 h-3 w-3" />Testing...</Badge>;
            default:
                return null;
        }
    };

    const getS3IntegrityBadge = () => {
        if (!execution.s3_uploaded) return null;
        switch (execution.s3_integrity_status) {
            case 'verified':
                return <Badge variant="success" size="sm"><Cloud className="mr-1 h-3 w-3" />S3 OK</Badge>;
            case 'failed':
                return <Badge variant="danger" size="sm"><Cloud className="mr-1 h-3 w-3" />S3 Failed</Badge>;
            case 'pending':
                return <Badge variant="warning" size="sm"><Cloud className="mr-1 h-3 w-3" />Checking S3...</Badge>;
            default:
                return <Badge variant="primary" size="sm" icon={<Cloud className="h-3 w-3" />}>S3</Badge>;
        }
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

    const handleRestore = async () => {
        const confirmed = await confirm({
            title: 'Restore from Backup',
            description: `This will restore the database from this backup. The current data will be replaced. Are you sure?`,
            confirmText: 'Restore',
            variant: 'danger',
        });
        if (confirmed) {
            onRestore();
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Backup',
            description: `Delete this backup execution? This cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            onDelete();
        }
    };

    const statusConfig = getStatusConfig(execution.status);

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <FileArchive className="h-5 w-5 text-foreground-muted" />
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium text-foreground">
                                {execution.filename || `backup-${execution.id}`}
                            </span>
                            <Badge variant={statusConfig.variant} size="sm" icon={statusConfig.icon}>
                                {statusConfig.label}
                            </Badge>
                            {getVerificationBadge()}
                            {getRestoreTestBadge()}
                            {getS3IntegrityBadge()}
                        </div>
                        <div className="mt-1 flex flex-wrap items-center gap-3 text-xs text-foreground-subtle">
                            <span>Created: {new Date(execution.created_at).toLocaleString()}</span>
                            {execution.finished_at && (
                                <span>Finished: {new Date(execution.finished_at).toLocaleString()}</span>
                            )}
                            {execution.size && <span>Size: {formatSize(execution.size)}</span>}
                            {execution.checksum && (
                                <span title={execution.checksum}>
                                    Checksum: {execution.checksum.substring(0, 8)}...
                                </span>
                            )}
                            {execution.restore_test_duration_seconds && (
                                <span>Restore test: {execution.restore_test_duration_seconds}s</span>
                            )}
                        </div>
                        {execution.status === 'failed' && execution.message && (
                            <p className="mt-2 text-xs text-danger">{execution.message}</p>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {execution.status === 'success' && (
                        <Dropdown>
                            <DropdownTrigger>
                                <Button variant="ghost" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem onClick={handleRestore}>
                                    <RotateCcw className="h-4 w-4" />
                                    Restore Database
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem onClick={handleDelete} className="text-danger">
                                    <Trash2 className="h-4 w-4" />
                                    Delete Backup
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function AdminBackupShow({ backup }: Props) {
    const confirm = useConfirm();
    const [isRunning, setIsRunning] = React.useState(false);

    const executions = backup.executions ?? [];

    const getDatabaseTypeConfig = (type: string) => {
        const configs: Record<string, { color: string; gradient: string; label: string }> = {
            postgresql: { color: 'text-blue-400', gradient: 'from-blue-500 to-blue-600', label: 'PostgreSQL' },
            mysql: { color: 'text-orange-400', gradient: 'from-orange-500 to-orange-600', label: 'MySQL' },
            mariadb: { color: 'text-teal-400', gradient: 'from-teal-500 to-teal-600', label: 'MariaDB' },
            mongodb: { color: 'text-green-400', gradient: 'from-green-500 to-green-600', label: 'MongoDB' },
            redis: { color: 'text-red-400', gradient: 'from-red-500 to-red-600', label: 'Redis' },
            keydb: { color: 'text-purple-400', gradient: 'from-purple-500 to-purple-600', label: 'KeyDB' },
            dragonfly: { color: 'text-pink-400', gradient: 'from-pink-500 to-pink-600', label: 'Dragonfly' },
            clickhouse: { color: 'text-yellow-400', gradient: 'from-yellow-500 to-yellow-600', label: 'ClickHouse' },
        };
        return configs[type.toLowerCase()] || { color: 'text-foreground-muted', gradient: 'from-gray-500 to-gray-600', label: type };
    };

    const handleRunBackup = async () => {
        const confirmed = await confirm({
            title: 'Run Backup Now',
            description: `Start a backup for "${backup.database_name}" immediately?`,
            confirmText: 'Run Backup',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRunning(true);
            router.post(`/admin/backups/${backup.uuid}/run`, {}, {
                preserveScroll: true,
                onFinish: () => setIsRunning(false),
            });
        }
    };

    const handleRestore = (executionId: number) => {
        router.post(`/admin/backups/executions/${executionId}/restore`, {}, {
            preserveScroll: true,
        });
    };

    const handleDeleteExecution = (executionId: number) => {
        router.delete(`/admin/backups/executions/${executionId}`, {
            preserveScroll: true,
        });
    };

    const dbConfig = getDatabaseTypeConfig(backup.database_type);
    const successCount = executions.filter((e) => e.status === 'success').length;
    const failedCount = executions.filter((e) => e.status === 'failed').length;

    return (
        <AdminLayout
            title={`Backup: ${backup.database_name}`}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Backups', href: '/admin/backups' },
                { label: backup.database_name },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className={`flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br ${dbConfig.gradient} text-white`}>
                                <Database className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{backup.database_name}</h1>
                                    <Badge variant="default">{dbConfig.label}</Badge>
                                    {backup.save_s3 && (
                                        <Badge variant="primary" icon={<Cloud className="h-3 w-3" />}>S3 Enabled</Badge>
                                    )}
                                    {!backup.enabled && (
                                        <Badge variant="danger">Disabled</Badge>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    {backup.frequency} &middot; {backup.team_name}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="primary"
                            onClick={handleRunBackup}
                            disabled={isRunning}
                        >
                            <Play className={`h-4 w-4 ${isRunning ? 'animate-spin' : ''}`} />
                            {isRunning ? 'Running...' : 'Run Backup Now'}
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Executions</p>
                                    <p className="text-2xl font-bold text-primary">{executions.length}</p>
                                </div>
                                <HardDrive className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Successful</p>
                                    <p className="text-2xl font-bold text-success">{successCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed</p>
                                    <p className="text-2xl font-bold text-danger">{failedCount}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Keep Locally</p>
                                    <p className="text-2xl font-bold text-foreground">{backup.number_of_backups_locally}</p>
                                </div>
                                <FileArchive className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Schedule Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Schedule Configuration</CardTitle>
                        <CardDescription>Backup schedule settings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-center gap-3">
                                <Clock className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Frequency</p>
                                    <p className="font-medium text-foreground">{backup.frequency}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Server className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Server</p>
                                    <p className="font-medium text-foreground">{backup.server_name}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Database className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Database Type</p>
                                    <p className="font-medium text-foreground">{dbConfig.label}</p>
                                </div>
                            </div>
                            {backup.save_s3 && backup.s3_storage_name && (
                                <div className="flex items-center gap-3">
                                    <Cloud className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">S3 Storage</p>
                                        <p className="font-medium text-foreground">{backup.s3_storage_name}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <ShieldCheck className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Verification</p>
                                    <p className="font-medium text-foreground">
                                        {backup.verify_after_backup ? 'Enabled' : 'Disabled'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <TestTube className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Restore Tests</p>
                                    <p className="font-medium text-foreground">
                                        {backup.restore_test_enabled
                                            ? backup.restore_test_frequency || 'Enabled'
                                            : 'Disabled'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Created</p>
                                    <p className="font-medium text-foreground">
                                        {new Date(backup.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Executions */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Backup History ({executions.length})</CardTitle>
                        <CardDescription>Recent backup executions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {executions.length === 0 ? (
                            <div className="py-12 text-center">
                                <HardDrive className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No backup executions yet</p>
                                <p className="text-xs text-foreground-subtle">
                                    Run a backup to see execution history
                                </p>
                            </div>
                        ) : (
                            <div>
                                {executions.map((execution) => (
                                    <ExecutionRow
                                        key={execution.id}
                                        execution={execution}
                                        backupUuid={backup.uuid}
                                        onRestore={() => handleRestore(execution.id)}
                                        onDelete={() => handleDeleteExecution(execution.id)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Warning */}
                <Card variant="glass" className="mt-6">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 text-warning" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Restore Warning</p>
                                <p className="mt-1 text-xs text-foreground-muted">
                                    Restoring a backup will replace ALL current data in the database with the backup data.
                                    Make sure you understand the implications before proceeding with a restore operation.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
