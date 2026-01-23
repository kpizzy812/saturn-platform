import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, Input } from '@/components/ui';
import { PreviewCard } from '@/components/features/PreviewCard';
import { GitPullRequest, Settings, Search, Filter, Plus } from 'lucide-react';
import type { PreviewDeployment, PreviewDeploymentStatus, Application } from '@/types';

interface Props {
    application: Application;
    previews?: PreviewDeployment[];
    projectUuid?: string;
    environmentUuid?: string;
}

export default function PreviewsIndex({ application, previews: propPreviews, projectUuid, environmentUuid }: Props) {
    const [previews, setPreviews] = React.useState<PreviewDeployment[]>(
        propPreviews || []
    );
    const [filterStatus, setFilterStatus] = React.useState<PreviewDeploymentStatus | 'all'>('all');
    const [searchQuery, setSearchQuery] = React.useState('');

    // Filter previews
    const filteredPreviews = React.useMemo(() => {
        let filtered = previews;

        // Filter by status
        if (filterStatus !== 'all') {
            filtered = filtered.filter(p => p.status === filterStatus);
        }

        // Filter by search query
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                p =>
                    p.pull_request_title.toLowerCase().includes(query) ||
                    p.branch.toLowerCase().includes(query) ||
                    p.pull_request_number.toString().includes(query)
            );
        }

        return filtered;
    }, [previews, filterStatus, searchQuery]);

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Preview Deployments' },
    ];

    return (
        <AppLayout title="Preview Deployments" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Preview Deployments</h1>
                    <p className="text-foreground-muted">
                        Manage preview deployments for pull requests
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Link href={`/applications/${application.uuid}/previews/settings`}>
                        <Button variant="secondary">
                            <Settings className="mr-2 h-4 w-4" />
                            Settings
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                placeholder="Search by PR number, title, or branch..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Filter Buttons */}
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                <Filter className="h-4 w-4" />
                                Status:
                            </span>
                            <FilterButton
                                active={filterStatus === 'all'}
                                onClick={() => setFilterStatus('all')}
                            >
                                All
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'running'}
                                onClick={() => setFilterStatus('running')}
                            >
                                Running
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'deploying'}
                                onClick={() => setFilterStatus('deploying')}
                            >
                                Deploying
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'failed'}
                                onClick={() => setFilterStatus('failed')}
                            >
                                Failed
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'stopped'}
                                onClick={() => setFilterStatus('stopped')}
                            >
                                Stopped
                            </FilterButton>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Preview Cards */}
            {filteredPreviews.length === 0 ? (
                <EmptyState
                    searchQuery={searchQuery}
                    filterStatus={filterStatus}
                    applicationUuid={application.uuid}
                />
            ) : (
                <div className="grid gap-4">
                    {filteredPreviews.map((preview) => (
                        <PreviewCard
                            key={preview.id}
                            preview={preview}
                            applicationUuid={application.uuid}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function FilterButton({
    children,
    active,
    onClick,
}: {
    children: React.ReactNode;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={`whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                active
                    ? 'bg-primary text-white'
                    : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

function EmptyState({
    searchQuery,
    filterStatus,
    applicationUuid,
}: {
    searchQuery: string;
    filterStatus: PreviewDeploymentStatus | 'all';
    applicationUuid: string;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <GitPullRequest className="h-8 w-8 text-foreground-muted" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No preview deployments found</h3>
                <p className="mt-2 text-center text-sm text-foreground-muted">
                    {searchQuery
                        ? 'Try adjusting your search query or filters'
                        : filterStatus !== 'all'
                        ? `No ${filterStatus} preview deployments found`
                        : 'No preview deployments have been created yet'}
                </p>
                {!searchQuery && filterStatus === 'all' && (
                    <Link href={`/applications/${applicationUuid}/previews/settings`}>
                        <Button variant="primary" className="mt-4">
                            <Settings className="mr-2 h-4 w-4" />
                            Configure Preview Deployments
                        </Button>
                    </Link>
                )}
            </CardContent>
        </Card>
    );
}
