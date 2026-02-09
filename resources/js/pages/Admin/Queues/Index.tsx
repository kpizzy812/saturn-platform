import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useConfirm } from '@/components/ui';
import {
    Dropdown,
    DropdownTrigger,
    DropdownContent,
    DropdownItem,
    DropdownDivider,
} from '@/components/ui/Dropdown';
import {
    ListOrdered,
    Clock,
    CheckCircle,
    XCircle,
    RefreshCw,
    Trash2,
    Search,
    MoreHorizontal,
    Play,
    AlertTriangle,
    Activity,
    Loader2,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';

interface QueueStats {
    pending: number;
    processing: number;
    completed: number;
    failed: number;
}

interface FailedJob {
    id: number;
    uuid: string;
    connection: string;
    queue: string;
    payload: string;
    exception: string;
    failed_at: string;
}

interface Props {
    stats: QueueStats;
    failedJobs: FailedJob[];
}

function FailedJobRow({ job, onRetry, onDelete }: { job: FailedJob; onRetry: () => void; onDelete: () => void }) {
    const [isExpanded, setIsExpanded] = React.useState(false);
    const confirm = useConfirm();

    // Parse payload to get job class name
    let jobName = 'Unknown Job';
    let jobData: Record<string, any> = {};
    try {
        const payload = JSON.parse(job.payload);
        jobName = payload.displayName || payload.job || 'Unknown Job';
        jobData = payload.data || {};
    } catch {
        // Invalid JSON
    }

    // Format exception - get first line
    const exceptionFirstLine = job.exception.split('\n')[0];

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Failed Job',
            description: `Are you sure you want to delete this failed job? This cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            onDelete();
        }
    };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <XCircle className="h-5 w-5 text-danger" />
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">{jobName}</span>
                                <Badge variant="default" size="sm">{job.queue}</Badge>
                            </div>
                            <p className="mt-1 text-sm text-danger">{exceptionFirstLine}</p>
                            <p className="mt-1 text-xs text-foreground-subtle">
                                Failed at: {new Date(job.failed_at).toLocaleString()}
                            </p>
                        </div>
                    </div>

                    {/* Expandable exception details */}
                    <button
                        onClick={() => setIsExpanded(!isExpanded)}
                        className="mt-2 flex items-center gap-1 text-xs text-foreground-muted hover:text-foreground"
                    >
                        {isExpanded ? (
                            <>
                                <ChevronUp className="h-3 w-3" />
                                Hide details
                            </>
                        ) : (
                            <>
                                <ChevronDown className="h-3 w-3" />
                                Show details
                            </>
                        )}
                    </button>

                    {isExpanded && (
                        <div className="mt-3 rounded-md bg-background-secondary p-3">
                            <p className="mb-2 text-xs font-medium text-foreground-muted">Exception:</p>
                            <pre className="max-h-48 overflow-auto whitespace-pre-wrap text-xs text-danger">
                                {job.exception}
                            </pre>
                            {Object.keys(jobData).length > 0 && (
                                <>
                                    <p className="mb-2 mt-4 text-xs font-medium text-foreground-muted">Payload Data:</p>
                                    <pre className="max-h-32 overflow-auto whitespace-pre-wrap text-xs text-foreground-subtle">
                                        {JSON.stringify(jobData, null, 2)}
                                    </pre>
                                </>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Dropdown>
                        <DropdownTrigger>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownTrigger>
                        <DropdownContent align="right">
                            <DropdownItem onClick={onRetry}>
                                <RefreshCw className="h-4 w-4" />
                                Retry Job
                            </DropdownItem>
                            <DropdownDivider />
                            <DropdownItem onClick={handleDelete} className="text-danger">
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </DropdownItem>
                        </DropdownContent>
                    </Dropdown>
                </div>
            </div>
        </div>
    );
}

export default function AdminQueuesIndex({ stats, failedJobs: initialFailedJobs }: Props) {
    const confirm = useConfirm();
    const [searchQuery, setSearchQuery] = React.useState('');
    const [isRetryingAll, setIsRetryingAll] = React.useState(false);
    const [isFlushing, setIsFlushing] = React.useState(false);

    const failedJobs = initialFailedJobs ?? [];

    const filteredJobs = failedJobs.filter((job) => {
        if (!searchQuery) return true;
        let jobName = '';
        try {
            const payload = JSON.parse(job.payload || '{}');
            jobName = payload.displayName || payload.job || '';
        } catch {
            // Invalid JSON payload - skip name matching
        }
        const query = searchQuery.toLowerCase();
        return (
            jobName.toLowerCase().includes(query) ||
            job.queue.toLowerCase().includes(query) ||
            job.exception.toLowerCase().includes(query)
        );
    });

    const handleRetryJob = (jobId: number) => {
        router.post(`/admin/queues/failed/${jobId}/retry`, {}, {
            preserveScroll: true,
        });
    };

    const handleDeleteJob = (jobId: number) => {
        router.delete(`/admin/queues/failed/${jobId}`, {
            preserveScroll: true,
        });
    };

    const handleRetryAll = async () => {
        const confirmed = await confirm({
            title: 'Retry All Failed Jobs',
            description: `Are you sure you want to retry all ${failedJobs.length} failed jobs?`,
            confirmText: 'Retry All',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRetryingAll(true);
            router.post('/admin/queues/failed/retry-all', {}, {
                preserveScroll: true,
                onFinish: () => setIsRetryingAll(false),
            });
        }
    };

    const handleFlushAll = async () => {
        const confirmed = await confirm({
            title: 'Delete All Failed Jobs',
            description: `Are you sure you want to delete all ${failedJobs.length} failed jobs? This cannot be undone.`,
            confirmText: 'Delete All',
            variant: 'danger',
        });
        if (confirmed) {
            setIsFlushing(true);
            router.delete('/admin/queues/failed/flush', {
                preserveScroll: true,
                onFinish: () => setIsFlushing(false),
            });
        }
    };

    return (
        <AdminLayout
            title="Queue Monitor"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Queues' },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Queue Monitor</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor background jobs and manage failed jobs
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Pending</p>
                                    <p className="text-2xl font-bold text-warning">{stats?.pending ?? 0}</p>
                                </div>
                                <Clock className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Processing</p>
                                    <p className="text-2xl font-bold text-primary">{stats?.processing ?? 0}</p>
                                </div>
                                <Loader2 className="h-8 w-8 text-primary/50 animate-spin" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Completed (24h)</p>
                                    <p className="text-2xl font-bold text-success">{stats?.completed ?? 0}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed</p>
                                    <p className="text-2xl font-bold text-danger">{stats?.failed ?? 0}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Failed Jobs Section */}
                <Card variant="glass">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Failed Jobs ({failedJobs.length})</CardTitle>
                                <CardDescription>Jobs that failed to process and need attention</CardDescription>
                            </div>
                            {failedJobs.length > 0 && (
                                <div className="flex gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleRetryAll}
                                        disabled={isRetryingAll}
                                    >
                                        <RefreshCw className={`h-4 w-4 ${isRetryingAll ? 'animate-spin' : ''}`} />
                                        Retry All
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={handleFlushAll}
                                        disabled={isFlushing}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Flush All
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Search */}
                        {failedJobs.length > 0 && (
                            <div className="mb-4">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search failed jobs..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                        )}

                        {/* Jobs List */}
                        {filteredJobs.length === 0 ? (
                            <div className="py-12 text-center">
                                {failedJobs.length === 0 ? (
                                    <>
                                        <CheckCircle className="mx-auto h-12 w-12 text-success" />
                                        <p className="mt-4 text-sm text-foreground-muted">No failed jobs</p>
                                        <p className="text-xs text-foreground-subtle">
                                            All jobs are processing normally
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <Search className="mx-auto h-12 w-12 text-foreground-muted" />
                                        <p className="mt-4 text-sm text-foreground-muted">No matching jobs</p>
                                        <p className="text-xs text-foreground-subtle">
                                            Try adjusting your search query
                                        </p>
                                    </>
                                )}
                            </div>
                        ) : (
                            <div>
                                {filteredJobs.map((job) => (
                                    <FailedJobRow
                                        key={job.id}
                                        job={job}
                                        onRetry={() => handleRetryJob(job.id)}
                                        onDelete={() => handleDeleteJob(job.id)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Info Card */}
                <Card variant="glass" className="mt-6">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 text-warning" />
                            <div>
                                <p className="text-sm font-medium text-foreground">About Queue Monitoring</p>
                                <p className="mt-1 text-xs text-foreground-muted">
                                    This page shows jobs managed by Laravel Horizon. Failed jobs can be retried or deleted.
                                    Jobs are processed by background workers and include deployments, backups, notifications, and more.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
