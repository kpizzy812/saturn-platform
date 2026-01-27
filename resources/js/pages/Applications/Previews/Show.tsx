import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, useConfirm } from '@/components/ui';
import { LogsViewer } from '@/components/features/LogsViewer';
import {
    GitPullRequest,
    ExternalLink,
    RotateCw,
    Trash2,
    GitBranch,
    GitCommit,
    Calendar,
    Clock,
    Server,
    Cpu,
    HardDrive,
    AlertCircle,
} from 'lucide-react';
import type { PreviewDeployment, Application } from '@/types';
import { formatRelativeTime } from '@/lib/utils';
import { getStatusColor, getStatusVariant } from '@/lib/statusUtils';

interface Props {
    application: Application;
    preview?: PreviewDeployment;
    previewUuid: string;
    projectUuid?: string;
    environmentUuid?: string;
}

export default function PreviewShow({ application, preview, previewUuid, projectUuid, environmentUuid }: Props) {
    const confirm = useConfirm();

    if (!preview) {
        return (
            <AppLayout title="Preview Not Found">
                <div className="flex flex-col items-center justify-center py-12">
                    <AlertCircle className="h-12 w-12 text-foreground-subtle" />
                    <h2 className="mt-4 text-lg font-medium text-foreground">Preview not found</h2>
                    <p className="mt-1 text-sm text-foreground-muted">The preview deployment could not be found.</p>
                    <Link href={`/applications/${application.uuid}/previews`} className="mt-6">
                        <Button variant="secondary">Back to Previews</Button>
                    </Link>
                </div>
            </AppLayout>
        );
    }

    const handleRedeploy = async () => {
        const confirmed = await confirm({
            title: 'Redeploy Preview',
            description: `Redeploy preview for PR #${preview.pull_request_number}?`,
            confirmText: 'Redeploy',
            variant: 'warning',
        });
        if (confirmed) {
            router.post(`/api/v1/previews/${preview.uuid}/redeploy`);
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Preview',
            description: `Delete preview for PR #${preview.pull_request_number}? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/api/v1/previews/${preview.uuid}`, {
                onSuccess: () => {
                    router.visit(`/applications/${application.uuid}/previews`);
                },
            });
        }
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Preview Deployments', href: `/applications/${application.uuid}/previews` },
        { label: `PR #${preview.pull_request_number}` },
    ];

    return (
        <AppLayout title={`Preview: PR #${preview.pull_request_number}`} breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start justify-between mb-4">
                    <div className="flex items-start gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400">
                            <GitPullRequest className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <h1 className="text-2xl font-bold text-foreground">
                                    PR #{preview.pull_request_number}
                                </h1>
                                <Badge variant={getStatusVariant(preview.status)}>
                                    {preview.status}
                                </Badge>
                            </div>
                            <p className="text-foreground-muted mb-2">
                                {preview.pull_request_title}
                            </p>
                            <div className="flex items-center gap-3 text-sm text-foreground-subtle">
                                <div className="flex items-center gap-1">
                                    <GitBranch className="h-4 w-4" />
                                    <code>{preview.branch}</code>
                                </div>
                                <span>·</span>
                                <div className="flex items-center gap-1">
                                    <GitCommit className="h-4 w-4" />
                                    <code>{preview.commit.substring(0, 8)}</code>
                                </div>
                                <span>·</span>
                                <div className="flex items-center gap-1">
                                    <Clock className="h-4 w-4" />
                                    Created {formatRelativeTime(preview.created_at)}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={preview.preview_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button variant="secondary">
                                <ExternalLink className="mr-2 h-4 w-4" />
                                Open Preview
                            </Button>
                        </a>
                        <Button variant="secondary" onClick={handleRedeploy}>
                            <RotateCw className="mr-2 h-4 w-4" />
                            Redeploy
                        </Button>
                        <Button variant="danger" onClick={handleDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Content */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Preview Info */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Preview Information</h2>
                            <div className="space-y-3">
                                <div className="flex items-start gap-3">
                                    <ExternalLink className="h-5 w-5 text-foreground-muted mt-0.5" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground mb-1">Preview URL</p>
                                        <a
                                            href={preview.preview_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm text-primary hover:underline break-all"
                                        >
                                            {preview.preview_url}
                                        </a>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <GitCommit className="h-5 w-5 text-foreground-muted mt-0.5" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground mb-1">Commit</p>
                                        <code className="text-sm text-foreground-muted">{preview.commit}</code>
                                        {preview.commit_message && (
                                            <p className="text-sm text-foreground-muted mt-1">{preview.commit_message}</p>
                                        )}
                                    </div>
                                </div>
                                {preview.auto_delete_at && (
                                    <div className="flex items-start gap-3">
                                        <Calendar className="h-5 w-5 text-foreground-muted mt-0.5" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-foreground mb-1">Auto-delete</p>
                                            <p className="text-sm text-foreground-muted">
                                                {formatRelativeTime(preview.auto_delete_at)}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deployment Logs */}
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="text-lg font-semibold text-foreground mb-4">Deployment Logs</h2>
                            <LogsViewer
                                logs={[]}
                                maxHeight="400px"
                            />
                        </CardContent>
                    </Card>

                    {/* Environment Variables */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-semibold text-foreground">Environment Variables</h2>
                            </div>
                            <p className="text-sm text-foreground-muted">
                                Environment variables are inherited from the parent application.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Status Card */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="text-sm font-semibold text-foreground mb-3">Status</h3>
                            <div className="flex items-center gap-2 mb-4">
                                <div className={`h-3 w-3 rounded-full ${getStatusColor(preview.status)}`} />
                                <span className="text-sm font-medium capitalize text-foreground">{preview.status}</span>
                            </div>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Created</span>
                                    <span className="text-foreground">{formatRelativeTime(preview.created_at)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Updated</span>
                                    <span className="text-foreground">{formatRelativeTime(preview.updated_at)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Resource Usage */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="text-sm font-semibold text-foreground mb-3">Resource Limits</h3>
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <Cpu className="h-5 w-5 text-foreground-muted" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground">CPU</p>
                                        <p className="text-xs text-foreground-muted">1 core</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <HardDrive className="h-5 w-5 text-foreground-muted" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-foreground">Memory</p>
                                        <p className="text-xs text-foreground-muted">512 MB</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Auto-Delete Warning */}
                    {preview.auto_delete_at && (
                        <Card className="border-warning/50 bg-warning/5">
                            <CardContent className="p-6">
                                <div className="flex gap-3">
                                    <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h3 className="text-sm font-semibold text-foreground mb-1">Auto-Delete Scheduled</h3>
                                        <p className="text-sm text-foreground-muted">
                                            This preview will be automatically deleted {formatRelativeTime(preview.auto_delete_at)}.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function EnvironmentVariable({ name, value }: { name: string; value: string }) {
    const [isRevealed, setIsRevealed] = React.useState(false);
    const isSensitive = value.includes('***');

    return (
        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3">
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-foreground mb-0.5">{name}</p>
                <code className="text-xs text-foreground-muted break-all">
                    {isSensitive && !isRevealed ? value : value}
                </code>
            </div>
            {isSensitive && (
                <button
                    onClick={() => setIsRevealed(!isRevealed)}
                    className="ml-2 text-xs text-primary hover:text-primary/80"
                >
                    {isRevealed ? 'Hide' : 'Reveal'}
                </button>
            )}
        </div>
    );
}
