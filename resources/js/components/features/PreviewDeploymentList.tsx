import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, Badge, Button, Spinner } from '@/components/ui';
import { GitPullRequest, ExternalLink, Plus, Settings } from 'lucide-react';
import type { PreviewDeployment } from '@/types';
import { formatRelativeTime } from '@/lib/utils';
import { getStatusVariant } from '@/lib/statusUtils';

interface PreviewDeploymentListProps {
    applicationUuid: string;
    previews?: PreviewDeployment[];
    compact?: boolean;
    maxItems?: number;
}

export function PreviewDeploymentList({
    applicationUuid,
    previews = [],
    compact = true,
    maxItems = 5,
}: PreviewDeploymentListProps) {
    const displayPreviews = compact ? previews.slice(0, maxItems) : previews;
    const hasMore = compact && previews.length > maxItems;

    if (previews.length === 0) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-background-tertiary mb-3">
                            <GitPullRequest className="h-6 w-6 text-foreground-muted" />
                        </div>
                        <h3 className="text-sm font-medium text-foreground mb-1">No Preview Deployments</h3>
                        <p className="text-sm text-foreground-muted mb-4">
                            Preview deployments will appear here when you enable them.
                        </p>
                        <Link href={`/applications/${applicationUuid}/previews/settings`}>
                            <Button variant="secondary" size="sm">
                                <Settings className="mr-2 h-4 w-4" />
                                Configure Previews
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-3">
            {displayPreviews.map((preview) => (
                <PreviewDeploymentRow
                    key={preview.id}
                    preview={preview}
                    applicationUuid={applicationUuid}
                />
            ))}
            {hasMore && (
                <Link href={`/applications/${applicationUuid}/previews`}>
                    <Card className="transition-colors hover:border-primary/50">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-center text-sm text-foreground-muted">
                                View {previews.length - maxItems} more preview{previews.length - maxItems > 1 ? 's' : ''}
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            )}
        </div>
    );
}

interface PreviewDeploymentRowProps {
    preview: PreviewDeployment;
    applicationUuid: string;
}

function PreviewDeploymentRow({ preview, applicationUuid }: PreviewDeploymentRowProps) {
    return (
        <Link href={`/applications/${applicationUuid}/previews/${preview.uuid}`}>
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-3">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400 flex-shrink-0">
                                <GitPullRequest className="h-4 w-4" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-0.5">
                                    <span className="font-medium text-sm text-foreground">
                                        PR #{preview.pull_request_number}
                                    </span>
                                    <Badge variant={getStatusVariant(preview.status)} size="sm">
                                        {preview.status}
                                    </Badge>
                                </div>
                                <p className="text-xs text-foreground-muted truncate">
                                    {preview.pull_request_title}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 flex-shrink-0">
                            <span className="text-xs text-foreground-subtle">
                                {formatRelativeTime(preview.created_at)}
                            </span>
                            <a
                                href={preview.preview_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:text-primary/80"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <ExternalLink className="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
