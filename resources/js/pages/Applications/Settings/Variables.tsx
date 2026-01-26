import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input } from '@/components/ui';
import { Key, Plus, Trash2, Eye, EyeOff, Download, Upload, Save } from 'lucide-react';
import type { Application } from '@/types';

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
    created_at?: string;
}

export default function ApplicationVariables({ application, variables: propVariables, projectUuid, environmentUuid }: Props) {
    const [variables, setVariables] = React.useState<EnvironmentVariable[]>(propVariables || []);
    const [revealedVars, setRevealedVars] = React.useState<Set<string | number>>(new Set());
    const [isSaving, setIsSaving] = React.useState(false);

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
                    is_build_time: v.is_buildtime ?? false,
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

    const handleExport = () => {
        const content = variables
            .filter(v => v.key.trim() !== '')
            .map(v => `${v.key}=${v.value}`)
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
