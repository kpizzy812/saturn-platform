import { useState, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button, Select, Badge } from '@/components/ui';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { ArrowLeft, ArrowRight, GitCompare, Plus, Minus, RefreshCw, Equal, Filter } from 'lucide-react';

interface EnvOption {
    id: number;
    uuid: string;
    name: string;
    type: string;
}

interface ResourceDiff {
    name: string;
    type: string;
    source_env: string;
    target_env: string;
    matched: boolean;
    only_in?: 'source' | 'target';
    diff: {
        added: string[];
        removed: string[];
        changed: string[];
        unchanged: string[];
    };
}

interface DiffSummary {
    total_resources: number;
    matched_resources: number;
    unmatched_resources: number;
    total_added: number;
    total_removed: number;
    total_changed: number;
    total_unchanged: number;
}

interface DiffResult {
    resources: ResourceDiff[];
    summary: DiffSummary;
}

interface Props {
    project: { id: number; uuid: string; name: string };
    environments: EnvOption[];
}

const typeFilterOptions = [
    { value: '', label: 'All Types' },
    { value: 'application', label: 'Applications' },
    { value: 'service', label: 'Services' },
    { value: 'database', label: 'Databases' },
];

export default function EnvDiff({ project, environments }: Props) {
    const [sourceEnvId, setSourceEnvId] = useState<string>('');
    const [targetEnvId, setTargetEnvId] = useState<string>('');
    const [resourceType, setResourceType] = useState<string>('');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<DiffResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const canCompare = sourceEnvId && targetEnvId && sourceEnvId !== targetEnvId;

    const handleCompare = useCallback(async () => {
        if (!canCompare) return;
        setLoading(true);
        setError(null);

        try {
            const res = await fetch(`/projects/${project.uuid}/env-diff/compare`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    source_env_id: parseInt(sourceEnvId),
                    target_env_id: parseInt(targetEnvId),
                    resource_type: resourceType || null,
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Failed to compare environments');
            }

            const data: DiffResult = await res.json();
            setResult(data);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }, [canCompare, project.uuid, sourceEnvId, targetEnvId, resourceType]);

    const sourceName = environments.find((e) => e.id === parseInt(sourceEnvId))?.name || '';
    const targetName = environments.find((e) => e.id === parseInt(targetEnvId))?.name || '';

    return (
        <>
            <Head title={`Env Diff — ${project.name}`} />
            <div className="mx-auto max-w-5xl px-4 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center gap-3">
                    <Link href={`/projects/${project.uuid}`}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            {project.name}
                        </Button>
                    </Link>
                </div>

                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">
                        <GitCompare className="mr-3 inline h-6 w-6" />
                        Environment Variable Diff
                    </h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Compare env var keys across environments. Values are never exposed.
                    </p>
                </div>

                {/* Controls */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-6">
                        <div className="grid gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground-muted">
                                    Source Environment
                                </label>
                                <Select
                                    value={sourceEnvId}
                                    onChange={(e) => setSourceEnvId(e.target.value)}
                                >
                                    <option value="">Select source...</option>
                                    {environments.map((env) => (
                                        <option key={env.id} value={env.id}>
                                            {env.name} ({env.type})
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            <div className="flex items-end justify-center pb-2">
                                <ArrowRight className="h-5 w-5 text-foreground-subtle" />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground-muted">
                                    Target Environment
                                </label>
                                <Select
                                    value={targetEnvId}
                                    onChange={(e) => setTargetEnvId(e.target.value)}
                                >
                                    <option value="">Select target...</option>
                                    {environments
                                        .filter((e) => e.id !== parseInt(sourceEnvId))
                                        .map((env) => (
                                            <option key={env.id} value={env.id}>
                                                {env.name} ({env.type})
                                            </option>
                                        ))}
                                </Select>
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <Filter className="h-4 w-4 text-foreground-subtle" />
                                <Select
                                    value={resourceType}
                                    onChange={(e) => setResourceType(e.target.value)}
                                    className="w-40"
                                >
                                    {typeFilterOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button
                                onClick={handleCompare}
                                disabled={!canCompare || loading}
                            >
                                {loading ? (
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <GitCompare className="mr-2 h-4 w-4" />
                                )}
                                {loading ? 'Comparing...' : 'Compare'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Error */}
                {error && (
                    <Card variant="glass" className="mb-6 border-danger/50">
                        <CardContent className="p-4 text-sm text-danger">{error}</CardContent>
                    </Card>
                )}

                {/* Results */}
                {result && (
                    <>
                        {/* Summary */}
                        <div className="mb-6 flex flex-wrap gap-3">
                            <Badge variant="success" size="sm">
                                <Plus className="mr-1 h-3 w-3" />
                                {result.summary.total_added} added
                            </Badge>
                            <Badge variant="danger" size="sm">
                                <Minus className="mr-1 h-3 w-3" />
                                {result.summary.total_removed} removed
                            </Badge>
                            <Badge variant="warning" size="sm">
                                <RefreshCw className="mr-1 h-3 w-3" />
                                {result.summary.total_changed} changed
                            </Badge>
                            <Badge variant="secondary" size="sm">
                                <Equal className="mr-1 h-3 w-3" />
                                {result.summary.total_unchanged} unchanged
                            </Badge>
                            <Badge variant="info" size="sm">
                                {result.summary.total_resources} resources
                            </Badge>
                        </div>

                        {result.resources.length === 0 ? (
                            <Card variant="glass">
                                <CardContent className="p-8 text-center text-foreground-muted">
                                    No resources found to compare.
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="space-y-4">
                                {result.resources.map((resource, idx) => (
                                    <ResourceDiffCard
                                        key={`${resource.name}-${resource.type}-${idx}`}
                                        resource={resource}
                                        sourceName={sourceName}
                                        targetName={targetName}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function ResourceDiffCard({
    resource,
    sourceName,
    targetName,
}: {
    resource: ResourceDiff;
    sourceName: string;
    targetName: string;
}) {
    const { diff } = resource;
    const hasDifferences = diff.added.length > 0 || diff.removed.length > 0 || diff.changed.length > 0;
    const totalKeys = diff.added.length + diff.removed.length + diff.changed.length + diff.unchanged.length;

    return (
        <Card variant="glass" hover>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <CardTitle className="text-base">{resource.name}</CardTitle>
                        <Badge variant="secondary" size="sm">
                            {resource.type}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        {!resource.matched && (
                            <Badge variant="warning" size="sm">
                                Only in {resource.only_in === 'source' ? sourceName : targetName}
                            </Badge>
                        )}
                        {resource.matched && !hasDifferences && (
                            <Badge variant="success" size="sm">In sync</Badge>
                        )}
                        {hasDifferences && (
                            <Badge variant="danger" size="sm">
                                {diff.added.length + diff.removed.length + diff.changed.length} differences
                            </Badge>
                        )}
                    </div>
                </div>
                {resource.matched && (
                    <CardDescription>
                        {sourceName} → {targetName} · {totalKeys} env vars
                    </CardDescription>
                )}
            </CardHeader>
            {(hasDifferences || !resource.matched) && (
                <CardContent className="pt-0">
                    <div className="space-y-2">
                        {diff.added.length > 0 && (
                            <div>
                                <p className="mb-1 text-xs font-medium text-success">
                                    + Only in {sourceName} ({diff.added.length})
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {diff.added.map((key) => (
                                        <code
                                            key={key}
                                            className="rounded bg-success/10 px-2 py-0.5 text-xs text-success"
                                        >
                                            {key}
                                        </code>
                                    ))}
                                </div>
                            </div>
                        )}
                        {diff.removed.length > 0 && (
                            <div>
                                <p className="mb-1 text-xs font-medium text-danger">
                                    − Only in {targetName} ({diff.removed.length})
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {diff.removed.map((key) => (
                                        <code
                                            key={key}
                                            className="rounded bg-danger/10 px-2 py-0.5 text-xs text-danger"
                                        >
                                            {key}
                                        </code>
                                    ))}
                                </div>
                            </div>
                        )}
                        {diff.changed.length > 0 && (
                            <div>
                                <p className="mb-1 text-xs font-medium text-warning">
                                    ~ Different values ({diff.changed.length})
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {diff.changed.map((key) => (
                                        <code
                                            key={key}
                                            className="rounded bg-warning/10 px-2 py-0.5 text-xs text-warning"
                                        >
                                            {key}
                                        </code>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </CardContent>
            )}
        </Card>
    );
}
