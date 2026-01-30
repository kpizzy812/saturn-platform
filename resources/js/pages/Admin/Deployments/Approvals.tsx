import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { CheckCircle, XCircle, Clock, GitBranch, Calendar } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Deployment {
    id: number;
    deployment_uuid: string;
    application_name: string;
    application_uuid: string;
    status: string;
    approval_status: string;
    commit: string | null;
    commit_message: string | null;
    team_name: string;
    team_id: number;
    created_at: string;
}

interface Props {
    deployments: {
        data: Deployment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function DeploymentApprovals({ deployments }: Props) {
    const [selectedDeployment, setSelectedDeployment] = useState<Deployment | null>(null);
    const [approvalNote, setApprovalNote] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [actionType, setActionType] = useState<'approve' | 'reject'>('approve');

    const handleApprove = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setActionType('approve');
        setShowModal(true);
    };

    const handleReject = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setActionType('reject');
        setShowModal(true);
    };

    const submitAction = () => {
        if (!selectedDeployment) return;

        const endpoint =
            actionType === 'approve'
                ? `/api/v1/deployment-approvals/${selectedDeployment.deployment_uuid}/approve`
                : `/api/v1/deployment-approvals/${selectedDeployment.deployment_uuid}/reject`;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ note: approvalNote }),
        })
            .then((res) => res.json())
            .then(() => {
                setShowModal(false);
                setApprovalNote('');
                router.reload({ only: ['deployments'] });
            })
            .catch((err) => {
                console.error('Failed to process approval:', err);
            });
    };

    return (
        <AdminLayout>
            <Head title="Deployment Approvals" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Deployment Approvals</h1>
                        <p className="text-sm text-foreground-muted mt-1">
                            Review and approve pending deployments
                        </p>
                    </div>
                    <Badge variant="outline" className="text-sm">
                        {deployments.total} Pending
                    </Badge>
                </div>

                {deployments.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <CheckCircle className="mx-auto h-12 w-12 text-success opacity-50" />
                            <p className="mt-4 text-sm text-foreground-muted">
                                No pending deployment approvals
                            </p>
                            <p className="text-xs text-foreground-subtle mt-1">
                                All deployments have been processed
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {deployments.data.map((deployment) => (
                            <Card key={deployment.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">
                                                {deployment.application_name}
                                            </CardTitle>
                                            <p className="text-xs text-foreground-muted mt-1">
                                                {deployment.team_name}
                                            </p>
                                        </div>
                                        <Badge variant="warning" className="ml-4">
                                            <Clock className="h-3 w-3 mr-1" />
                                            Pending Approval
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        {deployment.commit && (
                                            <div className="flex items-center gap-2">
                                                <GitBranch className="h-4 w-4 text-foreground-muted" />
                                                <span className="font-mono text-xs">
                                                    {deployment.commit.substring(0, 7)}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-xs">
                                                {new Date(deployment.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>

                                    {deployment.commit_message && (
                                        <div className="text-sm">
                                            <p className="text-foreground-muted text-xs mb-1">
                                                Commit message:
                                            </p>
                                            <p className="text-sm">{deployment.commit_message}</p>
                                        </div>
                                    )}

                                    <div className="flex gap-2 pt-2">
                                        <Button
                                            size="sm"
                                            variant="default"
                                            onClick={() => handleApprove(deployment)}
                                            className="flex items-center gap-2"
                                        >
                                            <CheckCircle className="h-4 w-4" />
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => handleReject(deployment)}
                                            className="flex items-center gap-2"
                                        >
                                            <XCircle className="h-4 w-4" />
                                            Reject
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Approval Modal */}
            {showModal && selectedDeployment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="bg-background border rounded-lg shadow-lg p-6 max-w-md w-full">
                        <h3 className="text-lg font-semibold mb-4">
                            {actionType === 'approve' ? 'Approve' : 'Reject'} Deployment
                        </h3>
                        <p className="text-sm text-foreground-muted mb-4">
                            {actionType === 'approve'
                                ? 'This deployment will be queued and executed immediately.'
                                : 'This deployment will be cancelled and marked as rejected.'}
                        </p>
                        <div className="mb-4">
                            <label className="text-sm font-medium mb-2 block">
                                Note (optional)
                            </label>
                            <textarea
                                className="w-full border rounded-md p-2 text-sm"
                                rows={3}
                                value={approvalNote}
                                onChange={(e) => setApprovalNote(e.target.value)}
                                placeholder="Add a note about this decision..."
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setShowModal(false);
                                    setApprovalNote('');
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant={actionType === 'approve' ? 'default' : 'destructive'}
                                onClick={submitAction}
                            >
                                {actionType === 'approve' ? 'Approve' : 'Reject'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
