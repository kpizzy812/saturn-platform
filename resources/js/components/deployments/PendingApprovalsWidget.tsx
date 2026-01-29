import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Modal, ModalFooter, Input, useToast } from '@/components/ui';
import { Clock, CheckCircle, XCircle, ChevronRight, RefreshCw } from 'lucide-react';
import type { DeploymentApproval } from '@/types/models';

interface Props {
    projectUuid?: string;
    showProjectName?: boolean;
    maxItems?: number;
}

export function PendingApprovalsWidget({ projectUuid, showProjectName = false, maxItems = 5 }: Props) {
    const { addToast } = useToast();
    const [approvals, setApprovals] = useState<DeploymentApproval[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

    // Reject modal state
    const [showRejectModal, setShowRejectModal] = useState(false);
    const [rejectingDeploymentUuid, setRejectingDeploymentUuid] = useState<string | null>(null);
    const [rejectReason, setRejectReason] = useState('');

    const fetchApprovals = async () => {
        setLoading(true);
        setError('');
        try {
            const url = projectUuid
                ? `/projects/${projectUuid}/approvals/pending/json`
                : '/approvals/pending/json';
            const res = await fetch(url, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (!res.ok) throw new Error('Failed to fetch');
            const data = await res.json();
            setApprovals(data.slice(0, maxItems));
        } catch {
            setError('Failed to load pending approvals');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchApprovals();
        // Set up polling every 30 seconds
        const interval = setInterval(fetchApprovals, 30000);
        return () => clearInterval(interval);
    }, [projectUuid]);

    const handleApprove = async (deploymentUuid: string) => {
        setActionLoading(deploymentUuid);
        try {
            const res = await fetch(`/deployments/${deploymentUuid}/approve/json`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (!res.ok) {
                const err = await res.json();
                addToast('error', 'Approval Failed', err.message || 'Failed to approve deployment');
                return;
            }
            addToast('success', 'Deployment Approved', 'The deployment has been approved.');
            fetchApprovals();
        } catch {
            addToast('error', 'Approval Failed', 'Failed to approve deployment. Please try again.');
        } finally {
            setActionLoading(null);
        }
    };

    const openRejectModal = (deploymentUuid: string) => {
        setRejectingDeploymentUuid(deploymentUuid);
        setRejectReason('');
        setShowRejectModal(true);
    };

    const handleReject = async () => {
        if (!rejectingDeploymentUuid) return;

        setActionLoading(rejectingDeploymentUuid);
        try {
            const res = await fetch(`/deployments/${rejectingDeploymentUuid}/reject/json`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ reason: rejectReason }),
            });
            if (!res.ok) {
                const err = await res.json();
                addToast('error', 'Rejection Failed', err.message || 'Failed to reject deployment');
                return;
            }
            addToast('success', 'Deployment Rejected', 'The deployment has been rejected.');
            setShowRejectModal(false);
            setRejectingDeploymentUuid(null);
            fetchApprovals();
        } catch {
            addToast('error', 'Rejection Failed', 'Failed to reject deployment. Please try again.');
        } finally {
            setActionLoading(null);
        }
    };

    if (loading && approvals.length === 0) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-foreground-muted">
                    <RefreshCw className="mx-auto mb-2 h-5 w-5 animate-spin" />
                    Loading approvals...
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Card>
                <CardContent className="py-8 text-center text-danger">
                    {error}
                    <Button variant="ghost" size="sm" onClick={fetchApprovals} className="ml-2">
                        Retry
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (approvals.length === 0) {
        return null; // Don't show widget if no pending approvals
    }

    return (
        <Card className="border-warning/50">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-warning">
                    <Clock className="h-5 w-5" />
                    Pending Approvals ({approvals.length})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="divide-y divide-border">
                    {approvals.map((approval) => (
                        <div key={approval.uuid} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="truncate font-medium text-foreground">
                                        {approval.application_name}
                                    </span>
                                    <Badge variant="warning" size="sm">
                                        {approval.environment_name}
                                    </Badge>
                                </div>
                                <div className="mt-0.5 text-sm text-foreground-muted">
                                    {showProjectName && approval.project_name && (
                                        <span>{approval.project_name} &middot; </span>
                                    )}
                                    Requested by {approval.requested_by}
                                    <span className="mx-1">&middot;</span>
                                    {formatTimeAgo(approval.requested_at)}
                                </div>
                            </div>
                            <div className="ml-4 flex shrink-0 items-center gap-2">
                                <Button
                                    variant="success"
                                    size="sm"
                                    onClick={() => handleApprove(approval.deployment_uuid)}
                                    disabled={actionLoading === approval.deployment_uuid}
                                >
                                    <CheckCircle className="mr-1 h-4 w-4" />
                                    Approve
                                </Button>
                                <Button
                                    variant="danger"
                                    size="sm"
                                    onClick={() => openRejectModal(approval.deployment_uuid)}
                                    disabled={actionLoading === approval.deployment_uuid}
                                >
                                    <XCircle className="mr-1 h-4 w-4" />
                                    Reject
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
                {approvals.length >= maxItems && (
                    <div className="mt-3 border-t border-border pt-3 text-center">
                        <Link
                            href="/approvals"
                            className="inline-flex items-center text-sm text-foreground-muted hover:text-foreground"
                        >
                            View all approvals
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </Link>
                    </div>
                )}
            </CardContent>

            {/* Reject Confirmation Modal */}
            <Modal
                isOpen={showRejectModal}
                onClose={() => {
                    setShowRejectModal(false);
                    setRejectingDeploymentUuid(null);
                    setRejectReason('');
                }}
                title="Reject Deployment"
                description="Are you sure you want to reject this deployment?"
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-danger/50 bg-danger/10 p-4">
                        <p className="text-sm font-medium text-danger">This action cannot be undone</p>
                        <p className="mt-1 text-sm text-foreground-muted">
                            The deployment will be cancelled and the requester will be notified.
                        </p>
                    </div>

                    <Input
                        label="Rejection Reason (optional)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        placeholder="Enter a reason for rejection..."
                    />

                    <ModalFooter>
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setShowRejectModal(false);
                                setRejectingDeploymentUuid(null);
                                setRejectReason('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleReject}
                            loading={actionLoading === rejectingDeploymentUuid}
                        >
                            <XCircle className="mr-2 h-4 w-4" />
                            Reject
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>
        </Card>
    );
}

function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}
