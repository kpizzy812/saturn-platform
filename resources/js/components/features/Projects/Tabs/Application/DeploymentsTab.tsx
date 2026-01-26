import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui';
import { Play, RefreshCw, Clock, FileText, GitCommit, Eye, MoreVertical, RotateCcw, StopCircle } from 'lucide-react';
import { useDeployments } from '@/hooks/useDeployments';
import { useToast } from '@/components/ui/Toast';
import { useConfirm } from '@/components/ui/ConfirmationModal';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import type { Deployment } from '@/types';
import type { SelectedService } from '../../types';

interface DeploymentsTabProps {
    service: SelectedService;
}

export function DeploymentsTab({ service }: DeploymentsTabProps) {
    const { deployments, isLoading, refetch, startDeployment, cancelDeployment } = useDeployments({
        applicationUuid: service.uuid,
        autoRefresh: true,
        refreshInterval: 5000,
    });
    const { toast } = useToast();
    const confirm = useConfirm();
    const [isDeploying, setIsDeploying] = useState(false);
    const [cancellingId, setCancellingId] = useState<string | null>(null);
    const [rollingBackId, setRollingBackId] = useState<string | null>(null);

    // Map API status to display status
    const getDisplayStatus = (status: string) => {
        switch (status) {
            case 'finished':
                return 'active';
            case 'in_progress':
            case 'queued':
                return 'building';
            case 'failed':
                return 'crashed';
            case 'cancelled':
                return 'cancelled';
            default:
                return status;
        }
    };

    const getBadgeStyle = (status: string) => {
        const displayStatus = getDisplayStatus(status);
        switch (displayStatus) {
            case 'active':
                return 'bg-green-500/10 text-green-500 border border-green-500/20';
            case 'building':
                return 'bg-blue-500/10 text-blue-500 border border-blue-500/20';
            case 'crashed':
                return 'bg-red-500/10 text-red-500 border border-red-500/20';
            case 'cancelled':
                return 'bg-orange-500/10 text-orange-500 border border-orange-500/20';
            default:
                return 'bg-gray-500/10 text-gray-500 border border-gray-500/20';
        }
    };

    const isInProgress = (status: string) => ['in_progress', 'queued'].includes(status);
    const canRollback = (status: string) => ['finished', 'failed'].includes(status);

    // Format time ago
    const formatTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins} min ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    };

    const handleDeploy = async () => {
        setIsDeploying(true);
        try {
            await startDeployment(service.uuid);
            toast({ title: 'Deployment started', variant: 'success' });
        } catch (err) {
            toast({
                title: 'Failed to deploy',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setIsDeploying(false);
        }
    };

    const handleViewLogs = (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        router.visit(`/applications/${service.uuid}/deployments/${deploymentUuid}`);
    };

    const handleRestart = async () => {
        try {
            const response = await fetch(`/api/v1/applications/${service.uuid}/restart`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to restart');
            toast({ title: 'Restart initiated', variant: 'success' });
            refetch();
        } catch (err) {
            toast({
                title: 'Failed to restart',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        }
    };

    const handleRollback = async (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        const confirmed = await confirm({
            title: 'Rollback deployment',
            description: `Are you sure you want to rollback to this deployment? This will redeploy the application with the previous configuration.`,
            confirmText: 'Rollback',
            variant: 'warning',
        });

        if (!confirmed) return;

        setRollingBackId(deploymentUuid);
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${service.uuid}/rollback/${deploymentUuid}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to rollback');
            }

            toast({ title: 'Rollback initiated', variant: 'success' });
            refetch();
        } catch (err) {
            toast({
                title: 'Failed to rollback',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setRollingBackId(null);
        }
    };

    const handleCancel = async (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        const confirmed = await confirm({
            title: 'Cancel deployment',
            description: 'Are you sure you want to cancel this deployment?',
            confirmText: 'Cancel Deployment',
            variant: 'danger',
        });

        if (!confirmed) return;

        setCancellingId(deploymentUuid);
        try {
            await cancelDeployment(deploymentUuid);
            toast({ title: 'Deployment cancelled', variant: 'success' });
        } catch (err) {
            toast({
                title: 'Failed to cancel',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setCancellingId(null);
        }
    };

    if (isLoading && deployments.length === 0) {
        return (
            <div className="space-y-4">
                <Button className="w-full" onClick={handleDeploy} disabled={isDeploying}>
                    <Play className="mr-2 h-4 w-4" />
                    {isDeploying ? 'Starting...' : 'Deploy Now'}
                </Button>
                <div className="flex items-center justify-center py-8 text-foreground-muted">
                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                    Loading deployments...
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Deploy Button */}
            <Button className="w-full" onClick={handleDeploy} disabled={isDeploying}>
                <Play className="mr-2 h-4 w-4" />
                {isDeploying ? 'Starting...' : 'Deploy Now'}
            </Button>

            {/* Deployments List */}
            <div className="space-y-4">
                {deployments.length === 0 ? (
                    <div className="text-center py-8 text-foreground-muted">
                        No deployments yet. Click "Deploy Now" to start your first deployment.
                    </div>
                ) : (
                    deployments.map((deploy, index) => {
                        const deploymentUuid = deploy.deployment_uuid || deploy.uuid;
                        const displayStatus = getDisplayStatus(deploy.status);

                        return (
                            <div key={deploy.id}>
                                {index === 0 && (
                                    <div className="mb-3 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-foreground-subtle">
                                        <FileText className="h-3 w-3" />
                                        Recent
                                    </div>
                                )}
                                {index === 2 && (
                                    <div className="mb-3 mt-6 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-foreground-subtle">
                                        <Clock className="h-3 w-3" />
                                        History
                                    </div>
                                )}
                                <div className="space-y-3 rounded-lg border border-border bg-background-secondary p-4">
                                    {/* Status Badge & Actions Menu */}
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className={`rounded-md px-2 py-1 text-xs font-medium uppercase ${getBadgeStyle(deploy.status)}`}>
                                                {displayStatus}
                                            </span>
                                            <span className="text-xs text-foreground-muted">
                                                {formatTimeAgo(deploy.created_at)}
                                            </span>
                                        </div>

                                        {/* Three-dot Actions Menu */}
                                        <Dropdown>
                                            <DropdownTrigger>
                                                <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground transition-colors">
                                                    <MoreVertical className="h-4 w-4" />
                                                </button>
                                            </DropdownTrigger>
                                            <DropdownContent align="right">
                                                <DropdownItem
                                                    onClick={() => handleViewLogs(deploy)}
                                                    icon={<Eye className="h-4 w-4" />}
                                                >
                                                    View Logs
                                                </DropdownItem>

                                                {deploy.status === 'finished' && index === 0 && (
                                                    <DropdownItem
                                                        onClick={handleRestart}
                                                        icon={<RefreshCw className="h-4 w-4" />}
                                                    >
                                                        Restart
                                                    </DropdownItem>
                                                )}

                                                <DropdownItem
                                                    onClick={handleDeploy}
                                                    icon={<Play className="h-4 w-4" />}
                                                >
                                                    Redeploy
                                                </DropdownItem>

                                                {canRollback(deploy.status) && index !== 0 && (
                                                    <DropdownItem
                                                        onClick={() => handleRollback(deploy)}
                                                        disabled={rollingBackId === deploymentUuid}
                                                        icon={<RotateCcw className={`h-4 w-4 ${rollingBackId === deploymentUuid ? 'animate-spin' : ''}`} />}
                                                    >
                                                        {rollingBackId === deploymentUuid ? 'Rolling back...' : 'Rollback to this'}
                                                    </DropdownItem>
                                                )}

                                                {isInProgress(deploy.status) && (
                                                    <>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            onClick={() => handleCancel(deploy)}
                                                            disabled={cancellingId === deploymentUuid}
                                                            danger
                                                            icon={<StopCircle className="h-4 w-4" />}
                                                        >
                                                            {cancellingId === deploymentUuid ? 'Cancelling...' : 'Cancel'}
                                                        </DropdownItem>
                                                    </>
                                                )}
                                            </DropdownContent>
                                        </Dropdown>
                                    </div>

                                    {/* Commit Info */}
                                    <div className="flex items-start gap-3">
                                        <div className="h-6 w-6 rounded-full bg-foreground-muted/20 flex items-center justify-center">
                                            <GitCommit className="h-3 w-3 text-foreground-muted" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-foreground truncate">
                                                {deploy.commit_message || 'Manual deployment'}
                                            </p>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                                <GitCommit className="h-3 w-3" />
                                                <code>{deploy.commit?.slice(0, 7) || deploymentUuid.slice(0, 7)}</code>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Deployment Progress */}
                                    {isInProgress(deploy.status) && (
                                        <div className="rounded-md bg-background p-3">
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500" />
                                                <p className="text-xs text-foreground-muted">
                                                    {deploy.status === 'queued' ? 'Waiting in queue...' : 'Deployment in progress...'}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {/* Quick Actions Row */}
                                    <div className="flex gap-2">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            className="flex-1"
                                            onClick={() => handleViewLogs(deploy)}
                                        >
                                            <Eye className="mr-2 h-3 w-3" />
                                            Logs
                                        </Button>
                                        {deploy.status === 'finished' && index === 0 && (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                className="flex-1"
                                                onClick={handleRestart}
                                            >
                                                <RefreshCw className="mr-2 h-3 w-3" />
                                                Restart
                                            </Button>
                                        )}
                                        {isInProgress(deploy.status) && (
                                            <Button
                                                variant="danger"
                                                size="sm"
                                                onClick={() => handleCancel(deploy)}
                                                disabled={cancellingId === deploymentUuid}
                                            >
                                                <StopCircle className="mr-2 h-3 w-3" />
                                                {cancellingId === deploymentUuid ? 'Cancelling...' : 'Cancel'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
