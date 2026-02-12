import { useState, useMemo } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, Modal, ModalFooter, Input } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import {
    GitCommit, Clock, RotateCw, Eye, ArrowLeft, Loader2, AlertCircle,
    Search, Filter, Shield, ShieldOff, History,
} from 'lucide-react';
import type { Application } from '@/types';
import { RollbackTimeline, type TimelineDeployment } from '@/components/features/RollbackTimeline';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';

type DeploymentStatus = 'finished' | 'failed' | 'in_progress' | 'queued' | 'cancelled' | 'rolled_back';

interface Deployment {
    id: number;
    deployment_uuid: string;
    commit: string;
    commit_message: string | null;
    status: DeploymentStatus;
    trigger: 'push' | 'rollback' | 'manual';
    rollback: boolean;
    is_webhook: boolean;
    is_api: boolean;
    duration: number | null;
    created_at: string;
    updated_at: string;
}

interface RollbackEvent {
    id: number;
    trigger_reason: string;
    trigger_type: 'manual' | 'automatic';
    status: 'triggered' | 'in_progress' | 'success' | 'failed' | 'skipped';
    from_commit: string | null;
    to_commit: string | null;
    error_message: string | null;
    triggered_at: string;
    completed_at: string | null;
    triggered_by_user?: {
        id: number;
        name: string;
    } | null;
}

interface RollbackSettings {
    auto_rollback_enabled: boolean;
    rollback_validation_seconds: number;
    rollback_max_restarts: number;
}

type StatusFilter = 'all' | 'finished' | 'failed' | 'in_progress';
type TriggerFilter = 'all' | 'push' | 'manual' | 'rollback';

interface Props {
    application: Application;
    projectUuid: string;
    environmentUuid: string;
    deployments: Deployment[];
    rollbackEvents: RollbackEvent[];
    rollbackSettings: RollbackSettings;
}

const REASON_LABELS: Record<string, string> = {
    crash_loop: 'Crash Loop Detected',
    health_check_failed: 'Health Check Failed',
    container_exited: 'Container Exited',
    manual: 'Manual Rollback',
    error_rate_exceeded: 'Error Rate Exceeded',
};

const EVENT_STATUS_VARIANT: Record<string, 'success' | 'danger' | 'warning' | 'info' | 'default'> = {
    success: 'success',
    failed: 'danger',
    in_progress: 'warning',
    triggered: 'info',
    skipped: 'default',
};

export default function ApplicationRollbackIndex({
    application, projectUuid, environmentUuid,
    deployments: initialDeployments, rollbackEvents, rollbackSettings,
}: Props) {
    const [showRollbackModal, setShowRollbackModal] = useState(false);
    const [selectedDeployment, setSelectedDeployment] = useState<Deployment | null>(null);
    const [isRollingBack, setIsRollingBack] = useState(false);
    const { addToast } = useToast();

    // Filters
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [triggerFilter, setTriggerFilter] = useState<TriggerFilter>('all');

    const appBasePath = `/project/${projectUuid}/${environmentUuid}/application/${application.uuid}`;

    // Filter deployments
    const filteredDeployments = useMemo(() => {
        return initialDeployments.filter((d) => {
            // Search by commit hash or message
            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                const matchesCommit = d.commit?.toLowerCase().includes(q);
                const matchesMessage = d.commit_message?.toLowerCase().includes(q);
                if (!matchesCommit && !matchesMessage) return false;
            }
            // Status filter
            if (statusFilter !== 'all' && d.status !== statusFilter) return false;
            // Trigger filter
            if (triggerFilter !== 'all' && d.trigger !== triggerFilter) return false;
            return true;
        });
    }, [initialDeployments, searchQuery, statusFilter, triggerFilter]);

    // Deployments available for rollback (only finished, not the most recent)
    const rollbackableDeployments = useMemo(() => {
        return filteredDeployments.filter((d) => d.status === 'finished');
    }, [filteredDeployments]);

    const currentDeployment = initialDeployments.length > 0 ? initialDeployments[0] : null;

    const hasActiveFilters = searchQuery || statusFilter !== 'all' || triggerFilter !== 'all';

    const handleRollbackClick = (deployment: Deployment | TimelineDeployment) => {
        setSelectedDeployment(deployment as Deployment);
        setShowRollbackModal(true);
    };

    const handleConfirmRollback = async () => {
        if (!selectedDeployment) return;

        setIsRollingBack(true);
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(
                `/applications/${application.uuid}/rollback/${selectedDeployment.deployment_uuid}`,
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
                setSelectedDeployment(null);

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

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setTriggerFilter('all');
    };

    const formatTimeAgo = (date: string): string => {
        const now = new Date();
        const then = new Date(date);
        const diff = now.getTime() - then.getTime();
        const minutes = Math.floor(diff / (1000 * 60));
        if (minutes < 1) return 'just now';
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 30) return `${days}d ago`;
        return then.toLocaleDateString();
    };

    const formatDuration = (seconds: number | null): string => {
        if (!seconds) return '-';
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
    };

    return (
        <AppLayout
            title={`Rollback - ${application.name}`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: 'Application', href: appBasePath },
                { label: 'Rollback' },
            ]}
        >
            {/* Back Button */}
            <Link
                href={appBasePath}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Application
            </Link>

            <div className="space-y-6">
                {/* Header */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-xl font-semibold text-foreground">Deployment History & Rollback</h2>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    View deployment history, manage rollbacks, and monitor auto-rollback status
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                {rollbackSettings.auto_rollback_enabled ? (
                                    <Badge variant="success" className="flex items-center gap-1.5">
                                        <Shield className="h-3 w-3" />
                                        Auto-Rollback On
                                    </Badge>
                                ) : (
                                    <Badge variant="default" className="flex items-center gap-1.5">
                                        <ShieldOff className="h-3 w-3" />
                                        Auto-Rollback Off
                                    </Badge>
                                )}
                                <Link href={`/applications/${application.uuid}/settings`}>
                                    <Button variant="secondary" size="sm">
                                        Settings
                                    </Button>
                                </Link>
                            </div>
                        </div>

                        {/* Stats row */}
                        <div className="mt-4 flex items-center gap-6 text-sm text-foreground-muted">
                            <span>{initialDeployments.length} total deployments</span>
                            <span>{initialDeployments.filter(d => d.status === 'finished').length} successful</span>
                            <span>{initialDeployments.filter(d => d.status === 'failed').length} failed</span>
                            {rollbackEvents.length > 0 && (
                                <span>{rollbackEvents.length} rollback events</span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Timeline */}
                {rollbackableDeployments.length > 0 && (
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 font-medium text-foreground">Deployment Timeline</h3>
                            <RollbackTimeline
                                deployments={rollbackableDeployments}
                                currentDeploymentId={currentDeployment?.id}
                                onSelectDeployment={handleRollbackClick}
                            />
                        </CardContent>
                    </Card>
                )}

                {/* Rollback Events */}
                {rollbackEvents.length > 0 && (
                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center gap-2">
                                <History className="h-4 w-4 text-foreground-muted" />
                                <h3 className="font-medium text-foreground">Recent Rollback Events</h3>
                            </div>
                            <div className="space-y-3">
                                {rollbackEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="rounded-lg border border-border bg-background-secondary p-4"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Badge variant={EVENT_STATUS_VARIANT[event.status] || 'default'}>
                                                        {event.status}
                                                    </Badge>
                                                    <Badge variant={event.trigger_type === 'automatic' ? 'info' : 'default'}>
                                                        {event.trigger_type}
                                                    </Badge>
                                                    <span className="text-sm text-foreground">
                                                        {REASON_LABELS[event.trigger_reason] || event.trigger_reason.replace(/_/g, ' ')}
                                                    </span>
                                                </div>
                                                {event.from_commit && event.to_commit && (
                                                    <p className="text-xs text-foreground-muted">
                                                        <code className="rounded bg-background-tertiary px-1 py-0.5">{event.from_commit.substring(0, 7)}</code>
                                                        {' → '}
                                                        <code className="rounded bg-background-tertiary px-1 py-0.5">{event.to_commit.substring(0, 7)}</code>
                                                    </p>
                                                )}
                                                {event.error_message && (
                                                    <p className="text-xs text-danger">{event.error_message}</p>
                                                )}
                                            </div>
                                            <div className="text-right text-xs text-foreground-muted">
                                                {event.triggered_by_user && (
                                                    <div className="mb-1">by {event.triggered_by_user.name}</div>
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

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-wrap items-center gap-3">
                            {/* Search */}
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Search by commit hash or message..."
                                    className="pl-9"
                                />
                            </div>

                            {/* Status Filter */}
                            <div className="flex items-center gap-1">
                                <Filter className="h-4 w-4 text-foreground-muted mr-1" />
                                {(['all', 'finished', 'failed', 'in_progress'] as StatusFilter[]).map((status) => (
                                    <button
                                        key={status}
                                        onClick={() => setStatusFilter(status)}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                            statusFilter === status
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                        }`}
                                    >
                                        {status === 'all' ? 'All' : status === 'in_progress' ? 'In Progress' : status.charAt(0).toUpperCase() + status.slice(1)}
                                    </button>
                                ))}
                            </div>

                            {/* Trigger Filter */}
                            <div className="flex items-center gap-1">
                                {(['all', 'push', 'manual', 'rollback'] as TriggerFilter[]).map((trigger) => (
                                    <button
                                        key={trigger}
                                        onClick={() => setTriggerFilter(trigger)}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                            triggerFilter === trigger
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                        }`}
                                    >
                                        {trigger === 'all' ? 'All Triggers' : trigger.charAt(0).toUpperCase() + trigger.slice(1)}
                                    </button>
                                ))}
                            </div>

                            {hasActiveFilters && (
                                <button
                                    onClick={clearFilters}
                                    className="text-xs text-foreground-muted hover:text-foreground underline"
                                >
                                    Clear filters
                                </button>
                            )}
                        </div>

                        {hasActiveFilters && (
                            <p className="mt-2 text-xs text-foreground-muted">
                                Showing {filteredDeployments.length} of {initialDeployments.length} deployments
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Deployments List */}
                {filteredDeployments.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <AlertCircle className="h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">
                                {hasActiveFilters ? 'No matching deployments' : 'No deployments found'}
                            </h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                {hasActiveFilters
                                    ? 'Try adjusting your filters'
                                    : 'No deployments available for this application'}
                            </p>
                            {hasActiveFilters && (
                                <Button variant="secondary" size="sm" className="mt-4" onClick={clearFilters}>
                                    Clear filters
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {filteredDeployments.map((deployment, index) => {
                            const isCurrentActive = deployment.id === currentDeployment?.id;
                            const commitShort = deployment.commit?.substring(0, 7) || 'unknown';
                            const message = deployment.commit_message || 'No commit message';
                            const canRollback = !isCurrentActive && deployment.status === 'finished';

                            return (
                                <Card key={deployment.id}>
                                    <CardContent className="p-4">
                                        <div className="flex items-start gap-4">
                                            <div className="mt-1">{getStatusIcon(deployment.status)}</div>
                                            <div className="flex-1 min-w-0">
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
                                                            {deployment.trigger === 'push' && (
                                                                <Badge variant="default" className="text-xs">Push</Badge>
                                                            )}
                                                        </div>
                                                        <p className="mt-1 text-sm text-foreground line-clamp-1">{message}</p>
                                                    </div>

                                                    <div className="flex items-center gap-2 flex-shrink-0">
                                                        <Link href={`${appBasePath}/deployment/${deployment.deployment_uuid}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="mr-1 h-3 w-3" />
                                                                View
                                                            </Button>
                                                        </Link>
                                                        {canRollback && (
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

                                                <div className="mt-3 flex items-center gap-4 text-xs text-foreground-muted flex-wrap">
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        <span>{formatTimeAgo(deployment.created_at)}</span>
                                                    </div>
                                                    {deployment.duration && (
                                                        <span>Duration: {formatDuration(deployment.duration)}</span>
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
                                    {selectedDeployment.commit?.substring(0, 12)}
                                </code>
                            </div>
                            <p className="mt-2 text-sm text-foreground">
                                {selectedDeployment.commit_message || 'No commit message'}
                            </p>
                            <div className="mt-3 flex items-center gap-3 text-xs text-foreground-muted">
                                <span>{formatTimeAgo(selectedDeployment.created_at)}</span>
                                {selectedDeployment.duration && (
                                    <span>Duration: {formatDuration(selectedDeployment.duration)}</span>
                                )}
                            </div>
                        </div>

                        <div className="rounded-lg border border-warning bg-warning/10 p-4">
                            <div className="flex items-start gap-2">
                                <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                <div className="text-sm text-foreground">
                                    <p className="font-medium">This action will:</p>
                                    <ul className="mt-2 list-disc list-inside space-y-1 text-foreground-muted">
                                        <li>Create a new deployment with the selected commit</li>
                                        <li>Replace the currently running version</li>
                                        <li>May cause brief downtime during transition</li>
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
