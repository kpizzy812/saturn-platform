import * as React from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, useConfirm } from '@/components/ui';
import {
    Key,
    Plus,
    Trash2,
    Eye,
    EyeOff,
    Download,
    Save,
    FileSearch,
    PackagePlus,
    GitCompare,
} from 'lucide-react';
import type { Application } from '@/types';
import axios from 'axios';
import { BulkImport } from './BulkImport';
import { EnvDiff } from './EnvDiff';

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

interface Props {
    application: Application;
    variables?: EnvironmentVariable[];
    projectUuid?: string;
    projectName?: string;
    environmentUuid?: string;
    environmentName?: string;
}

export default function EnvironmentVariablesPage({
    application,
    variables: propVariables,
    projectUuid,
    projectName,
}: Props) {
    const confirm = useConfirm();
    const [variables, setVariables] = React.useState<EnvironmentVariable[]>(propVariables || []);
    const [revealedVars, setRevealedVars] = React.useState<Set<string | number>>(new Set());
    const [isSaving, setIsSaving] = React.useState(false);
    const [isScanning, setIsScanning] = React.useState(false);
    const [scanResult, setScanResult] = React.useState<string | null>(null);
    const [isBulkImportOpen, setIsBulkImportOpen] = React.useState(false);
    const [isDiffOpen, setIsDiffOpen] = React.useState(false);

    // Sync when Inertia reloads
    React.useEffect(() => {
        setVariables(propVariables || []);
    }, [propVariables]);

    const existingKeys = variables.map((v) => v.key).filter(Boolean);

    const handleAddVariable = () => {
        setVariables((prev) => [
            ...prev,
            {
                id: `new-${Date.now()}`,
                key: '',
                value: '',
                is_buildtime: false,
                is_runtime: true,
            },
        ]);
    };

    const handleRemoveVariable = (id: number | string) => {
        setVariables((prev) => prev.filter((v) => v.id !== id));
    };

    const handleUpdateVariable = (
        id: number | string,
        field: keyof EnvironmentVariable,
        value: string | boolean,
    ) => {
        setVariables((prev) => prev.map((v) => (v.id === id ? { ...v, [field]: value } : v)));
    };

    const handleToggleReveal = (id: number | string) => {
        setRevealedVars((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const handleSave = () => {
        setIsSaving(true);
        const validVariables = variables
            .filter((v) => v.key.trim() !== '')
            .map((v) => ({ key: v.key, value: v.value, is_buildtime: v.is_buildtime ?? false }));

        router.patch(
            `/applications/${application.uuid}/envs/bulk`,
            { variables: validVariables },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    };

    const handleExport = async () => {
        const confirmed = await confirm({
            title: 'Export Environment Variables',
            description:
                'This will export all environment variables including sensitive values in plain text. Store the file securely and delete it when no longer needed.',
            confirmText: 'Export',
            variant: 'warning',
        });
        if (!confirmed) return;

        const content = variables
            .filter((v) => v.key.trim() !== '')
            .map((v) => `${v.key}=${v.value ?? ''}`)
            .join('\n');

        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${application.name}.env`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const handleScanEnvExample = async () => {
        setIsScanning(true);
        setScanResult(null);
        try {
            const response = await axios.post(
                `/web-api/applications/${application.uuid}/scan-env-example`,
            );
            const data = response.data as {
                created?: unknown[];
                skipped?: unknown[];
                required?: unknown[];
                framework?: string;
            };
            const created = data.created?.length || 0;
            const skipped = data.skipped?.length || 0;

            if (created === 0 && skipped === 0) {
                setScanResult('No .env.example file found in the repository.');
            } else {
                const parts: string[] = [];
                if (created > 0) parts.push(`${created} new variable${created > 1 ? 's' : ''} imported`);
                if (skipped > 0) parts.push(`${skipped} skipped (already defined)`);
                setScanResult(parts.join(', '));
                if (created > 0) router.reload({ only: ['variables'] });
            }
        } catch {
            setScanResult('Failed to scan repository. Check server connection.');
        } finally {
            setIsScanning(false);
        }
    };

    const handleBulkImported = () => {
        router.reload({ only: ['variables'] });
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: projectName || 'Project', href: `/projects/${projectUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Environment Variables' },
    ];

    return (
        <AppLayout title="Environment Variables" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="mb-4 flex flex-wrap items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Key className="h-6 w-6" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-bold text-foreground">Environment Variables</h1>
                        <p className="text-foreground-muted">
                            Manage environment variables for {application.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
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
                            onClick={() => setIsBulkImportOpen(true)}
                        >
                            <PackagePlus className="mr-2 h-4 w-4" />
                            Bulk Import
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setIsDiffOpen(true)}>
                            <GitCompare className="mr-2 h-4 w-4" />
                            Compare
                        </Button>
                        <Button size="sm" variant="secondary" onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving}>
                            <Save className="mr-2 h-4 w-4" />
                            {isSaving ? 'Saving...' : 'Save All'}
                        </Button>
                    </div>
                </div>
            </div>

            {/* Scan result notice */}
            {scanResult && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-info/50 bg-info/5 p-3 text-sm text-foreground-muted">
                    <FileSearch className="h-4 w-4 shrink-0 text-info" />
                    {scanResult}
                </div>
            )}

            {/* Variables list */}
            <Card>
                <CardContent className="p-6">
                    <div className="space-y-4">
                        {/* Column headers */}
                        <div className="grid grid-cols-12 gap-4 border-b border-border pb-3">
                            <div className="col-span-5 text-sm font-medium text-foreground-muted">Key</div>
                            <div className="col-span-4 text-sm font-medium text-foreground-muted">Value</div>
                            <div className="col-span-2 text-sm font-medium text-foreground-muted">Options</div>
                            <div className="col-span-1" />
                        </div>

                        {/* Empty state */}
                        {variables.length === 0 && (
                            <div className="py-8 text-center text-foreground-muted">
                                <Key className="mx-auto mb-3 h-12 w-12 opacity-50" />
                                <p>No environment variables configured.</p>
                                <p className="text-sm">Click "Add Variable" or use Bulk Import.</p>
                            </div>
                        )}

                        {/* Variable rows */}
                        {variables.map((variable) => (
                            <div key={variable.id} className="grid grid-cols-12 items-start gap-4">
                                <div className="col-span-5">
                                    <Input
                                        value={variable.key}
                                        onChange={(e) =>
                                            handleUpdateVariable(variable.id, 'key', e.target.value)
                                        }
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
                                <div className="col-span-4">
                                    <div className="relative">
                                        <Input
                                            type={revealedVars.has(variable.id) ? 'text' : 'password'}
                                            value={variable.value}
                                            onChange={(e) =>
                                                handleUpdateVariable(variable.id, 'value', e.target.value)
                                            }
                                            placeholder="value"
                                            className="pr-10 font-mono text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleToggleReveal(variable.id)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                            aria-label={
                                                revealedVars.has(variable.id) ? 'Hide value' : 'Show value'
                                            }
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
                                    <label className="flex cursor-pointer items-center gap-1.5 text-xs text-foreground-muted">
                                        <input
                                            type="checkbox"
                                            checked={variable.is_buildtime ?? false}
                                            onChange={(e) =>
                                                handleUpdateVariable(
                                                    variable.id,
                                                    'is_buildtime',
                                                    e.target.checked,
                                                )
                                            }
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
                                        aria-label="Remove variable"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {/* Add variable */}
                        <Button variant="secondary" onClick={handleAddVariable} className="w-full">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Variable
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Help text */}
            <Card className="mt-4 border-info/50 bg-info/5">
                <CardContent className="p-4">
                    <p className="text-sm text-foreground-muted">
                        <strong>Build:</strong> Available during the Docker build process. Runtime variables
                        are injected when the container starts.
                    </p>
                </CardContent>
            </Card>

            <BulkImport
                isOpen={isBulkImportOpen}
                onClose={() => setIsBulkImportOpen(false)}
                applicationUuid={application.uuid}
                existingKeys={existingKeys}
                onImported={handleBulkImported}
            />

            <EnvDiff
                isOpen={isDiffOpen}
                onClose={() => setIsDiffOpen(false)}
                application={application}
            />
        </AppLayout>
    );
}
