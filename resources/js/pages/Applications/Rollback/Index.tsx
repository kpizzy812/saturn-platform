import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, Modal, ModalFooter } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import { GitCommit, Clock, User, RotateCw, Eye, ArrowLeft, Loader2 } from 'lucide-react';
import type { Application } from '@/types';
import { RollbackTimeline } from '@/components/features/RollbackTimeline';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';

interface Props {
    application: Application;
    projectUuid: string;
    environmentUuid: string;
}

type DeploymentStatus = 'finished' | 'failed' | 'in_progress' | 'queued' | 'cancelled' | 'rolled_back';

interface Deployment {
    id: number;
    deployment_uuid: string;
    commit: string;
    commit_message: string | null;
    status: DeploymentStatus;
    created_at: string;
    updated_at: string;
    rollback: boolean;
    is_webhook: boolean;
    is_api: boolean;
}

interface RollbackEvent {
    id: number;
    application_id: number;
    failed_deployment_id: number | null;
    rollback_deployment_id: number | null;
    triggered_by_user_id: number | null;
    trigger_reason: string;
    trigger_type: 'manual' | 'automatic';
    status: 'triggered' | 'in_progress' | 'success' | 'failed' | 'skipped';
    from_commit: string | null;
    to_commit: string | null;
    triggered_at: string;
    completed_at: string | null;
    triggered_by_user?: {
        id: number;
        name: string;
        email: string;
    };
}

export default function ApplicationRollbackIndex({ application, projectUuid, environmentUuid }: Props) {
    const [deployments, setDeployments] = useState<Deployment[]>([]);
    const [rollbackEvents, setRollbackEvents] = useState<RollbackEvent[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [showRollbackModal, setShowRollbackModal] = useState(false);
    const [showDiffModal, setShowDiffModal] = useState(false);
    const [selectedDeployment, setSelectedDeployment] = useState<Deployment | null>(null);
    const [isRollingBack, setIsRollingBack] = useState(false);
    const { addToast } = useToast();

    // Load deployments and rollback events
    useEffect(() => {
        const loadData = async () => {
            try {
                setIsLoading(true);

                // Fetch deployments
                const deploymentsRes = await fetch(`/api/v1/applications/${application.uuid}/deployments?take=20`);
                if (deploymentsRes.ok) {
                    const deploymentsData = await deploymentsRes.json();
                    // Filter only successful deployments for rollback
                    const successfulDeployments = Array.isArray(deploymentsData)
                        ? deploymentsData.filter((d: Deployment) => d.status === 'finished')
                        : [];
                    setDeployments(successfulDeployments);
                }

                // Fetch rollback events
                const eventsRes = await fetch(`/api/v1/applications/${application.uuid}/rollback-events?take=10`);
                if (eventsRes.ok) {
                    const eventsData = await eventsRes.json();
                    setRollbackEvents(Array.isArray(eventsData) ? eventsData : []);
                }
            } catch (error) {
                console.error('Failed to load rollback data:', error);
                addToast('error', 'Failed to load rollback data');
            } finally {
                setIsLoading(false);
            }
        };

        loadData();
    }, [application.uuid, addToast]);

    const handleRollbackClick = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setShowRollbackModal(true);
    };

    const handleViewDiff = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setShowDiffModal(true);
    };

    const handleConfirmRollback = async () => {
        if (!selectedDeployment) return;

        setIsRollingBack(true);
        try {
            const response = await fetch(
                `/api/v1/applications/${application.uuid}/rollback/${selectedDeployment.deployment_uuid}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    },
                }
            );

            if (response.ok) {
                const data = await response.json();
                addToast('success', 'Rollback initiated successfully');
                setShowRollbackModal(false);
                setSelectedDeployment(null);

                // Redirect to deployment view
                if (data.deployment_uuid) {
                    router.visit(
                        `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}/deployment/${data.deployment_uuid}`
                    );
                }
            } else {
                const error = await response.json();
                addToast('error', error.message || 'Failed to initiate rollback');
            }
        } catch (error) {
            console.error('Rollback failed:', error);
            addToast('error', 'Failed to initiate rollback');
        } finally {
            setIsRollingBack(false);
        }
    };

    const formatTimeAgo = (date: string): string => {
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

    // Find current active deployment
    const currentDeployment = deployments.length > 0 ? deployments[0] : null;

    return (
        <AppLayout
            title={`Rollback - ${application.name}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: 'Application', href: `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}` },
                { label: 'Rollback' },
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/project/${projectUuid}/${environmentUuid}/application/${application.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Application
            </Link>

            <div className="space-y-6">
                {/* Header Info */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-xl font-semibold text-foreground">Deployment History</h2>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Roll back to any previous successful deployment with a single click
                                </p>
                            </div>
                            <Badge variant="info">
                                {deployments.length} successful deployments
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                {/* Timeline Visualization */}
                {!isLoading && deployments.length > 0 && (
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 font-medium text-foreground">Deployment Timeline</h3>
                            <RollbackTimeline
                                deployments={deployments}
                                currentDeploymentId={currentDeployment?.id}
                                onSelectDeployment={handleRollbackClick}
                            />
                        </CardContent>
                    </Card>
                )}

                {/* Rollback Events History */}
                {rollbackEvents.length > 0 && (
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 font-medium text-foreground">Recent Rollback Events</h3>
                            <div className="space-y-3">
                                {rollbackEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="rounded-lg border border-border bg-background-secondary p-4"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant={event.status === 'success' ? 'success' : event.status === 'failed' ? 'danger' : 'warning'}>
                                                        {event.status}
                                                    </Badge>
                                                    <Badge variant="default">
                                                        {event.trigger_type}
                                                    </Badge>
                                                </div>
                                                <p className="mt-2 text-sm text-foreground">
                                                    {event.trigger_reason.replace(/_/g, ' ')}
                                                </p>
                                                {event.from_commit && event.to_commit && (
                                                    <p className="mt-1 text-xs text-foreground-muted">
                                                        From <code>{event.from_commit.substring(0, 7)}</code> to{' '}
                                                        <code>{event.to_commit.substring(0, 7)}</code>
                                                    </p>
                                                )}
                                            </div>
                                            <div className="text-right text-xs text-foreground-muted">
                                                {event.triggered_by_user && (
                                                    <div className="mb-1">
                                                        by {event.triggered_by_user.name}
                                                    </div>
                                                )}
                                                <div>{formatTimeAgo(event.triggered_at)}</div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Deployments List */}
                {isLoading ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            <p className="mt-4 text-sm text-foreground-muted">Loading deployments...</p>
                        </CardContent>
                    </Card>
                ) : deployments.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <AlertCircle className="h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No deployments found</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                No successful deployments available for rollback
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {deployments.map((deployment, index) => {
                            const isCurrentActive = index === 0;
                            const commitShort = deployment.commit?.substring(0, 7) || 'unknown';
                            const message = deployment.commit_message || 'No commit message';

                            return (
                                <Card key={deployment.id}>
                                    <CardContent className="p-4">
                                        <div className="flex items-start gap-4">
                                            {/* Status Icon */}
                                            <div className="mt-1">{getStatusIcon(deployment.status)}</div>

                                            {/* Deployment Info */}
                                            <div className="flex-1 min-w-0">
                                                {/* Header */}
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <div className="flex items-center gap-2">
                                                                <GitCommit className="h-3.5 w-3.5 text-foreground-muted" />
                                                                <code className="text-sm font-medium text-foreground">
                                                                    {commitShort}
                                                                </code>
                                                            </div>
                                                            {isCurrentActive && (
                                                                <Badge variant="success">Active</Badge>
                                                            )}
                                                            <Badge variant={getStatusVariant(deployment.status)} className="capitalize">
                                                                {deployment.status.replace('_', ' ')}
                                                            </Badge>
                                                            {deployment.rollback && (
                                                                <Badge variant="warning">Rollback</Badge>
                                                            )}
                                                        </div>
                                                        <p className="mt-1 text-sm text-foreground line-clamp-1">
                                                            {message}
                                                        </p>
                                                    </div>

                                                    {/* Actions */}
                                                    <div className="flex items-center gap-2 flex-shrink-0">
                                                        <Link
                                                            href={`/project/${projectUuid}/${environmentUuid}/application/${application.uuid}/deployment/${deployment.deployment_uuid}`}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Eye className="mr-1 h-3 w-3" />
                                                                View
                                                            </Button>
                                                        </Link>
                                                        {!isCurrentActive && deployment.status === 'finished' && (
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                onClick={() => handleRollbackClick(deployment)}
                                                            >
                                                                <RotateCw className="mr-1 h-3 w-3" />
                                                                Rollback
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Meta Info */}
                                                <div className="mt-3 flex items-center gap-4 text-xs text-foreground-muted flex-wrap">
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        <span>{formatTimeAgo(deployment.created_at)}</span>
                                                    </div>
                                                    {deployment.is_webhook && (
                                                        <>
                                                            <span>·</span>
                                                            <Badge variant="default" className="text-xs">Webhook</Badge>
                                                        </>
                                                    )}
                                                    {deployment.is_api && (
                                                        <>
                                                            <span>·</span>
                                                            <Badge variant="default" className="text-xs">API</Badge>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Rollback Confirmation Modal */}
            <Modal
                isOpen={showRollbackModal}
                onClose={() => !isRollingBack && setShowRollbackModal(false)}
                title="Confirm Rollback"
                description="Are you sure you want to roll back to this deployment?"
            >
                {selectedDeployment && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex items-center gap-2">
                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                <code className="text-sm font-medium text-foreground">
                                    {selectedDeployment.commit}
                                </code>
                            </div>
                            <p className="mt-2 text-sm text-foreground">
                                {selectedDeployment.commit_message || 'No commit message'}
                            </p>
                            <div className="mt-3 flex items-center gap-3 text-xs text-foreground-muted">
                                <span>{formatTimeAgo(selectedDeployment.created_at)}</span>
                            </div>
                        </div>

                        <div className="rounded-lg border border-warning bg-warning/10 p-4">
                            <div className="flex items-start gap-2">
                                <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                <div className="text-sm text-foreground">
                                    <p className="font-medium">This action will:</p>
                                    <ul className="mt-2 list-disc list-inside space-y-1 text-foreground-muted">
                                        <li>Stop the current deployment</li>
                                        <li>Deploy the selected version</li>
                                        <li>May cause brief downtime</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <ModalFooter>
                            <Button
                                variant="secondary"
                                onClick={() => setShowRollbackModal(false)}
                                disabled={isRollingBack}
                            >
                                Cancel
                            </Button>
                            <Button onClick={handleConfirmRollback} disabled={isRollingBack}>
                                {isRollingBack ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Rolling back...
                                    </>
                                ) : (
                                    <>
                                        <RotateCw className="mr-2 h-4 w-4" />
                                        Confirm Rollback
                                    </>
                                )}
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
