import { GitCommit } from 'lucide-react';
import { Badge } from '@/components/ui';
import { getStatusIcon, getStatusLabel, getStatusVariant, type BadgeVariant } from '@/lib/statusUtils';

// Timeline-specific mapping from variant to node styles
const variantToTimelineClass: Record<BadgeVariant, { normal: string; active: string }> = {
    success: {
        normal: 'bg-primary/20 border-primary hover:bg-primary/30',
        active: 'bg-primary border-primary'
    },
    danger: {
        normal: 'bg-danger/20 border-danger hover:bg-danger/30',
        active: 'bg-danger border-danger'
    },
    warning: {
        normal: 'bg-warning/20 border-warning hover:bg-warning/30',
        active: 'bg-warning border-warning'
    },
    default: {
        normal: 'bg-foreground-subtle/20 border-foreground-subtle hover:bg-foreground-subtle/30',
        active: 'bg-foreground-subtle border-foreground-subtle'
    },
    info: {
        normal: 'bg-info/20 border-info hover:bg-info/30',
        active: 'bg-info border-info'
    },
    primary: {
        normal: 'bg-primary/20 border-primary hover:bg-primary/30',
        active: 'bg-primary border-primary'
    },
};

const getTimelineNodeClass = (status: string, isCurrent: boolean): string => {
    const variant = getStatusVariant(status);
    const styles = variantToTimelineClass[variant] ?? variantToTimelineClass.default;
    return isCurrent ? styles.active : styles.normal;
};

interface Deployment {
    id: number;
    deployment_uuid: string;
    commit: string;
    commit_message: string | null;
    status: string;
    created_at: string;
    rollback?: boolean;
}

interface RollbackTimelineProps {
    deployments: Deployment[];
    currentDeploymentId?: number;
    onSelectDeployment?: (deployment: Deployment) => void;
}

export function RollbackTimeline({
    deployments,
    currentDeploymentId,
    onSelectDeployment
}: RollbackTimelineProps) {
    const formatTimeAgo = (date: string): string => {
        const now = new Date();
        const then = new Date(date);
        const diff = now.getTime() - then.getTime();
        const minutes = Math.floor(diff / (1000 * 60));
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;
        const weeks = Math.floor(days / 7);
        if (weeks < 4) return `${weeks}w ago`;
        const months = Math.floor(days / 30);
        return `${months}mo ago`;
    };

    if (deployments.length === 0) {
        return (
            <div className="flex items-center justify-center py-8 text-sm text-foreground-muted">
                No deployments to display
            </div>
        );
    }

    return (
        <div className="relative">
            {/* Timeline Container */}
            <div className="flex items-start gap-2 overflow-x-auto pb-4">
                {/* Timeline Line */}
                <div className="absolute left-0 right-0 top-5 h-0.5 bg-border" style={{ zIndex: 0 }} />

                {/* Deployments */}
                <div className="relative flex gap-4" style={{ zIndex: 1 }}>
                    {deployments.map((deployment, index) => {
                        const isCurrent = deployment.id === currentDeploymentId;
                        const commitShort = deployment.commit?.substring(0, 7) || 'unknown';

                        return (
                            <div
                                key={deployment.id}
                                className="flex flex-col items-center"
                                style={{ minWidth: '120px' }}
                            >
                                {/* Timeline Node */}
                                <button
                                    onClick={() => onSelectDeployment?.(deployment)}
                                    className={`
                                        group relative flex h-10 w-10 items-center justify-center
                                        rounded-full border-2 transition-all duration-200
                                        ${getTimelineNodeClass(deployment.status, isCurrent)}
                                        ${onSelectDeployment ? 'cursor-pointer' : 'cursor-default'}
                                        ${!isCurrent && onSelectDeployment ? 'hover:scale-110' : ''}
                                    `}
                                    disabled={!onSelectDeployment}
                                >
                                    {getStatusIcon(deployment.status, { size: 'xs', className: isCurrent ? 'text-white' : undefined })}

                                    {/* Tooltip on hover */}
                                    {onSelectDeployment && (
                                        <div className="absolute bottom-full mb-2 hidden w-64 rounded-lg border border-border bg-background p-2 shadow-lg group-hover:block">
                                            <div className="flex items-center gap-2">
                                                <GitCommit className="h-3 w-3 text-foreground-muted" />
                                                <code className="text-xs font-medium text-foreground">
                                                    {commitShort}
                                                </code>
                                            </div>
                                            <p className="mt-1 text-xs text-foreground-muted line-clamp-2">
                                                {deployment.commit_message || 'No commit message'}
                                            </p>
                                            <div className="mt-2 flex items-center gap-2">
                                                <Badge variant={getStatusVariant(deployment.status)} className="text-xs">
                                                    {getStatusLabel(deployment.status)}
                                                </Badge>
                                                {deployment.rollback && (
                                                    <Badge variant="warning" className="text-xs">
                                                        Rollback
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </button>

                                {/* Deployment Info Below Node */}
                                <div className="mt-3 text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        <GitCommit className="h-3 w-3 text-foreground-muted" />
                                        <code className="text-xs font-medium text-foreground">
                                            {commitShort}
                                        </code>
                                    </div>
                                    {isCurrent && (
                                        <Badge variant="success" className="mt-1 text-xs">
                                            Active
                                        </Badge>
                                    )}
                                    <div className="mt-1 text-xs text-foreground-muted">
                                        {formatTimeAgo(deployment.created_at)}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Legend */}
            <div className="mt-6 flex flex-wrap items-center gap-4 border-t border-border pt-4 text-xs">
                <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-primary border-2 border-primary" />
                    <span className="text-foreground-muted">Current / Successful</span>
                </div>
                <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-danger/20 border-2 border-danger" />
                    <span className="text-foreground-muted">Failed</span>
                </div>
                <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-warning/20 border-2 border-warning" />
                    <span className="text-foreground-muted">Rolled Back</span>
                </div>
            </div>

            {/* Instructions */}
            {onSelectDeployment && (
                <div className="mt-4 rounded-lg border border-info bg-info/10 p-3">
                    <p className="text-xs text-foreground-muted">
                        Click on any deployment node to view details and rollback options
                    </p>
                </div>
            )}
        </div>
    );
}
