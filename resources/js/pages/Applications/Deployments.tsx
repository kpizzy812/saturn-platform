import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input } from '@/components/ui';
import { Rocket, GitCommit, Clock, User, Search, RotateCcw, ExternalLink, ChevronRight } from 'lucide-react';
import type { Application, Deployment, DeploymentStatus, DeploymentTrigger } from '@/types';

interface Props {
    application: Application;
    deployments?: Deployment[];
    projectUuid?: string;
    environmentUuid?: string;
}

// Extended deployment interface with additional details
interface ExtendedDeployment extends Deployment {
    trigger: DeploymentTrigger;
    duration?: number;
    deployed_by?: string;
    branch?: string;
}

// Mock data for demo
const MOCK_DEPLOYMENTS: ExtendedDeployment[] = [
    {
        id: 1,
        uuid: 'dep-001',
        application_id: 1,
        status: 'finished',
        commit: 'a1b2c3d4e5f6',
        commit_message: 'feat: Add user authentication',
        trigger: 'push',
        duration: 145,
        deployed_by: 'john.doe@example.com',
        branch: 'main',
        created_at: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
    },
    {
        id: 2,
        uuid: 'dep-002',
        application_id: 1,
        status: 'finished',
        commit: 'b2c3d4e5f6g7',
        commit_message: 'fix: Resolve memory leak in worker process',
        trigger: 'manual',
        duration: 132,
        deployed_by: 'jane.smith@example.com',
        branch: 'main',
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
    },
    {
        id: 3,
        uuid: 'dep-003',
        application_id: 1,
        status: 'failed',
        commit: 'c3d4e5f6g7h8',
        commit_message: 'refactor: Update database schema',
        trigger: 'push',
        duration: 45,
        deployed_by: 'john.doe@example.com',
        branch: 'main',
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 5).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 5).toISOString(),
    },
    {
        id: 4,
        uuid: 'dep-004',
        application_id: 1,
        status: 'finished',
        commit: 'd4e5f6g7h8i9',
        commit_message: 'chore: Update dependencies',
        trigger: 'rollback',
        duration: 128,
        deployed_by: 'jane.smith@example.com',
        branch: 'main',
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
    },
];

export default function ApplicationDeployments({ application, deployments: propDeployments, projectUuid, environmentUuid }: Props) {
    const [deployments, setDeployments] = React.useState<ExtendedDeployment[]>(propDeployments || MOCK_DEPLOYMENTS);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [selectedDeployment, setSelectedDeployment] = React.useState<ExtendedDeployment | null>(null);

    const filteredDeployments = React.useMemo(() => {
        if (!searchQuery) return deployments;
        const query = searchQuery.toLowerCase();
        return deployments.filter(d =>
            d.commit?.toLowerCase().includes(query) ||
            d.commit_message?.toLowerCase().includes(query) ||
            d.deployed_by?.toLowerCase().includes(query)
        );
    }, [deployments, searchQuery]);

    const handleRollback = async (deploymentUuid: string) => {
        if (!confirm('Are you sure you want to rollback to this deployment?')) return;

        router.post(`/api/v1/applications/${application.uuid}/rollback`, {
            deployment_uuid: deploymentUuid,
        });
    };

    const getStatusBadge = (status: DeploymentStatus) => {
        switch (status) {
            case 'finished':
                return <Badge variant="success">Success</Badge>;
            case 'failed':
                return <Badge variant="error">Failed</Badge>;
            case 'in_progress':
                return <Badge variant="info">In Progress</Badge>;
            case 'queued':
                return <Badge variant="warning">Queued</Badge>;
            case 'cancelled':
                return <Badge variant="default">Cancelled</Badge>;
        }
    };

    const getTriggerBadge = (trigger: DeploymentTrigger) => {
        const variants: Record<DeploymentTrigger, string> = {
            push: 'bg-blue-500/20 text-blue-400',
            manual: 'bg-purple-500/20 text-purple-400',
            rollback: 'bg-orange-500/20 text-orange-400',
            scheduled: 'bg-green-500/20 text-green-400',
        };

        return (
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${variants[trigger]}`}>
                {trigger}
            </span>
        );
    };

    const formatDuration = (seconds?: number) => {
        if (!seconds) return 'N/A';
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return minutes > 0 ? `${minutes}m ${secs}s` : `${secs}s`;
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Deployments' },
    ];

    return (
        <AppLayout title="Deployment History" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Rocket className="h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-foreground">Deployment History</h1>
                        <p className="text-foreground-muted">
                            View and manage your application deployments
                        </p>
                    </div>
                </div>
            </div>

            {/* Search */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search by commit, message, or user..."
                            className="pl-10"
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Deployments List */}
            <div className="space-y-4">
                {filteredDeployments.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                <Rocket className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No deployments found</h3>
                            <p className="mt-2 text-center text-sm text-foreground-muted">
                                {searchQuery ? 'Try adjusting your search query' : 'No deployments have been made yet'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    filteredDeployments.map((deployment) => (
                        <Card key={deployment.id} className="hover:border-primary/50 transition-colors">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-3 mb-3">
                                            {getStatusBadge(deployment.status)}
                                            {getTriggerBadge(deployment.trigger)}
                                            <span className="text-sm text-foreground-muted">
                                                {new Date(deployment.created_at).toLocaleString()}
                                            </span>
                                        </div>

                                        <div className="flex items-start gap-3 mb-3">
                                            <GitCommit className="h-5 w-5 text-foreground-muted flex-shrink-0 mt-0.5" />
                                            <div className="min-w-0">
                                                <p className="text-foreground font-medium truncate">
                                                    {deployment.commit_message || 'No commit message'}
                                                </p>
                                                <div className="flex items-center gap-3 mt-1">
                                                    <code className="text-xs text-foreground-muted bg-background-secondary px-2 py-0.5 rounded">
                                                        {deployment.commit?.slice(0, 7) || 'N/A'}
                                                    </code>
                                                    {deployment.branch && (
                                                        <span className="text-xs text-foreground-muted">
                                                            on <span className="text-foreground">{deployment.branch}</span>
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-6 text-sm text-foreground-muted">
                                            {deployment.deployed_by && (
                                                <div className="flex items-center gap-1.5">
                                                    <User className="h-4 w-4" />
                                                    {deployment.deployed_by}
                                                </div>
                                            )}
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="h-4 w-4" />
                                                {formatDuration(deployment.duration)}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-2 flex-shrink-0">
                                        <Link href={`/applications/${application.uuid}/deployments/${deployment.uuid}`}>
                                            <Button size="sm" variant="secondary">
                                                <ExternalLink className="mr-2 h-4 w-4" />
                                                Details
                                            </Button>
                                        </Link>
                                        {deployment.status === 'finished' && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => handleRollback(deployment.uuid)}
                                            >
                                                <RotateCcw className="mr-2 h-4 w-4" />
                                                Rollback
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
