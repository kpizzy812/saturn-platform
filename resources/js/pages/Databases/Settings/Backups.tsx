import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Checkbox, Select } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { ArrowLeft, Database, Save, Clock, HardDrive, Cloud, AlertCircle } from 'lucide-react';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
    backupSettings?: BackupSettings;
}

interface BackupSettings {
    enabled: boolean;
    frequency: 'hourly' | 'daily' | 'weekly' | 'monthly';
    retention: number;
    time: string;
    compression: boolean;
    s3_enabled: boolean;
    s3_bucket?: string;
    s3_region?: string;
    s3_access_key?: string;
    disable_local_backup: boolean;
}

export default function DatabaseBackupSettings({ database, backupSettings }: Props) {
    const { addToast } = useToast();

    // State for backup settings
    const [enabled, setEnabled] = useState(backupSettings?.enabled ?? true);
    const [frequency, setFrequency] = useState<BackupSettings['frequency']>(backupSettings?.frequency ?? 'daily');
    const [retention, setRetention] = useState(backupSettings?.retention ?? 7);
    const [time, setTime] = useState(backupSettings?.time ?? '02:00');
    const [compression, setCompression] = useState(backupSettings?.compression ?? true);
    const [s3Enabled, setS3Enabled] = useState(backupSettings?.s3_enabled ?? false);
    const [s3Bucket, setS3Bucket] = useState(backupSettings?.s3_bucket ?? '');
    const [s3Region, setS3Region] = useState(backupSettings?.s3_region ?? 'us-east-1');
    const [s3AccessKey, setS3AccessKey] = useState(backupSettings?.s3_access_key ?? '');
    const [s3SecretKey, setS3SecretKey] = useState('');
    const [disableLocalBackup, setDisableLocalBackup] = useState(backupSettings?.disable_local_backup ?? false);
    const [hasChanges, setHasChanges] = useState(false);

    const handleSave = () => {
        const settings: BackupSettings = {
            enabled,
            frequency,
            retention,
            time,
            compression,
            s3_enabled: s3Enabled,
            s3_bucket: s3Bucket,
            s3_region: s3Region,
            s3_access_key: s3AccessKey,
            disable_local_backup: disableLocalBackup,
        };

        router.patch(`/databases/${database.uuid}/settings/backups`, settings as any, {
            onSuccess: () => {
                addToast('success', 'Backup settings saved successfully');
                setHasChanges(false);
            },
            onError: () => {
                addToast('error', 'Failed to save backup settings');
            },
        });
    };

    const [isTesting, setIsTesting] = useState(false);

    const handleTestConnection = async () => {
        if (!s3Enabled || !s3Bucket || !s3AccessKey || !s3SecretKey) {
            addToast('error', 'Please fill in all S3 credentials');
            return;
        }

        setIsTesting(true);
        addToast('info', 'Testing S3 connection...');
        try {
            const response = await fetch('/api/databases/s3/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                },
                body: JSON.stringify({
                    bucket: s3Bucket,
                    region: s3Region,
                    access_key: s3AccessKey,
                    secret_key: s3SecretKey,
                }),
            });
            const data = await response.json();
            if (data.success) {
                addToast('success', 'S3 connection test successful!');
            } else {
                addToast('error', data.error || 'S3 connection test failed');
            }
        } catch {
            addToast('error', 'S3 connection test failed');
        } finally {
            setIsTesting(false);
        }
    };

    const markChanged = () => setHasChanges(true);

    return (
        <AppLayout
            title={`${database.name} - Backup Settings`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Settings', href: `/databases/${database.uuid}/settings` },
                { label: 'Backups' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}/settings`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Settings
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Backup Configuration</h1>
                    <p className="text-foreground-muted">Configure automated backup settings for {database.name}</p>
                </div>
                {hasChanges && (
                    <Button onClick={handleSave}>
                        <Save className="mr-2 h-4 w-4" />
                        Save Settings
                    </Button>
                )}
            </div>

            {/* General Backup Settings */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">General Backup Settings</h3>
                        <HardDrive className="h-5 w-5 text-foreground-muted" />
                    </div>

                    <div className="space-y-4">
                        <Checkbox
                            label="Enable automatic backups"
                            checked={enabled}
                            onChange={(e) => {
                                setEnabled(e.target.checked);
                                markChanged();
                            }}
                        />

                        {enabled && (
                            <>
                                <Select
                                    label="Backup Frequency"
                                    value={frequency}
                                    onChange={(e) => {
                                        setFrequency(e.target.value as BackupSettings['frequency']);
                                        markChanged();
                                    }}
                                    options={[
                                        { value: 'hourly', label: 'Hourly' },
                                        { value: 'daily', label: 'Daily' },
                                        { value: 'weekly', label: 'Weekly' },
                                        { value: 'monthly', label: 'Monthly' },
                                    ]}
                                />

                                <Input
                                    label="Backup Time"
                                    type="time"
                                    value={time}
                                    onChange={(e) => {
                                        setTime(e.target.value);
                                        markChanged();
                                    }}
                                    hint="Time to run daily/weekly/monthly backups (server timezone)"
                                />

                                <Input
                                    label="Retention Period (days)"
                                    type="number"
                                    min="1"
                                    max="365"
                                    value={retention}
                                    onChange={(e) => {
                                        setRetention(parseInt(e.target.value) || 7);
                                        markChanged();
                                    }}
                                    hint="Number of days to keep backups before automatic deletion"
                                />

                                <Checkbox
                                    label="Enable compression"
                                    checked={compression}
                                    onChange={(e) => {
                                        setCompression(e.target.checked);
                                        markChanged();
                                    }}
                                />
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* S3 Storage Settings */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">S3 Storage Settings</h3>
                        <Cloud className="h-5 w-5 text-foreground-muted" />
                    </div>

                    <div className="space-y-4">
                        <Checkbox
                            label="Store backups in S3 (or S3-compatible storage)"
                            checked={s3Enabled}
                            onChange={(e) => {
                                setS3Enabled(e.target.checked);
                                markChanged();
                            }}
                        />

                        {s3Enabled && (
                            <>
                                <Input
                                    label="S3 Bucket Name"
                                    value={s3Bucket}
                                    onChange={(e) => {
                                        setS3Bucket(e.target.value);
                                        markChanged();
                                    }}
                                    placeholder="my-database-backups"
                                    required
                                />

                                <Input
                                    label="S3 Region"
                                    value={s3Region}
                                    onChange={(e) => {
                                        setS3Region(e.target.value);
                                        markChanged();
                                    }}
                                    placeholder="us-east-1"
                                    required
                                />

                                <Input
                                    label="S3 Access Key ID"
                                    value={s3AccessKey}
                                    onChange={(e) => {
                                        setS3AccessKey(e.target.value);
                                        markChanged();
                                    }}
                                    placeholder="AKIAIOSFODNN7EXAMPLE"
                                    required
                                />

                                <Input
                                    label="S3 Secret Access Key"
                                    type="password"
                                    value={s3SecretKey}
                                    onChange={(e) => {
                                        setS3SecretKey(e.target.value);
                                        markChanged();
                                    }}
                                    placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
                                    required
                                />

                                <div className="flex gap-2">
                                    <Button variant="secondary" size="sm" onClick={handleTestConnection} disabled={isTesting}>
                                        {isTesting ? 'Testing...' : 'Test Connection'}
                                    </Button>
                                </div>

                                <Checkbox
                                    label="Disable local backups (only keep backups in S3)"
                                    checked={disableLocalBackup}
                                    onChange={(e) => {
                                        setDisableLocalBackup(e.target.checked);
                                        markChanged();
                                    }}
                                />

                                {disableLocalBackup && (
                                    <div className="rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4">
                                        <div className="flex items-start gap-3">
                                            <AlertCircle className="h-5 w-5 flex-shrink-0 text-yellow-500" />
                                            <div>
                                                <h4 className="mb-1 font-semibold text-yellow-500">Warning</h4>
                                                <p className="text-sm text-yellow-400">
                                                    With this option enabled, backups will only be stored in S3. Make sure your S3 credentials are correct and the bucket is accessible.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Backup Schedule Preview */}
            {enabled && (
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-medium text-foreground">Backup Schedule</h3>
                            <Clock className="h-5 w-5 text-foreground-muted" />
                        </div>
                        <div className="space-y-3">
                            <ScheduleItem
                                label="Next Backup"
                                value={getNextBackupTime(frequency, time)}
                            />
                            <ScheduleItem
                                label="Frequency"
                                value={frequency.charAt(0).toUpperCase() + frequency.slice(1)}
                            />
                            <ScheduleItem
                                label="Retention"
                                value={`${retention} days`}
                            />
                            <ScheduleItem
                                label="Storage Location"
                                value={s3Enabled ? (disableLocalBackup ? 'S3 Only' : 'Local + S3') : 'Local Server'}
                            />
                            <ScheduleItem
                                label="Compression"
                                value={compression ? 'Enabled' : 'Disabled'}
                            />
                        </div>
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}

interface ScheduleItemProps {
    label: string;
    value: string;
}

function ScheduleItem({ label, value }: ScheduleItemProps) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3">
            <span className="text-sm font-medium text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}

function getNextBackupTime(frequency: BackupSettings['frequency'], time: string): string {
    const now = new Date();
    const [hours, minutes] = time.split(':').map(Number);

    let next = new Date(now);
    next.setHours(hours, minutes, 0, 0);

    switch (frequency) {
        case 'hourly':
            next.setHours(now.getHours() + 1, 0, 0, 0);
            break;
        case 'daily':
            if (next <= now) {
                next.setDate(next.getDate() + 1);
            }
            break;
        case 'weekly':
            next.setDate(next.getDate() + (7 - next.getDay()));
            if (next <= now) {
                next.setDate(next.getDate() + 7);
            }
            break;
        case 'monthly':
            next.setMonth(next.getMonth() + 1, 1);
            if (next <= now) {
                next.setMonth(next.getMonth() + 1);
            }
            break;
    }

    return next.toLocaleString();
}
