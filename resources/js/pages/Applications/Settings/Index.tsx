import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import { Settings as SettingsIcon, Save, Info, AlertCircle } from 'lucide-react';
import type { Application } from '@/types';

interface Props {
    application: Application;
    projectUuid?: string;
    environmentUuid?: string;
}

export default function ApplicationSettings({ application, projectUuid, environmentUuid }: Props) {
    const [settings, setSettings] = React.useState({
        name: application.name || '',
        description: application.description || '',
        base_directory: application.base_directory || '/',
        build_command: application.build_command || '',
        install_command: application.install_command || '',
        start_command: application.start_command || '',
        health_check_path: application.health_check_path || '',
        health_check_interval: application.health_check_interval || 30,
        cpu_limit: application.limits_cpus || '',
        memory_limit: application.limits_memory || '',
        build_pack: application.build_pack || 'nixpacks',
        auto_deploy: application.is_auto_deploy_enabled ?? true,
        deploy_on_push: application.is_auto_deploy_enabled ?? true,
    });
    const [isSaving, setIsSaving] = React.useState(false);

    const handleSave = () => {
        console.log('handleSave called, settings:', settings);
        setIsSaving(true);
        router.patch(`/applications/${application.uuid}/settings`, settings, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Save success');
                setIsSaving(false);
            },
            onError: (errors) => {
                console.error('Save error:', errors);
                setIsSaving(false);
            },
        });
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Settings' },
    ];

    return (
        <AppLayout title="Application Settings" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <SettingsIcon className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Application Settings</h1>
                        <p className="text-foreground-muted">
                            Configure build, deploy, and resource settings for your application
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
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Application Name
                                    </label>
                                    <Input
                                        value={settings.name}
                                        onChange={(e) => setSettings({ ...settings, name: e.target.value })}
                                        placeholder="My Application"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Description
                                    </label>
                                    <Input
                                        value={settings.description}
                                        onChange={(e) => setSettings({ ...settings, description: e.target.value })}
                                        placeholder="A brief description of your application"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Build Settings */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Build Settings</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Build Pack
                                    </label>
                                    <Select
                                        value={settings.build_pack}
                                        onChange={(e) => setSettings({ ...settings, build_pack: e.target.value as 'nixpacks' | 'dockerfile' | 'dockercompose' | 'dockerimage' })}
                                    >
                                        <option value="nixpacks">Nixpacks</option>
                                        <option value="dockerfile">Dockerfile</option>
                                        <option value="dockercompose">Docker Compose</option>
                                        <option value="dockerimage">Docker Image</option>
                                    </Select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Base Directory
                                    </label>
                                    <Input
                                        value={settings.base_directory}
                                        onChange={(e) => setSettings({ ...settings, base_directory: e.target.value })}
                                        placeholder="/"
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Root directory relative to repository root. For monorepos, use e.g. <code className="bg-muted px-1 rounded">apps/api</code>
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Install Command
                                    </label>
                                    <Input
                                        value={settings.install_command}
                                        onChange={(e) => setSettings({ ...settings, install_command: e.target.value })}
                                        placeholder="npm install"
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Leave empty to use build pack defaults
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Build Command
                                    </label>
                                    <Input
                                        value={settings.build_command}
                                        onChange={(e) => setSettings({ ...settings, build_command: e.target.value })}
                                        placeholder="npm run build"
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Leave empty to use build pack defaults
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deploy Settings */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Deploy Settings</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Start Command
                                    </label>
                                    <Input
                                        value={settings.start_command}
                                        onChange={(e) => setSettings({ ...settings, start_command: e.target.value })}
                                        placeholder="npm start"
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Leave empty to use build pack defaults
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Health Check Path
                                    </label>
                                    <Input
                                        value={settings.health_check_path}
                                        onChange={(e) => setSettings({ ...settings, health_check_path: e.target.value })}
                                        placeholder="/health"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Health Check Interval (seconds)
                                    </label>
                                    <Input
                                        type="number"
                                        value={settings.health_check_interval}
                                        onChange={(e) => setSettings({ ...settings, health_check_interval: parseInt(e.target.value) })}
                                        min="10"
                                        max="300"
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <label className="text-sm font-medium text-foreground mb-1 block">
                                            Auto Deploy
                                        </label>
                                        <p className="text-sm text-foreground-muted">
                                            Automatically deploy when changes are pushed to the repository
                                        </p>
                                    </div>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            className="peer sr-only"
                                            checked={settings.deploy_on_push}
                                            onChange={(e) => setSettings({ ...settings, deploy_on_push: e.target.checked })}
                                        />
                                        <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                    </label>
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
                                        value={settings.cpu_limit}
                                        onChange={(e) => setSettings({ ...settings, cpu_limit: e.target.value })}
                                    >
                                        <option value="0.5">0.5 cores</option>
                                        <option value="1">1 core</option>
                                        <option value="2">2 cores</option>
                                        <option value="4">4 cores</option>
                                        <option value="8">8 cores</option>
                                    </Select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground mb-2 block">
                                        Memory Limit
                                    </label>
                                    <Select
                                        value={settings.memory_limit}
                                        onChange={(e) => setSettings({ ...settings, memory_limit: e.target.value })}
                                    >
                                        <option value="256M">256 MB</option>
                                        <option value="512M">512 MB</option>
                                        <option value="1G">1 GB</option>
                                        <option value="2G">2 GB</option>
                                        <option value="4G">4 GB</option>
                                        <option value="8G">8 GB</option>
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
                                <button
                                    type="button"
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover disabled:opacity-50"
                                    onClick={() => {
                                        alert('Button clicked! Settings: ' + JSON.stringify(settings));
                                        handleSave();
                                    }}
                                    disabled={isSaving}
                                >
                                    <Save className="h-4 w-4" />
                                    {isSaving ? 'Saving...' : 'Save Settings'}
                                </button>
                                <Link href={`/applications/${application.uuid}`}>
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
                                    <h3 className="text-sm font-semibold text-foreground mb-2">Build & Deploy</h3>
                                    <p className="text-sm text-foreground-muted mb-3">
                                        These settings control how your application is built and deployed.
                                    </p>
                                    <ul className="text-sm text-foreground-muted space-y-1 list-disc list-inside">
                                        <li>Build commands run during the build phase</li>
                                        <li>Start command runs when container starts</li>
                                        <li>Health checks monitor application status</li>
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Warning Card */}
                    <Card className="border-warning/50 bg-warning/5">
                        <CardContent className="p-6">
                            <div className="flex gap-3">
                                <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground mb-1">Restart Required</h3>
                                    <p className="text-sm text-foreground-muted">
                                        Changes to resource limits require application restart to take effect.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
