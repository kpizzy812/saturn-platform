import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, useConfirm } from '@/components/ui';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import {
    Plus, Clock, Play, Pause, Trash2,
    CheckCircle, XCircle, AlertCircle, Calendar
} from 'lucide-react';
import type { CronJob } from '@/types/models';

interface Props {
    cronJobs?: CronJob[];
}

export default function CronJobsIndex({ cronJobs = [] }: Props) {
    const confirm = useConfirm();
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | CronJob['status']>('all');

    // Filter cron jobs
    const filteredCronJobs = cronJobs.filter(job => {
        const matchesSearch = job.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            job.description?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            job.command.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || job.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const handleToggleStatus = (uuid: string, currentStatus: CronJob['status']) => {
        // In real app, this would call an API endpoint
        const newStatus = currentStatus === 'enabled' ? 'disabled' : 'enabled';
        router.post(`/cron-jobs/${uuid}/toggle`, { status: newStatus });
    };

    const handleRunNow = (uuid: string) => {
        // In real app, this would call an API endpoint
        router.post(`/cron-jobs/${uuid}/run`);
    };

    const handleDelete = async (uuid: string) => {
        const confirmed = await confirm({
            title: 'Delete Cron Job',
            description: 'Are you sure you want to delete this cron job?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/cron-jobs/${uuid}`);
        }
    };

    const formatCronSchedule = (schedule: string) => {
        // Simple cron expression to human-readable conversion
        const presets: Record<string, string> = {
            '* * * * *': 'Every minute',
            '*/5 * * * *': 'Every 5 minutes',
            '*/15 * * * *': 'Every 15 minutes',
            '*/30 * * * *': 'Every 30 minutes',
            '0 * * * *': 'Hourly',
            '0 0 * * *': 'Daily at midnight',
            '0 2 * * *': 'Daily at 2:00 AM',
            '0 9 * * *': 'Daily at 9:00 AM',
            '0 0 * * 0': 'Weekly on Sunday',
            '0 3 * * 0': 'Weekly on Sunday at 3:00 AM',
            '0 9 * * 1': 'Weekly on Monday at 9:00 AM',
            '0 0 1 * *': 'Monthly on the 1st',
        };
        return presets[schedule] || schedule;
    };

    const formatDuration = (seconds: number) => {
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    };

    const formatRelativeTime = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
        return `${Math.floor(diffMins / 1440)}d ago`;
    };

    const formatNextRun = (dateString: string | null) => {
        if (!dateString) return 'Not scheduled';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = date.getTime() - now.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Soon';
        if (diffMins < 60) return `In ${diffMins}m`;
        if (diffMins < 1440) return `In ${Math.floor(diffMins / 60)}h`;
        return `In ${Math.floor(diffMins / 1440)}d`;
    };

    const getStatusBadge = (status: CronJob['status']) => {
        switch (status) {
            case 'enabled':
                return <Badge variant="success">Enabled</Badge>;
            case 'disabled':
                return <Badge variant="default">Disabled</Badge>;
            case 'running':
                return <Badge variant="info">Running</Badge>;
            case 'failed':
                return <Badge variant="danger">Failed</Badge>;
        }
    };

    const getSuccessRate = (job: CronJob) => {
        const total = job.success_count + job.failure_count;
        if (total === 0) return 100;
        return Math.round((job.success_count / total) * 100);
    };

    return (
        <AppLayout
            title="Cron Jobs"
            breadcrumbs={[{ label: 'Cron Jobs' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Cron Jobs</h1>
                    <p className="text-foreground-muted">Schedule and manage recurring tasks</p>
                </div>
                <Link href="/cron-jobs/create">
                    <Button className="group">
                        <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                        New Cron Job
                    </Button>
                </Link>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <Input
                            placeholder="Search cron jobs..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="md:max-w-xs"
                        />
                        <div className="flex gap-2">
                            <Button
                                variant={statusFilter === 'all' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('all')}
                            >
                                All
                            </Button>
                            <Button
                                variant={statusFilter === 'enabled' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('enabled')}
                            >
                                Enabled
                            </Button>
                            <Button
                                variant={statusFilter === 'disabled' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('disabled')}
                            >
                                Disabled
                            </Button>
                            <Button
                                variant={statusFilter === 'running' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('running')}
                            >
                                Running
                            </Button>
                            <Button
                                variant={statusFilter === 'failed' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('failed')}
                            >
                                Failed
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Cron Jobs List */}
            {filteredCronJobs.length === 0 ? (
                <EmptyState searchQuery={searchQuery} />
            ) : (
                <StaggerList className="space-y-4">
                    {filteredCronJobs.map((job, i) => (
                        <StaggerItem key={job.id} index={i}>
                        <Card className="hover:border-border/80 transition-colors">
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <Link
                                                href={`/cron-jobs/${job.uuid}`}
                                                className="text-lg font-semibold text-foreground hover:text-primary transition-colors"
                                            >
                                                {job.name}
                                            </Link>
                                            {getStatusBadge(job.status)}
                                        </div>
                                        {job.description && (
                                            <p className="text-foreground-muted mb-3">{job.description}</p>
                                        )}
                                        <div className="flex flex-wrap gap-4 text-sm">
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">Schedule:</span>
                                                <code className="px-2 py-0.5 bg-background-tertiary rounded text-foreground text-xs">
                                                    {formatCronSchedule(job.schedule)}
                                                </code>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">Last run:</span>
                                                <span className="text-foreground">{formatRelativeTime(job.last_run)}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">Next run:</span>
                                                <span className="text-foreground">{formatNextRun(job.next_run)}</span>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex items-center gap-4 text-sm">
                                            <div className="flex items-center gap-1.5">
                                                <CheckCircle className="h-4 w-4 text-primary" />
                                                <span className="text-foreground">{job.success_count} successful</span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <XCircle className="h-4 w-4 text-danger" />
                                                <span className="text-foreground">{job.failure_count} failed</span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-foreground-muted">Success rate:</span>
                                                <span className="text-foreground font-medium">{getSuccessRate(job)}%</span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-foreground-muted">Avg duration:</span>
                                                <span className="text-foreground">{formatDuration(job.average_duration)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 ml-4">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRunNow(job.uuid)}
                                            disabled={job.status === 'running'}
                                        >
                                            <Play className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleToggleStatus(job.uuid, job.status)}
                                        >
                                            {job.status === 'enabled' || job.status === 'running' ? (
                                                <Pause className="h-4 w-4" />
                                            ) : (
                                                <Play className="h-4 w-4" />
                                            )}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDelete(job.uuid)}
                                        >
                                            <Trash2 className="h-4 w-4 text-danger" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        </StaggerItem>
                    ))}
                </StaggerList>
            )}
        </AppLayout>
    );
}

function EmptyState({ searchQuery }: { searchQuery: string }) {
    if (searchQuery) {
        return (
            <FadeIn>
            <Card className="p-12 text-center">
                <AlertCircle className="mx-auto h-12 w-12 text-foreground-muted animate-pulse-soft" />
                <h3 className="mt-4 text-lg font-medium text-foreground">No cron jobs found</h3>
                <p className="mt-2 text-foreground-muted">
                    Try adjusting your search query or filters.
                </p>
            </Card>
            </FadeIn>
        );
    }

    return (
        <FadeIn>
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Clock className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No cron jobs yet</h3>
            <p className="mt-2 text-foreground-muted">
                Create your first cron job to schedule recurring tasks.
            </p>
            <Link href="/cron-jobs/create" className="mt-6 inline-block">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Cron Job
                </Button>
            </Link>
        </Card>
        </FadeIn>
    );
}
