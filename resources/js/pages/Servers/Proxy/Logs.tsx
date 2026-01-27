import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, useConfirm } from '@/components/ui';
import { Terminal, Download, Trash2, Play, Pause, Search } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface LogEntry {
    timestamp: string;
    level: 'info' | 'warn' | 'error' | 'debug';
    message: string;
}

interface Props {
    server: ServerType;
    logs: LogEntry[];
}

export default function ProxyLogs({ server, logs: initialLogs }: Props) {
    const confirm = useConfirm();
    const [logs, setLogs] = useState<LogEntry[]>(initialLogs);

    // Update logs when Inertia props change (e.g., from reload)
    useEffect(() => {
        setLogs(initialLogs);
    }, [initialLogs]);
    const [isStreaming, setIsStreaming] = useState(true);
    const [selectedLevel, setSelectedLevel] = useState<string>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const logsEndRef = useRef<HTMLDivElement>(null);
    const logsContainerRef = useRef<HTMLDivElement>(null);
    const [autoScroll, setAutoScroll] = useState(true);

    // Auto-scroll to bottom when new logs arrive
    useEffect(() => {
        if (autoScroll && logsEndRef.current) {
            logsEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [logs, autoScroll]);

    // Detect manual scroll
    useEffect(() => {
        const container = logsContainerRef.current;
        if (!container) return;

        const handleScroll = () => {
            const { scrollTop, scrollHeight, clientHeight } = container;
            const isAtBottom = scrollHeight - scrollTop - clientHeight < 100;
            setAutoScroll(isAtBottom);
        };

        container.addEventListener('scroll', handleScroll);
        return () => container.removeEventListener('scroll', handleScroll);
    }, []);

    // Periodically reload logs when streaming
    useEffect(() => {
        if (!isStreaming) return;

        const interval = setInterval(() => {
            router.reload({ only: ['logs'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [isStreaming]);

    const handleDownloadLogs = () => {
        const logText = filteredLogs
            .map(log => `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`)
            .join('\n');

        const blob = new Blob([logText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `proxy-logs-${server.name}-${new Date().toISOString()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const handleClearLogs = async () => {
        const confirmed = await confirm({
            title: 'Clear Logs',
            description: 'Are you sure you want to clear all logs? This cannot be undone.',
            confirmText: 'Clear',
            variant: 'danger',
        });
        if (confirmed) {
            setLogs([]);
        }
    };

    const filteredLogs = logs.filter(log => {
        const levelMatch = selectedLevel === 'all' || log.level === selectedLevel;
        const searchMatch = searchQuery === '' ||
            log.message.toLowerCase().includes(searchQuery.toLowerCase());
        return levelMatch && searchMatch;
    });

    const getLevelColor = (level: LogEntry['level']) => {
        switch (level) {
            case 'error': return 'text-danger';
            case 'warn': return 'text-warning';
            case 'info': return 'text-info';
            case 'debug': return 'text-foreground-muted';
            default: return 'text-foreground';
        }
    };

    const getLevelBg = (level: LogEntry['level']) => {
        switch (level) {
            case 'error': return 'bg-danger/10';
            case 'warn': return 'bg-warning/10';
            case 'info': return 'bg-info/10';
            case 'debug': return 'bg-foreground-subtle/10';
            default: return 'bg-foreground/10';
        }
    };

    return (
        <AppLayout
            title={`Proxy Logs - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Proxy', href: `/servers/${server.uuid}/proxy` },
                { label: 'Logs' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <Terminal className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Proxy Logs</h1>
                        <p className="text-foreground-muted">
                            {isStreaming ? 'Live streaming' : 'Paused'} • {filteredLogs.length} entries
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setIsStreaming(!isStreaming)}
                    >
                        {isStreaming ? (
                            <>
                                <Pause className="mr-2 h-4 w-4" />
                                Pause
                            </>
                        ) : (
                            <>
                                <Play className="mr-2 h-4 w-4" />
                                Resume
                            </>
                        )}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleDownloadLogs}
                    >
                        <Download className="mr-2 h-4 w-4" />
                        Download
                    </Button>
                    <Button
                        variant="danger"
                        size="sm"
                        onClick={handleClearLogs}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Clear
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <Card className="mb-4">
                <CardContent className="p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">Filter:</span>
                            <div className="flex gap-1">
                                {['all', 'error', 'warn', 'info', 'debug'].map((level) => (
                                    <button
                                        key={level}
                                        onClick={() => setSelectedLevel(level)}
                                        className={`rounded px-3 py-1 text-xs font-medium transition-colors ${
                                            selectedLevel === level
                                                ? 'bg-primary text-white'
                                                : 'bg-background-subtle text-foreground-muted hover:bg-background-muted'
                                        }`}
                                    >
                                        {level.charAt(0).toUpperCase() + level.slice(1)}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                placeholder="Search logs..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-lg border border-border bg-background py-1.5 pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Logs Display */}
            <Card>
                <CardContent className="p-0">
                    <div
                        ref={logsContainerRef}
                        className="font-mono h-[600px] overflow-y-auto bg-background-deep p-4 text-sm"
                    >
                        {filteredLogs.length === 0 ? (
                            <div className="flex h-full items-center justify-center">
                                <div className="text-center">
                                    <Terminal className="mx-auto h-12 w-12 text-foreground-subtle" />
                                    <h3 className="mt-4 font-medium text-foreground">No logs available</h3>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        {searchQuery ? 'No logs match your search' : 'Logs will appear here once available'}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-1">
                                {filteredLogs.map((log, index) => (
                                    <div
                                        key={index}
                                        className={`flex items-start gap-3 rounded px-2 py-1 ${getLevelBg(log.level)}`}
                                    >
                                        <span className="text-foreground-muted">{log.timestamp}</span>
                                        <span className={`font-medium uppercase ${getLevelColor(log.level)}`}>
                                            [{log.level}]
                                        </span>
                                        <span className="flex-1 text-foreground">{log.message}</span>
                                    </div>
                                ))}
                                <div ref={logsEndRef} />
                            </div>
                        )}
                    </div>
                    {!autoScroll && (
                        <div className="border-t border-border bg-background p-2 text-center">
                            <button
                                onClick={() => {
                                    setAutoScroll(true);
                                    logsEndRef.current?.scrollIntoView({ behavior: 'smooth' });
                                }}
                                className="text-sm text-primary hover:underline"
                            >
                                Jump to bottom
                            </button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
