import { useState, useEffect, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Checkbox, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { ArrowLeft, Plus, Download, RotateCcw, Trash2, Clock, HardDrive, Save, Loader2 } from 'lucide-react';
import type { StandaloneDatabase } from '@/types';
import { getStatusIcon, getStatusVariant, getStatusLabel } from '@/lib/statusUtils';

interface Props {
    database: StandaloneDatabase;
    backups?: Backup[];
    scheduledBackup?: ScheduledBackup | null;
}

interface Backup {
    id: number;
    filename: string;
    size: string;
    status: 'completed' | 'failed' | 'in_progress';
    created_at: string;
}

interface ScheduledBackup {
    uuid: string;
    enabled: boolean;
    frequency: string;
    databases_to_backup?: string;
    created_at: string;
    updated_at: string;
}

// Map frequency to cron expression
const frequencyToCron: Record<string, string> = {
    hourly: '0 * * * *',
    daily: '0 0 * * *',
    weekly: '0 0 * * 0',
};

// Map cron expression back to frequency label
function cronToFrequency(cron?: string): string {
    if (!cron) return 'daily';
    if (cron.includes('* * * *') && cron.startsWith('0 ')) return 'hourly';
    if (cron === '0 0 * * 0') return 'weekly';
    return 'daily';
}

export default function DatabaseBackups({ database, backups, scheduledBackup: initialScheduledBackup }: Props) {
    const [scheduledBackup, setScheduledBackup] = useState<ScheduledBackup | null>(initialScheduledBackup || null);
    const [autoBackupEnabled, setAutoBackupEnabled] = useState(initialScheduledBackup?.enabled ?? false);
    const [backupFrequency, setBackupFrequency] = useState(cronToFrequency(initialScheduledBackup?.frequency));
    const [isSaving, setIsSaving] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);
    const confirm = useConfirm();
    const { addToast } = useToast();

    // Load scheduled backup config on mount if not provided via props
    useEffect(() => {
        if (initialScheduledBackup === undefined) {
            fetch(`/api/v1/databases/${database.uuid}/backups`)
                .then(res => res.ok ? res.json() : [])
                .then((data: ScheduledBackup[]) => {
                    if (data && data.length > 0) {
                        const backup = data[0];
                        setScheduledBackup(backup);
                        setAutoBackupEnabled(backup.enabled);
                        setBackupFrequency(cronToFrequency(backup.frequency));
                    }
                })
                .catch(() => {
                    // Ignore errors, keep defaults
                });
        }
    }, [database.uuid, initialScheduledBackup]);

    // Track changes
    useEffect(() => {
        if (scheduledBackup) {
            const enabledChanged = autoBackupEnabled !== scheduledBackup.enabled;
            const frequencyChanged = frequencyToCron[backupFrequency] !== scheduledBackup.frequency &&
                                     cronToFrequency(scheduledBackup.frequency) !== backupFrequency;
            setHasChanges(enabledChanged || frequencyChanged);
        } else {
            // No existing config - changes exist if enabled
            setHasChanges(autoBackupEnabled);
        }
    }, [autoBackupEnabled, backupFrequency, scheduledBackup]);

    // Save backup schedule settings
    const handleSaveSchedule = useCallback(async () => {
        setIsSaving(true);
        try {
            const payload = {
                enabled: autoBackupEnabled,
                frequency: frequencyToCron[backupFrequency] || backupFrequency,
            };

            let response: Response;
            if (scheduledBackup?.uuid) {
                // Update existing config
                response = await fetch(`/api/v1/databases/${database.uuid}/backups/${scheduledBackup.uuid}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(payload),
                });
            } else {
                // Create new config
                response = await fetch(`/api/v1/databases/${database.uuid}/backups`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(payload),
                });
            }

            if (response.ok) {
                const data = await response.json();
                if (data.uuid) {
                    setScheduledBackup(prev => prev ? { ...prev, ...payload, uuid: data.uuid } : {
                        uuid: data.uuid,
                        enabled: autoBackupEnabled,
                        frequency: frequencyToCron[backupFrequency],
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    });
                }
                setHasChanges(false);
                addToast('success', 'Backup schedule saved successfully');
            } else {
                const error = await response.json();
                addToast('error', error.message || 'Failed to save backup schedule');
            }
        } catch {
            addToast('error', 'Failed to save backup schedule');
        } finally {
            setIsSaving(false);
        }
    }, [database.uuid, scheduledBackup, autoBackupEnabled, backupFrequency, addToast]);

    // Show loading state when backups data is not yet available
    if (!backups) {
        return (
            <AppLayout
                title={`${database.name} - Backups`}
                breadcrumbs={[
                    { label: 'Databases', href: '/databases' },
                    { label: database.name, href: `/databases/${database.uuid}` },
                    { label: 'Backups' }
                ]}
            >
                <div className="flex items-center justify-center p-12">
                    <div className="text-center">
                        <HardDrive className="mx-auto h-12 w-12 animate-pulse text-foreground-muted" />
                        <p className="mt-4 text-foreground-muted">Loading backups...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const handleCreateBackup = () => {
        router.post(`/databases/${database.uuid}/backups`);
    };

    const handleRestore = async (backupId: number) => {
        const confirmed = await confirm({
            title: 'Restore Backup',
            description: 'Are you sure you want to restore this backup? This will overwrite the current database.',
            confirmText: 'Restore',
            variant: 'warning',
        });
        if (confirmed) {
            router.post(`/databases/${database.uuid}/backups/${backupId}/restore`);
        }
    };

    const handleDownload = (backupId: number) => {
        window.location.href = `/databases/${database.uuid}/backups/${backupId}/download`;
    };

    const handleDelete = async (backupId: number) => {
        const confirmed = await confirm({
            title: 'Delete Backup',
            description: 'Are you sure you want to delete this backup?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/databases/${database.uuid}/backups/${backupId}`);
        }
    };

    return (
        <AppLayout
            title={`${database.name} - Backups`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Backups' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {database.name}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Backups</h1>
                    <p className="text-foreground-muted">Manage backups for {database.name}</p>
                </div>
                <Button onClick={handleCreateBackup}>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Backup
                </Button>
            </div>

            {/* Auto-backup Settings */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Automatic Backups</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Schedule automatic backups to run periodically
                            </p>
                        </div>
                        <Checkbox
                            checked={autoBackupEnabled}
                            onChange={(e) => setAutoBackupEnabled(e.target.checked)}
                        />
                    </div>

                    {autoBackupEnabled && (
                        <div className="mt-4 space-y-3">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Backup Frequency
                                </label>
                                <div className="flex gap-2">
                                    {['hourly', 'daily', 'weekly'].map((freq) => (
                                        <button
                                            key={freq}
                                            onClick={() => setBackupFrequency(freq)}
                                            className={`rounded-md border px-4 py-2 text-sm transition-colors ${
                                                backupFrequency === freq
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border bg-background-secondary text-foreground-muted hover:bg-background-tertiary'
                                            }`}
                                        >
                                            {freq.charAt(0).toUpperCase() + freq.slice(1)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <Clock className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm text-foreground-muted">
                                    {scheduledBackup
                                        ? `Backup runs ${backupFrequency}`
                                        : 'Save to schedule backups'}
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Save button - shown when there are changes */}
                    {hasChanges && (
                        <div className="mt-4 flex items-center justify-end gap-3 border-t border-border pt-4">
                            <span className="text-sm text-foreground-muted">Unsaved changes</span>
                            <Button onClick={handleSaveSchedule} disabled={isSaving}>
                                {isSaving ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Saving...
                                    </>
                                ) : (
                                    <>
                                        <Save className="mr-2 h-4 w-4" />
                                        Save Schedule
                                    </>
                                )}
                            </Button>
                        </div>
                    )}
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
                                onRestore={() => handleRestore(backup.id)}
                                onDownload={() => handleDownload(backup.id)}
                                onDelete={() => handleDelete(backup.id)}
                            />
                        ))}
                    </div>
                )}
            </div>
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
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                            {getStatusIcon(backup.status)}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-foreground">{backup.filename}</h3>
                                <Badge variant={getStatusVariant(backup.status)}>{getStatusLabel(backup.status)}</Badge>
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
