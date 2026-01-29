import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import { Settings as SettingsIcon, Save, Info, AlertCircle, CheckCircle2, History } from 'lucide-react';
import type { Application } from '@/types';

interface ApplicationSettings {
    auto_rollback_enabled: boolean;
    rollback_validation_seconds: number;
    rollback_max_restarts: number;
    rollback_on_health_check_fail: boolean;
    rollback_on_crash_loop: boolean;
    docker_images_to_keep: number;
}

interface Props {
    application: Application;
    applicationSettings?: ApplicationSettings;
    projectUuid?: string;
    environmentUuid?: string;
}

export default function ApplicationSettingsPage({ application, applicationSettings, projectUuid, environmentUuid }: Props) {
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
        deploy_on_push: application.is_auto_deploy_enabled ?? true,
        // Rollback settings
        auto_rollback_enabled: applicationSettings?.auto_rollback_enabled ?? false,
        rollback_validation_seconds: applicationSettings?.rollback_validation_seconds ?? 300,
        rollback_max_restarts: applicationSettings?.rollback_max_restarts ?? 3,
        rollback_on_health_check_fail: applicationSettings?.rollback_on_health_check_fail ?? true,
        rollback_on_crash_loop: applicationSettings?.rollback_on_crash_loop ?? true,
        docker_images_to_keep: applicationSettings?.docker_images_to_keep ?? 2,
    });
    const [isSaving, setIsSaving] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [saveStatus, setSaveStatus] = React.useState<'idle' | 'success' | 'error'>('idle');

    React.useEffect(() => {
        if (saveStatus !== 'idle') {
            const timer = setTimeout(() => setSaveStatus('idle'), 3000);
            return () => clearTimeout(timer);
        }
    }, [saveStatus]);

    const handleSave = () => {
        setIsSaving(true);
        setErrors({});
        router.patch(`/applications/${application.uuid}/settings`, settings, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSaving(false);
                setSaveStatus('success');
            },
            onError: (validationErrors) => {
                setIsSaving(false);
                setErrors(validationErrors);
                setSaveStatus('error');
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

            {saveStatus === 'success' && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-green-500/50 bg-green-500/10 p-3 text-sm text-green-400">
                    <CheckCircle2 className="h-4 w-4" />
                    Settings saved successfully.
                </div>
            )}
            {saveStatus === 'error' && Object.keys(errors).length > 0 && (
                <div className="mb-4 rounded-lg border border-red-500/50 bg-red-500/10 p-3 text-sm text-red-400">
                    <div className="flex items-center gap-2 mb-1">
                        <AlertCircle className="h-4 w-4" />
                        Failed to save settings:
                    </div>
                    <ul className="list-disc list-inside ml-6">
                        {Object.entries(errors).map(([field, message]) => (
                            <li key={field}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

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
                                        min="1"
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

                    {/* Rollback Settings */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <History className="h-5 w-5 text-foreground-muted" />
                                <h2 className="text-lg font-semibold text-foreground">Rollback Settings</h2>
                            </div>
                            <div className="space-y-4">
                                {/* Auto Rollback Toggle */}
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <label className="text-sm font-medium text-foreground mb-1 block">
                                            Auto Rollback
                                        </label>
                                        <p className="text-sm text-foreground-muted">
                                            Automatically rollback to the previous version if deployment fails health checks
                                        </p>
                                    </div>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            className="peer sr-only"
                                            checked={settings.auto_rollback_enabled}
                                            onChange={(e) => setSettings({ ...settings, auto_rollback_enabled: e.target.checked })}
                                        />
                                        <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                    </label>
                                </div>

                                {/* Rollback conditions - only shown when auto rollback is enabled */}
                                {settings.auto_rollback_enabled && (
                                    <>
                                        <div className="border-t border-border pt-4">
                                            <p className="text-sm font-medium text-foreground mb-3">Rollback Triggers</p>
                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between">
                                                    <div className="flex-1">
                                                        <label className="text-sm text-foreground">
                                                            Rollback on Health Check Failure
                                                        </label>
                                                        <p className="text-xs text-foreground-muted">
                                                            Trigger rollback when container becomes unhealthy
                                                        </p>
                                                    </div>
                                                    <label className="relative inline-flex cursor-pointer items-center">
                                                        <input
                                                            type="checkbox"
                                                            className="peer sr-only"
                                                            checked={settings.rollback_on_health_check_fail}
                                                            onChange={(e) => setSettings({ ...settings, rollback_on_health_check_fail: e.target.checked })}
                                                        />
                                                        <div className="peer h-5 w-9 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                                    </label>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <div className="flex-1">
                                                        <label className="text-sm text-foreground">
                                                            Rollback on Crash Loop
                                                        </label>
                                                        <p className="text-xs text-foreground-muted">
                                                            Trigger rollback when container keeps restarting
                                                        </p>
                                                    </div>
                                                    <label className="relative inline-flex cursor-pointer items-center">
                                                        <input
                                                            type="checkbox"
                                                            className="peer sr-only"
                                                            checked={settings.rollback_on_crash_loop}
                                                            onChange={(e) => setSettings({ ...settings, rollback_on_crash_loop: e.target.checked })}
                                                        />
                                                        <div className="peer h-5 w-9 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="border-t border-border pt-4">
                                            <p className="text-sm font-medium text-foreground mb-3">Rollback Parameters</p>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label className="text-sm text-foreground mb-2 block">
                                                        Validation Period (seconds)
                                                    </label>
                                                    <Input
                                                        type="number"
                                                        value={settings.rollback_validation_seconds}
                                                        onChange={(e) => setSettings({ ...settings, rollback_validation_seconds: parseInt(e.target.value) || 300 })}
                                                        min="60"
                                                        max="1800"
                                                    />
                                                    <p className="text-xs text-foreground-muted mt-1">
                                                        Time to monitor after deploy (60-1800s)
                                                    </p>
                                                </div>
                                                <div>
                                                    <label className="text-sm text-foreground mb-2 block">
                                                        Max Restarts Before Rollback
                                                    </label>
                                                    <Input
                                                        type="number"
                                                        value={settings.rollback_max_restarts}
                                                        onChange={(e) => setSettings({ ...settings, rollback_max_restarts: parseInt(e.target.value) || 3 })}
                                                        min="1"
                                                        max="10"
                                                    />
                                                    <p className="text-xs text-foreground-muted mt-1">
                                                        Crash loop threshold (1-10)
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                )}

                                {/* Docker Images Retention */}
                                <div className="border-t border-border pt-4">
                                    <div>
                                        <label className="text-sm font-medium text-foreground mb-2 block">
                                            Docker Images to Keep
                                        </label>
                                        <Select
                                            value={settings.docker_images_to_keep.toString()}
                                            onChange={(e) => setSettings({ ...settings, docker_images_to_keep: parseInt(e.target.value) })}
                                        >
                                            <option value="1">1 (current only)</option>
                                            <option value="2">2 (recommended)</option>
                                            <option value="3">3</option>
                                            <option value="5">5</option>
                                            <option value="10">10</option>
                                        </Select>
                                        <p className="text-xs text-foreground-muted mt-1">
                                            Number of previous images to keep for rollback. Higher values use more disk space.
                                        </p>
                                    </div>
                                </div>

                                {/* Manual Rollback Link */}
                                <div className="border-t border-border pt-4">
                                    <Link
                                        href={`/applications/${application.uuid}/rollback`}
                                        className="inline-flex items-center gap-2 text-sm text-primary hover:underline"
                                    >
                                        <History className="h-4 w-4" />
                                        View deployment history &amp; manual rollback
                                    </Link>
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
                                    onClick={handleSave}
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
