import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Checkbox, Input, Select, Modal, ModalFooter, useConfirm } from '@/components/ui';
import { ArrowLeft, Plus, Download, RotateCcw, Trash2, Clock, HardDrive, Calendar, Settings, RefreshCw } from 'lucide-react';
import { getStatusIcon, getStatusVariant, getStatusLabel } from '@/lib/statusUtils';

interface Backup {
    id: number;
    uuid?: string;
    filename: string;
    name?: string;
    size: string;
    status: 'completed' | 'failed' | 'in_progress' | 'running' | 'success' | 'unknown';
    enabled?: boolean;
    frequency?: string;
    lastStatus?: string;
    lastRun?: string;
    created_at: string;
}

interface Props {
    volumeId?: string;
    volumeName?: string;
    backups?: Backup[];
}

export default function StorageBackups({
    volumeId,
    volumeName = 'Storage',
    backups: initialBackups = []
}: Props) {
    const confirm = useConfirm();
    const [backups, setBackups] = useState<Backup[]>(initialBackups);
    const [loading, setLoading] = useState(!initialBackups.length);

    // Fetch backups if not provided via props
    useEffect(() => {
        if (initialBackups.length > 0) {
            setBackups(initialBackups);
            setLoading(false);
            return;
        }

        const fetchBackups = async () => {
            try {
                const url = volumeId
                    ? `/api/v1/storage/${volumeId}/backups`
                    : '/storage/backups/json';

                const response = await fetch(url, {
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    setBackups(Array.isArray(data) ? data : data.backups || []);
                }
            } catch (error) {
                console.error('[Saturn] Failed to fetch backups:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchBackups();
    }, [volumeId, initialBackups]);

    const handleRefresh = () => {
        router.reload({ only: ['backups'] });
    };
    const [autoBackupEnabled, setAutoBackupEnabled] = useState(true);
    const [backupFrequency, setBackupFrequency] = useState('daily');
    const [retentionDays, setRetentionDays] = useState('30');
    const [showRestoreModal, setShowRestoreModal] = useState(false);
    const [selectedBackup, setSelectedBackup] = useState<Backup | null>(null);

    const handleCreateBackup = () => {
        router.post(`/storage/${volumeId}/backups`);
    };

    const handleRestore = (backup: Backup) => {
        setSelectedBackup(backup);
        setShowRestoreModal(true);
    };

    const confirmRestore = () => {
        if (selectedBackup) {
            router.post(`/storage/${volumeId}/backups/${selectedBackup.id}/restore`);
            setShowRestoreModal(false);
            setSelectedBackup(null);
        }
    };

    const handleDownload = (backupId: number) => {
        window.location.href = `/storage/${volumeId}/backups/${backupId}/download`;
    };

    const handleDelete = async (backupId: number, filename: string) => {
        const confirmed = await confirm({
            title: 'Delete Backup',
            description: `Are you sure you want to delete ${filename}?`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/storage/${volumeId}/backups/${backupId}`);
        }
    };

    const handleSaveSchedule = () => {
        router.post(`/storage/${volumeId}/backup-schedule`, {
            enabled: autoBackupEnabled,
            frequency: backupFrequency,
            retention_days: retentionDays,
        });
    };

    const breadcrumbs = volumeId
        ? [
            { label: 'Storage', href: '/volumes' },
            { label: volumeName, href: `/volumes/${volumeId}` },
            { label: 'Backups' }
        ]
        : [
            { label: 'Storage', href: '/storage/backups' },
            { label: 'Backups' }
        ];

    return (
        <AppLayout
            title={volumeId ? `${volumeName} - Backups` : 'Storage Backups'}
            breadcrumbs={breadcrumbs}
        >
            {/* Back Button */}
            {volumeId && (
                <Link
                    href={`/volumes/${volumeId}`}
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to {volumeName}
                </Link>
            )}

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Storage Backups</h1>
                    <p className="text-foreground-muted">
                        {volumeId ? `Manage backups for ${volumeName}` : 'Scheduled database backups'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={handleRefresh} disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                    {volumeId && (
                        <Button onClick={handleCreateBackup}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Backup
                        </Button>
                    )}
                </div>
            </div>

            {/* Backup Schedule Configuration - only show for volume-specific backups */}
            {volumeId && (
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Settings className="h-5 w-5 text-foreground-muted" />
                            <div>
                                <h3 className="font-medium text-foreground">Backup Schedule</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Configure automatic backup schedule
                                </p>
                            </div>
                        </div>
                        <Checkbox
                            checked={autoBackupEnabled}
                            onChange={(e) => setAutoBackupEnabled(e.target.checked)}
                        />
                    </div>

                    {autoBackupEnabled && (
                        <div className="mt-6 space-y-4 border-t border-border pt-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        Backup Frequency
                                    </label>
                                    <Select
                                        value={backupFrequency}
                                        onChange={(e) => setBackupFrequency(e.target.value)}
                                        options={[
                                            { value: 'hourly', label: 'Every Hour' },
                                            { value: 'daily', label: 'Daily' },
                                            { value: 'weekly', label: 'Weekly' },
                                            { value: 'monthly', label: 'Monthly' },
                                        ]}
                                    />
                                </div>

                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        Retention Period (days)
                                    </label>
                                    <Input
                                        type="number"
                                        value={retentionDays}
                                        onChange={(e) => setRetentionDays(e.target.value)}
                                        placeholder="30"
                                        min="1"
                                    />
                                </div>
                            </div>

                            <div className="flex items-start gap-3 rounded-lg border border-border bg-background-secondary p-3">
                                <Calendar className="mt-0.5 h-4 w-4 text-foreground-muted" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">Next Backup Scheduled</p>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        {new Date(Date.now() + 24 * 60 * 60 * 1000).toLocaleString()}
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button onClick={handleSaveSchedule} size="sm">
                                    Save Schedule
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
            )}

            {/* Retention Policy - only show for volume-specific backups */}
            {volumeId && (
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="flex items-start gap-3">
                        <Clock className="mt-1 h-5 w-5 text-foreground-muted" />
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">Retention Policy</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Backups older than {retentionDays} days will be automatically deleted to save storage space.
                            </p>
                            <div className="mt-3 flex items-center gap-2 text-sm">
                                <Badge variant="info">Active</Badge>
                                <span className="text-foreground-muted">
                                    Currently retaining {backups.filter(b => b.status === 'completed' || b.status === 'success').length} backups
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
            )}

            {/* Backups List */}
            <div className="space-y-3">
                <h2 className="text-lg font-medium text-foreground">Backup History</h2>

                {loading ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <RefreshCw className="mx-auto h-8 w-8 animate-spin text-foreground-muted" />
                            <p className="mt-4 text-foreground-muted">Loading backups...</p>
                        </CardContent>
                    </Card>
                ) : backups.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <HardDrive className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No backups yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                {volumeId
                                    ? 'Create your first backup to get started'
                                    : 'Scheduled database backups will appear here'}
                            </p>
                            {volumeId && (
                                <Button onClick={handleCreateBackup} className="mt-6">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Backup
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {backups.map((backup) => (
                            <BackupCard
                                key={backup.id}
                                backup={backup}
                                onRestore={() => handleRestore(backup)}
                                onDownload={() => handleDownload(backup.id)}
                                onDelete={() => handleDelete(backup.id, backup.filename || backup.name || `Backup #${backup.id}`)}
                                showActions={!!volumeId}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Restore Confirmation Modal */}
            <Modal
                isOpen={showRestoreModal}
                onClose={() => setShowRestoreModal(false)}
                title="Restore Backup"
                description="Are you sure you want to restore this backup?"
            >
                {selectedBackup && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <p className="text-sm font-medium text-foreground">Backup Details</p>
                            <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                                <p>Name: {selectedBackup.filename || selectedBackup.name || `Backup #${selectedBackup.id}`}</p>
                                {selectedBackup.size && <p>Size: {selectedBackup.size}</p>}
                                <p>Created: {new Date(selectedBackup.created_at).toLocaleString()}</p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-warning/50 bg-warning/10 p-4">
                            <p className="text-sm font-medium text-warning">Warning</p>
                            <p className="mt-1 text-sm text-foreground-muted">
                                This will overwrite the current volume data. This action cannot be undone.
                            </p>
                        </div>

                        <ModalFooter>
                            <Button variant="secondary" onClick={() => setShowRestoreModal(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={confirmRestore}>
                                Restore Backup
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

function BackupCard({
    backup,
    onRestore,
    onDownload,
    onDelete,
    showActions = true,
}: {
    backup: Backup;
    onRestore: () => void;
    onDownload: () => void;
    onDelete: () => void;
    showActions?: boolean;
}) {
    // Support both filename and name fields
    const displayName = backup.filename || backup.name || `Backup #${backup.id}`;
    // Normalize status values
    const normalizedStatus = backup.status === 'success' ? 'completed' : backup.status;
    const isCompleted = normalizedStatus === 'completed';
    const isFailed = normalizedStatus === 'failed';

    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                            {getStatusIcon(normalizedStatus)}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-foreground">{displayName}</h3>
                                <Badge variant={getStatusVariant(normalizedStatus)}>{getStatusLabel(normalizedStatus)}</Badge>
                                {backup.enabled !== undefined && (
                                    <Badge variant={backup.enabled ? 'success' : 'secondary'} size="sm">
                                        {backup.enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                {backup.size && (
                                    <span className="flex items-center gap-1">
                                        <HardDrive className="h-3.5 w-3.5" />
                                        {backup.size}
                                    </span>
                                )}
                                {backup.frequency && (
                                    <span className="flex items-center gap-1">
                                        <Calendar className="h-3.5 w-3.5" />
                                        {backup.frequency}
                                    </span>
                                )}
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    {backup.lastRun
                                        ? new Date(backup.lastRun).toLocaleString()
                                        : new Date(backup.created_at).toLocaleString()}
                                </span>
                            </div>
                        </div>
                    </div>

                    {showActions && isCompleted && (
                        <div className="flex gap-2">
                            <Button variant="secondary" size="sm" onClick={onDownload}>
                                <Download className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                            <Button variant="secondary" size="sm" onClick={onRestore}>
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Restore
                            </Button>
                            <Button variant="danger" size="sm" onClick={onDelete}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    )}

                    {showActions && isFailed && (
                        <Button variant="danger" size="sm" onClick={onDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
