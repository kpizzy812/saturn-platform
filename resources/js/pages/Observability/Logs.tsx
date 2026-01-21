import { AppLayout } from '@/components/layout';
import { useState, useEffect } from 'react';
import { Card, CardContent, Badge, Button, Input } from '@/components/ui';
import {
    Download,
    Share2,
    Play,
    Pause,
    Search,
    Filter,
    RefreshCw,
    Terminal,
} from 'lucide-react';

type LogLevel = 'info' | 'warn' | 'error' | 'debug';

interface LogEntry {
    id: string;
    timestamp: string;
    level: LogLevel;
    service: string;
    message: string;
    metadata?: Record<string, any>;
}

const services = ['All Services', 'API Gateway', 'Auth Service', 'Database', 'Worker Queue', 'Cache Layer'];
const logLevels: LogLevel[] = ['info', 'warn', 'error', 'debug'];

// Mock log generation
const generateLog = (id: number): LogEntry => {
    const levels: LogLevel[] = ['info', 'warn', 'error', 'debug'];
    const serviceNames = ['API Gateway', 'Auth Service', 'Database', 'Worker Queue', 'Cache Layer'];
    const messages = [
        'Request processed successfully',
        'Database connection established',
        'Cache miss for key: user:123',
        'Rate limit exceeded for IP: 192.168.1.1',
        'Authentication token validated',
        'Job processing completed',
        'Failed to connect to external service',
        'Memory usage at 75%',
        'New user registered: user@example.com',
        'Payment processed successfully',
        'Session expired for user: john@example.com',
        'Deployment started for version 2.5.0',
    ];

    const level = levels[Math.floor(Math.random() * levels.length)];
    const service = serviceNames[Math.floor(Math.random() * serviceNames.length)];
    const message = messages[Math.floor(Math.random() * messages.length)];

    return {
        id: `log-${id}`,
        timestamp: new Date().toISOString(),
        level,
        service,
        message,
        metadata: level === 'error' ? { stack: 'Error stack trace...', code: 500 } : undefined,
    };
};

const initialLogs: LogEntry[] = Array.from({ length: 20 }, (_, i) => generateLog(i));

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

function LogEntryRow({ log, expanded, onToggle }: { log: LogEntry; expanded: boolean; onToggle: () => void }) {
    const timestamp = new Date(log.timestamp).toLocaleTimeString();

    return (
        <div
            className="group cursor-pointer border-b border-border/50 px-4 py-3 transition-colors hover:bg-background-tertiary/50"
            onClick={onToggle}
        >
            <div className="flex items-start gap-3">
                <span className="font-mono text-xs text-foreground-muted">{timestamp}</span>
                <LogLevelBadge level={log.level} />
                <span className="text-sm font-medium text-foreground-muted">{log.service}</span>
                <span className="flex-1 text-sm text-foreground">{log.message}</span>
            </div>
            {expanded && log.metadata && (
                <div className="mt-2 rounded-md bg-background-tertiary p-3">
                    <pre className="text-xs text-foreground-muted">
                        {JSON.stringify(log.metadata, null, 2)}
                    </pre>
                </div>
            )}
        </div>
    );
}

export default function ObservabilityLogs() {
    const [logs, setLogs] = useState<LogEntry[]>(initialLogs);
    const [isStreaming, setIsStreaming] = useState(false);
    const [selectedService, setSelectedService] = useState('All Services');
    const [selectedLevel, setSelectedLevel] = useState<LogLevel | 'all'>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [expandedLogs, setExpandedLogs] = useState<Set<string>>(new Set());

    useEffect(() => {
        if (!isStreaming) return;

        const interval = setInterval(() => {
            const newLog = generateLog(Date.now());
            setLogs((prev) => [newLog, ...prev.slice(0, 99)]);
        }, 2000);

        return () => clearInterval(interval);
    }, [isStreaming]);

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
        const serviceMatch = selectedService === 'All Services' || log.service === selectedService;
        const levelMatch = selectedLevel === 'all' || log.level === selectedLevel;
        const searchMatch =
            !searchQuery ||
            log.message.toLowerCase().includes(searchQuery.toLowerCase()) ||
            log.service.toLowerCase().includes(searchQuery.toLowerCase());

        return serviceMatch && levelMatch && searchMatch;
    });

    const handleDownload = () => {
        console.log('Downloading logs...');
    };

    const handleShare = () => {
        console.log('Sharing logs...');
    };

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
                        <p className="text-foreground-muted">Centralized log streaming and search</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant={isStreaming ? 'danger' : 'secondary'}
                            size="sm"
                            onClick={() => setIsStreaming(!isStreaming)}
                        >
                            {isStreaming ? (
                                <>
                                    <Pause className="mr-2 h-4 w-4" />
                                    Pause Stream
                                </>
                            ) : (
                                <>
                                    <Play className="mr-2 h-4 w-4" />
                                    Start Stream
                                </>
                            )}
                        </Button>
                        <Button variant="secondary" size="sm" onClick={handleDownload}>
                            <Download className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        <Button variant="secondary" size="sm" onClick={handleShare}>
                            <Share2 className="mr-2 h-4 w-4" />
                            Share
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <input
                                    type="text"
                                    placeholder="Search logs..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                            <select
                                value={selectedService}
                                onChange={(e) => setSelectedService(e.target.value)}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                {services.map((service) => (
                                    <option key={service} value={service}>
                                        {service}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={selectedLevel}
                                onChange={(e) => setSelectedLevel(e.target.value as LogLevel | 'all')}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="all">All Levels</option>
                                {logLevels.map((level) => (
                                    <option key={level} value={level}>
                                        {level.toUpperCase()}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardContent>
                </Card>

                {/* Status Bar */}
                <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-2">
                        <div className={`h-2 w-2 rounded-full ${isStreaming ? 'animate-pulse bg-emerald-500' : 'bg-zinc-500'}`} />
                        <span className="text-foreground-muted">
                            {isStreaming ? 'Live streaming' : 'Stream paused'}
                        </span>
                    </div>
                    <div className="text-foreground-muted">
                        Showing {filteredLogs.length} of {logs.length} logs
                    </div>
                </div>

                {/* Log Stream */}
                <Card className="overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-border bg-background-tertiary/50 px-4 py-2">
                        <Terminal className="h-4 w-4 text-foreground-muted" />
                        <span className="text-sm font-medium text-foreground">Log Stream</span>
                    </div>
                    <div className="max-h-[600px] overflow-y-auto">
                        {filteredLogs.length === 0 ? (
                            <div className="py-12 text-center">
                                <Terminal className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No logs found</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Try adjusting your filters or starting the log stream
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
            </div>
        </AppLayout>
    );
}
