import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import {
    Download,
    RotateCw,
    ChevronDown,
    ChevronRight,
    Terminal,
    Search,
    Filter,
    Maximize2,
    Minimize2,
    Copy,
    ChevronLeft,
    Wifi,
    WifiOff,
    Loader2,
    XCircle,
    AlertCircle,
    CheckCircle,
    Clock,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useLogStream, type LogEntry } from '@/hooks/useLogStream';
import { getStatusIcon } from '@/lib/statusUtils';
import { AIAnalysisCard } from '@/components/features/AIAnalysisCard';

interface Props {
    deploymentUuid: string;
    deployment?: {
        status: string;
        application_name?: string;
        created_at?: string;
    };
}

interface BuildStep {
    id: number;
    name: string;
    status: 'pending' | 'running' | 'success' | 'failed';
    duration: string;
    logs: string[];
    startTime?: string;
    endTime?: string;
}

/**
 * Convert raw log entries to build steps format
 */
function convertLogsToSteps(logs: LogEntry[], deploymentStatus?: string): BuildStep[] {
    if (logs.length === 0) {
        return [{
            id: 1,
            name: 'Deployment',
            status: deploymentStatus === 'finished' ? 'success' :
                    deploymentStatus === 'failed' ? 'failed' :
                    deploymentStatus === 'cancelled-by-user' ? 'failed' : 'running',
            duration: 'calculating...',
            logs: ['Waiting for logs...'],
        }];
    }

    // Group logs by build step (detect step markers like ╔══════════════════════════════════════════╗)
    const steps: BuildStep[] = [];
     
    let currentStep: BuildStep | null = null as BuildStep | null;
    let stepId = 1;

    logs.forEach((log, index) => {
        const message = log.message;

        // Detect step header (╔ pattern or common step markers)
        const isStepHeader = message.includes('╔') ||
                            message.startsWith('###') ||
                            message.includes('====') ||
                            message.match(/^Step \d+/i);

        if (isStepHeader && message.includes('╔')) {
            // Save previous step
            if (currentStep) {
                steps.push(currentStep);
            }

            // Extract step name from next non-border line
            const nextLog = logs[index + 1];
            const stepName = nextLog?.message?.replace(/[║│]/g, '').trim() || `Step ${stepId}`;

            currentStep = {
                id: stepId++,
                name: stepName,
                status: 'running',
                duration: 'calculating...',
                logs: [],
                startTime: new Date(log.timestamp).toLocaleTimeString(),
            };
        } else if (currentStep) {
            // Add log to current step
            const timestamp = new Date(log.timestamp).toLocaleTimeString();
            currentStep.logs.push(`[${timestamp}] ${message}`);

            // Detect success/failure markers
            if (message.includes('✓') || message.toLowerCase().includes('success') || message.toLowerCase().includes('completed')) {
                currentStep.status = 'success';
                currentStep.endTime = timestamp;
            } else if (message.toLowerCase().includes('error') || message.toLowerCase().includes('failed') || log.level === 'error') {
                currentStep.status = 'failed';
                currentStep.endTime = timestamp;
            }
        } else {
            // No current step, create default one
            if (!currentStep) {
                currentStep = {
                    id: stepId++,
                    name: 'Deployment',
                    status: 'running',
                    duration: 'calculating...',
                    logs: [],
                    startTime: new Date(log.timestamp).toLocaleTimeString(),
                };
            }
            const timestamp = new Date(log.timestamp).toLocaleTimeString();
            currentStep.logs.push(`[${timestamp}] ${message}`);
        }
    });

    // Add last step
    if (currentStep) {
        // Set final status based on deployment status
        if (deploymentStatus === 'finished') {
            currentStep.status = 'success';
        } else if (deploymentStatus === 'failed' || deploymentStatus === 'cancelled-by-user') {
            currentStep.status = 'failed';
        }
        steps.push(currentStep);
    }

    // If no steps were created, create a single step with all logs
    if (steps.length === 0) {
        return [{
            id: 1,
            name: 'Deployment',
            status: deploymentStatus === 'finished' ? 'success' :
                    deploymentStatus === 'failed' ? 'failed' : 'running',
            duration: 'calculating...',
            logs: logs.map(l => `[${new Date(l.timestamp).toLocaleTimeString()}] ${l.message}`),
            startTime: logs[0] ? new Date(logs[0].timestamp).toLocaleTimeString() : undefined,
            endTime: logs.length > 0 ? new Date(logs[logs.length - 1].timestamp).toLocaleTimeString() : undefined,
        }];
    }

    return steps;
}

export default function BuildLogsView({ deploymentUuid, deployment }: Props) {
    // Use real log streaming hook
    const {
        logs,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        refresh,
        downloadLogs,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deploymentUuid,
        enableWebSocket: true,
        pollingInterval: 3000,
        maxLogEntries: 2000,
    });

    // Convert logs to build steps format
    const buildSteps = React.useMemo(
        () => convertLogsToSteps(logs, deployment?.status),
        [logs, deployment?.status]
    );

    // Find running step to expand by default
    const runningStepId = buildSteps.find(s => s.status === 'running')?.id || buildSteps[buildSteps.length - 1]?.id;
    const [expandedSteps, setExpandedSteps] = React.useState<Set<number>>(new Set([runningStepId || 1]));
    const [expandAll, setExpandAll] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [logLevel, setLogLevel] = React.useState<'all' | 'info' | 'warn' | 'error'>('all');
    const [isFullscreen, setIsFullscreen] = React.useState(false);

    // Update expanded step when running step changes
    React.useEffect(() => {
        if (runningStepId) {
            setExpandedSteps(prev => new Set([...prev, runningStepId]));
        }
    }, [runningStepId]);

    const toggleStep = (stepId: number) => {
        setExpandedSteps((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(stepId)) {
                newSet.delete(stepId);
            } else {
                newSet.add(stepId);
            }
            return newSet;
        });
    };

    const toggleExpandAll = () => {
        if (expandAll) {
            setExpandedSteps(new Set());
        } else {
            setExpandedSteps(new Set(buildSteps.map(s => s.id)));
        }
        setExpandAll(!expandAll);
    };

    const handleDownloadLogs = () => {
        // Use the hook's download function for raw logs
        downloadLogs();
    };

    const handleRefresh = async () => {
        await refresh();
    };

    const isBuilding = buildSteps.some((step) => step.status === 'running');
    const hasFailed = buildSteps.some((step) => step.status === 'failed');
    const totalDuration = buildSteps.reduce((acc, step) => {
        if (step.duration.includes('m')) {
            const [min, sec] = step.duration.split('m');
            return acc + parseInt(min) * 60 + parseFloat(sec);
        }
        return acc + parseFloat(step.duration);
    }, 0);

    const filterLogs = (logs: string[]) => {
        let filtered = logs;

        // Filter by log level
        if (logLevel !== 'all') {
            filtered = filtered.filter(log => {
                const lower = log.toLowerCase();
                if (logLevel === 'error') return lower.includes('error') || lower.includes('failed');
                if (logLevel === 'warn') return lower.includes('warn') || lower.includes('warning');
                if (logLevel === 'info') return !lower.includes('error') && !lower.includes('warn');
                return true;
            });
        }

        // Filter by search
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(log => log.toLowerCase().includes(query));
        }

        return filtered;
    };

    const content = (
        <div className="space-y-4">
            {/* AI Analysis Card - shown for failed deployments */}
            <AIAnalysisCard
                deploymentUuid={deploymentUuid}
                deploymentStatus={deployment?.status}
            />

            {/* Loading State */}
            {loading && logs.length === 0 && (
                <Card>
                    <CardContent className="flex items-center justify-center p-8">
                        <Loader2 className="mr-2 h-6 w-6 animate-spin text-primary" />
                        <span className="text-foreground-muted">Loading deployment logs...</span>
                    </CardContent>
                </Card>
            )}

            {/* Error State */}
            {error && (
                <Card className="border-danger/50 bg-danger/5">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2 text-danger">
                            <XCircle className="h-5 w-5" />
                            <span>Failed to load logs: {error.message}</span>
                        </div>
                        <Button variant="secondary" size="sm" onClick={handleRefresh} className="mt-2">
                            <RotateCw className="mr-2 h-4 w-4" />
                            Retry
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">Build Logs</h3>
                            <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-foreground-muted">
                                {isBuilding ? (
                                    <>
                                        <AlertCircle className="h-4 w-4 animate-pulse text-warning" />
                                        <span>Build in progress...</span>
                                    </>
                                ) : hasFailed ? (
                                    <>
                                        <XCircle className="h-4 w-4 text-danger" />
                                        <span>Build failed</span>
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle className="h-4 w-4 text-primary" />
                                        <span>Build completed successfully</span>
                                    </>
                                )}
                                <span>·</span>
                                <span>Total: {Math.floor(totalDuration / 60)}m {(totalDuration % 60).toFixed(1)}s</span>
                                <span>·</span>
                                {/* Streaming Status Indicator */}
                                {isConnected ? (
                                    <span className="flex items-center gap-1 text-primary">
                                        <Wifi className="h-3.5 w-3.5" />
                                        <span className="text-xs">Live</span>
                                    </span>
                                ) : isPolling ? (
                                    <span className="flex items-center gap-1 text-warning">
                                        <RotateCw className="h-3.5 w-3.5 animate-spin" />
                                        <span className="text-xs">Polling</span>
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-foreground-muted">
                                        <WifiOff className="h-3.5 w-3.5" />
                                        <span className="text-xs">Offline</span>
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Pause/Resume Streaming */}
                            {(isConnected || isPolling) && (
                                <Button variant="secondary" size="sm" onClick={toggleStreaming}>
                                    {isStreaming ? (
                                        <>
                                            <Clock className="mr-2 h-4 w-4" />
                                            Pause
                                        </>
                                    ) : (
                                        <>
                                            <RotateCw className="mr-2 h-4 w-4" />
                                            Resume
                                        </>
                                    )}
                                </Button>
                            )}
                            <Button variant="secondary" size="sm" onClick={toggleExpandAll}>
                                {expandAll ? (
                                    <>
                                        <Minimize2 className="mr-2 h-4 w-4" />
                                        Collapse All
                                    </>
                                ) : (
                                    <>
                                        <Maximize2 className="mr-2 h-4 w-4" />
                                        Expand All
                                    </>
                                )}
                            </Button>
                            <Button variant="secondary" size="sm" onClick={handleDownloadLogs}>
                                <Download className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                            {hasFailed && (
                                <Button size="sm">
                                    <RotateCw className="mr-2 h-4 w-4" />
                                    Retry Build
                                </Button>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                placeholder="Search within logs..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:border-primary focus:outline-none"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Filter className="h-4 w-4 text-foreground-muted" />
                            <select
                                value={logLevel}
                                onChange={(e) => setLogLevel(e.target.value as 'all' | 'info' | 'warn' | 'error')}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                            >
                                <option value="all">All Levels</option>
                                <option value="info">Info</option>
                                <option value="warn">Warnings</option>
                                <option value="error">Errors</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Build Steps */}
            <div className="space-y-2">
                {buildSteps.map((step) => {
                    const filteredLogs = filterLogs(step.logs);
                    const hasFilteredResults = filteredLogs.length > 0;
                    const showStep = !searchQuery && logLevel === 'all' || hasFilteredResults;

                    if (!showStep) return null;

                    return (
                        <Card key={step.id}>
                            <CardContent className="p-0">
                                {/* Step Header */}
                                <button
                                    onClick={() => toggleStep(step.id)}
                                    className="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-background-secondary"
                                >
                                    <div className="flex items-center gap-3">
                                        {getStatusIcon(step.status)}
                                        <div>
                                            <h4 className="font-medium text-foreground">{step.name}</h4>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                                {step.startTime && (
                                                    <>
                                                        <span>{step.startTime}</span>
                                                        {step.endTime && (
                                                            <>
                                                                <span>→</span>
                                                                <span>{step.endTime}</span>
                                                            </>
                                                        )}
                                                        <span>·</span>
                                                    </>
                                                )}
                                                <span>{step.duration}</span>
                                                {(searchQuery || logLevel !== 'all') && (
                                                    <>
                                                        <span>·</span>
                                                        <span>{filteredLogs.length} / {step.logs.length} lines</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`rounded px-2 py-1 text-xs font-medium ${
                                            step.status === 'success'
                                                ? 'bg-primary/10 text-primary'
                                                : step.status === 'failed'
                                                ? 'bg-danger/10 text-danger'
                                                : step.status === 'running'
                                                ? 'bg-warning/10 text-warning'
                                                : 'bg-foreground-subtle/10 text-foreground-muted'
                                        }`}>
                                            {step.status}
                                        </span>
                                        {expandedSteps.has(step.id) ? (
                                            <ChevronDown className="h-5 w-5 text-foreground-muted" />
                                        ) : (
                                            <ChevronRight className="h-5 w-5 text-foreground-muted" />
                                        )}
                                    </div>
                                </button>

                                {/* Step Logs */}
                                {expandedSteps.has(step.id) && (
                                    <div className="border-t border-border">
                                        <div className="bg-background-tertiary p-4">
                                            <div className="mb-3 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Terminal className="h-4 w-4 text-foreground-muted" />
                                                    <span className="text-xs font-medium text-foreground-muted">
                                                        Build Output
                                                    </span>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(filteredLogs.join('\n'));
                                                    }}
                                                >
                                                    <Copy className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                            <pre className="max-h-96 overflow-x-auto overflow-y-auto rounded-md bg-black p-4 font-mono text-xs text-green-400">
                                                {filteredLogs.length === 0 ? (
                                                    <div className="text-center text-foreground-muted">
                                                        No logs match your filters
                                                    </div>
                                                ) : (
                                                    filteredLogs.map((log, index) => (
                                                        <div key={index} className="leading-relaxed hover:bg-green-400/10">
                                                            {log}
                                                        </div>
                                                    ))
                                                )}
                                                {step.status === 'running' && (
                                                    <div className="mt-1 animate-pulse">▌</div>
                                                )}
                                            </pre>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );

    return (
        <AppLayout
            title="Build Logs"
            breadcrumbs={[
                { label: 'Deployments', href: '/deployments' },
                { label: deployment?.application_name || deploymentUuid, href: `/deployments/${deploymentUuid}` },
                { label: 'Build Logs' },
            ]}
        >
            <div className="mb-4 flex items-center justify-between">
                <Link href={`/deployments/${deploymentUuid}`}>
                    <Button variant="ghost" size="sm">
                        <ChevronLeft className="mr-1 h-4 w-4" />
                        Back to Deployment
                    </Button>
                </Link>
                {/* Clear logs button */}
                {logs.length > 0 && (
                    <Button variant="ghost" size="sm" onClick={clearLogs}>
                        Clear Logs
                    </Button>
                )}
            </div>

            {content}
        </AppLayout>
    );
}
