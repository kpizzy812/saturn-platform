import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input } from '@/components/ui';
import { GitBranch, Save, Info, AlertCircle, CheckCircle2, Settings as SettingsIcon } from 'lucide-react';
import type { Application, ApplicationSettings } from '@/types';

interface Props {
    application: Application;
    applicationSettings?: ApplicationSettings;
    projectUuid?: string;
    projectName?: string;
    environmentUuid?: string;
    environmentName?: string;
}

export default function CanarySettingsPage({
    application,
    applicationSettings,
    projectUuid,
    projectName,
    environmentUuid: _environmentUuid,
    environmentName: _environmentName,
}: Props) {
    const defaultSteps = [10, 25, 50, 100];

    const [settings, setSettings] = React.useState({
        canary_enabled: applicationSettings?.canary_enabled ?? false,
        canary_steps: applicationSettings?.canary_steps ?? defaultSteps,
        canary_step_minutes: applicationSettings?.canary_step_minutes ?? 5,
        canary_auto_promote: applicationSettings?.canary_auto_promote ?? true,
        canary_error_rate_threshold: applicationSettings?.canary_error_rate_threshold ?? 5,
    });

    const [stepsInput, setStepsInput] = React.useState(
        (applicationSettings?.canary_steps ?? defaultSteps).join(', '),
    );
    const [isSaving, setIsSaving] = React.useState(false);
    const [isTogglingCanary, setIsTogglingCanary] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [saveStatus, setSaveStatus] = React.useState<'idle' | 'success' | 'error'>('idle');

    React.useEffect(() => {
        if (saveStatus !== 'idle') {
            const timer = setTimeout(() => setSaveStatus('idle'), 3000);
            return () => clearTimeout(timer);
        }
    }, [saveStatus]);

    const parseSteps = (input: string): number[] =>
        input
            .split(',')
            .map((s) => parseInt(s.trim(), 10))
            .filter((n) => !isNaN(n) && n >= 1 && n <= 100);

    const handleToggleCanary = (enabled: boolean) => {
        setSettings((prev) => ({ ...prev, canary_enabled: enabled }));
        setIsTogglingCanary(true);
        router.patch(
            `/applications/${application.uuid}/settings`,
            { canary_enabled: enabled },
            {
                preserveScroll: true,
                onFinish: () => setIsTogglingCanary(false),
                onError: () => {
                    setSettings((prev) => ({ ...prev, canary_enabled: !enabled }));
                },
            },
        );
    };

    const handleSave = () => {
        const steps = parseSteps(stepsInput);
        const payload = {
            ...settings,
            canary_steps: steps.length > 0 ? steps : null,
        };
        setIsSaving(true);
        setErrors({});
        router.patch(`/applications/${application.uuid}/settings`, payload, {
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
        ...(projectUuid ? [{ label: projectName || 'Project', href: `/projects/${projectUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Settings', href: `/applications/${application.uuid}/settings` },
        { label: 'Canary Deployment' },
    ];

    return (
        <AppLayout title="Canary Deployment Settings" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <GitBranch className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Canary Deployment</h1>
                        <p className="text-foreground-muted">
                            Gradually shift traffic to new versions to reduce deployment risk
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
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-3 mb-4">
                                <GitBranch className="h-5 w-5 text-foreground-muted" />
                                <h2 className="text-lg font-semibold text-foreground">Canary Deployment</h2>
                            </div>

                            {/* Enable toggle */}
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <label className="text-sm font-medium text-foreground mb-1 block">
                                        Enable Canary Deployments
                                    </label>
                                    <p className="text-sm text-foreground-muted">
                                        Route a portion of traffic to the new version before full rollout
                                    </p>
                                </div>
                                <label
                                    className={`relative inline-flex items-center ${isTogglingCanary ? 'opacity-50 pointer-events-none' : 'cursor-pointer'}`}
                                >
                                    <input
                                        type="checkbox"
                                        className="peer sr-only"
                                        checked={settings.canary_enabled}
                                        disabled={isTogglingCanary}
                                        onChange={(e) => handleToggleCanary(e.target.checked)}
                                    />
                                    <div className="peer h-6 w-11 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                </label>
                            </div>

                            {/* Canary parameters — only shown when enabled */}
                            {settings.canary_enabled && (
                                <div className="border-t border-border pt-4 mt-4 space-y-4">
                                    {/* Traffic Steps */}
                                    <div>
                                        <label className="text-sm font-medium text-foreground mb-2 block">
                                            Traffic Steps (%)
                                        </label>
                                        <Input
                                            value={stepsInput}
                                            onChange={(e) => setStepsInput(e.target.value)}
                                            placeholder="10, 25, 50, 100"
                                        />
                                        <p className="text-xs text-foreground-muted mt-1">
                                            Comma-separated percentages for progressive traffic shifting (e.g.{' '}
                                            <code className="bg-muted px-1 rounded">10, 25, 50, 100</code>)
                                        </p>
                                        {errors['canary_steps'] && (
                                            <p className="text-xs text-red-400 mt-1">{errors['canary_steps']}</p>
                                        )}
                                    </div>

                                    {/* Step duration & error threshold */}
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="text-sm font-medium text-foreground mb-2 block">
                                                Minutes per Step
                                            </label>
                                            <Input
                                                type="number"
                                                value={settings.canary_step_minutes}
                                                onChange={(e) =>
                                                    setSettings({
                                                        ...settings,
                                                        canary_step_minutes: parseInt(e.target.value) || 5,
                                                    })
                                                }
                                                min="1"
                                                max="60"
                                            />
                                            <p className="text-xs text-foreground-muted mt-1">
                                                Time to observe each step (1–60 min)
                                            </p>
                                            {errors['canary_step_minutes'] && (
                                                <p className="text-xs text-red-400 mt-1">
                                                    {errors['canary_step_minutes']}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-foreground mb-2 block">
                                                Error Rate Threshold (%)
                                            </label>
                                            <Input
                                                type="number"
                                                value={settings.canary_error_rate_threshold}
                                                onChange={(e) =>
                                                    setSettings({
                                                        ...settings,
                                                        canary_error_rate_threshold: parseInt(e.target.value) || 5,
                                                    })
                                                }
                                                min="0"
                                                max="100"
                                            />
                                            <p className="text-xs text-foreground-muted mt-1">
                                                HTTP 5xx rate (%) that triggers rollback (0–100)
                                            </p>
                                            {errors['canary_error_rate_threshold'] && (
                                                <p className="text-xs text-red-400 mt-1">
                                                    {errors['canary_error_rate_threshold']}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Auto Promote */}
                                    <div className="border-t border-border pt-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex-1">
                                                <label className="text-sm font-medium text-foreground">
                                                    Auto Promote
                                                </label>
                                                <p className="text-xs text-foreground-muted mt-1">
                                                    Automatically advance to the next traffic step when metrics are
                                                    healthy
                                                </p>
                                            </div>
                                            <label className="relative inline-flex cursor-pointer items-center">
                                                <input
                                                    type="checkbox"
                                                    className="peer sr-only"
                                                    checked={settings.canary_auto_promote}
                                                    onChange={(e) =>
                                                        setSettings({
                                                            ...settings,
                                                            canary_auto_promote: e.target.checked,
                                                        })
                                                    }
                                                />
                                                <div className="peer h-5 w-9 rounded-full bg-gray-600 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full"></div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            )}
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
                                <Link href={`/applications/${application.uuid}/settings`}>
                                    <Button variant="secondary" className="w-full">
                                        Back to Settings
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Navigation */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="text-sm font-semibold text-foreground mb-3">Settings</h3>
                            <div className="space-y-1">
                                <Link
                                    href={`/applications/${application.uuid}/settings`}
                                    className="flex items-center gap-2 px-3 py-2 text-sm text-foreground-muted hover:text-foreground hover:bg-background-secondary rounded-lg transition-colors"
                                >
                                    <SettingsIcon className="h-4 w-4" />
                                    General Settings
                                </Link>
                                <Link
                                    href={`/applications/${application.uuid}/settings/canary`}
                                    className="flex items-center gap-2 px-3 py-2 text-sm text-foreground bg-background-secondary rounded-lg"
                                >
                                    <GitBranch className="h-4 w-4" />
                                    Canary Deployment
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Info */}
                    <Card className="border-info/50 bg-info/5">
                        <CardContent className="p-6">
                            <div className="flex gap-3">
                                <Info className="h-5 w-5 text-info flex-shrink-0 mt-0.5" />
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground mb-2">How Canary Works</h3>
                                    <ul className="text-sm text-foreground-muted space-y-1 list-disc list-inside">
                                        <li>New version receives a small % of traffic</li>
                                        <li>Metrics are checked at each step</li>
                                        <li>If error rate exceeds threshold, rollback triggers</li>
                                        <li>Otherwise traffic increases to the next step</li>
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
