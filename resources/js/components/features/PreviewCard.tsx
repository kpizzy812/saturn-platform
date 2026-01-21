import { Link, router } from '@inertiajs/react';
import { Card, CardContent, Badge } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import {
    GitPullRequest,
    MoreVertical,
    ExternalLink,
    RotateCw,
    Trash2,
    GitBranch,
    Calendar,
    Clock
} from 'lucide-react';
import type { PreviewDeployment, PreviewDeploymentStatus } from '@/types';
import { formatRelativeTime } from '@/lib/utils';

interface PreviewCardProps {
    preview: PreviewDeployment;
    applicationUuid: string;
}

const getStatusColor = (status: PreviewDeploymentStatus): string => {
    switch (status) {
        case 'running':
            return 'bg-green-500';
        case 'stopped':
            return 'bg-gray-500';
        case 'deploying':
            return 'bg-yellow-500';
        case 'failed':
            return 'bg-red-500';
        case 'deleting':
            return 'bg-orange-500';
        default:
            return 'bg-gray-500';
    }
};

const getStatusVariant = (status: PreviewDeploymentStatus): 'success' | 'danger' | 'warning' | 'default' => {
    switch (status) {
        case 'running':
            return 'success';
        case 'failed':
            return 'danger';
        case 'deploying':
        case 'deleting':
            return 'warning';
        default:
            return 'default';
    }
};

export function PreviewCard({ preview, applicationUuid }: PreviewCardProps) {
    const handleRedeploy = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm(`Redeploy preview for PR #${preview.pull_request_number}?`)) {
            router.post(`/api/v1/previews/${preview.uuid}/redeploy`);
        }
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm(`Delete preview for PR #${preview.pull_request_number}? This action cannot be undone.`)) {
            router.delete(`/api/v1/previews/${preview.uuid}`);
        }
    };

    const handleOpenUrl = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        window.open(preview.preview_url, '_blank');
    };

    return (
        <Link href={`/applications/${applicationUuid}/previews/${preview.uuid}`}>
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                        <div className="flex items-start gap-3 flex-1">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400">
                                <GitPullRequest className="h-5 w-5" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <h3 className="font-medium text-foreground">
                                        PR #{preview.pull_request_number}
                                    </h3>
                                    <Badge variant={getStatusVariant(preview.status)}>
                                        {preview.status}
                                    </Badge>
                                </div>
                                <p className="text-sm text-foreground-muted truncate mb-2">
                                    {preview.pull_request_title}
                                </p>
                                <div className="flex items-center gap-3 text-xs text-foreground-subtle">
                                    <div className="flex items-center gap-1">
                                        <GitBranch className="h-3 w-3" />
                                        <code className="text-xs">{preview.branch}</code>
                                    </div>
                                    <span>·</span>
                                    <div className="flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        {formatRelativeTime(preview.created_at)}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Dropdown>
                            <DropdownTrigger>
                                <button
                                    className="rounded-md p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <MoreVertical className="h-4 w-4" />
                                </button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem onClick={handleOpenUrl}>
                                    <ExternalLink className="h-4 w-4" />
                                    Open Preview
                                </DropdownItem>
                                <DropdownItem onClick={handleRedeploy}>
                                    <RotateCw className="h-4 w-4" />
                                    Redeploy
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem onClick={handleDelete} danger>
                                    <Trash2 className="h-4 w-4" />
                                    Delete Preview
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

                    {/* Preview URL */}
                    <div className="mt-4 flex items-center gap-2">
                        <ExternalLink className="h-3.5 w-3.5 text-foreground-muted" />
                        <a
                            href={preview.preview_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-primary hover:underline truncate"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {preview.preview_url}
                        </a>
                    </div>

                    {/* Status indicator */}
                    <div className="mt-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className={`h-2 w-2 rounded-full ${getStatusColor(preview.status)}`} />
                            <span className="text-sm capitalize text-foreground-muted">{preview.status}</span>
                        </div>
                        {preview.auto_delete_at && (
                            <div className="flex items-center gap-1 text-xs text-foreground-subtle">
                                <Calendar className="h-3 w-3" />
                                <span>Auto-deletes {formatRelativeTime(preview.auto_delete_at)}</span>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
