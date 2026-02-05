import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, useConfirm } from '@/components/ui';
import { Key, Plus, Trash2, Eye, EyeOff, Download, Upload, Save, FileSearch } from 'lucide-react';
import type { Application } from '@/types';
import axios from 'axios';

interface Props {
    application: Application;
    variables?: EnvironmentVariable[];
    projectUuid?: string;
    environmentUuid?: string;
}

// Structure from backend (matches routes/web.php)
interface EnvironmentVariable {
    id: number | string;
    key: string;
    value: string;
    is_multiline?: boolean;
    is_literal?: boolean;
    is_runtime?: boolean;
    is_buildtime?: boolean;
    is_required?: boolean;
    source_template?: string | null;
    created_at?: string;
}

export default function ApplicationVariables({ application, variables: propVariables, projectUuid, environmentUuid }: Props) {
    const confirm = useConfirm();
    const [variables, setVariables] = React.useState<EnvironmentVariable[]>(propVariables || []);
    const [revealedVars, setRevealedVars] = React.useState<Set<string | number>>(new Set());
    const [isSaving, setIsSaving] = React.useState(false);
    const [isScanning, setIsScanning] = React.useState(false);
    const [scanResult, setScanResult] = React.useState<string | null>(null);

    const handleAddVariable = () => {
        const newVar: EnvironmentVariable = {
            id: `new-${Date.now()}`,
            key: '',
            value: '',
            is_buildtime: false,
            is_runtime: true,
        };
        setVariables([...variables, newVar]);
    };

    const handleRemoveVariable = (id: number | string) => {
        setVariables(variables.filter(v => v.id !== id));
    };

    const handleUpdateVariable = (id: number | string, field: keyof EnvironmentVariable, value: string | boolean) => {
        setVariables(variables.map(v => v.id === id ? { ...v, [field]: value } : v));
    };

    const handleToggleReveal = (id: number | string) => {
        const newRevealed = new Set(revealedVars);
        if (newRevealed.has(id)) {
            newRevealed.delete(id);
        } else {
            newRevealed.add(id);
        }
        setRevealedVars(newRevealed);
    };

    const handleSave = async () => {
        setIsSaving(true);
        try {
            // Filter out empty variables and send bulk update
            const validVariables = variables
                .filter(v => v.key.trim() !== '')
                .map(v => ({
                    key: v.key,
                    value: v.value,
                    is_buildtime: v.is_buildtime ?? false,
                }));

            router.patch(`/applications/${application.uuid}/envs/bulk`, {
                variables: validVariables,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                },
                onError: () => {
                    setIsSaving(false);
                },
            });
        } catch (error) {
            setIsSaving(false);
        }
    };

    const handleExport = async () => {
        // Security warning before exporting sensitive data
        const confirmed = await confirm({
            title: 'Export Environment Variables',
            description: 'This will export all environment variables including sensitive values (passwords, API keys, secrets) in plain text. Make sure you store the exported file securely and delete it when no longer needed.',
            confirmText: 'Export',
            variant: 'warning',
        });

        if (!confirmed) return;

        const content = variables
            .filter(v => v.key.trim() !== '')
            .map(v => `${v.key}=${v.value ?? ''}`)
            .join('\n');

        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${application.name}-env-vars.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    const handleImport = () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.txt,.env';
        input.onchange = (e: Event) => {
            const target = e.target as HTMLInputElement;
            const file = target.files?.[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const content = event.target?.result as string;
                const lines = content.split('\n');
                const imported: EnvironmentVariable[] = [];

                lines.forEach(line => {
                    const trimmed = line.trim();
                    if (trimmed && !trimmed.startsWith('#')) {
                        const [key, ...valueParts] = trimmed.split('=');
                        if (key) {
                            imported.push({
                                id: `imported-${Date.now()}-${Math.random()}`,
                                key: key.trim(),
                                value: valueParts.join('=').trim(),
                                is_buildtime: false,
                                is_runtime: true,
                            });
                        }
                    }
                });

                setVariables([...variables, ...imported]);
            };
            reader.readAsText(file);
        };
        input.click();
    };

    const handleScanEnvExample = async () => {
        setIsScanning(true);
        setScanResult(null);
        try {
            const response = await axios.post(`/web-api/applications/${application.uuid}/scan-env-example`);
            const data = response.data;
            const created = data.created?.length || 0;
            const skipped = data.skipped?.length || 0;
            const required = data.required?.length || 0;
            const framework = data.framework;

            if (created === 0 && skipped === 0) {
                setScanResult('No .env.example file found in the repository.');
            } else {
                const parts = [];
                if (created > 0) parts.push(`${created} new variable${created > 1 ? 's' : ''} imported`);
                if (skipped > 0) parts.push(`${skipped} skipped (already defined)`);
                if (required > 0) parts.push(`${required} require${required > 1 ? '' : 's'} a value`);
                if (framework) parts.push(`framework: ${framework}`);
                setScanResult(parts.join(', '));

                // Reload the page to show new variables
                if (created > 0) {
                    router.reload({ only: ['variables'] });
                }
            }
        } catch {
            setScanResult('Failed to scan repository. Check server connection.');
        } finally {
            setIsScanning(false);
        }
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Environment Variables' },
    ];

    return (
        <AppLayout title="Environment Variables" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Key className="h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-foreground">Environment Variables</h1>
                        <p className="text-foreground-muted">
                            Manage environment variables for your application
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={handleScanEnvExample}
                            disabled={isScanning}
                        >
                            <FileSearch className="mr-2 h-4 w-4" />
                            {isScanning ? 'Scanning...' : 'Scan .env.example'}
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={handleImport}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={handleExport}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                        <Button
                            variant="default"
                            onClick={handleSave}
                            disabled={isSaving}
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {isSaving ? 'Saving...' : 'Save All'}
                        </Button>
                    </div>
                </div>
            </div>

            {/* Scan Result */}
            {scanResult && (
                <div className="mb-4 rounded-lg border border-info/50 bg-info/5 p-3 text-sm text-foreground-muted">
                    <div className="flex items-center gap-2">
                        <FileSearch className="h-4 w-4 text-info" />
                        {scanResult}
                    </div>
                </div>
            )}

            {/* Variables List */}
            <Card>
                <CardContent className="p-6">
                    <div className="space-y-4">
                        {/* Header Row */}
                        <div className="grid grid-cols-12 gap-4 pb-3 border-b border-border">
                            <div className="col-span-4 text-sm font-medium text-foreground-muted">Key</div>
                            <div className="col-span-5 text-sm font-medium text-foreground-muted">Value</div>
                            <div className="col-span-2 text-sm font-medium text-foreground-muted">Options</div>
                            <div className="col-span-1"></div>
                        </div>

                        {/* Empty state */}
                        {variables.length === 0 && (
                            <div className="text-center py-8 text-foreground-muted">
                                <Key className="mx-auto h-12 w-12 opacity-50 mb-3" />
                                <p>No environment variables configured.</p>
                                <p className="text-sm">Click "Add Variable" to create one.</p>
                            </div>
                        )}

                        {/* Variables */}
                        {variables.map((variable) => (
                            <div key={variable.id} className="grid grid-cols-12 gap-4 items-start">
                                <div className="col-span-4">
                                    <Input
                                        value={variable.key}
                                        onChange={(e) => handleUpdateVariable(variable.id, 'key', e.target.value)}
                                        placeholder="VARIABLE_NAME"
                                        className="font-mono text-sm"
                                    />
                                    <div className="mt-1 flex gap-1">
                                        {variable.source_template && (
                                            <span className="inline-flex items-center rounded-full bg-info/10 px-2 py-0.5 text-[10px] font-medium text-info">
                                                .env.example
                                            </span>
                                        )}
                                        {variable.is_required && !variable.value && (
                                            <span className="inline-flex items-center rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-medium text-warning">
                                                required
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="col-span-5">
                                    <div className="relative">
                                        <Input
                                            type={revealedVars.has(variable.id) ? 'text' : 'password'}
                                            value={variable.value}
                                            onChange={(e) => handleUpdateVariable(variable.id, 'value', e.target.value)}
                                            placeholder="value"
                                            className="font-mono text-sm pr-10"
                                        />
                                        <button
                                            onClick={() => handleToggleReveal(variable.id)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                        >
                                            {revealedVars.has(variable.id) ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                </div>
                                <div className="col-span-2 flex gap-2">
                                    <label className="flex items-center gap-1.5 text-xs text-foreground-muted cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={variable.is_buildtime ?? false}
                                            onChange={(e) => handleUpdateVariable(variable.id, 'is_buildtime', e.target.checked)}
                                            className="rounded border-border"
                                        />
                                        Build
                                    </label>
                                </div>
                                <div className="col-span-1 flex justify-end">
                                    <Button
                                        size="sm"
                                        variant="danger"
                                        onClick={() => handleRemoveVariable(variable.id)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {/* Add Variable Button */}
                        <Button
                            variant="secondary"
                            onClick={handleAddVariable}
                            className="w-full"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Variable
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Help Text */}
            <Card className="mt-4 border-info/50 bg-info/5">
                <CardContent className="p-4">
                    <p className="text-sm text-foreground-muted">
                        <strong>Build:</strong> Available during build process (docker build). Otherwise available at runtime.
                    </p>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
