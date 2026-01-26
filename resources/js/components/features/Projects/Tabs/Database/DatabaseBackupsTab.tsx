import { useState } from 'react';
import { Button } from '@/components/ui';
import { Plus, RefreshCw, Clock, HardDrive } from 'lucide-react';
import { useDatabaseBackups } from '@/hooks/useDatabases';
import type { SelectedService } from '../../types';

interface DatabaseBackupsTabProps {
    service: SelectedService;
}

export function DatabaseBackupsTab({ service }: DatabaseBackupsTabProps) {
    const { backups, isLoading, error, createBackup, deleteBackup, restoreBackup, refetch } = useDatabaseBackups({
        databaseUuid: service.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const [isCreating, setIsCreating] = useState(false);
    const [restoringBackupUuid, setRestoringBackupUuid] = useState<string | null>(null);

    const handleCreateBackup = async () => {
        setIsCreating(true);
        try {
            await createBackup();
            alert('Backup creation started');
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to create backup');
        } finally {
            setIsCreating(false);
        }
    };

    const handleScheduleBackup = () => {
        alert('Backup scheduling modal coming soon. Configure in database settings.');
    };

    const handleRestoreBackup = async (backupUuid: string, executionUuid?: string) => {
        if (!window.confirm('Are you sure you want to restore this backup? Current data will be replaced.')) {
            return;
        }
        setRestoringBackupUuid(backupUuid);
        try {
            await restoreBackup(backupUuid, executionUuid);
            alert('Database restore initiated. This may take a few minutes.');
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to restore backup');
        } finally {
            setRestoringBackupUuid(null);
        }
    };

    const handleDeleteBackup = async (backupUuid: string) => {
        if (window.confirm('Are you sure you want to delete this backup?')) {
            try {
                await deleteBackup(backupUuid);
                alert('Backup deleted');
            } catch (err) {
                alert(err instanceof Error ? err.message : 'Failed to delete backup');
            }
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to load backups. Check API permissions.
                </div>
            )}

            {/* Actions */}
            <div className="flex gap-2">
                <Button size="sm" onClick={handleCreateBackup} disabled={isCreating}>
                    {isCreating ? (
                        <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                    ) : (
                        <Plus className="mr-1 h-3 w-3" />
                    )}
                    Create Backup
                </Button>
                <Button size="sm" variant="secondary" onClick={handleScheduleBackup}>
                    <Clock className="mr-1 h-3 w-3" />
                    Schedule
                </Button>
                <Button size="sm" variant="ghost" onClick={refetch}>
                    <RefreshCw className="h-3 w-3" />
                </Button>
            </div>

            {/* Backup List */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Backup Executions</h3>
                {(() => {
                    // Flatten all executions from all backup configurations
                    const allExecutions = backups.flatMap(backup =>
                        (backup.executions || []).map(exec => ({
                            ...exec,
                            backupUuid: backup.uuid,
                            backupFrequency: backup.frequency,
                        }))
                    ).sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

                    if (allExecutions.length === 0) {
                        return (
                            <div className="rounded-lg border border-dashed border-border p-6 text-center">
                                <HardDrive className="mx-auto h-8 w-8 text-foreground-subtle" />
                                <p className="mt-2 text-sm text-foreground-muted">No backups yet</p>
                                <p className="mt-1 text-xs text-foreground-subtle">
                                    Create your first backup to protect your data
                                </p>
                            </div>
                        );
                    }

                    return (
                        <div className="space-y-2">
                            {allExecutions.map((exec) => (
                                <div
                                    key={exec.uuid}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <HardDrive className={`h-4 w-4 ${
                                            exec.status === 'success' ? 'text-emerald-500' :
                                            exec.status === 'in_progress' ? 'text-blue-500 animate-pulse' :
                                            'text-red-500'
                                        }`} />
                                        <div>
                                            <p className="text-sm text-foreground">
                                                {new Date(exec.created_at).toLocaleString()}
                                            </p>
                                            <p className="text-xs text-foreground-muted">
                                                {exec.size || 'N/A'} • {exec.status}
                                                {exec.database_name && ` • ${exec.database_name}`}
                                                {exec.restore_status && exec.restore_status !== 'pending' && (
                                                    <span className={`ml-2 ${
                                                        exec.restore_status === 'success' ? 'text-emerald-500' :
                                                        exec.restore_status === 'in_progress' ? 'text-blue-500' :
                                                        'text-red-500'
                                                    }`}>
                                                        (Restore: {exec.restore_status})
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => handleRestoreBackup(exec.backupUuid, exec.uuid)}
                                            disabled={exec.status !== 'success' || restoringBackupUuid === exec.backupUuid || exec.restore_status === 'in_progress'}
                                        >
                                            {restoringBackupUuid === exec.backupUuid || exec.restore_status === 'in_progress' ? (
                                                <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                                            ) : null}
                                            Restore
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-red-500 hover:text-red-400"
                                            onClick={() => handleDeleteBackup(exec.backupUuid)}
                                            disabled={exec.status === 'in_progress'}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    );
                })()}
            </div>

            {/* Info */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <p className="text-xs text-foreground-muted">
                    Backups are incremental and use copy-on-write technology.
                    You only pay for unique data stored.
                </p>
            </div>
        </div>
    );
}
