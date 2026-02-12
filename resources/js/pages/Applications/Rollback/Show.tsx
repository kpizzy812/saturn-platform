import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Modal, ModalFooter } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import {
    GitCommit, Clock, ArrowLeft, RotateCw, AlertCircle,
    Calendar, CheckCircle, XCircle, Loader2, Eye
} from 'lucide-react';
import type { Application } from '@/types';
import { getStatusLabel } from '@/lib/statusUtils';

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
    duration: number | null;
}

interface CurrentDeployment {
    id: number;
    deployment_uuid: string;
    commit: string;
    commit_message: string | null;
    status: string;
    created_at: string;
}

interface Props {
    application: Application;
    deployment: DeploymentDetail;
    currentDeployment: CurrentDeployment | null;
    projectUuid: string;
    environmentUuid: string;
}

export default function ApplicationRollbackShow({
    application,
    deployment,
    currentDeployment,
    projectUuid,
    environmentUuid
}: Props) {
    const [showRollbackModal, setShowRollbackModal] = useState(false);
    const [isRollingBack, setIsRollingBack] = useState(false);
    const { addToast } = useToast();

    const appBasePath = `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}`;

    const handleConfirmRollback = async () => {
        if (!deployment) return;

        setIsRollingBack(true);
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(
                `/applications/${application.uuid}/rollback/${deployment.deployment_uuid}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                }
            );

            if (response.ok) {
                const data = await response.json();
                addToast('success', 'Rollback initiated successfully');
                setShowRollbackModal(false);

                if (data.deployment_uuid) {
                    router.visit(`${appBasePath}/deployment/${data.deployment_uuid}`);
                }
            } else {
                const error = await response.json().catch(() => ({ message: 'Failed to initiate rollback' }));
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

    const formatDuration = (seconds: number | null): string => {
        if (!seconds) return '-';
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
    };

    const isCurrentDeployment = deployment.deployment_uuid === currentDeployment?.deployment_uuid;
    const canRollback = deployment.status === 'finished' && !isCurrentDeployment;

    return (
        <AppLayout
            title={`Deployment Details - ${application.name}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: 'Application', href: appBasePath },
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
                            {deployment.duration && (
                                <div>
                                    <label className="text-sm font-medium text-foreground-muted">Duration</label>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Clock className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm text-foreground">
                                            {formatDuration(deployment.duration)}
                                        </span>
                                    </div>
                                </div>
                            )}
                            <div>
                                <label className="text-sm font-medium text-foreground-muted">Trigger Source</label>
                                <div className="mt-1 flex items-center gap-2">
                                    {deployment.is_webhook ? (
                                        <Badge variant="info">Webhook</Badge>
                                    ) : deployment.is_api ? (
                                        <Badge variant="info">API</Badge>
                                    ) : deployment.rollback ? (
                                        <Badge variant="warning">Rollback</Badge>
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
                                    {!deployment.force_rebuild && !deployment.rollback && (
                                        <span className="text-sm text-foreground-muted">Standard build</span>
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
                                href={`${appBasePath}/deployment/${deployment.deployment_uuid}`}
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

            {/* Rollback Confirmation Modal */}
            <Modal
                isOpen={showRollbackModal}
                onClose={() => !isRollingBack && setShowRollbackModal(false)}
                title="Confirm Rollback"
                description="Are you sure you want to roll back to this deployment?"
            >
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
                            {deployment.duration && (
                                <span>Duration: {formatDuration(deployment.duration)}</span>
                            )}
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
            </Modal>
        </AppLayout>
    );
}
