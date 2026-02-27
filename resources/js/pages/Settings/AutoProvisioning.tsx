import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, useToast } from '@/components/ui';
import { router } from '@inertiajs/react';
import type { RouterPayload } from '@/types/inertia';
import type { AutoProvisioningSettings, CloudProviderToken } from '@/types/models';

interface Props {
    settings: AutoProvisioningSettings;
    cloudTokens: CloudProviderToken[];
}

const SERVER_TYPES = ['cx22', 'cx32', 'cx42', 'cx52', 'cpx11', 'cpx21', 'cpx31'];
const LOCATIONS = [
    { value: 'nbg1', label: 'Nuremberg (nbg1)' },
    { value: 'hel1', label: 'Helsinki (hel1)' },
    { value: 'fsn1', label: 'Falkenstein (fsn1)' },
    { value: 'ash', label: 'Ashburn, VA (ash)' },
    { value: 'sjc', label: 'Hillsboro, OR (sjc)' },
];

export default function AutoProvisioningSettings({ settings, cloudTokens }: Props) {
    const { toast } = useToast();
    const [form, setForm] = React.useState<AutoProvisioningSettings>({ ...settings });

    const set = <K extends keyof AutoProvisioningSettings>(key: K, value: AutoProvisioningSettings[K]) =>
        setForm((prev) => ({ ...prev, [key]: value }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/settings/auto-provisioning', form as unknown as RouterPayload, {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Settings saved', variant: 'success' }),
            onError: () => toast({ title: 'Failed to save settings', variant: 'error' }),
        });
    };

    return (
        <SettingsLayout activeSection="auto-provisioning">
            <div className="space-y-6">
                <div>
                    <h2 className="text-lg font-semibold text-foreground">Auto-Provisioning</h2>
                    <p className="text-sm text-foreground-muted">
                        Automatically provision new servers when resource thresholds are exceeded
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Main toggle */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Auto-Provisioning</CardTitle>
                            <CardDescription>Enable automatic server provisioning</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <label className="flex cursor-pointer items-center gap-3">
                                <div
                                    onClick={() => set('auto_provision_enabled', !form.auto_provision_enabled)}
                                    className={`relative h-6 w-11 rounded-full transition-colors ${
                                        form.auto_provision_enabled ? 'bg-primary' : 'bg-border'
                                    }`}
                                >
                                    <span
                                        className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${
                                            form.auto_provision_enabled ? 'translate-x-5' : 'translate-x-0.5'
                                        }`}
                                    />
                                </div>
                                <span className="text-sm font-medium text-foreground">
                                    {form.auto_provision_enabled ? 'Enabled' : 'Disabled'}
                                </span>
                            </label>

                            {form.auto_provision_enabled && (
                                <div className="grid gap-4 pt-2 md:grid-cols-2">
                                    {/* Cloud Provider Token */}
                                    <div className="space-y-1">
                                        <label className="block text-sm font-medium text-foreground">
                                            Cloud Provider Token
                                        </label>
                                        <select
                                            value={form.cloud_provider_token_uuid ?? ''}
                                            onChange={(e) => set('cloud_provider_token_uuid', e.target.value || null)}
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <option value="">— Select token —</option>
                                            {cloudTokens.map((t) => (
                                                <option key={t.uuid} value={t.uuid}>
                                                    {t.name} ({t.provider})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Server Type */}
                                    <div className="space-y-1">
                                        <label className="block text-sm font-medium text-foreground">
                                            Server Type
                                        </label>
                                        <select
                                            value={form.auto_provision_server_type ?? ''}
                                            onChange={(e) => set('auto_provision_server_type', e.target.value || null)}
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <option value="">— Select —</option>
                                            {SERVER_TYPES.map((s) => (
                                                <option key={s} value={s}>
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Location */}
                                    <div className="space-y-1">
                                        <label className="block text-sm font-medium text-foreground">
                                            Location
                                        </label>
                                        <select
                                            value={form.auto_provision_location ?? ''}
                                            onChange={(e) => set('auto_provision_location', e.target.value || null)}
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <option value="">— Select —</option>
                                            {LOCATIONS.map((l) => (
                                                <option key={l.value} value={l.value}>
                                                    {l.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Max servers per day */}
                                    <div className="space-y-1">
                                        <label className="block text-sm font-medium text-foreground">
                                            Max Servers / Day
                                        </label>
                                        <input
                                            type="number"
                                            min={1}
                                            max={10}
                                            value={form.auto_provision_max_servers_per_day}
                                            onChange={(e) =>
                                                set('auto_provision_max_servers_per_day', parseInt(e.target.value) || 1)
                                            }
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        />
                                    </div>

                                    {/* Cooldown */}
                                    <div className="space-y-1">
                                        <label className="block text-sm font-medium text-foreground">
                                            Cooldown (minutes)
                                        </label>
                                        <input
                                            type="number"
                                            min={15}
                                            max={240}
                                            value={form.auto_provision_cooldown_minutes}
                                            onChange={(e) =>
                                                set('auto_provision_cooldown_minutes', parseInt(e.target.value) || 30)
                                            }
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Resource Monitoring */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Resource Monitoring</CardTitle>
                            <CardDescription>Trigger provisioning based on resource usage thresholds</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <label className="flex cursor-pointer items-center gap-3">
                                <div
                                    onClick={() => set('resource_monitoring_enabled', !form.resource_monitoring_enabled)}
                                    className={`relative h-6 w-11 rounded-full transition-colors ${
                                        form.resource_monitoring_enabled ? 'bg-primary' : 'bg-border'
                                    }`}
                                >
                                    <span
                                        className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${
                                            form.resource_monitoring_enabled ? 'translate-x-5' : 'translate-x-0.5'
                                        }`}
                                    />
                                </div>
                                <span className="text-sm font-medium text-foreground">
                                    Enable resource monitoring
                                </span>
                            </label>

                            {form.resource_monitoring_enabled && (
                                <div className="grid gap-6 pt-2 md:grid-cols-2">
                                    <ThresholdGroup
                                        label="CPU (%)"
                                        warning={form.resource_warning_cpu_threshold}
                                        critical={form.resource_critical_cpu_threshold}
                                        onWarning={(v) => set('resource_warning_cpu_threshold', v)}
                                        onCritical={(v) => set('resource_critical_cpu_threshold', v)}
                                    />
                                    <ThresholdGroup
                                        label="Memory (%)"
                                        warning={form.resource_warning_memory_threshold}
                                        critical={form.resource_critical_memory_threshold}
                                        onWarning={(v) => set('resource_warning_memory_threshold', v)}
                                        onCritical={(v) => set('resource_critical_memory_threshold', v)}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end">
                        <Button type="submit">Save Settings</Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    );
}

function ThresholdGroup({
    label,
    warning,
    critical,
    onWarning,
    onCritical,
}: {
    label: string;
    warning: number;
    critical: number;
    onWarning: (v: number) => void;
    onCritical: (v: number) => void;
}) {
    return (
        <div className="space-y-3">
            <p className="text-sm font-medium text-foreground">{label}</p>
            <div className="space-y-2">
                <div className="flex items-center justify-between text-xs text-foreground-muted">
                    <span>Warning</span>
                    <span className="font-medium text-yellow-500">{warning}%</span>
                </div>
                <input
                    type="range"
                    min={0}
                    max={100}
                    value={warning}
                    onChange={(e) => onWarning(parseInt(e.target.value))}
                    className="w-full accent-yellow-500"
                />
            </div>
            <div className="space-y-2">
                <div className="flex items-center justify-between text-xs text-foreground-muted">
                    <span>Critical</span>
                    <span className="font-medium text-red-500">{critical}%</span>
                </div>
                <input
                    type="range"
                    min={0}
                    max={100}
                    value={critical}
                    onChange={(e) => onCritical(parseInt(e.target.value))}
                    className="w-full accent-red-500"
                />
            </div>
        </div>
    );
}
