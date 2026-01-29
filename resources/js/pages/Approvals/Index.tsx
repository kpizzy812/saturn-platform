import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Badge, Modal, ModalFooter, Input, useToast } from '@/components/ui';
import { Clock, CheckCircle, XCircle, RefreshCw, AlertTriangle, Inbox } from 'lucide-react';
import type { DeploymentApproval } from '@/types/models';

export default function ApprovalsIndex() {
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
            const res = await fetch('/approvals/pending/json', {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (!res.ok) throw new Error('Failed to fetch');
            const data = await res.json();
            setApprovals(data);
        } catch {
            setError('Failed to load pending approvals');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchApprovals();
        // Poll every 30 seconds
        const interval = setInterval(fetchApprovals, 30000);
        return () => clearInterval(interval);
    }, []);

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
            addToast('success', 'Deployment Approved', 'The deployment has been approved and will proceed.');
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

    const formatTimeAgo = (dateString: string): string => {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return `${Math.floor(seconds / 86400)}d ago`;
    };

    return (
        <AppLayout
            title="Pending Approvals"
            breadcrumbs={[{ label: 'Approvals' }]}
        >
            <div className="mx-auto max-w-4xl">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Pending Approvals</h1>
                        <p className="mt-1 text-foreground-muted">
                            Review and approve deployment requests for production environments
                        </p>
                    </div>
                    <Button variant="secondary" onClick={fetchApprovals} disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>

                {error && (
                    <Card className="mb-6 border-danger/50">
                        <CardContent className="flex items-center gap-3 py-4">
                            <AlertTriangle className="h-5 w-5 text-danger" />
                            <span className="text-danger">{error}</span>
                            <Button variant="ghost" size="sm" onClick={fetchApprovals} className="ml-auto">
                                Retry
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {loading && approvals.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <RefreshCw className="mx-auto mb-3 h-8 w-8 animate-spin text-foreground-muted" />
                            <p className="text-foreground-muted">Loading pending approvals...</p>
                        </CardContent>
                    </Card>
                ) : approvals.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <Inbox className="mx-auto mb-3 h-12 w-12 text-foreground-muted" />
                            <h3 className="text-lg font-medium text-foreground">No Pending Approvals</h3>
                            <p className="mt-1 text-foreground-muted">
                                All deployment requests have been processed
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {approvals.map((approval) => (
                            <Card key={approval.uuid} className="border-warning/30">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-3">
                                                <Clock className="h-5 w-5 text-warning" />
                                                {approval.application_name}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {approval.project_name && (
                                                    <span className="mr-2">{approval.project_name} /</span>
                                                )}
                                                <Badge variant="warning" size="sm">
                                                    {approval.environment_name}
                                                </Badge>
                                            </CardDescription>
                                        </div>
                                        <Badge variant="warning">Pending</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-4 flex items-center gap-4 text-sm text-foreground-muted">
                                        <span>
                                            Requested by <span className="font-medium text-foreground">{approval.requested_by}</span>
                                        </span>
                                        <span>&middot;</span>
                                        <span>{formatTimeAgo(approval.requested_at)}</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Button
                                            variant="success"
                                            onClick={() => handleApprove(approval.deployment_uuid)}
                                            disabled={actionLoading === approval.deployment_uuid}
                                        >
                                            <CheckCircle className="mr-2 h-4 w-4" />
                                            Approve Deployment
                                        </Button>
                                        <Button
                                            variant="danger"
                                            onClick={() => openRejectModal(approval.deployment_uuid)}
                                            disabled={actionLoading === approval.deployment_uuid}
                                        >
                                            <XCircle className="mr-2 h-4 w-4" />
                                            Reject
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Info about approvals */}
                <Card className="mt-8 border-info/30 bg-info/5">
                    <CardContent className="py-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 h-5 w-5 text-info" />
                            <div className="text-sm">
                                <p className="font-medium text-foreground">About Deployment Approvals</p>
                                <p className="mt-1 text-foreground-muted">
                                    Deployments to production environments require approval from a project admin or owner.
                                    This helps ensure code is reviewed before going live.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

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
                        hint="This will be shared with the person who requested the deployment"
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
                            Reject Deployment
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>
        </AppLayout>
    );
}
