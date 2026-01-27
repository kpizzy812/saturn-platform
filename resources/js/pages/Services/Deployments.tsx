import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, Badge, Button, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { GitCommit, Clock, RotateCw, Play, Loader2, AlertCircle } from 'lucide-react';
import type { Service } from '@/types';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';

type DeploymentStatus = 'queued' | 'in_progress' | 'finished' | 'failed' | 'cancelled';

interface Deployment {
    id: number;
    commit?: string;
    commit_sha?: string;
    message?: string;
    commit_message?: string;
    status: DeploymentStatus;
    time?: string;
    created_at?: string;
    duration?: string;
    author?: string;
}

interface Props {
    service: Service;
    deployments?: Deployment[];
}

// Helper to format time ago
const formatTimeAgo = (date?: string): string => {
    if (!date) return 'Unknown';
    const now = new Date();
    const then = new Date(date);
    const diff = now.getTime() - then.getTime();
    const minutes = Math.floor(diff / (1000 * 60));
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
};

export function DeploymentsTab({ service, deployments: propDeployments = [] }: Props) {
    const confirm = useConfirm();
    const [filter, setFilter] = useState<DeploymentStatus | 'all'>('all');
    const [deployments, setDeployments] = useState<Deployment[]>(propDeployments);
    const [isLoading, setIsLoading] = useState(propDeployments.length === 0);
    const { addToast } = useToast();

    // Fetch deployments if not provided
    useEffect(() => {
        if (propDeployments.length === 0 && service.uuid) {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            fetch(`/api/v1/services/${service.uuid}/deployments`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            })
                .then(res => res.ok ? res.json() : [])
                .then(data => {
                    setDeployments(Array.isArray(data) ? data : data.data || []);
                    setIsLoading(false);
                })
                .catch(() => setIsLoading(false));
        }
    }, [service.uuid, propDeployments]);
    const [isDeploying, setIsDeploying] = useState(false);
    const [rollingBack, setRollingBack] = useState<number | null>(null);

    const handleDeploy = () => {
        setIsDeploying(true);
        router.post(`/api/v1/services/${service.uuid}/start`, {}, {
            onFinish: () => setIsDeploying(false),
            onError: () => {
                setIsDeploying(false);
                addToast('error', 'Failed to start deployment');
            },
        });
    };

    const handleRollback = async (deploymentId: number, commit: string) => {
        const confirmed = await confirm({
            title: 'Rollback Deployment',
            description: `Are you sure you want to rollback to commit ${commit.substring(0, 7)}?`,
            confirmText: 'Rollback',
            variant: 'warning',
        });
        if (confirmed) {
            setRollingBack(deploymentId);
            router.post(`/api/v1/services/${service.uuid}/start`, { commit }, {
                onFinish: () => setRollingBack(null),
                onError: () => {
                    setRollingBack(null);
                    addToast('error', 'Failed to rollback');
                },
            });
        }
    };

    const filteredDeployments = filter === 'all'
        ? deployments
        : deployments.filter(d => d.status === filter);

    return (
        <div className="space-y-4">
            {/* Deploy Button */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Deploy Service</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Trigger a new deployment from the latest commit
                            </p>
                        </div>
                        <Button onClick={handleDeploy} disabled={isDeploying}>
                            <Play className={`mr-2 h-4 w-4 ${isDeploying ? 'animate-pulse' : ''}`} />
                            {isDeploying ? 'Deploying...' : 'Deploy Now'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Filter Buttons */}
            <div className="flex items-center gap-2">
                <button
                    onClick={() => setFilter('all')}
                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                        filter === 'all'
                            ? 'bg-foreground text-background'
                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                    }`}
                >
                    All
                </button>
                <button
                    onClick={() => setFilter('finished')}
                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                        filter === 'finished'
                            ? 'bg-foreground text-background'
                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                    }`}
                >
                    Finished
                </button>
                <button
                    onClick={() => setFilter('failed')}
                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                        filter === 'failed'
                            ? 'bg-foreground text-background'
                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                    }`}
                >
                    Failed
                </button>
                <button
                    onClick={() => setFilter('in_progress')}
                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                        filter === 'in_progress'
                            ? 'bg-foreground text-background'
                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                    }`}
                >
                    In Progress
                </button>
            </div>

            {/* Deployments List */}
            <div className="space-y-2">
                {isLoading ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            <p className="mt-4 text-sm text-foreground-muted">Loading deployments...</p>
                        </CardContent>
                    </Card>
                ) : filteredDeployments.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <AlertCircle className="h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No deployments found</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                {filter === 'all'
                                    ? 'No deployments have been made yet'
                                    : `No ${filter} deployments found`}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    filteredDeployments.map((deployment) => {
                        const commit = deployment.commit || deployment.commit_sha || 'unknown';
                        const message = deployment.message || deployment.commit_message || 'No commit message';
                        const time = deployment.time || formatTimeAgo(deployment.created_at);
                        const author = deployment.author || 'Unknown';
                        const duration = deployment.duration || '-';

                        return (
                            <Card key={deployment.id}>
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-start gap-3">
                                            <div className="mt-0.5">{getStatusIcon(deployment.status)}</div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <GitCommit className="h-3.5 w-3.5 text-foreground-muted" />
                                                    <code className="text-sm font-medium text-foreground">
                                                        {commit.substring(0, 7)}
                                                    </code>
                                                </div>
                                                <p className="mt-1 text-sm text-foreground">{message}</p>
                                                <div className="mt-2 flex items-center gap-3 text-xs text-foreground-muted">
                                                    <span>{author}</span>
                                                    <span>·</span>
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        <span>{time}</span>
                                                    </div>
                                                    <span>·</span>
                                                    <span>{duration}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={getStatusVariant(deployment.status)}>
                                                {deployment.status.replace('_', ' ')}
                                            </Badge>
                                            {deployment.status === 'finished' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRollback(deployment.id, commit)}
                                                    disabled={rollingBack === deployment.id}
                                                >
                                                    <RotateCw className={`mr-1 h-3 w-3 ${rollingBack === deployment.id ? 'animate-spin' : ''}`} />
                                                    {rollingBack === deployment.id ? 'Rolling back...' : 'Rollback'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })
                )}
            </div>
        </div>
    );
}
