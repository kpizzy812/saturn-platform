import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Select, Input, Checkbox } from '@/components/ui';
import { Save, Shield, Clock, HardDrive } from 'lucide-react';
import type { S3Storage } from '@/types';

interface Props {
    storages: S3Storage[];
    settings?: {
        default_storage_uuid: string | null;
        default_retention_days: number;
        encryption_enabled: boolean;
        compression_enabled: boolean;
        auto_cleanup_enabled: boolean;
        auto_cleanup_days: number;
    };
}

export default function StorageSettings({ storages = [], settings }: Props) {
    const [defaultStorage, setDefaultStorage] = useState(settings?.default_storage_uuid || '');
    const [retentionDays, setRetentionDays] = useState(String(settings?.default_retention_days || 30));
    const [encryptionEnabled, setEncryptionEnabled] = useState(settings?.encryption_enabled ?? true);
    const [compressionEnabled, setCompressionEnabled] = useState(settings?.compression_enabled ?? true);
    const [autoCleanupEnabled, setAutoCleanupEnabled] = useState(settings?.auto_cleanup_enabled ?? false);
    const [autoCleanupDays, setAutoCleanupDays] = useState(String(settings?.auto_cleanup_days || 90));

    const handleSave = () => {
        router.post('/storage/settings', {
            default_storage_uuid: defaultStorage || null,
            default_retention_days: parseInt(retentionDays) || 30,
            encryption_enabled: encryptionEnabled,
            compression_enabled: compressionEnabled,
            auto_cleanup_enabled: autoCleanupEnabled,
            auto_cleanup_days: parseInt(autoCleanupDays) || 90,
        });
    };

    return (
        <AppLayout
            title="Storage Settings"
            breadcrumbs={[
                { label: 'Storage', href: '/storage' },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-4xl px-6 py-8">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-foreground">Storage Settings</h1>
                    <p className="text-foreground-muted">
                        Configure global storage settings, retention policies, and encryption options
                    </p>
                </div>

                <div className="space-y-6">
                    {/* Default Storage */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <HardDrive className="h-5 w-5 text-primary" />
                                <CardTitle>Default Storage</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Select
                                    label="Default Backup Storage"
                                    value={defaultStorage}
                                    onChange={(e) => setDefaultStorage(e.target.value)}
                                    hint="Select the default storage provider for new database backups"
                                >
                                    <option value="">None (Manual selection required)</option>
                                    {storages.map((storage) => (
                                        <option key={storage.uuid} value={storage.uuid}>
                                            {storage.name} ({storage.bucket})
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {storages.length === 0 && (
                                <div className="rounded-lg border border-warning bg-warning/10 p-4">
                                    <p className="text-sm text-warning">
                                        No storage providers configured. Add a storage provider to enable backups.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Retention Policies */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-info" />
                                <CardTitle>Retention Policies</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <Input
                                    label="Default Retention Period"
                                    type="number"
                                    min="1"
                                    max="365"
                                    value={retentionDays}
                                    onChange={(e) => setRetentionDays(e.target.value)}
                                    hint="Days to keep backups (1-365)"
                                />

                                <Input
                                    label="Auto-Cleanup After"
                                    type="number"
                                    min="30"
                                    max="365"
                                    value={autoCleanupDays}
                                    onChange={(e) => setAutoCleanupDays(e.target.value)}
                                    hint="Days before automatic cleanup"
                                    disabled={!autoCleanupEnabled}
                                />
                            </div>

                            <div>
                                <Checkbox
                                    id="auto-cleanup"
                                    label="Enable Automatic Cleanup"
                                    checked={autoCleanupEnabled}
                                    onChange={(e) => setAutoCleanupEnabled(e.target.checked)}
                                    hint="Automatically delete backups older than the specified period"
                                />
                            </div>

                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                <h4 className="mb-2 font-medium text-foreground">Retention Policy Rules</h4>
                                <ul className="space-y-1 text-sm text-foreground-muted">
                                    <li>• Backups older than the retention period will be marked for deletion</li>
                                    <li>• Manual backups are excluded from automatic cleanup</li>
                                    <li>• You can override retention per database backup schedule</li>
                                    <li>• Deleted backups cannot be recovered</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Encryption Settings */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-success" />
                                <CardTitle>Encryption & Compression</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Checkbox
                                    id="encryption"
                                    label="Enable Encryption"
                                    checked={encryptionEnabled}
                                    onChange={(e) => setEncryptionEnabled(e.target.checked)}
                                    hint="Encrypt backups before uploading to storage (AES-256)"
                                />
                            </div>

                            <div>
                                <Checkbox
                                    id="compression"
                                    label="Enable Compression"
                                    checked={compressionEnabled}
                                    onChange={(e) => setCompressionEnabled(e.target.checked)}
                                    hint="Compress backups to save storage space (gzip)"
                                />
                            </div>

                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                <h4 className="mb-2 font-medium text-foreground">Security Information</h4>
                                <ul className="space-y-1 text-sm text-foreground-muted">
                                    <li>• Encryption uses AES-256-CBC with a unique key per backup</li>
                                    <li>• Compression reduces storage costs by up to 70%</li>
                                    <li>• Encrypted backups can only be restored by this Saturn Platform instance</li>
                                    <li>• Server-side encryption (SSE) may also be enabled in your S3 provider</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Save Button */}
                    <div className="flex justify-end">
                        <Button onClick={handleSave}>
                            <Save className="mr-2 h-4 w-4" />
                            Save Settings
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
