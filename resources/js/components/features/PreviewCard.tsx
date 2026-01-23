import { Link, router } from '@inertiajs/react';
import { Card, CardContent, Badge, useConfirm, useToast } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import {
    GitPullRequest,
    MoreVertical,
    ExternalLink,
    RotateCw,
    Trash2,
    GitBranch,
    Calendar,
    Clock,
    AlertTriangle
} from 'lucide-react';
import type { PreviewDeployment } from '@/types';
import { formatRelativeTime, isSafeUrl, safeOpenUrl } from '@/lib/utils';
import { getStatusColor, getStatusVariant } from '@/lib/statusUtils';

interface PreviewCardProps {
    preview: PreviewDeployment;
    applicationUuid: string;
}

export function PreviewCard({ preview, applicationUuid }: PreviewCardProps) {
    const confirm = useConfirm();
    const { addToast } = useToast();
    const isUrlSafe = isSafeUrl(preview.preview_url);

    const handleRedeploy = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
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

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        const confirmed = await confirm({
            title: 'Delete Preview',
            description: `Delete preview for PR #${preview.pull_request_number}? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/api/v1/previews/${preview.uuid}`);
        }
    };

    const handleOpenUrl = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (!safeOpenUrl(preview.preview_url)) {
            addToast('Unable to open URL - invalid or unsafe protocol', 'error');
        }
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
                        {isUrlSafe ? (
                            <ExternalLink className="h-3.5 w-3.5 text-foreground-muted" />
                        ) : (
                            <AlertTriangle className="h-3.5 w-3.5 text-warning" />
                        )}
                        {isUrlSafe ? (
                            <a
                                href={preview.preview_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-primary hover:underline truncate"
                                onClick={(e) => e.stopPropagation()}
                            >
                                {preview.preview_url}
                            </a>
                        ) : (
                            <span className="text-sm text-foreground-muted truncate" title="URL uses unsafe protocol">
                                {preview.preview_url}
                            </span>
                        )}
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
