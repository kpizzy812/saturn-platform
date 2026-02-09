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
    Shield,
    ShieldCheck,
    ShieldX,
    TestTube,
    DollarSign,
} from 'lucide-react';

interface BackupExecution {
    id: number;
    uuid: string;
    status: 'success' | 'failed' | 'running';
    size?: number;
    filename?: string;
    message?: string;
    verification_status?: 'pending' | 'verified' | 'failed' | 'skipped';
    restore_test_status?: 'pending' | 'success' | 'failed' | 'skipped';
    s3_integrity_status?: 'pending' | 'verified' | 'failed';
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
    backups: {
        data: BackupSchedule[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total: number;
        enabled: number;
        with_s3: number;
        failed_last_24h: number;
        verified_last_24h: number;
        verification_failed_last_24h: number;
        restore_test_enabled_count: number;
        restore_tests_passed: number;
        restore_tests_failed: number;
        total_storage_local: number;
        total_storage_s3: number;
        estimated_monthly_cost: number;
    };
    databaseTypes: string[];
    filters: {
        search: string;
        type: string;
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

    const getVerificationConfig = (status?: string) => {
        switch (status) {
            case 'verified':
                return { variant: 'success' as const, label: 'Verified', icon: ShieldCheck };
            case 'failed':
                return { variant: 'danger' as const, label: 'Verification Failed', icon: ShieldX };
            case 'pending':
                return { variant: 'warning' as const, label: 'Verifying...', icon: Shield };
            case 'skipped':
                return { variant: 'default' as const, label: 'Skipped', icon: Shield };
            default:
                return null;
        }
    };

    const getRestoreTestConfig = (status?: string) => {
        switch (status) {
            case 'success':
                return { variant: 'success' as const, label: 'Restore OK', icon: TestTube };
            case 'failed':
                return { variant: 'danger' as const, label: 'Restore Failed', icon: TestTube };
            case 'pending':
                return { variant: 'warning' as const, label: 'Testing...', icon: TestTube };
            case 'skipped':
                return { variant: 'default' as const, label: 'Not Tested', icon: TestTube };
            default:
                return null;
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
                        <div className="mt-2 space-y-1">
                            <div className="flex items-center gap-4 text-xs text-foreground-muted">
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
                            {/* Verification & Restore Test Status */}
                            <div className="flex items-center gap-2">
                                {(() => {
                                    const verifyConfig = getVerificationConfig(backup.last_execution.verification_status);
                                    if (verifyConfig) {
                                        const Icon = verifyConfig.icon;
                                        return (
                                            <Badge variant={verifyConfig.variant} size="sm">
                                                <Icon className="mr-1 h-3 w-3" />
                                                {verifyConfig.label}
                                            </Badge>
                                        );
                                    }
                                    return null;
                                })()}
                                {(() => {
                                    const restoreConfig = getRestoreTestConfig(backup.last_execution.restore_test_status);
                                    if (restoreConfig && backup.last_execution.restore_test_status !== 'skipped') {
                                        const Icon = restoreConfig.icon;
                                        return (
                                            <Badge variant={restoreConfig.variant} size="sm">
                                                <Icon className="mr-1 h-3 w-3" />
                                                {restoreConfig.label}
                                            </Badge>
                                        );
                                    }
                                    return null;
                                })()}
                                {backup.last_execution.s3_integrity_status === 'verified' && (
                                    <Badge variant="success" size="sm">
                                        <Cloud className="mr-1 h-3 w-3" />
                                        S3 OK
                                    </Badge>
                                )}
                                {backup.last_execution.s3_integrity_status === 'failed' && (
                                    <Badge variant="danger" size="sm">
                                        <Cloud className="mr-1 h-3 w-3" />
                                        S3 Failed
                                    </Badge>
                                )}
                            </div>
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

function formatStorageSize(bytes: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    return `${size.toFixed(1)} ${units[unitIndex]}`;
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function AdminBackupsIndex({ backups: paginatedBackups, stats, databaseTypes: serverDbTypes, filters }: Props) {
    const [searchQuery, setSearchQuery] = React.useState(filters?.search ?? '');
    const [typeFilter, setTypeFilter] = React.useState<string>(filters?.type || 'all');
    const [showAdvancedStats, setShowAdvancedStats] = React.useState(false);

    const items = paginatedBackups?.data ?? [];
    const databaseTypes = (serverDbTypes ?? []).map((t) => t.toLowerCase());

    // Server-side search with debounce
    const applyFilters = (newFilters: { search?: string; type?: string }) => {
        const params: Record<string, string> = {};
        const merged = {
            search: filters?.search ?? '',
            type: filters?.type ?? '',
            ...newFilters,
        };
        if (merged.search) params.search = merged.search;
        if (merged.type && merged.type !== 'all') params.type = merged.type;

        router.get('/admin/backups', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters?.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const handleTypeChange = (type: string) => {
        setTypeFilter(type);
        applyFilters({ type: type === 'all' ? '' : type });
    };

    const handleRunBackup = (uuid: string) => {
        router.post(`/admin/backups/${uuid}/run`, {}, {
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        const params: Record<string, string> = { page: String(page) };
        if (filters?.search) params.search = filters.search;
        if (filters?.type) params.type = filters.type;
        router.get('/admin/backups', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

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

                {/* Basic Stats */}
                <div className="mb-4 grid gap-4 sm:grid-cols-4">
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

                {/* Advanced Stats Toggle */}
                <div className="mb-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowAdvancedStats(!showAdvancedStats)}
                        className="text-foreground-muted hover:text-foreground"
                    >
                        {showAdvancedStats ? 'Hide' : 'Show'} Advanced Stats
                        <RefreshCw className={`ml-2 h-4 w-4 transition-transform ${showAdvancedStats ? 'rotate-180' : ''}`} />
                    </Button>
                </div>

                {/* Advanced Stats */}
                {showAdvancedStats && (
                    <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Verification Stats (24h) */}
                        <Card variant="glass">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-foreground-subtle">Verified (24h)</p>
                                        <p className="text-2xl font-bold text-success">{stats?.verified_last_24h ?? 0}</p>
                                        {(stats?.verification_failed_last_24h ?? 0) > 0 && (
                                            <p className="text-xs text-danger">{stats.verification_failed_last_24h} failed</p>
                                        )}
                                    </div>
                                    <ShieldCheck className="h-8 w-8 text-success/50" />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Restore Tests */}
                        <Card variant="glass">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-foreground-subtle">Restore Tests</p>
                                        <div className="flex items-baseline gap-2">
                                            <p className="text-2xl font-bold text-success">{stats?.restore_tests_passed ?? 0}</p>
                                            {(stats?.restore_tests_failed ?? 0) > 0 && (
                                                <p className="text-sm text-danger">/ {stats.restore_tests_failed} failed</p>
                                            )}
                                        </div>
                                        <p className="text-xs text-foreground-muted">
                                            {stats?.restore_test_enabled_count ?? 0} enabled
                                        </p>
                                    </div>
                                    <TestTube className="h-8 w-8 text-info/50" />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Storage Usage */}
                        <Card variant="glass">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-foreground-subtle">Total Storage</p>
                                        <p className="text-lg font-bold text-foreground">
                                            {formatStorageSize(stats?.total_storage_local ?? 0)}
                                        </p>
                                        <p className="text-xs text-foreground-muted">
                                            S3: {formatStorageSize(stats?.total_storage_s3 ?? 0)}
                                        </p>
                                    </div>
                                    <Server className="h-8 w-8 text-foreground-muted/50" />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Estimated Cost */}
                        <Card variant="glass">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-foreground-subtle">Est. Monthly Cost</p>
                                        <p className="text-2xl font-bold text-warning">
                                            {formatCurrency(stats?.estimated_monthly_cost ?? 0)}
                                        </p>
                                        <p className="text-xs text-foreground-muted">S3 storage</p>
                                    </div>
                                    <DollarSign className="h-8 w-8 text-warning/50" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

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
                                    onClick={() => handleTypeChange('all')}
                                >
                                    All
                                </Button>
                                {databaseTypes.map((type) => (
                                    <Button
                                        key={type}
                                        variant={typeFilter === type ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleTypeChange(type)}
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
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Backup Schedules ({paginatedBackups?.total ?? 0})</CardTitle>
                                <CardDescription>Scheduled database backups across all databases</CardDescription>
                            </div>
                            {paginatedBackups?.last_page > 1 && (
                                <p className="text-sm text-foreground-muted">
                                    Page {paginatedBackups.current_page} of {paginatedBackups.last_page}
                                </p>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <HardDrive className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">
                                    {!filters?.search && !filters?.type ? 'No backup schedules configured' : 'No matching backups'}
                                </p>
                                <p className="text-xs text-foreground-subtle">
                                    {!filters?.search && !filters?.type
                                        ? 'Configure backups for your databases to see them here'
                                        : 'Try adjusting your search or filters'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((backup) => (
                                    <BackupRow
                                        key={backup.id}
                                        backup={backup}
                                        onRunNow={() => handleRunBackup(backup.uuid)}
                                    />
                                ))}
                            </div>
                        )}

                        {/* Pagination */}
                        {paginatedBackups?.last_page > 1 && (
                            <div className="mt-6 flex items-center justify-center gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => handlePageChange(paginatedBackups.current_page - 1)}
                                    disabled={paginatedBackups.current_page <= 1}
                                >
                                    Previous
                                </Button>
                                <span className="px-3 text-sm text-foreground-muted">
                                    {paginatedBackups.current_page} / {paginatedBackups.last_page}
                                </span>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => handlePageChange(paginatedBackups.current_page + 1)}
                                    disabled={paginatedBackups.current_page >= paginatedBackups.last_page}
                                >
                                    Next
                                </Button>
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
