import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Modal, ModalFooter } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import {
    GitCommit, Clock, User, ArrowLeft, RotateCw, AlertCircle,
    Calendar, GitBranch, CheckCircle, XCircle, Loader2, Eye
} from 'lucide-react';
import type { Application } from '@/types';
import { getStatusLabel } from '@/lib/statusUtils';

interface Props {
    application: Application;
    deploymentUuid: string;
    projectUuid: string;
    environmentUuid: string;
}

interface DeploymentDetail {
    id: number;
    deployment_uuid: string;
    commit: string;
    commit_message: string | null;
    status: string;
    created_at: string;
    updated_at: string;
    rollback: boolean;
    force_rebuild: boolean;
    is_webhook: boolean;
    is_api: boolean;
    server_id: number;
    server_name?: string;
}

export default function ApplicationRollbackShow({
    application,
    deploymentUuid,
    projectUuid,
    environmentUuid
}: Props) {
    const [deployment, setDeployment] = useState<DeploymentDetail | null>(null);
    const [currentDeployment, setCurrentDeployment] = useState<DeploymentDetail | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [showRollbackModal, setShowRollbackModal] = useState(false);
    const [showDiffModal, setShowDiffModal] = useState(false);
    const [isRollingBack, setIsRollingBack] = useState(false);
    const { addToast } = useToast();

    useEffect(() => {
        const loadData = async () => {
            try {
                setIsLoading(true);

                const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

                // Fetch the specific deployment
                const deploymentRes = await fetch(`/api/v1/deployments/${deploymentUuid}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                });
                if (deploymentRes.ok) {
                    const deploymentData = await deploymentRes.json();
                    setDeployment(deploymentData);
                }

                // Fetch current (latest) deployment
                const deploymentsRes = await fetch(`/api/v1/applications/${application.uuid}/deployments?take=1`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                });
                if (deploymentsRes.ok) {
                    const deploymentsData = await deploymentsRes.json();
                    if (Array.isArray(deploymentsData) && deploymentsData.length > 0) {
                        setCurrentDeployment(deploymentsData[0]);
                    }
                }
            } catch {
                addToast('error', 'Failed to load deployment details');
            } finally {
                setIsLoading(false);
            }
        };

        loadData();
    }, [application.uuid, deploymentUuid, addToast]);

    const handleConfirmRollback = async () => {
        if (!deployment) return;

        setIsRollingBack(true);
        try {
            const response = await fetch(
                `/api/v1/applications/${application.uuid}/rollback/${deployment.deployment_uuid}`,
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

                // Redirect to the new deployment
                if (data.deployment_uuid) {
                    router.visit(
                        `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}/deployment/${data.deployment_uuid}`
                    );
                }
            } else {
                const error = await response.json();
                addToast('error', error.message || 'Failed to initiate rollback');
            }
        } catch {
            addToast('error', 'Failed to initiate rollback');
        } finally {
            setIsRollingBack(false);
        }
    };

    const formatDate = (date: string): string => {
        return new Date(date).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const isCurrentDeployment = deployment?.deployment_uuid === currentDeployment?.deployment_uuid;
    const canRollback = deployment?.status === 'finished' && !isCurrentDeployment;

    return (
        <AppLayout
            title={`Deployment Details - ${application.name}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: 'Application', href: `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}` },
                { label: 'Rollback', href: `/applications/${application.uuid}/rollback` },
                { label: 'Details' },
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/applications/${application.uuid}/rollback`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Rollback History
            </Link>

            {isLoading ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-12">
                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                        <p className="mt-4 text-sm text-foreground-muted">Loading deployment details...</p>
                    </CardContent>
                </Card>
            ) : !deployment ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-12">
                        <AlertCircle className="h-12 w-12 text-danger" />
                        <h3 className="mt-4 font-medium text-foreground">Deployment not found</h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            The requested deployment could not be found
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-6">
                    {/* Header Card */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 flex-wrap">
                                        <h2 className="text-2xl font-bold text-foreground">
                                            Deployment Details
                                        </h2>
                                        {isCurrentDeployment && (
                                            <Badge variant="success">Current Active</Badge>
                                        )}
                                        {deployment.rollback && (
                                            <Badge variant="warning">Rollback</Badge>
                                        )}
                                        {deployment.status === 'finished' ? (
                                            <Badge variant="success">
                                                <CheckCircle className="mr-1 h-3 w-3" />
                                                Successful
                                            </Badge>
                                        ) : deployment.status === 'failed' ? (
                                            <Badge variant="danger">
                                                <XCircle className="mr-1 h-3 w-3" />
                                                Failed
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning">{getStatusLabel(deployment.status)}</Badge>
                                        )}
                                    </div>
                                    <p className="mt-2 text-foreground-muted">
                                        Review deployment details and roll back if needed
                                    </p>
                                </div>
                                {canRollback && (
                                    <Button onClick={() => setShowRollbackModal(true)}>
                                        <RotateCw className="mr-2 h-4 w-4" />
                                        Rollback to this Version
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deployment Information */}
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Left Column */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Commit Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Commit Hash</label>
                                    <div className="mt-1 flex items-center gap-2">
                                        <GitCommit className="h-4 w-4 text-foreground-muted" />
                                        <code className="rounded bg-background-tertiary px-2 py-1 text-sm font-medium text-foreground">
                                            {deployment.commit}
                                        </code>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Commit Message</label>
                                    <p className="mt-1 text-sm text-foreground">
                                        {deployment.commit_message || 'No commit message'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Deployment UUID</label>
                                    <code className="mt-1 block rounded bg-background-tertiary px-2 py-1 text-xs font-medium text-foreground">
                                        {deployment.deployment_uuid}
                                    </code>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Right Column */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Deployment Metadata</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Deployed At</label>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm text-foreground">
                                            {formatDate(deployment.created_at)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Trigger Source</label>
                                    <div className="mt-1 flex items-center gap-2">
                                        {deployment.is_webhook ? (
                                            <Badge variant="info">Webhook</Badge>
                                        ) : deployment.is_api ? (
                                            <Badge variant="info">API</Badge>
                                        ) : (
                                            <Badge variant="default">Manual</Badge>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Build Options</label>
                                    <div className="mt-1 flex flex-wrap gap-2">
                                        {deployment.force_rebuild && (
                                            <Badge variant="warning">Force Rebuild</Badge>
                                        )}
                                        {deployment.rollback && (
                                            <Badge variant="warning">Rollback</Badge>
                                        )}
                                    </div>
                                </div>
                                {deployment.server_name && (
                                    <div>
                                        <label className="text-sm font-medium text-foreground-muted">Server</label>
                                        <p className="mt-1 text-sm text-foreground">
                                            {deployment.server_name}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Comparison with Current Deployment */}
                    {!isCurrentDeployment && currentDeployment && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Comparison with Current Deployment</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg border border-border bg-background-secondary p-4">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <div className="mb-2 text-sm font-medium text-foreground-muted">
                                                This Deployment
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                                <code className="text-sm font-medium text-foreground">
                                                    {deployment.commit.substring(0, 7)}
                                                </code>
                                            </div>
                                            <p className="mt-1 text-sm text-foreground-muted line-clamp-2">
                                                {deployment.commit_message || 'No commit message'}
                                            </p>
                                            <div className="mt-2 flex items-center gap-1 text-xs text-foreground-muted">
                                                <Clock className="h-3 w-3" />
                                                <span>{formatDate(deployment.created_at)}</span>
                                            </div>
                                        </div>

                                        <div>
                                            <div className="mb-2 flex items-center gap-2 text-sm font-medium text-foreground-muted">
                                                <span>Current Active Deployment</span>
                                                <Badge variant="success" className="text-xs">Active</Badge>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                                <code className="text-sm font-medium text-foreground">
                                                    {currentDeployment.commit.substring(0, 7)}
                                                </code>
                                            </div>
                                            <p className="mt-1 text-sm text-foreground-muted line-clamp-2">
                                                {currentDeployment.commit_message || 'No commit message'}
                                            </p>
                                            <div className="mt-2 flex items-center gap-1 text-xs text-foreground-muted">
                                                <Clock className="h-3 w-3" />
                                                <span>{formatDate(currentDeployment.created_at)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {canRollback && (
                                        <div className="mt-4 rounded-lg border border-warning bg-warning/10 p-3">
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                                <div className="text-sm">
                                                    <p className="font-medium text-foreground">Rolling back will:</p>
                                                    <ul className="mt-1 list-disc list-inside space-y-0.5 text-foreground-muted">
                                                        <li>Replace current deployment with this version</li>
                                                        <li>May cause brief service interruption</li>
                                                        <li>Create a new rollback event</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Actions Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-3">
                                <Link
                                    href={`/project/${projectUuid}/${environmentUuid}/application/${application.uuid}/deployment/${deployment.deployment_uuid}`}
                                >
                                    <Button variant="secondary">
                                        <Eye className="mr-2 h-4 w-4" />
                                        View Full Deployment Logs
                                    </Button>
                                </Link>
                                {canRollback && (
                                    <Button onClick={() => setShowRollbackModal(true)}>
                                        <RotateCw className="mr-2 h-4 w-4" />
                                        Rollback to this Version
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Rollback Confirmation Modal */}
            <Modal
                isOpen={showRollbackModal}
                onClose={() => !isRollingBack && setShowRollbackModal(false)}
                title="Confirm Rollback"
                description="Are you sure you want to roll back to this deployment?"
            >
                {deployment && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex items-center gap-2">
                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                <code className="text-sm font-medium text-foreground">
                                    {deployment.commit}
                                </code>
                            </div>
                            <p className="mt-2 text-sm text-foreground">
                                {deployment.commit_message || 'No commit message'}
                            </p>
                            <div className="mt-3 flex items-center gap-3 text-xs text-foreground-muted">
                                <span>{formatDate(deployment.created_at)}</span>
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
                                        <li>Create a rollback event in the history</li>
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
