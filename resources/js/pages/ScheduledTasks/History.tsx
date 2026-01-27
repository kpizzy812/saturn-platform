import { useState, useMemo, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import {
    Card,
    CardContent,
    Button,
    Badge,
    Input,
    Select,
    Modal,
    ModalFooter,
} from '@/components/ui';
import {
    Clock,
    Calendar,
    CheckCircle,
    XCircle,
    Download,
    RefreshCw,
    Filter,
    ArrowLeft,
    Play,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import type { ScheduledTask } from '@/types';

interface Props {
    history?: ScheduledTask[];
}

export default function ScheduledTasksHistory({ history = [] }: Props) {
    const { toast } = useToast();
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'completed' | 'failed'>('all');
    const [dateRange, setDateRange] = useState('all');
    const [expandedTaskId, setExpandedTaskId] = useState<number | null>(null);
    const [viewTaskId, setViewTaskId] = useState<number | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    // Filter history
    const filteredHistory = useMemo(() => {
        let filtered = history;

        // Filter by status
        if (statusFilter !== 'all') {
            filtered = filtered.filter((task) => task.status === statusFilter);
        }

        // Filter by date range
        if (dateRange !== 'all') {
            const now = Date.now();
            const ranges: Record<string, number> = {
                '24h': 86400000,
                '7d': 86400000 * 7,
                '30d': 86400000 * 30,
            };
            const rangeMs = ranges[dateRange];
            if (rangeMs) {
                filtered = filtered.filter((task) => {
                    const taskDate = new Date(task.executed_at || task.scheduled_for).getTime();
                    return now - taskDate <= rangeMs;
                });
            }
        }

        // Filter by search query
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                (task) =>
                    task.name.toLowerCase().includes(query) ||
                    task.description?.toLowerCase().includes(query) ||
                    task.command.toLowerCase().includes(query)
            );
        }

        // Sort by execution time (most recent first)
        return filtered.sort((a, b) => {
            const aTime = new Date(a.executed_at || a.scheduled_for).getTime();
            const bTime = new Date(b.executed_at || b.scheduled_for).getTime();
            return bTime - aTime;
        });
    }, [history, statusFilter, dateRange, searchQuery]);

    const handleRerunTask = (task: ScheduledTask) => {
        router.post('/scheduled-tasks', {
            name: `${task.name} (Re-run)`,
            description: task.description,
            command: task.command,
            scheduled_for: new Date(Date.now() + 300000).toISOString(), // 5 minutes from now
        });
    };

    const handleExportHistory = useCallback((format: 'csv' | 'json' = 'csv') => {
        if (filteredHistory.length === 0) {
            toast({
                title: 'No Data',
                description: 'No history entries to export',
                variant: 'error',
            });
            return;
        }

        setIsExporting(true);

        try {
            const filename = `scheduled-tasks-history-${new Date().toISOString().split('T')[0]}.${format}`;

            // Prepare data for export
            const exportData = filteredHistory.map((task) => ({
                id: task.id,
                name: task.name,
                description: task.description || '',
                command: task.command,
                status: task.status,
                exit_code: task.exit_code,
                duration_seconds: task.duration,
                executed_at: task.executed_at || '',
                scheduled_for: task.scheduled_for,
                output: task.output || '',
                error: task.error || '',
            }));

            let blob: Blob;

            if (format === 'json') {
                const jsonContent = JSON.stringify({ data: exportData }, null, 2);
                blob = new Blob([jsonContent], { type: 'application/json' });
            } else {
                // CSV export with BOM for UTF-8
                const headers = ['ID', 'Name', 'Description', 'Command', 'Status', 'Exit Code', 'Duration (s)', 'Executed At', 'Scheduled For', 'Output', 'Error'];
                const csvRows = [
                    headers.join(','),
                    ...exportData.map((row) => [
                        row.id,
                        `"${(row.name || '').replace(/"/g, '""')}"`,
                        `"${(row.description || '').replace(/"/g, '""')}"`,
                        `"${(row.command || '').replace(/"/g, '""')}"`,
                        row.status,
                        row.exit_code ?? '',
                        row.duration_seconds ?? '',
                        row.executed_at,
                        row.scheduled_for,
                        `"${(row.output || '').replace(/"/g, '""').replace(/\n/g, '\\n')}"`,
                        `"${(row.error || '').replace(/"/g, '""').replace(/\n/g, '\\n')}"`,
                    ].join(','))
                ];
                const csvContent = '\uFEFF' + csvRows.join('\n');
                blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
            }

            // Download file
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            toast({
                title: 'Export Successful',
                description: `Exported ${filteredHistory.length} entries to ${filename}`,
            });
        } catch (err) {
            toast({
                title: 'Export Failed',
                description: err instanceof Error ? err.message : 'Failed to export history',
                variant: 'error',
            });
        } finally {
            setIsExporting(false);
        }
    }, [filteredHistory, toast]);

    const formatDuration = (seconds: number | null) => {
        if (!seconds) return 'N/A';
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    };

    const getStatusBadge = (status: ScheduledTask['status'], exitCode: number | null) => {
        if (status === 'completed' && exitCode === 0) {
            return (
                <Badge variant="success" className="flex items-center gap-1">
                    <CheckCircle className="h-3 w-3" />
                    Success
                </Badge>
            );
        }
        return (
            <Badge variant="danger" className="flex items-center gap-1">
                <XCircle className="h-3 w-3" />
                Failed
            </Badge>
        );
    };

    const viewingTask = history.find((t) => t.id === viewTaskId);

    return (
        <AppLayout
            title="Task History"
            breadcrumbs={[
                { label: 'Scheduled Tasks', href: '/scheduled-tasks' },
                { label: 'History' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <div className="mb-4">
                    <Link href="/scheduled-tasks">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Tasks
                        </Button>
                    </Link>
                </div>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Task History</h1>
                        <p className="text-foreground-muted">
                            View execution history of all scheduled tasks
                        </p>
                    </div>
                    <Select
                        value=""
                        onChange={(e) => {
                            if (e.target.value) {
                                handleExportHistory(e.target.value as 'csv' | 'json');
                            }
                        }}
                        disabled={isExporting || filteredHistory.length === 0}
                        className="w-[140px]"
                    >
                        <option value="" disabled>
                            {isExporting ? 'Exporting...' : 'Export'}
                        </option>
                        <option value="csv">Export CSV</option>
                        <option value="json">Export JSON</option>
                    </Select>
                </div>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Input
                                placeholder="Search history..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                        </div>
                        <div>
                            <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as 'all' | 'completed' | 'failed')}>
                                <option value="all">All Status</option>
                                <option value="completed">Success</option>
                                <option value="failed">Failed</option>
                            </Select>
                        </div>
                        <div>
                            <Select value={dateRange} onChange={(e) => setDateRange(e.target.value)}>
                                <option value="all">All Time</option>
                                <option value="24h">Last 24 Hours</option>
                                <option value="7d">Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Statistics */}
            <div className="mb-6 grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Total Executions</div>
                        <div className="mt-1 text-2xl font-bold text-foreground">
                            {filteredHistory.length}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Success Rate</div>
                        <div className="mt-1 text-2xl font-bold text-primary">
                            {filteredHistory.length > 0
                                ? Math.round(
                                      (filteredHistory.filter(
                                          (t) => t.status === 'completed' && t.exit_code === 0
                                      ).length /
                                          filteredHistory.length) *
                                          100
                                  )
                                : 0}
                            %
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Avg Duration</div>
                        <div className="mt-1 text-2xl font-bold text-foreground">
                            {filteredHistory.length > 0
                                ? formatDuration(
                                      Math.round(
                                          filteredHistory.reduce((sum, t) => sum + (t.duration || 0), 0) /
                                              filteredHistory.length
                                      )
                                  )
                                : 'N/A'}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* History Timeline */}
            {filteredHistory.length === 0 ? (
                <Card className="p-12 text-center">
                    <Clock className="mx-auto h-12 w-12 text-foreground-muted" />
                    <h3 className="mt-4 text-lg font-medium text-foreground">No history found</h3>
                    <p className="mt-2 text-foreground-muted">
                        Try adjusting your filters or run some tasks.
                    </p>
                </Card>
            ) : (
                <div className="space-y-3">
                    {filteredHistory.map((task) => (
                        <Card
                            key={task.id}
                            className="transition-colors hover:border-border/80"
                        >
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="mb-2 flex items-center gap-3">
                                            <h3 className="font-semibold text-foreground">
                                                {task.name}
                                            </h3>
                                            {getStatusBadge(task.status, task.exit_code)}
                                        </div>
                                        <div className="mb-2 flex flex-wrap gap-4 text-sm">
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">
                                                    Executed:
                                                </span>
                                                <span className="text-foreground">
                                                    {task.executed_at
                                                        ? new Date(
                                                              task.executed_at
                                                          ).toLocaleString()
                                                        : 'N/A'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">
                                                    Duration:
                                                </span>
                                                <span className="text-foreground">
                                                    {formatDuration(task.duration)}
                                                </span>
                                            </div>
                                            {task.exit_code !== null && (
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-foreground-muted">
                                                        Exit code:
                                                    </span>
                                                    <span className="text-foreground">
                                                        {task.exit_code}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        {expandedTaskId === task.id && (
                                            <div className="mt-3 space-y-3">
                                                <div>
                                                    <code className="block rounded-md bg-background-tertiary px-3 py-2 text-sm text-foreground">
                                                        {task.command}
                                                    </code>
                                                </div>
                                                {task.output && (
                                                    <div>
                                                        <div className="mb-1 text-sm font-medium text-foreground">
                                                            Output:
                                                        </div>
                                                        <div className="rounded-md bg-background-tertiary p-3">
                                                            <pre className="max-h-40 overflow-y-auto text-xs text-foreground">
                                                                {task.output}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                )}
                                                {task.error && (
                                                    <div>
                                                        <div className="mb-1 text-sm font-medium text-danger">
                                                            Error:
                                                        </div>
                                                        <div className="rounded-md bg-danger/10 p-3">
                                                            <pre className="max-h-40 overflow-y-auto text-xs text-danger">
                                                                {task.error}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className="ml-4 flex items-center gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRerunTask(task)}
                                        >
                                            <RefreshCw className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setExpandedTaskId(
                                                    expandedTaskId === task.id ? null : task.id
                                                )
                                            }
                                        >
                                            {expandedTaskId === task.id ? (
                                                <ChevronUp className="h-4 w-4" />
                                            ) : (
                                                <ChevronDown className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
