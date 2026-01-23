import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Tabs, useConfirm } from '@/components/ui';
import {
    Cloud,
    Settings,
    RefreshCw,
    Database,
    CheckCircle,
    XCircle,
    Server,
    HardDrive,
    Trash2,
    Edit,
    ExternalLink
} from 'lucide-react';
import type { S3Storage } from '@/types';

interface BackupInfo {
    id: number;
    uuid: string;
    database_name: string;
    database_type: string;
    filename: string;
    size: string;
    status: 'completed' | 'in_progress' | 'failed';
    created_at: string;
}

interface Props {
    storage: S3Storage;
    backups?: BackupInfo[];
    usageStats?: {
        totalBackups: number;
        totalSize: string;
        lastBackup: string | null;
    };
}

// Provider configuration for display
const providerInfo: Record<string, { name: string; color: string; icon: string }> = {
    aws: { name: 'AWS S3', color: 'bg-gradient-to-br from-orange-500 to-orange-600', icon: '🔶' },
    wasabi: { name: 'Wasabi', color: 'bg-gradient-to-br from-green-500 to-green-600', icon: '🌿' },
    backblaze: { name: 'Backblaze B2', color: 'bg-gradient-to-br from-red-500 to-red-600', icon: '🔴' },
    minio: { name: 'MinIO', color: 'bg-gradient-to-br from-pink-500 to-pink-600', icon: '🪣' },
    custom: { name: 'Custom S3', color: 'bg-gradient-to-br from-blue-500 to-blue-600', icon: '☁️' },
};

// Detect provider from endpoint
function detectProvider(storage: S3Storage): string {
    const endpoint = storage.endpoint?.toLowerCase() || '';
    if (!endpoint || endpoint.includes('amazonaws.com')) return 'aws';
    if (endpoint.includes('wasabi')) return 'wasabi';
    if (endpoint.includes('backblaze')) return 'backblaze';
    if (endpoint.includes('minio')) return 'minio';
    return 'custom';
}

export default function StorageShow({ storage, backups = [], usageStats }: Props) {
    const confirm = useConfirm();
    const provider = detectProvider(storage);
    const info = providerInfo[provider];

    const tabs = [
        {
            label: 'Overview',
            content: <OverviewTab storage={storage} info={info} />,
        },
        {
            label: 'Backups',
            content: <BackupsTab backups={backups} />,
        },
        {
            label: 'Usage',
            content: <UsageTab usageStats={usageStats} />,
        },
        {
            label: 'Settings',
            content: <SettingsTab storage={storage} />,
        },
    ];

    return (
        <AppLayout
            title={storage.name}
            breadcrumbs={[
                { label: 'Storage', href: '/storage' },
                { label: storage.name },
            ]}
        >
            {/* Storage Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${info.color} text-white text-2xl`}>
                        {info.icon}
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-foreground">{storage.name}</h1>
                            {storage.is_usable ? (
                                <Badge variant="success">Connected</Badge>
                            ) : (
                                <Badge variant="danger">Connection Failed</Badge>
                            )}
                        </div>
                        <p className="text-foreground-muted">
                            {info.name} • {storage.bucket}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => router.post(`/storage/${storage.uuid}/test`)}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Test Connection
                    </Button>
                    <Link href={`/storage/${storage.uuid}/edit`}>
                        <Button variant="secondary" size="sm">
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={async () => {
                            const confirmed = await confirm({
                                title: 'Delete Storage',
                                description: 'Are you sure you want to delete this storage? Backups will remain in the bucket.',
                                confirmText: 'Delete',
                                variant: 'danger',
                            });
                            if (confirmed) {
                                router.delete(`/storage/${storage.uuid}`);
                            }
                        }}
                    >
                        <Trash2 className="h-4 w-4 text-danger" />
                    </Button>
                </div>
            </div>

            {/* Tabs */}
            <Tabs tabs={tabs} />
        </AppLayout>
    );
}

function OverviewTab({ storage, info }: { storage: S3Storage; info: typeof providerInfo.aws }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {/* Connection Status */}
            <Card>
                <CardHeader>
                    <CardTitle>Connection Status</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <InfoRow
                        label="Status"
                        value={
                            storage.is_usable ? (
                                <div className="flex items-center gap-1.5 text-success">
                                    <CheckCircle className="h-4 w-4" />
                                    <span>Connected</span>
                                </div>
                            ) : (
                                <div className="flex items-center gap-1.5 text-danger">
                                    <XCircle className="h-4 w-4" />
                                    <span>Connection Failed</span>
                                </div>
                            )
                        }
                    />
                    <InfoRow label="Provider" value={info.name} />
                    <InfoRow label="Bucket" value={storage.bucket} />
                    <InfoRow label="Region" value={storage.region} />
                    {storage.endpoint && (
                        <InfoRow
                            label="Endpoint"
                            value={
                                <a
                                    href={storage.endpoint}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1 text-primary hover:underline"
                                >
                                    <span className="truncate">{storage.endpoint}</span>
                                    <ExternalLink className="h-3 w-3 shrink-0" />
                                </a>
                            }
                        />
                    )}
                    {storage.path && <InfoRow label="Path" value={storage.path} />}
                </CardContent>
            </Card>

            {/* Storage Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Storage Information</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {storage.description && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                Description
                            </label>
                            <p className="text-sm text-foreground">{storage.description}</p>
                        </div>
                    )}
                    <InfoRow
                        label="Created"
                        value={new Date(storage.created_at).toLocaleDateString()}
                    />
                    <InfoRow
                        label="Last Updated"
                        value={new Date(storage.updated_at).toLocaleDateString()}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function BackupsTab({ backups }: { backups: BackupInfo[] }) {
    if (backups.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-16">
                    <Database className="h-12 w-12 text-foreground-subtle" />
                    <h3 className="mt-4 font-medium text-foreground">No backups yet</h3>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Backups using this storage will appear here
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="p-0">
                <div className="divide-y divide-border">
                    {backups.map((backup) => (
                        <div key={backup.id} className="p-4 hover:bg-background-secondary">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Database className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="font-medium text-foreground">{backup.database_name}</p>
                                        <p className="text-sm text-foreground-muted">
                                            {backup.filename} • {backup.size}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Badge
                                        variant={
                                            backup.status === 'completed'
                                                ? 'success'
                                                : backup.status === 'failed'
                                                  ? 'danger'
                                                  : 'default'
                                        }
                                    >
                                        {backup.status}
                                    </Badge>
                                    <span className="text-sm text-foreground-subtle">
                                        {new Date(backup.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function UsageTab({ usageStats }: { usageStats?: Props['usageStats'] }) {
    return (
        <div className="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Database className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">Total Backups</p>
                            <p className="text-2xl font-bold text-foreground">
                                {usageStats?.totalBackups || 0}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                            <HardDrive className="h-5 w-5 text-info" />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">Total Size</p>
                            <p className="text-2xl font-bold text-foreground">
                                {usageStats?.totalSize || '0 GB'}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                            <CheckCircle className="h-5 w-5 text-success" />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">Last Backup</p>
                            <p className="text-2xl font-bold text-foreground">
                                {usageStats?.lastBackup
                                    ? new Date(usageStats.lastBackup).toLocaleDateString()
                                    : 'Never'}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function SettingsTab({ storage }: { storage: S3Storage }) {
    const confirm = useConfirm();

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Storage Settings</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-foreground-muted">
                        Configure storage settings, retention policies, and encryption options.
                    </p>
                    <Link href={`/storage/settings`} className="mt-4 inline-block">
                        <Button>Open Storage Settings</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-danger">Danger Zone</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-foreground-muted mb-4">
                        Delete this storage configuration. Backups will remain in the S3 bucket.
                    </p>
                    <Button
                        variant="danger"
                        onClick={async () => {
                            const confirmed = await confirm({
                                title: 'Delete Storage',
                                description: 'Are you sure you want to delete this storage? This action cannot be undone.',
                                confirmText: 'Delete',
                                variant: 'danger',
                            });
                            if (confirmed) {
                                router.delete(`/storage/${storage.uuid}`);
                            }
                        }}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete Storage
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm text-foreground text-right">{value}</span>
        </div>
    );
}
