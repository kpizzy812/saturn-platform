import { useState, useEffect, useCallback } from 'react';
import { Modal, ModalFooter, Button, Badge } from '@/components/ui';
import { Clock, CheckCircle, XCircle, AlertTriangle, RefreshCw, User } from 'lucide-react';
import type { DeploymentApproval } from '@/types/models';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    deploymentUuid: string;
    applicationName: string;
    environmentName: string;
    onApproved?: () => void;
    onRejected?: () => void;
}

type ApprovalStatus = 'loading' | 'pending' | 'approved' | 'rejected' | 'error';

export function DeploymentApprovalModal({
    isOpen,
    onClose,
    deploymentUuid,
    applicationName,
    environmentName,
    onApproved,
    onRejected,
}: Props) {
    const [status, setStatus] = useState<ApprovalStatus>('loading');
    const [approval, setApproval] = useState<DeploymentApproval | null>(null);
    const [error, setError] = useState('');

    const fetchApprovalStatus = useCallback(async () => {
        try {
            const res = await fetch(`/api/v1/deployments/${deploymentUuid}/approval-status`);
            if (!res.ok) {
                if (res.status === 404) {
                    // No approval required or not found
                    setStatus('error');
                    setError('No approval request found for this deployment');
                    return;
                }
                throw new Error('Failed to fetch approval status');
            }
            const data = await res.json();
            setApproval(data);
            setStatus(data.status as ApprovalStatus);

            if (data.status === 'approved' && onApproved) {
                onApproved();
            } else if (data.status === 'rejected' && onRejected) {
                onRejected();
            }
        } catch {
            setStatus('error');
            setError('Failed to fetch approval status');
        }
    }, [deploymentUuid, onApproved, onRejected]);

    useEffect(() => {
        if (isOpen && deploymentUuid) {
            fetchApprovalStatus();
            // Poll every 5 seconds for status updates
            const interval = setInterval(fetchApprovalStatus, 5000);
            return () => clearInterval(interval);
        }
    }, [isOpen, deploymentUuid, fetchApprovalStatus]);

    const getStatusIcon = () => {
        switch (status) {
            case 'loading':
                return <RefreshCw className="h-12 w-12 animate-spin text-foreground-muted" />;
            case 'pending':
                return <Clock className="h-12 w-12 text-warning" />;
            case 'approved':
                return <CheckCircle className="h-12 w-12 text-success" />;
            case 'rejected':
                return <XCircle className="h-12 w-12 text-danger" />;
            case 'error':
                return <AlertTriangle className="h-12 w-12 text-danger" />;
        }
    };

    const getStatusBadge = () => {
        switch (status) {
            case 'pending':
                return <Badge variant="warning">Pending Approval</Badge>;
            case 'approved':
                return <Badge variant="success">Approved</Badge>;
            case 'rejected':
                return <Badge variant="danger">Rejected</Badge>;
            default:
                return null;
        }
    };

    const getStatusMessage = () => {
        switch (status) {
            case 'loading':
                return 'Loading approval status...';
            case 'pending':
                return 'Waiting for approval from a project admin or owner.';
            case 'approved':
                return 'Deployment has been approved and will proceed shortly.';
            case 'rejected':
                return approval?.comment
                    ? `Deployment was rejected: ${approval.comment}`
                    : 'Deployment was rejected.';
            case 'error':
                return error;
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Deployment Approval Required"
            size="default"
        >
            <div className="text-center">
                <div className="mb-4 flex justify-center">
                    {getStatusIcon()}
                </div>

                <div className="mb-4 space-y-2">
                    <div className="text-lg font-semibold text-foreground">
                        {applicationName}
                    </div>
                    <div className="flex items-center justify-center gap-2">
                        <span className="text-foreground-muted">Environment:</span>
                        <Badge variant="info">{environmentName}</Badge>
                    </div>
                    {getStatusBadge() && (
                        <div className="flex justify-center">
                            {getStatusBadge()}
                        </div>
                    )}
                </div>

                <p className="mb-4 text-sm text-foreground-muted">
                    {getStatusMessage()}
                </p>

                {status === 'pending' && (
                    <div className="rounded-lg border border-warning/30 bg-warning/5 p-4">
                        <div className="flex items-start gap-3">
                            <Clock className="mt-0.5 h-5 w-5 shrink-0 text-warning" />
                            <div className="text-left text-sm">
                                <p className="font-medium text-warning">Approval Required</p>
                                <p className="mt-1 text-foreground-muted">
                                    Deployments to production environments require approval from
                                    a project admin or owner. An approval request has been sent
                                    automatically.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {approval && status !== 'pending' && approval.approved_by && (
                    <div className="mt-4 flex items-center justify-center gap-2 text-sm text-foreground-muted">
                        <User className="h-4 w-4" />
                        <span>
                            {status === 'approved' ? 'Approved' : 'Rejected'} by{' '}
                            <span className="font-medium text-foreground">
                                {approval.approved_by}
                            </span>
                        </span>
                    </div>
                )}
            </div>

            <ModalFooter>
                <Button
                    variant="secondary"
                    onClick={onClose}
                >
                    {status === 'approved' ? 'Close' : 'Cancel & Close'}
                </Button>
                {status === 'pending' && (
                    <Button
                        variant="ghost"
                        onClick={fetchApprovalStatus}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh Status
                    </Button>
                )}
            </ModalFooter>
        </Modal>
    );
}
