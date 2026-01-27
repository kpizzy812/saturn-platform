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
    Shield,
    Zap,
    AlertTriangle,
    Save,
    RotateCcw,
} from 'lucide-react';

interface SystemSettings {
    site_name: string;
    site_url: string;
    admin_email: string;
    maintenance_mode: boolean;
    registration_enabled: boolean;
    email_verification_required: boolean;
    two_factor_required: boolean;
    max_teams_per_user: number;
    max_servers_per_team: number;
    session_lifetime: number;
}

interface FeatureFlags {
    enable_oauth: boolean;
    enable_api: boolean;
    enable_webhooks: boolean;
    enable_backups: boolean;
    enable_monitoring: boolean;
}

interface Props {
    settings: SystemSettings;
    featureFlags: FeatureFlags;
}

const defaultSettings: SystemSettings = {
    site_name: '',
    site_url: '',
    admin_email: '',
    maintenance_mode: false,
    registration_enabled: true,
    email_verification_required: true,
    two_factor_required: false,
    max_teams_per_user: 5,
    max_servers_per_team: 10,
    session_lifetime: 120,
};

const defaultFeatureFlags: FeatureFlags = {
    enable_oauth: false,
    enable_api: true,
    enable_webhooks: true,
    enable_backups: false,
    enable_monitoring: false,
};

export default function AdminSettingsIndex({
    settings = defaultSettings,
    featureFlags = defaultFeatureFlags,
}: Props) {
    const confirm = useConfirm();
    const [formData, setFormData] = React.useState(settings);
    const [flags, setFlags] = React.useState(featureFlags);
    const [isSaving, setIsSaving] = React.useState(false);

    const handleSave = () => {
        setIsSaving(true);
        router.post(
            '/admin/settings',
            { settings: formData, featureFlags: flags },
            {
                onFinish: () => setIsSaving(false),
            }
        );
    };

    const handleReset = async () => {
        const confirmed = await confirm({
            title: 'Reset Settings',
            description: 'Reset all settings to defaults?',
            confirmText: 'Reset',
            variant: 'warning',
        });
        if (confirmed) {
            setFormData(defaultSettings);
            setFlags(defaultFeatureFlags);
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
                            <CardDescription>Basic system configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Site Name
                                </label>
                                <Input
                                    value={formData.site_name}
                                    onChange={(e) =>
                                        setFormData({ ...formData, site_name: e.target.value })
                                    }
                                    placeholder="Saturn Platform Cloud"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Site URL
                                </label>
                                <Input
                                    value={formData.site_url}
                                    onChange={(e) =>
                                        setFormData({ ...formData, site_url: e.target.value })
                                    }
                                    placeholder="https://example.com"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Admin Email
                                </label>
                                <Input
                                    type="email"
                                    value={formData.admin_email}
                                    onChange={(e) =>
                                        setFormData({ ...formData, admin_email: e.target.value })
                                    }
                                    placeholder="admin@example.com"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Security Settings */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-primary" />
                                <CardTitle>Security Settings</CardTitle>
                            </div>
                            <CardDescription>Authentication and security options</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Registration Enabled</p>
                                    <p className="text-sm text-foreground-muted">
                                        Allow new users to register
                                    </p>
                                </div>
                                <Checkbox
                                    checked={formData.registration_enabled}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, registration_enabled: checked === true })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Email Verification Required</p>
                                    <p className="text-sm text-foreground-muted">
                                        Require email verification for new users
                                    </p>
                                </div>
                                <Checkbox
                                    checked={formData.email_verification_required}
                                    onCheckedChange={(checked) =>
                                        setFormData({
                                            ...formData,
                                            email_verification_required: checked === true,
                                        })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Two-Factor Required</p>
                                    <p className="text-sm text-foreground-muted">
                                        Require 2FA for all users
                                    </p>
                                </div>
                                <Checkbox
                                    checked={formData.two_factor_required}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, two_factor_required: checked === true })
                                    }
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Session Lifetime (minutes)
                                </label>
                                <Input
                                    type="number"
                                    value={formData.session_lifetime}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            session_lifetime: parseInt(e.target.value) || 120,
                                        })
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Resource Limits */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings className="h-5 w-5 text-primary" />
                                <CardTitle>Resource Limits</CardTitle>
                            </div>
                            <CardDescription>Configure resource allocation limits</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Max Teams per User
                                </label>
                                <Input
                                    type="number"
                                    value={formData.max_teams_per_user}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            max_teams_per_user: parseInt(e.target.value) || 5,
                                        })
                                    }
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Max Servers per Team
                                </label>
                                <Input
                                    type="number"
                                    value={formData.max_servers_per_team}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            max_servers_per_team: parseInt(e.target.value) || 10,
                                        })
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Feature Flags */}
                    <Card variant="glass">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Zap className="h-5 w-5 text-primary" />
                                <CardTitle>Feature Flags</CardTitle>
                            </div>
                            <CardDescription>Enable or disable system features</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">OAuth Integration</p>
                                    <p className="text-sm text-foreground-muted">
                                        Enable OAuth login providers
                                    </p>
                                </div>
                                <Checkbox
                                    checked={flags.enable_oauth}
                                    onCheckedChange={(checked) =>
                                        setFlags({ ...flags, enable_oauth: checked === true })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">API Access</p>
                                    <p className="text-sm text-foreground-muted">Enable REST API endpoints</p>
                                </div>
                                <Checkbox
                                    checked={flags.enable_api}
                                    onCheckedChange={(checked) =>
                                        setFlags({ ...flags, enable_api: checked === true })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Webhooks</p>
                                    <p className="text-sm text-foreground-muted">
                                        Enable webhook notifications
                                    </p>
                                </div>
                                <Checkbox
                                    checked={flags.enable_webhooks}
                                    onCheckedChange={(checked) =>
                                        setFlags({ ...flags, enable_webhooks: checked === true })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Automated Backups</p>
                                    <p className="text-sm text-foreground-muted">
                                        Enable automated backup system
                                    </p>
                                </div>
                                <Checkbox
                                    checked={flags.enable_backups}
                                    onCheckedChange={(checked) =>
                                        setFlags({ ...flags, enable_backups: checked === true })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">System Monitoring</p>
                                    <p className="text-sm text-foreground-muted">
                                        Enable advanced monitoring features
                                    </p>
                                </div>
                                <Checkbox
                                    checked={flags.enable_monitoring}
                                    onCheckedChange={(checked) =>
                                        setFlags({ ...flags, enable_monitoring: checked === true })
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Maintenance Mode */}
                    <Card variant="glass" className={formData.maintenance_mode ? 'border-warning' : ''}>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-warning" />
                                <CardTitle>Maintenance Mode</CardTitle>
                            </div>
                            <CardDescription>
                                Put the system into maintenance mode (blocks all non-admin access)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between rounded-lg border border-warning/20 bg-warning/5 p-4">
                                <div>
                                    <p className="font-medium text-foreground">Maintenance Mode</p>
                                    <p className="text-sm text-foreground-muted">
                                        {formData.maintenance_mode
                                            ? 'System is currently in maintenance mode'
                                            : 'System is operational'}
                                    </p>
                                </div>
                                <Checkbox
                                    checked={formData.maintenance_mode}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, maintenance_mode: checked === true })
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
