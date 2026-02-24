import { AppLayout } from '@/components/layout';
import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, Badge, Button, Select, Spinner } from '@/components/ui';
import {
    Play,
    Pause,
    Search,
    Terminal,
    Copy,
    Check,
    RefreshCw,
    AlertCircle,
    Server,
} from 'lucide-react';
import { useToast } from '@/components/ui/Toast';

type LogLevel = 'info' | 'warn' | 'error' | 'debug';

interface Resource {
    uuid: string;
    name: string;
    type: 'application' | 'service' | 'database';
    status: string;
}

interface LogEntry {
    id: string;
    timestamp: string;
    level: LogLevel;
    message: string;
}

interface Props {
    resources?: Resource[];
}

function parseLogLevel(message: string): LogLevel {
    const lowerMsg = message.toLowerCase();
    if (lowerMsg.includes('error') || lowerMsg.includes('fatal') || lowerMsg.includes('exception')) {
        return 'error';
    }
    if (lowerMsg.includes('warn') || lowerMsg.includes('warning')) {
        return 'warn';
    }
    if (lowerMsg.includes('debug')) {
        return 'debug';
    }
    return 'info';
}

function parseTimestamp(line: string): { timestamp: string; message: string } {
    // Try to extract ISO timestamp from beginning of line
    const isoMatch = line.match(/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)\s*/);
    if (isoMatch) {
        return {
            timestamp: isoMatch[1],
            message: line.slice(isoMatch[0].length),
        };
    }
    // Try common log format timestamps
    const commonMatch = line.match(/^\[?(\d{2}[/-]\w{3}[/-]\d{4}[:\s]\d{2}:\d{2}:\d{2})\]?\s*/);
    if (commonMatch) {
        return {
            timestamp: commonMatch[1],
            message: line.slice(commonMatch[0].length),
        };
    }
    return {
        timestamp: new Date().toISOString(),
        message: line,
    };
}

function LogLevelBadge({ level }: { level: LogLevel }) {
    const config = {
        info: { variant: 'info' as const, label: 'INFO' },
        warn: { variant: 'warning' as const, label: 'WARN' },
        error: { variant: 'danger' as const, label: 'ERROR' },
        debug: { variant: 'default' as const, label: 'DEBUG' },
    };

    const { variant, label } = config[level];
    return <Badge variant={variant}>{label}</Badge>;
}

function LogEntryRow({ log, expanded: _expanded, onToggle }: { log: LogEntry; expanded: boolean; onToggle: () => void }) {
    const timestamp = new Date(log.timestamp).toLocaleTimeString();

    return (
        <div
            className="group cursor-pointer border-b border-border/50 px-4 py-2 transition-colors hover:bg-background-tertiary/50"
            onClick={onToggle}
        >
            <div className="flex items-start gap-3">
                <span className="shrink-0 font-mono text-xs text-foreground-muted">{timestamp}</span>
                <LogLevelBadge level={log.level} />
                <span className="flex-1 break-all font-mono text-sm text-foreground">{log.message}</span>
            </div>
        </div>
    );
}

export default function ObservabilityLogs({ resources = [] }: Props) {
    const { toast } = useToast();
    const [logs, setLogs] = useState<LogEntry[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isStreaming, setIsStreaming] = useState(false);
    const [selectedResource, setSelectedResource] = useState<string>('');
    const [selectedLevel, setSelectedLevel] = useState<LogLevel | 'all'>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [expandedLogs, setExpandedLogs] = useState<Set<string>>(new Set());
    const [isCopied, setIsCopied] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lines, setLines] = useState(100);

    const selectedResourceData = resources.find(r => r.uuid === selectedResource);

    const fetchLogs = useCallback(async () => {
        if (!selectedResource || !selectedResourceData) return;

        setIsLoading(true);
        setError(null);

        try {
            const endpoint = `/api/v1/${selectedResourceData.type}s/${selectedResource}/logs?lines=${lines}`;
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || `Failed to fetch logs (${response.status})`);
            }

            const data = await response.json();

            // Normalize logs from different API response formats
            let rawLines: string[] = [];
            if (selectedResourceData.type === 'service') {
                // Services API returns { containers: { name: { type, name, status, logs: "string"|null } } }
                const containers = (data.containers || {}) as Record<string, { logs?: string | null; name?: string }>;
                for (const [containerName, containerData] of Object.entries(containers)) {
                    if (containerData.logs) {
                        const lines = containerData.logs.split('\n').filter(Boolean);
                        rawLines.push(...lines.map(line => `[${containerData.name || containerName}] ${line}`));
                    }
                }
            } else {
                // Applications/Databases API returns { logs: "string" }
                const logsValue = data.logs || '';
                if (typeof logsValue === 'string') {
                    rawLines = logsValue.split('\n').filter(Boolean);
                } else if (Array.isArray(logsValue)) {
                    rawLines = logsValue;
                }
            }

            // Parse logs into structured format
            const parsedLogs: LogEntry[] = rawLines.map((line, index) => {
                const { timestamp, message } = parseTimestamp(line);
                return {
                    id: `log-${index}-${Date.now()}`,
                    timestamp,
                    level: parseLogLevel(message),
                    message: message || line,
                };
            });

            setLogs(parsedLogs);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to fetch logs';
            setError(message);
            toast({
                title: 'Error',
                description: message,
                variant: 'error',
            });
        } finally {
            setIsLoading(false);
        }
    }, [selectedResource, selectedResourceData, lines, toast]);

    // Fetch logs when resource changes
    useEffect(() => {
        if (selectedResource) {
            fetchLogs();
        } else {
            setLogs([]);
        }
    }, [selectedResource, fetchLogs]);

    // Auto-refresh when streaming
    useEffect(() => {
        if (!isStreaming || !selectedResource) return;

        const interval = setInterval(() => {
            fetchLogs();
        }, 5000); // Refresh every 5 seconds

        return () => clearInterval(interval);
    }, [isStreaming, selectedResource, fetchLogs]);

    const toggleExpanded = (logId: string) => {
        setExpandedLogs((prev) => {
            const next = new Set(prev);
            if (next.has(logId)) {
                next.delete(logId);
            } else {
                next.add(logId);
            }
            return next;
        });
    };

    const filteredLogs = logs.filter((log) => {
        const levelMatch = selectedLevel === 'all' || log.level === selectedLevel;
        const searchMatch = !searchQuery || log.message.toLowerCase().includes(searchQuery.toLowerCase());
        return levelMatch && searchMatch;
    });

    const handleDownload = useCallback((format: 'json' | 'txt' = 'txt') => {
        if (filteredLogs.length === 0) {
            toast({
                title: 'No Logs',
                description: 'No logs available to download',
                variant: 'error',
            });
            return;
        }

        const resourceName = selectedResourceData?.name || 'logs';
        const filename = `${resourceName.replace(/\s+/g, '-')}-${new Date().toISOString().split('T')[0]}.${format}`;

        let content: string;
        let mimeType: string;

        if (format === 'json') {
            content = JSON.stringify({ logs: filteredLogs }, null, 2);
            mimeType = 'application/json';
        } else {
            content = filteredLogs
                .map((log) => `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`)
                .join('\n');
            mimeType = 'text/plain';
        }

        const blob = new Blob([content], { type: `${mimeType};charset=utf-8` });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        toast({
            title: 'Download Complete',
            description: `Downloaded ${filteredLogs.length} log entries to ${filename}`,
        });
    }, [filteredLogs, selectedResourceData, toast]);

    const handleCopy = useCallback(async () => {
        if (filteredLogs.length === 0) {
            toast({
                title: 'No Logs',
                description: 'No logs available to copy',
                variant: 'error',
            });
            return;
        }

        const shareText = filteredLogs
            .slice(0, 100)
            .map((log) => `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`)
            .join('\n');

        try {
            await navigator.clipboard.writeText(shareText);
            setIsCopied(true);
            toast({
                title: 'Copied to Clipboard',
                description: `${Math.min(filteredLogs.length, 100)} log entries copied`,
            });

            setTimeout(() => setIsCopied(false), 2000);
        } catch (err) {
            toast({
                title: 'Copy Failed',
                description: 'Failed to copy logs to clipboard',
                variant: 'error',
            });
        }
    }, [filteredLogs, toast]);

    const logLevels: LogLevel[] = ['info', 'warn', 'error', 'debug'];

    return (
        <AppLayout
            title="Logs"
            breadcrumbs={[{ label: 'Observability', href: '/observability' }, { label: 'Logs' }]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Logs Viewer</h1>
                        <p className="text-foreground-muted">View container logs from your resources</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={fetchLogs}
                            disabled={!selectedResource || isLoading}
                        >
                            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        </Button>
                        <Button
                            variant={isStreaming ? 'danger' : 'secondary'}
                            size="sm"
                            onClick={() => setIsStreaming(!isStreaming)}
                            disabled={!selectedResource}
                        >
                            {isStreaming ? (
                                <>
                                    <Pause className="mr-2 h-4 w-4" />
                                    Stop
                                </>
                            ) : (
                                <>
                                    <Play className="mr-2 h-4 w-4" />
                                    Auto-refresh
                                </>
                            )}
                        </Button>
                        <Select
                            value=""
                            onChange={(e) => {
                                if (e.target.value) {
                                    handleDownload(e.target.value as 'json' | 'txt');
                                }
                            }}
                            disabled={filteredLogs.length === 0}
                            className="w-[140px]"
                        >
                            <option value="" disabled>Download</option>
                            <option value="txt">Download TXT</option>
                            <option value="json">Download JSON</option>
                        </Select>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleCopy}
                            disabled={filteredLogs.length === 0}
                        >
                            {isCopied ? (
                                <>
                                    <Check className="mr-2 h-4 w-4" />
                                    Copied!
                                </>
                            ) : (
                                <>
                                    <Copy className="mr-2 h-4 w-4" />
                                    Copy
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Resource Selection & Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">Resource</label>
                                <Select
                                    value={selectedResource}
                                    onChange={(e) => setSelectedResource(e.target.value)}
                                >
                                    <option value="">Select a resource...</option>
                                    {resources.length > 0 ? (
                                        <>
                                            {resources.filter(r => r.type === 'application').length > 0 && (
                                                <optgroup label="Applications">
                                                    {resources.filter(r => r.type === 'application').map((r) => (
                                                        <option key={r.uuid} value={r.uuid}>
                                                            {r.name}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            )}
                                            {resources.filter(r => r.type === 'service').length > 0 && (
                                                <optgroup label="Services">
                                                    {resources.filter(r => r.type === 'service').map((r) => (
                                                        <option key={r.uuid} value={r.uuid}>
                                                            {r.name}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            )}
                                            {resources.filter(r => r.type === 'database').length > 0 && (
                                                <optgroup label="Databases">
                                                    {resources.filter(r => r.type === 'database').map((r) => (
                                                        <option key={r.uuid} value={r.uuid}>
                                                            {r.name}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            )}
                                        </>
                                    ) : (
                                        <option value="" disabled>No resources found</option>
                                    )}
                                </Select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">Log Level</label>
                                <Select
                                    value={selectedLevel}
                                    onChange={(e) => setSelectedLevel(e.target.value as LogLevel | 'all')}
                                >
                                    <option value="all">All Levels</option>
                                    {logLevels.map((level) => (
                                        <option key={level} value={level}>
                                            {level.toUpperCase()}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">Lines</label>
                                <Select
                                    value={lines.toString()}
                                    onChange={(e) => setLines(parseInt(e.target.value))}
                                >
                                    <option value="50">50 lines</option>
                                    <option value="100">100 lines</option>
                                    <option value="500">500 lines</option>
                                    <option value="1000">1000 lines</option>
                                </Select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <input
                                        type="text"
                                        placeholder="Filter logs..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Status Bar */}
                {selectedResource && (
                    <div className="flex items-center gap-4 text-sm">
                        <div className="flex items-center gap-2">
                            <div className={`h-2 w-2 rounded-full ${isStreaming ? 'animate-pulse bg-emerald-500' : 'bg-zinc-500'}`} />
                            <span className="text-foreground-muted">
                                {isStreaming ? 'Auto-refreshing every 5s' : 'Manual refresh'}
                            </span>
                        </div>
                        <div className="text-foreground-muted">
                            Showing {filteredLogs.length} of {logs.length} logs
                        </div>
                    </div>
                )}

                {/* Log Stream */}
                <Card className="overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-border bg-background-tertiary/50 px-4 py-2">
                        <Terminal className="h-4 w-4 text-foreground-muted" />
                        <span className="text-sm font-medium text-foreground">
                            {selectedResourceData ? `Logs: ${selectedResourceData.name}` : 'Log Stream'}
                        </span>
                    </div>
                    <div className="max-h-[600px] overflow-y-auto">
                        {!selectedResource ? (
                            <div className="py-12 text-center">
                                <Server className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">Select a Resource</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Choose an application, service, or database to view its logs
                                </p>
                            </div>
                        ) : isLoading ? (
                            <div className="flex items-center justify-center py-12">
                                <Spinner className="h-8 w-8" />
                            </div>
                        ) : error ? (
                            <div className="py-12 text-center">
                                <AlertCircle className="mx-auto h-12 w-12 text-danger" />
                                <h3 className="mt-4 text-lg font-medium text-danger">Failed to Load Logs</h3>
                                <p className="mt-2 text-sm text-foreground-muted">{error}</p>
                                <Button variant="secondary" onClick={fetchLogs} className="mt-4">
                                    Try Again
                                </Button>
                            </div>
                        ) : filteredLogs.length === 0 ? (
                            <div className="py-12 text-center">
                                <Terminal className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No logs found</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    {logs.length > 0
                                        ? 'Try adjusting your filters'
                                        : 'The container may not be running or has no logs yet'}
                                </p>
                            </div>
                        ) : (
                            filteredLogs.map((log) => (
                                <LogEntryRow
                                    key={log.id}
                                    log={log}
                                    expanded={expandedLogs.has(log.id)}
                                    onToggle={() => toggleExpanded(log.id)}
                                />
                            ))
                        )}
                    </div>
                </Card>

                {/* Log Statistics */}
                {filteredLogs.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-4">
                        {logLevels.map((level) => {
                            const count = filteredLogs.filter((log) => log.level === level).length;
                            return (
                                <Card key={level}>
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-foreground-muted">
                                                    {level.toUpperCase()}
                                                </p>
                                                <p className="text-2xl font-semibold text-foreground">{count}</p>
                                            </div>
                                            <LogLevelBadge level={level} />
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
