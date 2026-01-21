import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import {
    Settings as SettingsIcon,
    Info,
    Save,
    AlertCircle,
} from 'lucide-react';
import type { Application, PreviewDeploymentSettings } from '@/types';

interface Props {
    application: Application;
    settings?: PreviewDeploymentSettings;
    projectUuid?: string;
    environmentUuid?: string;
}

// Mock settings for demo
const MOCK_SETTINGS: PreviewDeploymentSettings = {
    enabled: true,
    auto_deploy_on_pr: true,
    url_template: 'pr-{pr_number}-{app_name}.preview.example.com',
    auto_delete_days: 7,
    resource_limits: {
        cpu: '1',
        memory: '512M',
    },
};

export default function PreviewSettings({ application, settings: propSettings, projectUuid, environmentUuid }: Props) {
    const [settings, setSettings] = React.useState<PreviewDeploymentSettings>(
        propSettings || MOCK_SETTINGS
    );
    const [isSaving, setIsSaving] = React.useState(false);

    const handleSave = async () => {
        setIsSaving(true);
        try {
            router.patch(`/api/v1/applications/${application.uuid}/preview-settings`, settings, {
                onSuccess: () => {
                    // Success notification would be shown here
                },
                onFinish: () => {
                    setIsSaving(false);
                },
            });
        } catch (error) {
            setIsSaving(false);
        }
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Preview Deployments', href: `/applications/${application.uuid}/previews` },
        { label: 'Settings' },
    ];

    return (
        <AppLayout title="Preview Deployment Settings" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <SettingsIcon className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Preview Deployment Settings</h1>
                        <p className="text-foreground-muted">
                            Configure how preview deployments work for this application
                        </p>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Settings */}
                <div className="lg:col-span-2 space-y-6">
                    {/* General Settings */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">General Settings</h2>
                            <div className="space-y-4">
                                {/* Enable/Disable */}
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <label className="text-sm font-medium text-foreground mb-1 block">
                                            Enable Preview Deployments
                                        </label>
                                        <p className="text-sm text-foreground-muted">
                                            Create preview deployments for pull requests
                                        </p>
                                    </div>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            className="peer sr-only"
                                            checked={settings.enabled}
                                            onChange={(e) =>
                                                setSettings({ ...settings, enabled: e.target.checked })
                                            }
                                        />
                                        <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                    </label>
                                </div>

                                {/* Auto-deploy on PR */}
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <label className="text-sm font-medium text-foreground mb-1 block">
                                            Auto-deploy on Pull Request
                                        </label>
                                        <p className="text-sm text-foreground-muted">
                                            Automatically create preview deployments when PRs are opened
                                        </p>
                                    </div>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            className="peer sr-only"
                                            checked={settings.auto_deploy_on_pr}
                                            onChange={(e) =>
                                                setSettings({ ...settings, auto_deploy_on_pr: e.target.checked })
                                            }
                                            disabled={!settings.enabled}
                                        />
                                        <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                                    </label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* URL Configuration */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">URL Configuration</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Preview URL Template
                                    </label>
                                    <Input
                                        value={settings.url_template}
                                        onChange={(e) =>
                                            setSettings({ ...settings, url_template: e.target.value })
                                        }
                                        placeholder="pr-{pr_number}-{app_name}.preview.example.com"
                                        disabled={!settings.enabled}
                                    />
                                    <p className="text-xs text-foreground-muted mt-2">
                                        Available variables: <code className="text-xs">{'{pr_number}'}</code>,{' '}
                                        <code className="text-xs">{'{app_name}'}</code>,{' '}
                                        <code className="text-xs">{'{branch}'}</code>
                                    </p>
                                </div>
                                <div className="rounded-lg border border-info/50 bg-info/5 p-4">
                                    <div className="flex gap-3">
                                        <Info className="h-5 w-5 text-info flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h4 className="text-sm font-semibold text-foreground mb-1">Example URL</h4>
                                            <p className="text-sm text-foreground-muted">
                                                PR #42 would be deployed to:{' '}
                                                <code className="text-xs bg-background-tertiary px-1.5 py-0.5 rounded">
                                                    pr-42-{application.name.toLowerCase()}.preview.example.com
                                                </code>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Lifecycle Settings */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Lifecycle Settings</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Auto-delete after (days)
                                    </label>
                                    <Select
                                        value={settings.auto_delete_days.toString()}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                auto_delete_days: parseInt(e.target.value),
                                            })
                                        }
                                        disabled={!settings.enabled}
                                    >
                                        <option value="1">1 day</option>
                                        <option value="3">3 days</option>
                                        <option value="7">7 days</option>
                                        <option value="14">14 days</option>
                                        <option value="30">30 days</option>
                                        <option value="0">Never</option>
                                    </Select>
                                    <p className="text-xs text-foreground-muted mt-2">
                                        Automatically delete preview deployments after this many days of inactivity
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Resource Limits */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Resource Limits</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        CPU Limit
                                    </label>
                                    <Select
                                        value={settings.resource_limits.cpu}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                resource_limits: {
                                                    ...settings.resource_limits,
                                                    cpu: e.target.value,
                                                },
                                            })
                                        }
                                        disabled={!settings.enabled}
                                    >
                                        <option value="0.5">0.5 cores</option>
                                        <option value="1">1 core</option>
                                        <option value="2">2 cores</option>
                                        <option value="4">4 cores</option>
                                    </Select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Memory Limit
                                    </label>
                                    <Select
                                        value={settings.resource_limits.memory}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                resource_limits: {
                                                    ...settings.resource_limits,
                                                    memory: e.target.value,
                                                },
                                            })
                                        }
                                        disabled={!settings.enabled}
                                    >
                                        <option value="256M">256 MB</option>
                                        <option value="512M">512 MB</option>
                                        <option value="1G">1 GB</option>
                                        <option value="2G">2 GB</option>
                                        <option value="4G">4 GB</option>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Actions */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="text-sm font-semibold text-foreground mb-3">Actions</h3>
                            <div className="space-y-2">
                                <Button
                                    variant="primary"
                                    className="w-full"
                                    onClick={handleSave}
                                    disabled={isSaving}
                                >
                                    <Save className="mr-2 h-4 w-4" />
                                    {isSaving ? 'Saving...' : 'Save Settings'}
                                </Button>
                                <Link href={`/applications/${application.uuid}/previews`}>
                                    <Button variant="secondary" className="w-full">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Info Card */}
                    <Card className="border-info/50 bg-info/5">
                        <CardContent className="p-6">
                            <div className="flex gap-3">
                                <Info className="h-5 w-5 text-info flex-shrink-0 mt-0.5" />
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground mb-2">About Preview Deployments</h3>
                                    <p className="text-sm text-foreground-muted mb-3">
                                        Preview deployments create temporary environments for each pull request, making it easy to review changes before merging.
                                    </p>
                                    <ul className="text-sm text-foreground-muted space-y-1 list-disc list-inside">
                                        <li>Automatically deployed on PR creation</li>
                                        <li>Isolated environments per PR</li>
                                        <li>Auto-cleanup when PR is merged/closed</li>
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Warning Card */}
                    {!settings.enabled && (
                        <Card className="border-warning/50 bg-warning/5">
                            <CardContent className="p-6">
                                <div className="flex gap-3">
                                    <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h3 className="text-sm font-semibold text-foreground mb-1">Preview Deployments Disabled</h3>
                                        <p className="text-sm text-foreground-muted">
                                            Enable preview deployments to automatically create preview environments for pull requests.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
