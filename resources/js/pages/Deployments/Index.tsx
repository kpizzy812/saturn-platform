import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, Input } from '@/components/ui';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import { formatRelativeTime } from '@/lib/utils';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';
import type { Deployment, DeploymentStatus } from '@/types';
import {
    GitCommit,
    Clock,
    Play,
    RotateCw,
    Search,
    Filter,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface Props {
    deployments?: Deployment[];
    currentPage?: number;
    totalPages?: number;
    filters?: {
        status?: DeploymentStatus | 'all';
        service?: string;
        dateRange?: string;
    };
}

// Extended deployment type with additional fields
interface ExtendedDeployment extends Deployment {
    author?: {
        name: string;
        email: string;
        avatar?: string;
    };
    duration?: string;
    trigger?: 'push' | 'manual' | 'rollback' | 'scheduled';
    service_name?: string;
}

export default function DeploymentsIndex({ deployments: propDeployments, currentPage = 1, totalPages = 1, filters: initialFilters }: Props) {
    const [deployments, _setDeployments] = React.useState<ExtendedDeployment[]>(
        Array.isArray(propDeployments) ? propDeployments as ExtendedDeployment[] : []
    );
    const [filterStatus, setFilterStatus] = React.useState<DeploymentStatus | 'all'>(initialFilters?.status || 'all');
    const [searchQuery, setSearchQuery] = React.useState('');
    const [serviceFilter, setServiceFilter] = React.useState<string>(initialFilters?.service || 'all');

    // Get unique service names for filter
    const serviceNames = React.useMemo(() => {
        const names = new Set(deployments.map(d => d.service_name).filter(Boolean));
        return Array.from(names);
    }, [deployments]);

    // Filter deployments
    const filteredDeployments = React.useMemo(() => {
        let filtered = deployments;

        // Filter by status
        if (filterStatus !== 'all') {
            filtered = filtered.filter(d => d.status === filterStatus);
        }

        // Filter by service
        if (serviceFilter !== 'all') {
            filtered = filtered.filter(d => d.service_name === serviceFilter);
        }

        // Filter by search query
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                d =>
                    d.commit_message?.toLowerCase().includes(query) ||
                    d.commit?.toLowerCase().includes(query) ||
                    d.author?.name.toLowerCase().includes(query) ||
                    d.service_name?.toLowerCase().includes(query)
            );
        }

        return filtered;
    }, [deployments, filterStatus, serviceFilter, searchQuery]);

    const getTriggerBadgeColor = (trigger?: string) => {
        switch (trigger) {
            case 'push':
                return 'bg-info/10 text-info';
            case 'manual':
                return 'bg-primary/10 text-primary';
            case 'rollback':
                return 'bg-warning/10 text-warning';
            case 'scheduled':
                return 'bg-foreground-muted/10 text-foreground-muted';
            default:
                return 'bg-foreground-muted/10 text-foreground-muted';
        }
    };

    return (
        <AppLayout title="Deployments" breadcrumbs={[{ label: 'Deployments' }]}>
            <div className="mx-auto max-w-6xl">
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-foreground">Deployment History</h1>
                <p className="text-foreground-muted">
                    Track and manage all deployments across your services
                </p>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                placeholder="Search by commit message, hash, author, or service..."
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
                                active={filterStatus === 'finished'}
                                onClick={() => setFilterStatus('finished')}
                            >
                                Finished
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'failed'}
                                onClick={() => setFilterStatus('failed')}
                            >
                                Failed
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'in_progress'}
                                onClick={() => setFilterStatus('in_progress')}
                            >
                                In Progress
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'queued'}
                                onClick={() => setFilterStatus('queued')}
                            >
                                Queued
                            </FilterButton>
                            <FilterButton
                                active={filterStatus === 'cancelled'}
                                onClick={() => setFilterStatus('cancelled')}
                            >
                                Cancelled
                            </FilterButton>

                            <div className="ml-4 h-4 w-px bg-border" />

                            <span className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                Service:
                            </span>
                            <FilterButton
                                active={serviceFilter === 'all'}
                                onClick={() => setServiceFilter('all')}
                            >
                                All Services
                            </FilterButton>
                            {serviceNames.map(name => (
                                <FilterButton
                                    key={name}
                                    active={serviceFilter === name}
                                    onClick={() => setServiceFilter(name!)}
                                >
                                    {name}
                                </FilterButton>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Deployments Timeline */}
            {filteredDeployments.length === 0 ? (
                <EmptyState searchQuery={searchQuery} filterStatus={filterStatus} />
            ) : (
                <>
                    <StaggerList className="space-y-3">
                        {filteredDeployments.map((deployment, i) => (
                            <StaggerItem key={deployment.id} index={i}>
                                <DeploymentCard
                                    deployment={deployment}
                                    getTriggerBadgeColor={getTriggerBadgeColor}
                                />
                            </StaggerItem>
                        ))}
                    </StaggerList>

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <Card className="mt-6">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-foreground-muted">
                                        Page {currentPage} of {totalPages}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            disabled={currentPage === 1}
                                        >
                                            <ChevronLeft className="mr-1 h-4 w-4" />
                                            Previous
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            disabled={currentPage === totalPages}
                                        >
                                            Next
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </>
            )}
            </div>
        </AppLayout>
    );
}

interface DeploymentCardProps {
    deployment: ExtendedDeployment;
    getTriggerBadgeColor: (trigger?: string) => string;
}

function DeploymentCard({ deployment, getTriggerBadgeColor }: DeploymentCardProps) {
    const initials = deployment.author?.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <Link href={`/deployments/${deployment.uuid}`}>
            <Card className="transition-all hover:border-primary hover:shadow-md">
                <CardContent className="p-4">
                    <div className="flex items-start gap-4">
                        {/* Status Icon */}
                        <div className="mt-1">{getStatusIcon(deployment.status)}</div>

                        {/* Main Content */}
                        <div className="flex-1 space-y-2">
                            {/* Commit Info */}
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <GitCommit className="h-4 w-4 text-foreground-muted" />
                                        <code className="text-sm font-medium text-primary">
                                            {deployment.commit?.substring(0, 8)}
                                        </code>
                                        <Badge variant={getStatusVariant(deployment.status)}>
                                            {deployment.status.replace('_', ' ')}
                                        </Badge>
                                        {deployment.trigger && (
                                            <span className={`rounded-md px-2 py-0.5 text-xs font-medium ${getTriggerBadgeColor(deployment.trigger)}`}>
                                                {deployment.trigger}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-2 text-sm text-foreground">
                                        {deployment.commit_message}
                                    </p>
                                </div>

                                {/* Actions */}
                                {deployment.status === 'finished' && (
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                if (confirm('Rollback to this deployment?') && deployment.application_uuid) {
                                                    fetch(`/api/v1/applications/${deployment.application_uuid}/rollback/${deployment.deployment_uuid || deployment.uuid}`, {
                                                        method: 'POST',
                                                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' },
                                                    }).then(() => router.reload());
                                                }
                                            }}
                                        >
                                            <RotateCw className="mr-1 h-3.5 w-3.5" />
                                            Rollback
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {/* Metadata */}
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-foreground-muted">
                                {/* Author */}
                                {deployment.author && (
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-xs font-medium text-white">
                                            {deployment.author.avatar ? (
                                                <img
                                                    src={deployment.author.avatar}
                                                    alt={deployment.author.name}
                                                    className="h-full w-full rounded-full object-cover"
                                                />
                                            ) : (
                                                initials
                                            )}
                                        </div>
                                        <span>{deployment.author.name}</span>
                                    </div>
                                )}

                                <span>·</span>

                                {/* Time */}
                                <div className="flex items-center gap-1.5">
                                    <Clock className="h-3.5 w-3.5" />
                                    <span>{formatRelativeTime(deployment.created_at)}</span>
                                </div>

                                {/* Duration */}
                                {deployment.duration && (
                                    <>
                                        <span>·</span>
                                        <span>Duration: {deployment.duration}</span>
                                    </>
                                )}

                                {/* Service */}
                                {deployment.service_name && (
                                    <>
                                        <span>·</span>
                                        <span className="font-medium text-foreground">
                                            {deployment.service_name}
                                        </span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </Link>
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

function EmptyState({ searchQuery, filterStatus }: { searchQuery: string; filterStatus: DeploymentStatus | 'all' }) {
    return (
        <FadeIn>
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Play className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No deployments found</h3>
                <p className="mt-2 text-center text-sm text-foreground-muted">
                    {searchQuery
                        ? 'Try adjusting your search query or filters'
                        : filterStatus !== 'all'
                        ? `No ${filterStatus} deployments found`
                        : 'No deployments have been made yet'}
                </p>
            </CardContent>
        </Card>
        </FadeIn>
    );
}
