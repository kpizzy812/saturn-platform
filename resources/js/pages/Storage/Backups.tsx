import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Checkbox, Input, Select, Modal, ModalFooter, useConfirm } from '@/components/ui';
import { ArrowLeft, Plus, Download, RotateCcw, Trash2, Clock, HardDrive, CheckCircle2, XCircle, AlertCircle, Calendar, Settings } from 'lucide-react';

interface Props {
    volumeId?: string;
    volumeName?: string;
}

interface Backup {
    id: number;
    filename: string;
    size: string;
    status: 'completed' | 'failed' | 'in_progress';
    created_at: string;
}

// Mock backups data
const mockBackups: Backup[] = [
    {
        id: 1,
        filename: 'volume-backup-2024-01-20-103045.tar.gz',
        size: '1.2 GB',
        status: 'completed',
        created_at: '2024-01-20T10:30:45Z',
    },
    {
        id: 2,
        filename: 'volume-backup-2024-01-19-103012.tar.gz',
        size: '1.15 GB',
        status: 'completed',
        created_at: '2024-01-19T10:30:12Z',
    },
    {
        id: 3,
        filename: 'volume-backup-2024-01-18-103008.tar.gz',
        size: '1.18 GB',
        status: 'completed',
        created_at: '2024-01-18T10:30:08Z',
    },
    {
        id: 4,
        filename: 'volume-backup-2024-01-17-103000.tar.gz',
        size: '0 MB',
        status: 'failed',
        created_at: '2024-01-17T10:30:00Z',
    },
    {
        id: 5,
        filename: 'volume-backup-2024-01-16-103034.tar.gz',
        size: '1.12 GB',
        status: 'completed',
        created_at: '2024-01-16T10:30:34Z',
    },
];

export default function StorageBackups({ volumeId = 'vol_123', volumeName = 'app-data' }: Props) {
    const confirm = useConfirm();
    const [backups] = useState<Backup[]>(mockBackups);
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

    return (
        <AppLayout
            title={`${volumeName} - Backups`}
            breadcrumbs={[
                { label: 'Storage', href: '/volumes' },
                { label: volumeName, href: `/volumes/${volumeId}` },
                { label: 'Backups' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/volumes/${volumeId}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {volumeName}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Storage Backups</h1>
                    <p className="text-foreground-muted">Manage backups for {volumeName}</p>
                </div>
                <Button onClick={handleCreateBackup}>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Backup
                </Button>
            </div>

            {/* Backup Schedule Configuration */}
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

            {/* Retention Policy */}
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
                                    Currently retaining {backups.filter(b => b.status === 'completed').length} backups
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Backups List */}
            <div className="space-y-3">
                <h2 className="text-lg font-medium text-foreground">Backup History</h2>

                {backups.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <HardDrive className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No backups yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Create your first backup to get started
                            </p>
                            <Button onClick={handleCreateBackup} className="mt-6">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Backup
                            </Button>
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
                                onDelete={() => handleDelete(backup.id, backup.filename)}
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
                                <p>File: {selectedBackup.filename}</p>
                                <p>Size: {selectedBackup.size}</p>
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
}: {
    backup: Backup;
    onRestore: () => void;
    onDownload: () => void;
    onDelete: () => void;
}) {
    const getStatusIcon = () => {
        switch (backup.status) {
            case 'completed':
                return <CheckCircle2 className="h-5 w-5 text-green-500" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-red-500" />;
            case 'in_progress':
                return <AlertCircle className="h-5 w-5 text-yellow-500" />;
        }
    };

    const getStatusBadge = () => {
        switch (backup.status) {
            case 'completed':
                return <Badge variant="success">Completed</Badge>;
            case 'failed':
                return <Badge variant="danger">Failed</Badge>;
            case 'in_progress':
                return <Badge variant="warning">In Progress</Badge>;
        }
    };

    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                            {getStatusIcon()}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-foreground">{backup.filename}</h3>
                                {getStatusBadge()}
                            </div>
                            <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                <span className="flex items-center gap-1">
                                    <HardDrive className="h-3.5 w-3.5" />
                                    {backup.size}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    {new Date(backup.created_at).toLocaleString()}
                                </span>
                            </div>
                        </div>
                    </div>

                    {backup.status === 'completed' && (
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

                    {backup.status === 'failed' && (
                        <Button variant="danger" size="sm" onClick={onDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
