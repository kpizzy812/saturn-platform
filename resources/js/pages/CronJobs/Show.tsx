import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import {
    Card, CardContent, CardHeader, CardTitle, Button, Badge, Tabs, useConfirm
} from '@/components/ui';
import {
    Edit, Trash2, Play, Pause, Clock, CheckCircle, XCircle,
    AlertCircle, TrendingUp, Calendar, Terminal, Settings as SettingsIcon
} from 'lucide-react';
import { getStatusVariant, getStatusLabel, getStatusIcon } from '@/lib/statusUtils';
import type { CronJob, CronJobExecution } from '@/types/models';

interface Props {
    cronJob: CronJob;
    executions?: CronJobExecution[];
}

export default function CronJobShow({ cronJob, executions = [] }: Props) {
    const confirm = useConfirm();
    const [selectedExecution, setSelectedExecution] = useState<CronJobExecution | null>(null);

    const handleToggleStatus = () => {
        const newStatus = cronJob.status === 'enabled' ? 'disabled' : 'enabled';
        router.post(`/cron-jobs/${cronJob.uuid}/toggle`, { status: newStatus });
    };

    const handleRunNow = () => {
        router.post(`/cron-jobs/${cronJob.uuid}/run`);
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Cron Job',
            description: 'Are you sure you want to delete this cron job?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/cron-jobs/${cronJob.uuid}`, {
                onSuccess: () => router.visit('/cron-jobs'),
            });
        }
    };

    const formatCronSchedule = (schedule: string) => {
        const presets: Record<string, string> = {
            '* * * * *': 'Every minute',
            '*/5 * * * *': 'Every 5 minutes',
            '0 * * * *': 'Hourly',
            '0 0 * * *': 'Daily at midnight',
            '0 2 * * *': 'Daily at 2:00 AM',
            '0 0 * * 0': 'Weekly on Sunday',
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

    const formatRelativeTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
        return `${Math.floor(diffMins / 1440)}d ago`;
    };

    const getSuccessRate = () => {
        const total = cronJob.success_count + cronJob.failure_count;
        if (total === 0) return 100;
        return Math.round((cronJob.success_count / total) * 100);
    };

    const tabs = [
        {
            label: 'Overview',
            content: <OverviewTab cronJob={cronJob} executions={executions} />,
        },
        {
            label: 'Execution History',
            content: (
                <ExecutionHistoryTab
                    executions={executions}
                    onSelectExecution={setSelectedExecution}
                    selectedExecution={selectedExecution}
                />
            ),
        },
        {
            label: 'Configuration',
            content: <ConfigurationTab cronJob={cronJob} />,
        },
    ];

    return (
        <AppLayout
            title={cronJob.name}
            breadcrumbs={[
                { label: 'Cron Jobs', href: '/cron-jobs' },
                { label: cronJob.name },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${
                        cronJob.status === 'enabled' ? 'bg-primary/10' :
                        cronJob.status === 'running' ? 'bg-info/10' :
                        cronJob.status === 'failed' ? 'bg-danger/10' : 'bg-background-tertiary'
                    }`}>
                        <Clock className={`h-7 w-7 ${
                            cronJob.status === 'enabled' ? 'text-primary' :
                            cronJob.status === 'running' ? 'text-info' :
                            cronJob.status === 'failed' ? 'text-danger' : 'text-foreground-muted'
                        }`} />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-foreground">{cronJob.name}</h1>
                            <Badge variant={getStatusVariant(cronJob.status)}>{getStatusLabel(cronJob.status)}</Badge>
                        </div>
                        {cronJob.description && (
                            <p className="text-foreground-muted">{cronJob.description}</p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleRunNow}
                        disabled={cronJob.status === 'running'}
                    >
                        <Play className="mr-2 h-4 w-4" />
                        Run Now
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleToggleStatus}
                    >
                        {cronJob.status === 'enabled' || cronJob.status === 'running' ? (
                            <>
                                <Pause className="mr-2 h-4 w-4" />
                                Disable
                            </>
                        ) : (
                            <>
                                <Play className="mr-2 h-4 w-4" />
                                Enable
                            </>
                        )}
                    </Button>
                    <Link href={`/cron-jobs/${cronJob.uuid}/edit`}>
                        <Button variant="secondary" size="sm">
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="danger" size="sm" onClick={handleDelete}>
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            {/* Tabs */}
            <Tabs tabs={tabs} />
        </AppLayout>
    );
}

function OverviewTab({ cronJob, executions = [] }: { cronJob: CronJob; executions?: CronJobExecution[] }) {
    const formatDuration = (seconds: number) => {
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    };

    const getSuccessRate = () => {
        const total = cronJob.success_count + cronJob.failure_count;
        if (total === 0) return 100;
        return Math.round((cronJob.success_count / total) * 100);
    };

    const formatRelativeTime = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    return (
        <div className="space-y-6">
            {/* Stats Cards */}
            <div className="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <CheckCircle className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Success Rate</p>
                                <p className="text-2xl font-bold text-foreground">{getSuccessRate()}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                <TrendingUp className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Total Runs</p>
                                <p className="text-2xl font-bold text-foreground">
                                    {cronJob.success_count + cronJob.failure_count}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                <Clock className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Avg Duration</p>
                                <p className="text-2xl font-bold text-foreground">
                                    {formatDuration(cronJob.average_duration)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10">
                                <XCircle className="h-5 w-5 text-danger" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Failures</p>
                                <p className="text-2xl font-bold text-foreground">{cronJob.failure_count}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Schedule Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Schedule Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-foreground-muted mb-1">Schedule</p>
                                <code className="px-3 py-2 bg-background-tertiary rounded-md text-foreground block">
                                    {cronJob.schedule}
                                </code>
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted mb-1">Timezone</p>
                                <p className="text-foreground">{cronJob.timezone}</p>
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted mb-1">Last Run</p>
                                <p className="text-foreground">{formatRelativeTime(cronJob.last_run)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted mb-1">Next Run</p>
                                <p className="text-foreground">{formatRelativeTime(cronJob.next_run)}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Execution Trend */}
            <Card>
                <CardHeader>
                    <CardTitle>Execution Trend (Last 7 Days)</CardTitle>
                </CardHeader>
                <CardContent>
                    <ExecutionTrendChart executions={executions} />
                </CardContent>
            </Card>
        </div>
    );
}

function ExecutionHistoryTab({
    executions,
    onSelectExecution,
    selectedExecution,
}: {
    executions: CronJobExecution[];
    onSelectExecution: (execution: CronJobExecution | null) => void;
    selectedExecution: CronJobExecution | null;
}) {
    const formatDuration = (seconds: number | null) => {
        if (!seconds) return 'N/A';
        if (seconds < 60) return `${seconds}s`;
        return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            {/* Execution List */}
            <div>
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Executions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {executions.map((execution) => (
                                <button
                                    key={execution.id}
                                    onClick={() => onSelectExecution(execution)}
                                    className={`w-full text-left p-4 rounded-lg border transition-colors ${
                                        selectedExecution?.id === execution.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border bg-background-secondary hover:bg-background-tertiary'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <div className="flex items-center gap-2">
                                            {getStatusIcon(execution.status, { size: 'sm' })}
                                            <span className="text-sm font-medium text-foreground">
                                                {formatDateTime(execution.started_at)}
                                            </span>
                                        </div>
                                        <Badge variant={getStatusVariant(execution.status)}>{getStatusLabel(execution.status)}</Badge>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs text-foreground-muted">
                                        <span>Duration: {formatDuration(execution.duration)}</span>
                                        {execution.exit_code !== null && (
                                            <span>Exit code: {execution.exit_code}</span>
                                        )}
                                    </div>
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Execution Details */}
            <div>
                {selectedExecution ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Execution Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <p className="text-sm text-foreground-muted mb-1">Status</p>
                                    <Badge variant={getStatusVariant(selectedExecution.status)}>{getStatusLabel(selectedExecution.status)}</Badge>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted mb-1">Started At</p>
                                    <p className="text-foreground">{formatDateTime(selectedExecution.started_at)}</p>
                                </div>
                                {selectedExecution.finished_at && (
                                    <div>
                                        <p className="text-sm text-foreground-muted mb-1">Finished At</p>
                                        <p className="text-foreground">{formatDateTime(selectedExecution.finished_at)}</p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-sm text-foreground-muted mb-1">Duration</p>
                                    <p className="text-foreground">{formatDuration(selectedExecution.duration)}</p>
                                </div>
                                {selectedExecution.exit_code !== null && (
                                    <div>
                                        <p className="text-sm text-foreground-muted mb-1">Exit Code</p>
                                        <p className="text-foreground">{selectedExecution.exit_code}</p>
                                    </div>
                                )}
                                {selectedExecution.output && (
                                    <div>
                                        <p className="text-sm text-foreground-muted mb-2 flex items-center gap-2">
                                            <Terminal className="h-4 w-4" />
                                            Output
                                        </p>
                                        <pre className="p-4 bg-background-tertiary rounded-lg text-sm text-foreground overflow-x-auto">
                                            {selectedExecution.output}
                                        </pre>
                                    </div>
                                )}
                                {selectedExecution.error && (
                                    <div>
                                        <p className="text-sm text-danger mb-2 flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4" />
                                            Error
                                        </p>
                                        <pre className="p-4 bg-danger/10 border border-danger rounded-lg text-sm text-danger overflow-x-auto">
                                            {selectedExecution.error}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="p-12 text-center">
                        <Terminal className="mx-auto h-12 w-12 text-foreground-muted" />
                        <h3 className="mt-4 text-lg font-medium text-foreground">No execution selected</h3>
                        <p className="mt-2 text-foreground-muted">
                            Select an execution from the list to view details.
                        </p>
                    </Card>
                )}
            </div>
        </div>
    );
}

function ExecutionTrendChart({ executions }: { executions: CronJobExecution[] }) {
    // Group executions by day (last 7 days)
    const now = new Date();
    const days: { label: string; date: string; success: number; failure: number }[] = [];

    for (let i = 6; i >= 0; i--) {
        const d = new Date(now);
        d.setDate(d.getDate() - i);
        const dateStr = d.toISOString().split('T')[0];
        const dayLabel = d.toLocaleDateString(undefined, { weekday: 'short' });
        days.push({ label: dayLabel, date: dateStr, success: 0, failure: 0 });
    }

    for (const exec of executions) {
        const execDate = new Date(exec.started_at).toISOString().split('T')[0];
        const day = days.find((d) => d.date === execDate);
        if (day) {
            if (exec.status === 'success') {
                day.success++;
            } else if (exec.status === 'failed') {
                day.failure++;
            }
        }
    }

    const maxVal = Math.max(1, ...days.map((d) => d.success + d.failure));
    const hasData = days.some((d) => d.success > 0 || d.failure > 0);

    if (!hasData) {
        return (
            <div className="flex h-48 items-center justify-center rounded-lg bg-background-tertiary">
                <div className="text-center">
                    <TrendingUp className="mx-auto h-8 w-8 text-foreground-muted" />
                    <p className="mt-2 text-sm text-foreground-muted">No execution data for the last 7 days</p>
                </div>
            </div>
        );
    }

    return (
        <div>
            {/* Bar chart */}
            <div className="flex items-end gap-2" style={{ height: '200px' }}>
                {days.map((day) => {
                    const total = day.success + day.failure;
                    const successHeight = total > 0 ? (day.success / maxVal) * 100 : 0;
                    const failureHeight = total > 0 ? (day.failure / maxVal) * 100 : 0;

                    return (
                        <div key={day.date} className="flex flex-1 flex-col items-center gap-1">
                            <div className="relative flex w-full flex-col items-center" style={{ height: '170px' }}>
                                <div className="flex w-full max-w-8 flex-col-reverse" style={{ height: '100%' }}>
                                    {successHeight > 0 && (
                                        <div
                                            className="w-full rounded-t bg-primary transition-all duration-300"
                                            style={{ height: `${successHeight}%` }}
                                            title={`${day.success} successful`}
                                        />
                                    )}
                                    {failureHeight > 0 && (
                                        <div
                                            className="w-full bg-danger transition-all duration-300"
                                            style={{ height: `${failureHeight}%` }}
                                            title={`${day.failure} failed`}
                                        />
                                    )}
                                </div>
                            </div>
                            <span className="text-xs text-foreground-muted">{day.label}</span>
                        </div>
                    );
                })}
            </div>

            {/* Legend */}
            <div className="mt-4 flex items-center justify-center gap-6 text-xs text-foreground-muted">
                <div className="flex items-center gap-1.5">
                    <div className="h-2.5 w-2.5 rounded-sm bg-primary" />
                    <span>Success</span>
                </div>
                <div className="flex items-center gap-1.5">
                    <div className="h-2.5 w-2.5 rounded-sm bg-danger" />
                    <span>Failure</span>
                </div>
            </div>
        </div>
    );
}

function ConfigurationTab({ cronJob }: { cronJob: CronJob }) {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>General</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Name</p>
                            <p className="text-foreground">{cronJob.name}</p>
                        </div>
                        {cronJob.description && (
                            <div>
                                <p className="text-sm text-foreground-muted mb-1">Description</p>
                                <p className="text-foreground">{cronJob.description}</p>
                            </div>
                        )}
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Command</p>
                            <code className="px-3 py-2 bg-background-tertiary rounded-md text-foreground block">
                                {cronJob.command}
                            </code>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Schedule Settings</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Cron Expression</p>
                            <code className="px-3 py-2 bg-background-tertiary rounded-md text-foreground block">
                                {cronJob.schedule}
                            </code>
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Timezone</p>
                            <p className="text-foreground">{cronJob.timezone}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Execution Settings</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Timeout</p>
                            <p className="text-foreground">{cronJob.timeout} seconds</p>
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Retries</p>
                            <p className="text-foreground">{cronJob.retries}</p>
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted mb-1">Notify on Failure</p>
                            <p className="text-foreground">{cronJob.notify_on_failure ? 'Yes' : 'No'}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
