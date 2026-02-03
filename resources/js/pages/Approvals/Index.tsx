import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Badge, Modal, ModalFooter, Input, useToast } from '@/components/ui';
import { Clock, CheckCircle, XCircle, RefreshCw, AlertTriangle, Inbox, ArrowRight, Box, Database, Settings2 } from 'lucide-react';
import type { DeploymentApproval, EnvironmentMigration } from '@/types/models';

type TabType = 'deployments' | 'migrations';

export default function ApprovalsIndex() {
    const { addToast } = useToast();
    const [activeTab, setActiveTab] = useState<TabType>('deployments');

    // Deployment approvals state
    const [approvals, setApprovals] = useState<DeploymentApproval[]>([]);
    const [loadingApprovals, setLoadingApprovals] = useState(true);
    const [approvalsError, setApprovalsError] = useState('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

    // Migration approvals state
    const [migrations, setMigrations] = useState<EnvironmentMigration[]>([]);
    const [loadingMigrations, setLoadingMigrations] = useState(true);
    const [migrationsError, setMigrationsError] = useState('');

    // Reject modal state
    const [showRejectModal, setShowRejectModal] = useState(false);
    const [rejectingUuid, setRejectingUuid] = useState<string | null>(null);
    const [rejectingType, setRejectingType] = useState<'deployment' | 'migration'>('deployment');
    const [rejectReason, setRejectReason] = useState('');

    const fetchApprovals = async () => {
        setLoadingApprovals(true);
        setApprovalsError('');
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
            setApprovalsError('Failed to load pending approvals');
        } finally {
            setLoadingApprovals(false);
        }
    };

    const fetchMigrations = async () => {
        setLoadingMigrations(true);
        setMigrationsError('');
        try {
            const res = await fetch('/api/v1/migrations/pending', {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (!res.ok) throw new Error('Failed to fetch');
            const data = await res.json();
            setMigrations(data.data || []);
        } catch {
            setMigrationsError('Failed to load pending migrations');
        } finally {
            setLoadingMigrations(false);
        }
    };

    const refreshAll = () => {
        fetchApprovals();
        fetchMigrations();
    };

    useEffect(() => {
        fetchApprovals();
        fetchMigrations();
        // Poll every 30 seconds
        const interval = setInterval(refreshAll, 30000);
        return () => clearInterval(interval);
    }, []);

    const handleApproveDeployment = async (deploymentUuid: string) => {
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

    const handleApproveMigration = async (migrationUuid: string) => {
        setActionLoading(migrationUuid);
        try {
            const res = await fetch(`/api/v1/migrations/${migrationUuid}/approve`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (!res.ok) {
                const err = await res.json();
                addToast('error', 'Approval Failed', err.message || 'Failed to approve migration');
                return;
            }
            addToast('success', 'Migration Approved', 'The migration has been approved and will proceed.');
            fetchMigrations();
        } catch {
            addToast('error', 'Approval Failed', 'Failed to approve migration. Please try again.');
        } finally {
            setActionLoading(null);
        }
    };

    const openRejectModal = (uuid: string, type: 'deployment' | 'migration') => {
        setRejectingUuid(uuid);
        setRejectingType(type);
        setRejectReason('');
        setShowRejectModal(true);
    };

    const handleReject = async () => {
        if (!rejectingUuid) return;

        setActionLoading(rejectingUuid);
        try {
            const endpoint = rejectingType === 'deployment'
                ? `/deployments/${rejectingUuid}/reject/json`
                : `/api/v1/migrations/${rejectingUuid}/reject`;

            const res = await fetch(endpoint, {
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
                addToast('error', 'Rejection Failed', err.message || `Failed to reject ${rejectingType}`);
                return;
            }
            addToast('success', `${rejectingType === 'deployment' ? 'Deployment' : 'Migration'} Rejected`, `The ${rejectingType} has been rejected.`);
            setShowRejectModal(false);
            setRejectingUuid(null);
            if (rejectingType === 'deployment') {
                fetchApprovals();
            } else {
                fetchMigrations();
            }
        } catch {
            addToast('error', 'Rejection Failed', `Failed to reject ${rejectingType}. Please try again.`);
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

    const getResourceIcon = (sourceTypeName?: string) => {
        const type = sourceTypeName || 'Application';
        if (type.includes('PostgreSQL') || type.includes('MySQL') || type.includes('Database')) {
            return Database;
        }
        if (type === 'Service') {
            return Settings2;
        }
        return Box;
    };

    const loading = loadingApprovals || loadingMigrations;

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
                            Review and approve deployment and migration requests
                        </p>
                    </div>
                    <Button variant="secondary" onClick={refreshAll} disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>

                {/* Tabs */}
                <div className="mb-6 flex border-b border-border">
                    <button
                        onClick={() => setActiveTab('deployments')}
                        className={`relative px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'deployments'
                                ? 'text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Deployments
                        {approvals.length > 0 && (
                            <Badge variant="warning" size="sm" className="ml-2">
                                {approvals.length}
                            </Badge>
                        )}
                        {activeTab === 'deployments' && (
                            <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                        )}
                    </button>
                    <button
                        onClick={() => setActiveTab('migrations')}
                        className={`relative px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'migrations'
                                ? 'text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Migrations
                        {migrations.length > 0 && (
                            <Badge variant="warning" size="sm" className="ml-2">
                                {migrations.length}
                            </Badge>
                        )}
                        {activeTab === 'migrations' && (
                            <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                        )}
                    </button>
                </div>

                {/* Deployments Tab */}
                {activeTab === 'deployments' && (
                    <>
                        {approvalsError && (
                            <Card className="mb-6 border-danger/50">
                                <CardContent className="flex items-center gap-3 py-4">
                                    <AlertTriangle className="h-5 w-5 text-danger" />
                                    <span className="text-danger">{approvalsError}</span>
                                    <Button variant="ghost" size="sm" onClick={fetchApprovals} className="ml-auto">
                                        Retry
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {loadingApprovals && approvals.length === 0 ? (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <RefreshCw className="mx-auto mb-3 h-8 w-8 animate-spin text-foreground-muted" />
                                    <p className="text-foreground-muted">Loading pending deployments...</p>
                                </CardContent>
                            </Card>
                        ) : approvals.length === 0 ? (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <Inbox className="mx-auto mb-3 h-12 w-12 text-foreground-muted" />
                                    <h3 className="text-lg font-medium text-foreground">No Pending Deployments</h3>
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
                                                    onClick={() => handleApproveDeployment(approval.deployment_uuid)}
                                                    disabled={actionLoading === approval.deployment_uuid}
                                                >
                                                    <CheckCircle className="mr-2 h-4 w-4" />
                                                    Approve
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    onClick={() => openRejectModal(approval.deployment_uuid, 'deployment')}
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
                    </>
                )}

                {/* Migrations Tab */}
                {activeTab === 'migrations' && (
                    <>
                        {migrationsError && (
                            <Card className="mb-6 border-danger/50">
                                <CardContent className="flex items-center gap-3 py-4">
                                    <AlertTriangle className="h-5 w-5 text-danger" />
                                    <span className="text-danger">{migrationsError}</span>
                                    <Button variant="ghost" size="sm" onClick={fetchMigrations} className="ml-auto">
                                        Retry
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {loadingMigrations && migrations.length === 0 ? (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <RefreshCw className="mx-auto mb-3 h-8 w-8 animate-spin text-foreground-muted" />
                                    <p className="text-foreground-muted">Loading pending migrations...</p>
                                </CardContent>
                            </Card>
                        ) : migrations.length === 0 ? (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <Inbox className="mx-auto mb-3 h-12 w-12 text-foreground-muted" />
                                    <h3 className="text-lg font-medium text-foreground">No Pending Migrations</h3>
                                    <p className="mt-1 text-foreground-muted">
                                        All migration requests have been processed
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="space-y-4">
                                {migrations.map((migration) => {
                                    const ResourceIcon = getResourceIcon(migration.source_type_name);
                                    const sourceName = migration.source?.name || 'Unknown Resource';
                                    const sourceType = migration.source_type_name || 'Unknown';
                                    const requestedBy = migration.requested_by_user?.name || 'Unknown User';
                                    const sourceEnv = migration.source_environment?.name || 'Unknown';
                                    const targetEnv = migration.target_environment?.name || 'Unknown';

                                    return (
                                        <Card key={migration.uuid} className="border-warning/30">
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <div className="rounded-lg bg-muted p-2">
                                                            <ResourceIcon className="h-5 w-5" />
                                                        </div>
                                                        <div>
                                                            <CardTitle className="text-lg">{sourceName}</CardTitle>
                                                            <CardDescription>{sourceType}</CardDescription>
                                                        </div>
                                                    </div>
                                                    <Badge variant="warning">Pending Approval</Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-4">
                                                {/* Migration direction */}
                                                <div className="flex items-center justify-center gap-4 py-2">
                                                    <div className="text-center">
                                                        <p className="text-sm font-medium">{sourceEnv}</p>
                                                        <p className="text-xs text-foreground-muted">Source</p>
                                                    </div>
                                                    <ArrowRight className="h-4 w-4 text-foreground-muted" />
                                                    <div className="text-center">
                                                        <p className="text-sm font-medium">{targetEnv}</p>
                                                        <p className="text-xs text-foreground-muted">Target</p>
                                                    </div>
                                                </div>

                                                {/* Details */}
                                                <div className="rounded-lg border p-3 space-y-2 text-sm">
                                                    <div className="flex justify-between">
                                                        <span className="text-foreground-muted">Requested by</span>
                                                        <span className="font-medium">{requestedBy}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-foreground-muted">Requested at</span>
                                                        <span>{formatTimeAgo(migration.created_at)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-foreground-muted">Target server</span>
                                                        <span>{migration.target_server?.name || 'Unknown'}</span>
                                                    </div>
                                                </div>

                                                {/* Options */}
                                                {migration.options && (
                                                    <div className="text-xs text-foreground-muted">
                                                        Options:{' '}
                                                        {[
                                                            migration.options.copy_env_vars && 'Copy Env Vars',
                                                            migration.options.copy_volumes && 'Copy Volumes',
                                                            migration.options.update_existing && 'Update Existing',
                                                            migration.options.config_only && 'Config Only',
                                                        ]
                                                            .filter(Boolean)
                                                            .join(', ') || 'Default'}
                                                    </div>
                                                )}

                                                {/* Actions */}
                                                <div className="flex items-center gap-3">
                                                    <Button
                                                        variant="success"
                                                        onClick={() => handleApproveMigration(migration.uuid)}
                                                        disabled={actionLoading === migration.uuid}
                                                    >
                                                        <CheckCircle className="mr-2 h-4 w-4" />
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        variant="danger"
                                                        onClick={() => openRejectModal(migration.uuid, 'migration')}
                                                        disabled={actionLoading === migration.uuid}
                                                    >
                                                        <XCircle className="mr-2 h-4 w-4" />
                                                        Reject
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}

                {/* Info about approvals */}
                <Card className="mt-8 border-info/30 bg-info/5">
                    <CardContent className="py-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 h-5 w-5 text-info" />
                            <div className="text-sm">
                                <p className="font-medium text-foreground">About Approvals</p>
                                <p className="mt-1 text-foreground-muted">
                                    {activeTab === 'deployments'
                                        ? 'Deployments to production environments require approval from a project admin or owner. This helps ensure code is reviewed before going live.'
                                        : 'Migrations to production environments require approval from a project admin or owner. This helps ensure resources are properly reviewed before being promoted.'}
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
                    setRejectingUuid(null);
                    setRejectReason('');
                }}
                title={`Reject ${rejectingType === 'deployment' ? 'Deployment' : 'Migration'}`}
                description={`Are you sure you want to reject this ${rejectingType}?`}
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-danger/50 bg-danger/10 p-4">
                        <p className="text-sm font-medium text-danger">This action cannot be undone</p>
                        <p className="mt-1 text-sm text-foreground-muted">
                            The {rejectingType} will be cancelled and the requester will be notified.
                        </p>
                    </div>

                    <Input
                        label="Rejection Reason (optional)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        placeholder="Enter a reason for rejection..."
                        hint={`This will be shared with the person who requested the ${rejectingType}`}
                    />

                    <ModalFooter>
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setShowRejectModal(false);
                                setRejectingUuid(null);
                                setRejectReason('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleReject}
                            loading={actionLoading === rejectingUuid}
                        >
                            <XCircle className="mr-2 h-4 w-4" />
                            Reject {rejectingType === 'deployment' ? 'Deployment' : 'Migration'}
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>
        </AppLayout>
    );
}
