import { AppLayout } from '@/components/layout';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge } from '@/components/ui';
import { DeploymentGraph, type DeploymentStage } from '@/components/features/DeploymentGraph';
import { formatRelativeTime } from '@/lib/utils';
import {
    Rocket,
    Settings,
    Clock,
    AlertCircle,
    CheckCircle,
    Loader2,
    Timer,
    ChevronRight,
    Search,
    User,
    GitCommit,
    ExternalLink,
    ArrowRight,
} from 'lucide-react';

interface Operation {
    id: string;
    type: 'deployment' | 'config_change';
    name: string;
    status: 'success' | 'error' | 'in_progress' | 'queued';
    duration: number | null;
    timestamp: string;
    resource: { type: string; name: string; id: string };
    user: { name: string; email: string };
    commit: string | null;
    triggeredBy: string | null;
    stages: StageData[] | null;
    changes: { old: Record<string, unknown> | null; attributes: Record<string, unknown> | null } | null;
}

interface StageData {
    id: string;
    name: string;
    status: string;
    duration: number | null;
}

interface Props {
    operations?: Operation[];
}

const statusConfig = {
    success: { icon: CheckCircle, color: 'text-emerald-400', badge: 'success' as const, label: 'Success' },
    error: { icon: AlertCircle, color: 'text-red-400', badge: 'danger' as const, label: 'Error' },
    in_progress: { icon: Loader2, color: 'text-blue-400', badge: 'info' as const, label: 'In Progress' },
    queued: { icon: Timer, color: 'text-yellow-400', badge: 'warning' as const, label: 'Queued' },
};

function formatDuration(seconds: number | null): string {
    if (seconds === null || seconds === undefined) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
}

function OperationListItem({ operation, isSelected, onClick }: { operation: Operation; isSelected: boolean; onClick: () => void }) {
    const config = statusConfig[operation.status] || statusConfig.success;
    const StatusIcon = config.icon;
    const TypeIcon = operation.type === 'deployment' ? Rocket : Settings;
    const isAnimated = operation.status === 'in_progress';

    return (
        <div
            onClick={onClick}
            className={`group cursor-pointer rounded-lg border p-4 transition-all hover:border-border-hover hover:bg-background-tertiary ${
                isSelected ? 'border-primary bg-background-tertiary' : 'border-border bg-background-secondary'
            }`}
        >
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-3">
                    <div className="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-background-tertiary">
                        <TypeIcon className="h-4 w-4 text-foreground-muted" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <h3 className="truncate font-medium text-foreground">{operation.name}</h3>
                            <Badge variant={config.badge}>
                                <span className="flex items-center gap-1">
                                    <StatusIcon className={`h-3 w-3 ${isAnimated ? 'animate-spin' : ''}`} />
                                    {config.label}
                                </span>
                            </Badge>
                        </div>
                        <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-foreground-muted">
                            {operation.duration !== null && (
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {formatDuration(operation.duration)}
                                </span>
                            )}
                            <span>{operation.timestamp ? formatRelativeTime(operation.timestamp) : ''}</span>
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-background-tertiary px-2 py-0.5 text-xs text-foreground-muted">
                                {operation.resource.name}
                            </span>
                            {operation.commit && (
                                <span className="flex items-center gap-1 rounded-full bg-background-tertiary px-2 py-0.5 text-xs font-mono text-foreground-muted">
                                    <GitCommit className="h-3 w-3" />
                                    {operation.commit}
                                </span>
                            )}
                            <span className="flex items-center gap-1 text-xs text-foreground-muted">
                                <User className="h-3 w-3" />
                                {operation.user.name}
                            </span>
                        </div>
                    </div>
                </div>
                <ChevronRight className="h-5 w-5 flex-shrink-0 text-foreground-muted transition-transform group-hover:translate-x-1" />
            </div>
        </div>
    );
}

function DeploymentDetail({ operation }: { operation: Operation }) {
    const config = statusConfig[operation.status] || statusConfig.success;

    // Convert stages to DeploymentStage format for DeploymentGraph
    const graphStages: DeploymentStage[] = operation.stages
        ? operation.stages.map((s) => ({
              id: s.id,
              name: s.name,
              status: s.status as DeploymentStage['status'],
              duration: s.duration ?? undefined,
          }))
        : [];

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <Rocket className="h-5 w-5" />
                            {operation.name}
                        </CardTitle>
                        <Badge variant={config.badge}>{config.label}</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-foreground-muted">Duration</p>
                            <p className="font-semibold text-foreground">{formatDuration(operation.duration)}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">Triggered By</p>
                            <p className="text-foreground">{operation.triggeredBy ?? '-'}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">User</p>
                            <p className="text-foreground">{operation.user.name}</p>
                        </div>
                        {operation.commit && (
                            <div>
                                <p className="text-foreground-muted">Commit</p>
                                <p className="font-mono text-foreground">{operation.commit}</p>
                            </div>
                        )}
                        <div>
                            <p className="text-foreground-muted">Resource</p>
                            <p className="text-foreground">{operation.resource.name}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">Timestamp</p>
                            <p className="text-foreground">{operation.timestamp ? formatRelativeTime(operation.timestamp) : '-'}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {graphStages.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Deployment Stages</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DeploymentGraph stages={graphStages} compact={false} />
                    </CardContent>
                </Card>
            )}

            {operation.resource.id && (
                <div className="flex justify-end">
                    <a
                        href={`/project/${operation.resource.id}`}
                        className="flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        View deployment logs
                        <ExternalLink className="h-3 w-3" />
                    </a>
                </div>
            )}
        </div>
    );
}

function ConfigChangeDetail({ operation }: { operation: Operation }) {
    const changes = operation.changes;
    const hasOld = changes?.old && Object.keys(changes.old).length > 0;
    const hasNew = changes?.attributes && Object.keys(changes.attributes).length > 0;
    const hasDiff = hasOld || hasNew;

    // Collect all keys from old + new
    const allKeys = new Set<string>();
    if (changes?.old) Object.keys(changes.old).forEach((k) => allKeys.add(k));
    if (changes?.attributes) Object.keys(changes.attributes).forEach((k) => allKeys.add(k));

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5" />
                            {operation.name}
                        </CardTitle>
                        <Badge variant="success">Config Change</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-foreground-muted">Resource</p>
                            <p className="text-foreground">{operation.resource.name}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">Resource Type</p>
                            <p className="capitalize text-foreground">{operation.resource.type}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">User</p>
                            <p className="text-foreground">{operation.user.name}</p>
                        </div>
                        <div>
                            <p className="text-foreground-muted">Timestamp</p>
                            <p className="text-foreground">{operation.timestamp ? formatRelativeTime(operation.timestamp) : '-'}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {hasDiff && (
                <Card>
                    <CardHeader>
                        <CardTitle>Changes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {[...allKeys].map((key) => {
                                const oldVal = changes?.old?.[key];
                                const newVal = changes?.attributes?.[key];
                                return (
                                    <div key={key} className="rounded-md border border-border p-3">
                                        <p className="mb-1 text-sm font-medium text-foreground">{key}</p>
                                        <div className="flex items-center gap-2 text-sm">
                                            {oldVal !== undefined && (
                                                <span className="rounded bg-red-500/10 px-2 py-0.5 font-mono text-xs text-red-400">
                                                    {String(oldVal)}
                                                </span>
                                            )}
                                            {oldVal !== undefined && newVal !== undefined && (
                                                <ArrowRight className="h-3 w-3 text-foreground-muted" />
                                            )}
                                            {newVal !== undefined && (
                                                <span className="rounded bg-emerald-500/10 px-2 py-0.5 font-mono text-xs text-emerald-400">
                                                    {String(newVal)}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export default function ObservabilityTraces({ operations: propOperations }: Props) {
    const operations = propOperations ?? [];
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [typeFilter, setTypeFilter] = useState<'all' | 'deployment' | 'config_change'>('all');
    const [statusFilter, setStatusFilter] = useState<'all' | 'success' | 'error' | 'in_progress'>('all');

    const selectedOperation = operations.find((op) => op.id === selectedId) ?? null;

    const filteredOperations = operations.filter((op) => {
        const searchMatch =
            !searchQuery ||
            op.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            op.resource.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            op.user.name.toLowerCase().includes(searchQuery.toLowerCase());
        const typeMatch = typeFilter === 'all' || op.type === typeFilter;
        const statusMatch = statusFilter === 'all' || op.status === statusFilter;
        return searchMatch && typeMatch && statusMatch;
    });

    return (
        <AppLayout
            title="Operations"
            breadcrumbs={[{ label: 'Observability', href: '/observability' }, { label: 'Operations' }]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Operations</h1>
                        <p className="text-foreground-muted">Deployment history and resource changes</p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <input
                                    type="text"
                                    placeholder="Search operations..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                            <select
                                value={typeFilter}
                                onChange={(e) => setTypeFilter(e.target.value as typeof typeFilter)}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="all">All Types</option>
                                <option value="deployment">Deployments</option>
                                <option value="config_change">Config Changes</option>
                            </select>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="all">All Statuses</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                                <option value="in_progress">In Progress</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Operations List */}
                    <div>
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            Recent Operations
                            {filteredOperations.length > 0 && (
                                <span className="ml-2 text-sm font-normal text-foreground-muted">
                                    ({filteredOperations.length})
                                </span>
                            )}
                        </h2>
                        <div className="space-y-3">
                            {filteredOperations.map((op) => (
                                <OperationListItem
                                    key={op.id}
                                    operation={op}
                                    isSelected={op.id === selectedId}
                                    onClick={() => setSelectedId(op.id)}
                                />
                            ))}
                            {filteredOperations.length === 0 && (
                                <div className="rounded-lg border border-border bg-background-secondary p-8 text-center">
                                    <p className="text-foreground-muted">No operations found</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Detail Panel */}
                    <div>
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Details</h2>
                        {selectedOperation ? (
                            selectedOperation.type === 'deployment' ? (
                                <DeploymentDetail operation={selectedOperation} />
                            ) : (
                                <ConfigChangeDetail operation={selectedOperation} />
                            )
                        ) : (
                            <Card className="p-12 text-center">
                                <Rocket className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No operation selected</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Select an operation from the list to view details
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
