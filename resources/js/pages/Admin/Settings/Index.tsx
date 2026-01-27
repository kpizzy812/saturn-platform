import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Checkbox } from '@/components/ui/Checkbox';
import { useConfirm } from '@/components/ui';
import {
    Settings,
    Globe,
    Mail,
    Save,
    RotateCcw,
} from 'lucide-react';

interface InstanceSettingsData {
    id?: number;
    fqdn?: string;
    instance_name?: string;
    allowed_ip_ranges?: string;
    is_auto_update_enabled?: boolean;
    auto_update_frequency?: string;
    update_check_frequency?: string;
    is_wire_navigate_enabled?: boolean;
    smtp_enabled?: boolean;
    smtp_host?: string;
    smtp_port?: number;
    smtp_from_address?: string;
    resend_enabled?: boolean;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    settings: InstanceSettingsData;
}

export default function AdminSettingsIndex({
    settings,
}: Props) {
    const confirm = useConfirm();
    const [formData, setFormData] = React.useState<InstanceSettingsData>(settings ?? {});
    const [isSaving, setIsSaving] = React.useState(false);

    const handleSave = () => {
        setIsSaving(true);
        router.post(
            '/admin/settings',
            { settings: formData } as any,
            {
                onFinish: () => setIsSaving(false),
            }
        );
    };

    const handleReset = async () => {
        const confirmed = await confirm({
            title: 'Reset Settings',
            description: 'Reset all settings to values from server?',
            confirmText: 'Reset',
            variant: 'warning',
        });
        if (confirmed) {
            setFormData(settings ?? {});
        }
    };

    return (
        <AdminLayout
            title="Settings"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-5xl px-6 py-8">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">System Settings</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Configure global system settings and feature flags
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={handleReset}>
                            <RotateCcw className="h-4 w-4" />
                            Reset
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving}>
                            <Save className="h-4 w-4" />
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </div>

                <div className="space-y-6">
                    {/* General Settings */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Globe className="h-5 w-5 text-primary" />
                                <CardTitle>General Settings</CardTitle>
                            </div>
                            <CardDescription>Basic instance configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Instance Name
                                </label>
                                <Input
                                    value={formData.instance_name || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, instance_name: e.target.value })
                                    }
                                    placeholder="Saturn Platform"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    FQDN
                                </label>
                                <Input
                                    value={formData.fqdn || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, fqdn: e.target.value })
                                    }
                                    placeholder="https://saturn.example.com"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Allowed IP Ranges
                                </label>
                                <Input
                                    value={formData.allowed_ip_ranges || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, allowed_ip_ranges: e.target.value })
                                    }
                                    placeholder="0.0.0.0/0"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Update Settings */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings className="h-5 w-5 text-primary" />
                                <CardTitle>Update Settings</CardTitle>
                            </div>
                            <CardDescription>Auto-update configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Auto Update</p>
                                    <p className="text-sm text-foreground-muted">
                                        Enable automatic updates
                                    </p>
                                </div>
                                <Checkbox
                                    checked={formData.is_auto_update_enabled || false}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, is_auto_update_enabled: checked === true })
                                    }
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Auto Update Frequency
                                </label>
                                <Input
                                    value={formData.auto_update_frequency || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, auto_update_frequency: e.target.value })
                                    }
                                    placeholder="0 0 * * *"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Email Settings */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Mail className="h-5 w-5 text-primary" />
                                <CardTitle>Email Settings</CardTitle>
                            </div>
                            <CardDescription>SMTP and email delivery configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">SMTP Enabled</p>
                                    <p className="text-sm text-foreground-muted">
                                        {formData.smtp_enabled ? 'SMTP is configured' : 'SMTP not configured'}
                                    </p>
                                </div>
                                <Badge variant={formData.smtp_enabled ? 'success' : 'default'}>
                                    {formData.smtp_enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            {formData.smtp_port && (
                                <div>
                                    <p className="text-sm text-foreground-subtle">SMTP Port</p>
                                    <p className="font-medium text-foreground">{formData.smtp_port}</p>
                                </div>
                            )}
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Resend Enabled</p>
                                    <p className="text-sm text-foreground-muted">
                                        Alternative email delivery via Resend
                                    </p>
                                </div>
                                <Badge variant={formData.resend_enabled ? 'success' : 'default'}>
                                    {formData.resend_enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
