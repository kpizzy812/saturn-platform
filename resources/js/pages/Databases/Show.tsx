import { useState, useMemo } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { getStatusLabel, getStatusVariant } from '@/lib/statusUtils';
import { ArrowLeft, Copy, Eye, EyeOff, RotateCw, Download, Upload, Trash2, Server, HardDrive, Activity, Loader2, ArrowRightLeft } from 'lucide-react';
import { BrandIcon } from '@/components/ui/BrandIcon';
import { TransferModal } from '@/components/transfer';
import { useDatabaseMetrics, formatMetricValue } from '@/hooks';
import type { StandaloneDatabase, DatabaseType } from '@/types';
import { PostgreSQLPanel } from '@/components/features/databases/PostgreSQLPanel';
import { MySQLPanel } from '@/components/features/databases/MySQLPanel';
import { MongoDBPanel } from '@/components/features/databases/MongoDBPanel';
import { RedisPanel } from '@/components/features/databases/RedisPanel';
import { ClickHousePanel } from '@/components/features/databases/ClickHousePanel';

interface Props {
    database: StandaloneDatabase;
}

// Get initial tab index from URL parameter
function getInitialTabFromUrl(databaseType: DatabaseType): number {
    if (typeof window === 'undefined') return 0;

    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab')?.toLowerCase();

    if (!tabParam) return 0;

    // Redis-like databases have: Overview, Keys, Settings, Logs
    const isRedisLike = ['redis', 'keydb', 'dragonfly'].includes(databaseType);
    if (isRedisLike) {
        const redisTabMap: Record<string, number> = {
            'overview': 0,
            'keys': 1,
            'settings': 2,
            'logs': 3,
        };
        return redisTabMap[tabParam] ?? 0;
    }

    // SQL databases have different tab structures
    // PostgreSQL: Overview, Query, Tables, Settings, Logs
    const sqlTabMap: Record<string, number> = {
        'overview': 0,
        'query': 1,
        'tables': 2,
        'settings': 3,
        'logs': 4,
    };
    return sqlTabMap[tabParam] ?? 0;
}

const databaseTypeConfig: Record<DatabaseType, { color: string; bgColor: string; displayName: string }> = {
    postgresql: { color: 'text-info', bgColor: 'bg-info/10', displayName: 'PostgreSQL' },
    mysql: { color: 'text-warning', bgColor: 'bg-warning/10', displayName: 'MySQL' },
    mariadb: { color: 'text-warning', bgColor: 'bg-warning/10', displayName: 'MariaDB' },
    mongodb: { color: 'text-success', bgColor: 'bg-success/10', displayName: 'MongoDB' },
    redis: { color: 'text-danger', bgColor: 'bg-danger/10', displayName: 'Redis' },
    keydb: { color: 'text-danger', bgColor: 'bg-danger/10', displayName: 'KeyDB' },
    dragonfly: { color: 'text-primary', bgColor: 'bg-primary/10', displayName: 'Dragonfly' },
    clickhouse: { color: 'text-warning', bgColor: 'bg-warning/10', displayName: 'ClickHouse' },
};

export default function DatabaseShow({ database }: Props) {
    const config = databaseTypeConfig[database.database_type] || databaseTypeConfig.postgresql;
    const [isRestarting, setIsRestarting] = useState(false);
    const [currentStatus, setCurrentStatus] = useState(database.status);
    const [showTransferModal, setShowTransferModal] = useState(false);
    const { addToast } = useToast();

    // Real-time database status updates
    const { isConnected: _isConnected } = useRealtimeStatus({
        onDatabaseStatusChange: (data) => {
            // Update database status when WebSocket event arrives
            if (data.databaseId === database.id) {
                // Handle both string and object status formats
                const newStatus = typeof data.status === 'string'
                    ? { state: data.status, health: 'unknown' }
                    : data.status as { state: string; health: string };
                setCurrentStatus(newStatus);
            }
        },
    });

    const handleRestart = () => {
        setIsRestarting(true);
        router.post(`/databases/${database.uuid}/restart`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setIsRestarting(false);
                addToast('success', 'Database restart initiated');
            },
            onError: () => {
                setIsRestarting(false);
                addToast('error', 'Failed to restart database');
            },
        });
    };

    // Get initial tab index from URL
    const initialTab = useMemo(() => getInitialTabFromUrl(database.database_type), [database.database_type]);

    // Get database-specific panel based on type
    const getDatabasePanel = (type: DatabaseType) => {
        switch (type) {
            case 'postgresql':
                return <PostgreSQLPanel database={database} initialTab={initialTab} />;
            case 'mysql':
            case 'mariadb':
                return <MySQLPanel database={database} initialTab={initialTab} />;
            case 'mongodb':
                return <MongoDBPanel database={database} initialTab={initialTab} />;
            case 'redis':
            case 'keydb':
            case 'dragonfly':
                return <RedisPanel database={database} initialTab={initialTab} />;
            case 'clickhouse':
                return <ClickHousePanel database={database} initialTab={initialTab} />;
            default:
                // Fallback to generic tabs for unsupported types
                return <Tabs tabs={[
                    { label: 'Connection', content: <ConnectionTab database={database} /> },
                    { label: 'Metrics', content: <MetricsTab database={database} /> },
                    { label: 'Backups', content: <BackupsTab database={database} /> },
                    { label: 'Settings', content: <SettingsTab database={database} /> },
                ]} defaultIndex={initialTab} />;
        }
    };

    return (
        <AppLayout
            title={database.name}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name }
            ]}
        >
            {/* Back Button */}
            <Link
                href="/databases"
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Databases
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${config.bgColor} ${config.color}`}>
                        <BrandIcon name={database.database_type} className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">{database.name}</h1>
                        <div className="flex items-center gap-2">
                            <Badge variant="default">{config.displayName}</Badge>
                            <StatusBadge status={typeof currentStatus === 'object' ? currentStatus.state : currentStatus} />
                        </div>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary" size="sm" onClick={handleRestart} disabled={isRestarting}>
                        <RotateCw className={`mr-2 h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                        {isRestarting ? 'Restarting...' : 'Restart'}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => setShowTransferModal(true)}>
                        <ArrowRightLeft className="mr-2 h-4 w-4" />
                        Transfer
                    </Button>
                    <Link href={`/databases/${database.uuid}/import`}>
                        <Button variant="secondary" size="sm">
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                    </Link>
                    <Link href={`/databases/${database.uuid}/backups`}>
                        <Button variant="secondary" size="sm">
                            <Download className="mr-2 h-4 w-4" />
                            Backups
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Database-specific panel */}
            {getDatabasePanel(database.database_type)}

            {/* Transfer Modal */}
            <TransferModal
                isOpen={showTransferModal}
                onClose={() => setShowTransferModal(false)}
                database={database}
            />
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: string | { state: string; health: string } }) {
    const statusValue = typeof status === 'object' ? status.state : status;
    return <Badge variant={getStatusVariant(statusValue)}>{getStatusLabel(statusValue)}</Badge>;
}

function ConnectionTab({ database }: { database: StandaloneDatabase }) {
    const [showPassword, setShowPassword] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);

    // Connection details from backend
    const connectionDetails = {
        host: database.connection?.internal_host || database.internal_db_url?.split('@')[1]?.split(':')[0] || '',
        port: database.connection?.port || (database.database_type === 'postgresql' ? '5432' : database.database_type === 'mysql' || database.database_type === 'mariadb' ? '3306' : database.database_type === 'mongodb' ? '27017' : '6379'),
        database: database.connection?.database || database.name,
        username: database.connection?.username || '',
        password: database.connection?.password || '',
        connectionString: database.internal_db_url || '',
    };

    const copyToClipboard = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        setTimeout(() => setCopiedField(null), 2000);
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection String</h3>
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                        <code className="flex-1 font-mono text-sm text-foreground">
                            {connectionDetails.connectionString}
                        </code>
                        <button
                            onClick={() => copyToClipboard(connectionDetails.connectionString, 'connectionString')}
                            className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                    {copiedField === 'connectionString' && (
                        <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection Details</h3>
                    <div className="space-y-3">
                        <ConnectionField
                            label="Host"
                            value={connectionDetails.host}
                            onCopy={() => copyToClipboard(connectionDetails.host, 'host')}
                            copied={copiedField === 'host'}
                        />
                        <ConnectionField
                            label="Port"
                            value={connectionDetails.port}
                            onCopy={() => copyToClipboard(connectionDetails.port, 'port')}
                            copied={copiedField === 'port'}
                        />
                        <ConnectionField
                            label="Database"
                            value={connectionDetails.database}
                            onCopy={() => copyToClipboard(connectionDetails.database, 'database')}
                            copied={copiedField === 'database'}
                        />
                        <ConnectionField
                            label="Username"
                            value={connectionDetails.username}
                            onCopy={() => copyToClipboard(connectionDetails.username, 'username')}
                            copied={copiedField === 'username'}
                        />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                Password
                            </label>
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <code className="flex-1 font-mono text-sm text-foreground">
                                    {showPassword ? connectionDetails.password : '••••••••••••••••••••'}
                                </code>
                                <button
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => copyToClipboard(connectionDetails.password, 'password')}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            {copiedField === 'password' && (
                                <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function ConnectionField({ label, value, onCopy, copied }: { label: string; value: string; onCopy: () => void; copied: boolean }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                {label}
            </label>
            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                <code className="flex-1 font-mono text-sm text-foreground">{value}</code>
                <button
                    onClick={onCopy}
                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                >
                    <Copy className="h-4 w-4" />
                </button>
            </div>
            {copied && (
                <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
            )}
        </div>
    );
}

function MetricsTab({ database }: { database: StandaloneDatabase }) {
    const { metrics: dbMetrics, isLoading } = useDatabaseMetrics({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const metricsObj = dbMetrics as Record<string, unknown> | null;

    const metricsDisplay = [
        {
            label: 'Active Connections',
            value: isLoading ? '...' : formatMetricValue(metricsObj?.activeConnections as number | null | undefined),
            icon: Server,
            color: 'text-info',
            bgColor: 'bg-info/10',
        },
        {
            label: 'Database Size',
            value: isLoading ? '...' : ((metricsObj?.databaseSize as string) || 'N/A'),
            icon: HardDrive,
            color: 'text-success',
            bgColor: 'bg-success/10',
        },
        {
            label: 'Queries/sec',
            value: isLoading ? '...' : formatMetricValue(metricsObj?.queriesPerSec as number | null | undefined),
            icon: Activity,
            color: 'text-primary',
            bgColor: 'bg-primary/10',
        },
    ];

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                {metricsDisplay.map((metric) => (
                    <Card key={metric.label}>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-3">
                                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${metric.bgColor}`}>
                                    <metric.icon className={`h-5 w-5 ${metric.color}`} />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">{metric.label}</p>
                                    <p className="text-2xl font-bold text-foreground">{metric.value}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {isLoading && (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-12">
                        <Loader2 className="h-8 w-8 animate-spin text-foreground-muted" />
                        <p className="mt-3 text-sm text-foreground-muted">Loading metrics...</p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function BackupsTab({ database }: { database: StandaloneDatabase }) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
                <Download className="h-12 w-12 text-foreground-subtle" />
                <h3 className="mt-4 font-medium text-foreground">No backups configured</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    Configure automated backups to protect your data
                </p>
                <Link href={`/databases/${database.uuid}/backups`} className="mt-6 inline-block">
                    <Button>
                        <Download className="mr-2 h-4 w-4" />
                        Configure Backups
                    </Button>
                </Link>
            </CardContent>
        </Card>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Database',
            description: `Are you sure you want to delete ${database.name}? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/databases/${database.uuid}`);
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">General</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Name</label>
                            <p className="text-sm text-foreground">{database.name}</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Type</label>
                            <p className="text-sm text-foreground">
                                {databaseTypeConfig[database.database_type]?.displayName || database.database_type}
                            </p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Created</label>
                            <p className="text-sm text-foreground">
                                {new Date(database.created_at).toLocaleString()}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-3 text-lg font-medium text-danger">Danger Zone</h3>
                    <p className="mb-4 text-sm text-foreground-muted">
                        Once you delete a database, there is no going back. Please be certain.
                    </p>
                    <Button variant="danger" size="sm" onClick={handleDelete}>
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete Database
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}
