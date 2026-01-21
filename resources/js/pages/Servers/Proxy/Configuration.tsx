import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Alert } from '@/components/ui';
import { Save, RotateCcw, FileCode, CheckCircle, AlertTriangle } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
    configuration: string;
    filePath: string;
}

export default function ProxyConfiguration({ server, configuration, filePath }: Props) {
    const [config, setConfig] = useState(configuration);
    const [isDirty, setIsDirty] = useState(false);
    const [validationError, setValidationError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const handleConfigChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        setConfig(e.target.value);
        setIsDirty(true);
        setValidationError(null);
    };

    const handleSave = () => {
        // Basic validation for YAML/JSON
        try {
            // Try to validate basic structure
            const lines = config.split('\n');
            if (lines.length === 0 || !config.trim()) {
                setValidationError('Configuration cannot be empty');
                return;
            }

            setIsSaving(true);
            router.post(`/servers/${server.uuid}/proxy/configuration`, {
                configuration: config,
            }, {
                onSuccess: () => {
                    setIsDirty(false);
                    setIsSaving(false);
                },
                onError: () => {
                    setIsSaving(false);
                },
            });
        } catch (error) {
            setValidationError('Invalid configuration format');
        }
    };

    const handleReset = () => {
        if (confirm('Are you sure you want to reset to the original configuration? All changes will be lost.')) {
            setConfig(configuration);
            setIsDirty(false);
            setValidationError(null);
        }
    };

    const handleResetToDefault = () => {
        if (confirm('Are you sure you want to reset to the default configuration? This will overwrite all custom settings.')) {
            router.post(`/servers/${server.uuid}/proxy/configuration/reset`, {}, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout
            title={`Proxy Configuration - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Proxy', href: `/servers/${server.uuid}/proxy` },
                { label: 'Configuration' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <FileCode className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Proxy Configuration</h1>
                        <p className="text-foreground-muted">{filePath}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleResetToDefault}
                    >
                        <RotateCcw className="mr-2 h-4 w-4" />
                        Reset to Default
                    </Button>
                    {isDirty && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                        >
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Discard Changes
                        </Button>
                    )}
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={handleSave}
                        disabled={!isDirty || isSaving}
                    >
                        <Save className="mr-2 h-4 w-4" />
                        {isSaving ? 'Saving...' : 'Save & Apply'}
                    </Button>
                </div>
            </div>

            {/* Status Alerts */}
            {isDirty && (
                <Alert variant="warning" className="mb-4">
                    <AlertTriangle className="h-4 w-4" />
                    <span>You have unsaved changes. Don't forget to save and apply your configuration.</span>
                </Alert>
            )}

            {validationError && (
                <Alert variant="danger" className="mb-4">
                    <AlertTriangle className="h-4 w-4" />
                    <span>{validationError}</span>
                </Alert>
            )}

            {/* Configuration Editor */}
            <Card>
                <CardHeader>
                    <CardTitle>Docker Compose Configuration</CardTitle>
                    <p className="text-sm text-foreground-muted">
                        Edit the proxy configuration. Changes will be applied when you save.
                    </p>
                </CardHeader>
                <CardContent>
                    <div className="relative">
                        <textarea
                            value={config}
                            onChange={handleConfigChange}
                            className="font-mono text-sm w-full rounded-lg border border-border bg-background p-4 text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            rows={30}
                            spellCheck={false}
                        />
                        <div className="mt-2 flex items-center justify-between text-xs text-foreground-muted">
                            <span>{config.split('\n').length} lines</span>
                            <span>{config.length} characters</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Help Card */}
            <div className="mt-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Configuration Help</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-start gap-2">
                            <CheckCircle className="mt-0.5 h-4 w-4 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Syntax Validation</p>
                                <p className="text-sm text-foreground-muted">
                                    Make sure your YAML syntax is correct before saving.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-2">
                            <CheckCircle className="mt-0.5 h-4 w-4 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Apply Changes</p>
                                <p className="text-sm text-foreground-muted">
                                    Saving will restart the proxy to apply changes. This may cause brief downtime.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-2">
                            <CheckCircle className="mt-0.5 h-4 w-4 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Backup</p>
                                <p className="text-sm text-foreground-muted">
                                    A backup of your configuration is created before applying changes.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
