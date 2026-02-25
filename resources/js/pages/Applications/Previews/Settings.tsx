import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input } from '@/components/ui';
import {
    Settings as SettingsIcon,
    Info,
    Save,
    AlertCircle,
} from 'lucide-react';
import type { Application } from '@/types';

// Settings structure from backend (matches routes/web.php)
interface PreviewSettings {
    preview_url_template: string | null;
    instant_deploy_preview: boolean;
}

interface Props {
    application: Application;
    settings?: PreviewSettings;
    projectUuid?: string;
    projectName?: string;
    environmentUuid?: string;
    environmentName?: string;
}

// Default settings when none provided
const DEFAULT_SETTINGS: PreviewSettings = {
    preview_url_template: null,
    instant_deploy_preview: false,
};

export default function PreviewSettingsPage({ application, settings: propSettings, projectUuid, projectName }: Props) {
    const [settings, setSettings] = React.useState<PreviewSettings>(
        propSettings || DEFAULT_SETTINGS
    );
    const [isSaving, setIsSaving] = React.useState(false);

    const handleSave = async () => {
        setIsSaving(true);
        try {
            router.patch(`/api/v1/applications/${application.uuid}`, {
                preview_url_template: settings.preview_url_template,
                instant_deploy: settings.instant_deploy_preview,
            }, {
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
        ...(projectUuid ? [{ label: projectName || 'Project', href: `/projects/${projectUuid}` }] : []),
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
                                {/* Instant Deploy on PR */}
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <label className="text-sm font-medium text-foreground mb-1 block">
                                            Instant Deploy on Pull Request
                                        </label>
                                        <p className="text-sm text-foreground-muted">
                                            Automatically create preview deployments when PRs are opened
                                        </p>
                                    </div>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            className="peer sr-only"
                                            checked={settings.instant_deploy_preview}
                                            onChange={(e) =>
                                                setSettings({ ...settings, instant_deploy_preview: e.target.checked })
                                            }
                                        />
                                        <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
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
                                        value={settings.preview_url_template || ''}
                                        onChange={(e) =>
                                            setSettings({ ...settings, preview_url_template: e.target.value || null })
                                        }
                                        placeholder="pr-{{pr_id}}-{{app_name}}.preview.your-domain.com"
                                    />
                                    <p className="text-xs text-foreground-muted mt-2">
                                        Available variables: <code className="text-xs">{'{{pr_id}}'}</code>,{' '}
                                        <code className="text-xs">{'{{app_name}}'}</code>,{' '}
                                        <code className="text-xs">{'{{branch}}'}</code>
                                    </p>
                                </div>
                                {settings.preview_url_template && (
                                    <div className="rounded-lg border border-info/50 bg-info/5 p-4">
                                        <div className="flex gap-3">
                                            <Info className="h-5 w-5 text-info flex-shrink-0 mt-0.5" />
                                            <div>
                                                <h4 className="text-sm font-semibold text-foreground mb-1">Example URL</h4>
                                                <p className="text-sm text-foreground-muted">
                                                    PR #42 would be deployed to:{' '}
                                                    <code className="text-xs bg-background-tertiary px-1.5 py-0.5 rounded">
                                                        {settings.preview_url_template
                                                            .replace('{{pr_id}}', '42')
                                                            .replace('{{app_name}}', application.name.toLowerCase())
                                                            .replace('{{branch}}', 'feature-branch')}
                                                    </code>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
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
                                    variant="default"
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
                    {!settings.instant_deploy_preview && (
                        <Card className="border-warning/50 bg-warning/5">
                            <CardContent className="p-6">
                                <div className="flex gap-3">
                                    <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h3 className="text-sm font-semibold text-foreground mb-1">Instant Deploy Disabled</h3>
                                        <p className="text-sm text-foreground-muted">
                                            Enable instant deploy to automatically create preview environments for pull requests.
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
