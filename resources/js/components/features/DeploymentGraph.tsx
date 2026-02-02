import * as React from 'react';
import { cn } from '@/lib/utils';
import { Spinner } from '@/components/ui/Spinner';
import {
    CheckCircle2,
    XCircle,
    Circle,
    Loader2,
    GitBranch,
    Package,
    Upload,
    Rocket,
    Heart,
    Clock,
    ChevronDown,
    ChevronRight,
} from 'lucide-react';

// Types
export interface DeploymentStage {
    id: string;
    name: string;
    status: 'pending' | 'running' | 'completed' | 'failed' | 'skipped';
    startedAt?: string;
    completedAt?: string;
    duration?: number;
    logs?: string[];
    error?: string;
}

interface DeploymentGraphProps {
    stages: DeploymentStage[];
    currentStage?: string;
    isLive?: boolean;
    onStageClick?: (stage: DeploymentStage) => void;
    className?: string;
    compact?: boolean;
}

// Stage icons
const stageIcons: Record<string, React.ComponentType<{ className?: string }>> = {
    prepare: Clock,
    clone: GitBranch,
    build: Package,
    push: Upload,
    deploy: Rocket,
    healthcheck: Heart,
};

// Stage display names
const stageNames: Record<string, string> = {
    prepare: 'Prepare',
    clone: 'Clone',
    build: 'Build',
    push: 'Push',
    deploy: 'Deploy',
    healthcheck: 'Health Check',
};

// Status colors and icons
const statusConfig = {
    pending: {
        icon: Circle,
        color: 'text-foreground-muted',
        bgColor: 'bg-foreground-muted/10',
        borderColor: 'border-foreground-muted/30',
        lineColor: 'bg-foreground-muted/30',
    },
    running: {
        icon: Loader2,
        color: 'text-primary',
        bgColor: 'bg-primary/10',
        borderColor: 'border-primary/50',
        lineColor: 'bg-primary/50',
        animate: true,
    },
    completed: {
        icon: CheckCircle2,
        color: 'text-success',
        bgColor: 'bg-success/10',
        borderColor: 'border-success/50',
        lineColor: 'bg-success',
    },
    failed: {
        icon: XCircle,
        color: 'text-danger',
        bgColor: 'bg-danger/10',
        borderColor: 'border-danger/50',
        lineColor: 'bg-danger',
    },
    skipped: {
        icon: Circle,
        color: 'text-foreground-muted',
        bgColor: 'bg-foreground-muted/5',
        borderColor: 'border-foreground-muted/20',
        lineColor: 'bg-foreground-muted/20',
    },
};

// Format duration
function formatDuration(seconds?: number): string {
    if (!seconds) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}

// Single stage node
function StageNode({
    stage,
    isLast,
    isLive,
    onClick,
    compact,
}: {
    stage: DeploymentStage;
    isLast: boolean;
    isLive?: boolean;
    onClick?: () => void;
    compact?: boolean;
}) {
    const [expanded, setExpanded] = React.useState(false);
    const config = statusConfig[stage.status];
    const StageIcon = stageIcons[stage.id] || Circle;
    const StatusIcon = config.icon;

    const handleClick = () => {
        if (stage.logs && stage.logs.length > 0) {
            setExpanded(!expanded);
        }
        onClick?.();
    };

    return (
        <div className="flex items-start">
            {/* Node */}
            <div className="flex flex-col items-center">
                {/* Icon container */}
                <button
                    onClick={handleClick}
                    className={cn(
                        'relative flex items-center justify-center rounded-xl border-2 transition-all duration-300',
                        config.bgColor,
                        config.borderColor,
                        compact ? 'h-10 w-10' : 'h-14 w-14',
                        stage.status === 'running' && 'shadow-lg shadow-primary/20',
                        (stage.logs?.length ?? 0) > 0 && 'cursor-pointer hover:scale-105',
                    )}
                >
                    {/* Stage icon */}
                    <StageIcon className={cn(config.color, compact ? 'h-4 w-4' : 'h-6 w-6')} />

                    {/* Status indicator */}
                    <div className={cn(
                        'absolute -bottom-1 -right-1 rounded-full bg-background p-0.5',
                        compact ? 'h-4 w-4' : 'h-5 w-5'
                    )}>
                        <StatusIcon
                            className={cn(
                                config.color,
                                compact ? 'h-3 w-3' : 'h-4 w-4',
                                config.animate && 'animate-spin'
                            )}
                        />
                    </div>

                    {/* Live indicator pulse */}
                    {stage.status === 'running' && isLive && (
                        <div className="absolute inset-0 rounded-xl animate-ping bg-primary/20" />
                    )}
                </button>

                {/* Connecting line */}
                {!isLast && (
                    <div
                        className={cn(
                            'w-0.5 transition-all duration-500',
                            compact ? 'h-4' : 'h-8',
                            stage.status === 'completed' || stage.status === 'failed'
                                ? config.lineColor
                                : 'bg-foreground-muted/20'
                        )}
                    />
                )}
            </div>

            {/* Label and details */}
            <div className={cn('ml-3 flex-1', compact ? 'min-w-[80px]' : 'min-w-[120px]')}>
                <div className="flex items-center gap-2">
                    <span className={cn(
                        'font-medium',
                        config.color,
                        compact ? 'text-xs' : 'text-sm'
                    )}>
                        {stageNames[stage.id] || stage.name}
                    </span>
                    {(stage.logs?.length ?? 0) > 0 && (
                        expanded ? (
                            <ChevronDown className="h-3 w-3 text-foreground-muted" />
                        ) : (
                            <ChevronRight className="h-3 w-3 text-foreground-muted" />
                        )
                    )}
                </div>

                {!compact && (
                    <div className="text-xs text-foreground-muted mt-0.5">
                        {stage.status === 'running' && stage.startedAt && (
                            <span className="flex items-center gap-1">
                                <Spinner size="sm" className="h-3 w-3" />
                                Running...
                            </span>
                        )}
                        {stage.status === 'completed' && (
                            <span>{formatDuration(stage.duration)}</span>
                        )}
                        {stage.status === 'failed' && (
                            <span className="text-danger">Failed</span>
                        )}
                        {stage.status === 'pending' && (
                            <span>Waiting...</span>
                        )}
                    </div>
                )}

                {/* Error message */}
                {stage.error && !compact && (
                    <div className="mt-2 p-2 rounded bg-danger/10 border border-danger/20">
                        <p className="text-xs text-danger">{stage.error}</p>
                    </div>
                )}

                {/* Expanded logs */}
                {expanded && stage.logs && stage.logs.length > 0 && (
                    <div className="mt-2 p-2 rounded bg-background-secondary border border-border max-h-40 overflow-y-auto">
                        <pre className="text-xs text-foreground-muted font-mono whitespace-pre-wrap">
                            {stage.logs.slice(-20).join('\n')}
                        </pre>
                    </div>
                )}
            </div>
        </div>
    );
}

// Progress bar
function ProgressBar({ stages, className }: { stages: DeploymentStage[]; className?: string }) {
    const completed = stages.filter(s => s.status === 'completed').length;
    const failed = stages.some(s => s.status === 'failed');
    const progress = Math.round((completed / stages.length) * 100);

    return (
        <div className={cn('w-full', className)}>
            <div className="flex justify-between text-xs text-foreground-muted mb-1">
                <span>{failed ? 'Failed' : `${progress}% complete`}</span>
                <span>{completed}/{stages.length} stages</span>
            </div>
            <div className="h-2 rounded-full bg-background-tertiary overflow-hidden">
                <div
                    className={cn(
                        'h-full rounded-full transition-all duration-500',
                        failed ? 'bg-danger' : 'bg-success'
                    )}
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}

// Main component
export function DeploymentGraph({
    stages,
    currentStage,
    isLive,
    onStageClick,
    className,
    compact,
}: DeploymentGraphProps) {
    const totalDuration = stages.reduce((acc, s) => acc + (s.duration || 0), 0);
    const isCompleted = stages.every(s => s.status === 'completed' || s.status === 'skipped');
    const isFailed = stages.some(s => s.status === 'failed');

    return (
        <div className={cn('space-y-4', className)}>
            {/* Header */}
            {!compact && (
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <h3 className="font-medium text-foreground">Deployment Pipeline</h3>
                        {isLive && !isCompleted && !isFailed && (
                            <span className="flex items-center gap-1 text-xs text-primary">
                                <span className="relative flex h-2 w-2">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75" />
                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-primary" />
                                </span>
                                Live
                            </span>
                        )}
                    </div>
                    {totalDuration > 0 && (
                        <span className="text-sm text-foreground-muted">
                            Total: {formatDuration(totalDuration)}
                        </span>
                    )}
                </div>
            )}

            {/* Progress bar */}
            {!compact && <ProgressBar stages={stages} />}

            {/* Graph */}
            <div className={cn(
                'flex',
                compact ? 'flex-row gap-2 items-center' : 'flex-col gap-0'
            )}>
                {stages.map((stage, index) => (
                    <StageNode
                        key={stage.id}
                        stage={stage}
                        isLast={index === stages.length - 1}
                        isLive={isLive}
                        onClick={() => onStageClick?.(stage)}
                        compact={compact}
                    />
                ))}
            </div>

            {/* Status message */}
            {!compact && (
                <div className={cn(
                    'p-3 rounded-lg border text-sm',
                    isCompleted && 'bg-success/10 border-success/30 text-success',
                    isFailed && 'bg-danger/10 border-danger/30 text-danger',
                    !isCompleted && !isFailed && 'bg-primary/10 border-primary/30 text-primary'
                )}>
                    {isCompleted && (
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4" />
                            Deployment completed successfully
                        </div>
                    )}
                    {isFailed && (
                        <div className="flex items-center gap-2">
                            <XCircle className="h-4 w-4" />
                            Deployment failed at {stageNames[stages.find(s => s.status === 'failed')?.id || ''] || 'unknown'} stage
                        </div>
                    )}
                    {!isCompleted && !isFailed && currentStage && (
                        <div className="flex items-center gap-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Currently: {stageNames[currentStage] || currentStage}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// Utility to parse logs and detect stages
export function parseDeploymentLogs(logs: Array<{ output: string; timestamp?: string }>): DeploymentStage[] {
    const stages: DeploymentStage[] = [
        { id: 'prepare', name: 'Prepare', status: 'pending' },
        { id: 'clone', name: 'Clone', status: 'pending' },
        { id: 'build', name: 'Build', status: 'pending' },
        { id: 'push', name: 'Push', status: 'pending' },
        { id: 'deploy', name: 'Deploy', status: 'pending' },
        { id: 'healthcheck', name: 'Health Check', status: 'pending' },
    ];

    const stagePatterns: Record<string, { start: RegExp; end?: RegExp; fail?: RegExp }> = {
        prepare: {
            start: /Preparing container|Starting deployment|Deployment started/i,
            end: /helper image.*ready|preparation complete/i,
        },
        clone: {
            start: /Importing|Cloning|Checking out|git clone/i,
            end: /Creating build-time|Clone complete|commit sha/i,
            fail: /Failed to clone|git.*error/i,
        },
        build: {
            start: /Building docker image started|docker build|nixpacks build/i,
            end: /Building docker image completed|Successfully built|build complete/i,
            fail: /Build failed|error during build/i,
        },
        push: {
            start: /Pushing image|docker push/i,
            end: /Successfully pushed|push complete/i,
            fail: /Failed to push|push error/i,
        },
        deploy: {
            start: /Rolling update started|Starting container|docker-compose up|up --build/i,
            end: /New container started|Container created/i,
            fail: /Failed to start|Container.*exit/i,
        },
        healthcheck: {
            start: /Waiting for healthcheck|Health check started/i,
            end: /New container is healthy|Rolling update completed|Container is stable/i,
            fail: /unhealthy|healthcheck.*fail/i,
        },
    };

    let currentStageIndex = -1;
    const stageLogs: Record<string, string[]> = {};
    const stageTimes: Record<string, { start?: string; end?: string }> = {};

    // Initialize logs arrays
    stages.forEach(s => {
        stageLogs[s.id] = [];
        stageTimes[s.id] = {};
    });

    // Parse each log entry
    for (const log of logs) {
        const output = log.output || '';

        // Check each stage pattern
        for (let i = 0; i < stages.length; i++) {
            const stage = stages[i];
            const patterns = stagePatterns[stage.id];
            if (!patterns) continue;

            // Check for stage start
            if (patterns.start.test(output)) {
                if (currentStageIndex < i) {
                    // Mark all previous stages as completed
                    for (let j = 0; j <= currentStageIndex; j++) {
                        if (stages[j].status === 'running') {
                            stages[j].status = 'completed';
                            stageTimes[stages[j].id].end = log.timestamp;
                        }
                    }
                    currentStageIndex = i;
                    stages[i].status = 'running';
                    stageTimes[stage.id].start = log.timestamp;
                }
            }

            // Check for stage end
            if (patterns.end?.test(output) && stages[i].status === 'running') {
                stages[i].status = 'completed';
                stageTimes[stage.id].end = log.timestamp;
            }

            // Check for failure
            if (patterns.fail?.test(output)) {
                stages[i].status = 'failed';
                stages[i].error = output.substring(0, 200);
            }
        }

        // Add log to current stage
        if (currentStageIndex >= 0) {
            stageLogs[stages[currentStageIndex].id].push(output);
        }
    }

    // Calculate durations and assign logs
    stages.forEach(stage => {
        stage.logs = stageLogs[stage.id];
        const times = stageTimes[stage.id];
        if (times.start && times.end) {
            const start = new Date(times.start).getTime();
            const end = new Date(times.end).getTime();
            stage.duration = Math.round((end - start) / 1000);
        }
        stage.startedAt = times.start;
        stage.completedAt = times.end;
    });

    return stages;
}

export default DeploymentGraph;
