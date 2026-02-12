import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Badge } from '@/components/ui';
import {
    GitBranch,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Loader2,
    ShieldCheck,
    RotateCcw,
    ArrowRight,
} from 'lucide-react';
import type { EnvironmentMigration } from '@/types';
import { formatDistanceToNow } from 'date-fns';

interface Props {
    migrations: {
        data: EnvironmentMigration[];
        current_page: number;
        last_page: number;
        total: number;
    };
    statusFilter?: string;
}

const statusConfig: Record<string, { icon: typeof Clock; color: string; label: string }> = {
    pending: { icon: Clock, color: 'bg-yellow-500/10 text-yellow-500', label: 'Pending' },
    approved: { icon: ShieldCheck, color: 'bg-blue-500/10 text-blue-500', label: 'Approved' },
    rejected: { icon: XCircle, color: 'bg-red-500/10 text-red-500', label: 'Rejected' },
    in_progress: { icon: Loader2, color: 'bg-blue-500/10 text-blue-500', label: 'In Progress' },
    completed: { icon: CheckCircle, color: 'bg-green-500/10 text-green-500', label: 'Completed' },
    failed: { icon: AlertCircle, color: 'bg-red-500/10 text-red-500', label: 'Failed' },
    rolled_back: { icon: RotateCcw, color: 'bg-orange-500/10 text-orange-500', label: 'Rolled Back' },
    cancelled: { icon: XCircle, color: 'bg-gray-500/10 text-gray-500', label: 'Cancelled' },
};

function getSourceName(migration: EnvironmentMigration): string {
    return migration.source?.name || 'Unknown';
}

function getSourceTypeName(migration: EnvironmentMigration): string {
    return migration.source_type_name || migration.source_type?.split('\\').pop()?.replace('Standalone', '') || 'Resource';
}

function getModeLabel(migration: EnvironmentMigration): string {
    return migration.options?.mode === 'promote' ? 'Promote' : 'Clone';
}

function getDirection(migration: EnvironmentMigration): string {
    const src = migration.source_environment?.name || 'Source';
    const tgt = migration.target_environment?.name || 'Target';
    return `${src} → ${tgt}`;
}

export default function MigrationsIndex({ migrations, statusFilter }: Props) {
    const filterByStatus = (status: string | null) => {
        router.get('/migrations', status ? { status } : {}, { preserveState: true });
    };

    return (
        <AppLayout
            title="Migrations"
            breadcrumbs={[{ label: 'Migrations' }]}
        >
            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-foreground">Environment Migrations</h1>
                    <p className="text-foreground-muted">Deploy resources across environments: dev → uat → production</p>
                </div>

                {/* Status Filter */}
                <div className="mb-4 flex flex-wrap gap-2">
                    <Button
                        variant={!statusFilter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => filterByStatus(null)}
                    >
                        All ({migrations.total})
                    </Button>
                    {Object.entries(statusConfig).map(([status, config]) => (
                        <Button
                            key={status}
                            variant={statusFilter === status ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => filterByStatus(status)}
                        >
                            {config.label}
                        </Button>
                    ))}
                </div>

                {/* Migrations List */}
                {migrations.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="space-y-3">
                        {migrations.data.map((migration) => (
                            <MigrationCard key={migration.id} migration={migration} />
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {migrations.last_page > 1 && (
                    <div className="mt-6 flex justify-center gap-2">
                        {Array.from({ length: migrations.last_page }, (_, i) => i + 1).map((page) => (
                            <Button
                                key={page}
                                variant={page === migrations.current_page ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => router.get('/migrations', { page, status: statusFilter })}
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function MigrationCard({ migration }: { migration: EnvironmentMigration }) {
    const config = statusConfig[migration.status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = migration.status === 'in_progress';

    return (
        <Link href={`/migrations/${migration.uuid}`}>
            <div className="rounded-lg border border-border/50 bg-background-secondary/30 p-4 hover:border-border transition-colors">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`rounded-full p-2 ${config.color}`}>
                            <StatusIcon className={`h-4 w-4 ${isAnimated ? 'animate-spin' : ''}`} />
                        </div>
                        <div>
                            <div className="font-medium text-foreground flex items-center gap-2">
                                {getSourceName(migration)}
                                <Badge variant="outline" className="text-xs">
                                    {getSourceTypeName(migration)}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {getModeLabel(migration)}
                                </Badge>
                            </div>
                            <div className="text-sm text-foreground-muted flex items-center gap-1">
                                {migration.source_environment?.project?.name && (
                                    <span>{migration.source_environment.project.name}</span>
                                )}
                                <span className="flex items-center gap-1">
                                    {getDirection(migration)}
                                </span>
                                <span>•</span>
                                <span>{migration.target_server?.name || 'Unknown Server'}</span>
                            </div>
                        </div>
                    </div>
                    <div className="text-right flex items-center gap-3">
                        <div>
                            <Badge variant="outline" className={config.color}>
                                {config.label}
                            </Badge>
                            <div className="mt-1 text-xs text-foreground-muted">
                                {formatDistanceToNow(new Date(migration.created_at), { addSuffix: true })}
                            </div>
                        </div>
                        <ArrowRight className="h-4 w-4 text-foreground-muted" />
                    </div>
                </div>

                {/* Progress bar for in-progress migrations */}
                {isAnimated && (
                    <div className="mt-3">
                        <div className="flex items-center justify-between text-xs text-foreground-muted mb-1">
                            <span>{migration.current_step || 'Processing...'}</span>
                            <span>{migration.progress}%</span>
                        </div>
                        <div className="h-1.5 bg-background-tertiary rounded-full overflow-hidden">
                            <div
                                className="h-full bg-primary transition-all duration-300"
                                style={{ width: `${migration.progress}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Error message for failed migrations */}
                {migration.status === 'failed' && migration.error_message && (
                    <div className="mt-3 text-sm text-red-500 truncate">
                        {migration.error_message}
                    </div>
                )}

                {/* Pending approval indicator */}
                {migration.status === 'pending' && migration.requires_approval && (
                    <div className="mt-3 text-sm text-yellow-500 flex items-center gap-1">
                        <ShieldCheck className="h-3.5 w-3.5" />
                        Awaiting approval
                    </div>
                )}
            </div>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <GitBranch className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No migrations yet</h3>
            <p className="mt-1 text-sm text-foreground-muted text-center max-w-md">
                Migrate resources between environments using the Migrate button on any project's environment page.
                The pipeline flows: dev → uat → production.
            </p>
        </div>
    );
}
