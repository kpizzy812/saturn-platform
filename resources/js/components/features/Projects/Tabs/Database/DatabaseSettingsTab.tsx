import { router } from '@inertiajs/react';
import { Button, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import type { SelectedService } from '../../types';

interface DatabaseSettingsTabProps {
    service: SelectedService;
}

export function DatabaseSettingsTab({ service }: DatabaseSettingsTabProps) {
    const confirm = useConfirm();
    const { addToast } = useToast();

    const handleResetDatabase = async () => {
        const confirmed = await confirm({
            title: 'Reset Database',
            description: `Are you sure you want to reset ${service.name}? All data will be erased and the database will be restarted with a clean state.`,
            confirmText: 'Reset',
            variant: 'danger',
        });
        if (confirmed) {
            router.post(`/databases/${service.uuid}/restart`, {}, {
                preserveScroll: true,
                onSuccess: () => addToast('success', 'Database reset initiated'),
                onError: () => addToast('error', 'Failed to reset database'),
            });
        }
    };

    const handleDeleteDatabase = async () => {
        const confirmed = await confirm({
            title: 'Delete Database',
            description: `Are you sure you want to delete ${service.name}? This action cannot be undone and all data will be lost.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/databases/${service.uuid}`, {
                onSuccess: () => addToast('success', 'Database deleted'),
                onError: () => addToast('error', 'Failed to delete database'),
            });
        }
    };

    // Extract version from image (e.g. "postgres:15-alpine" → "15-alpine")
    const version = service.version || service.image?.split(':')[1] || 'N/A';

    return (
        <div className="space-y-6">
            {/* Database Info */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Database</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Type</span>
                            <span className="text-sm text-foreground capitalize">{service.dbType || 'N/A'}</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Version</span>
                            <span className="text-sm text-foreground">{version}</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Status</span>
                            <span className="text-sm text-foreground capitalize">{service.status || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Danger Zone */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-red-500">Danger Zone</h3>
                <div className="space-y-2">
                    <Button variant="danger" size="sm" onClick={handleResetDatabase}>
                        Reset Database
                    </Button>
                    <Button variant="danger" size="sm" className="ml-2" onClick={handleDeleteDatabase}>
                        Delete Database
                    </Button>
                </div>
            </div>
        </div>
    );
}
