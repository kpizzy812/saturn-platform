import { useState, useRef, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import { Search, Download, Trash2, Pause, Play, ChevronDown, Activity, Box, Database, ArrowLeft } from 'lucide-react';
import { useLogStream } from '@/hooks/useLogStream';
import { Link } from '@inertiajs/react';
import type { Service, ServiceContainer } from '@/types';

interface Props {
    service: Service;
    containers?: ServiceContainer[];
}

type LogLevel = 'all' | 'info' | 'warn' | 'error';

export function LogsTab({ service, containers = [] }: Props) {
    const [filter, setFilter] = useState<LogLevel>('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [autoScroll, setAutoScroll] = useState(true);
    const [selectedContainer, setSelectedContainer] = useState<string>(
        containers.length > 0 ? containers[0].name : '',
    );
    const logsEndRef = useRef<HTMLDivElement>(null);

    // Real-time log streaming with container filter
    const {
        logs,
        isStreaming,
        isConnected: _isConnected,
        clearLogs,
        toggleStreaming,
        downloadLogs: handleDownloadLogs,
    } = useLogStream({
        resourceType: 'service',
        resourceId: service.uuid,
        container: selectedContainer || undefined,
        maxLogEntries: 500,
        autoScroll,
    });

    // Auto-scroll to bottom when new logs arrive
    useEffect(() => {
        if (autoScroll && isStreaming) {
            logsEndRef.current?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [logs, autoScroll, isStreaming]);

    const filteredLogs = logs.filter((log) => {
        const matchesFilter = filter === 'all' || log.level === filter;
        const matchesSearch = searchTerm === '' ||
            log.message.toLowerCase().includes(searchTerm.toLowerCase());
        return matchesFilter && matchesSearch;
    });

    const getLevelColor = (level?: string) => {
        switch (level) {
            case 'info':
                return 'text-info';
            case 'warn':
            case 'warning':
                return 'text-warning';
            case 'error':
                return 'text-danger';
            default:
                return 'text-foreground-muted';
        }
    };

    const getLevelBg = (level?: string) => {
        switch (level) {
            case 'info':
                return 'bg-info/10';
            case 'warn':
            case 'warning':
                return 'bg-warning/10';
            case 'error':
                return 'bg-danger/10';
            default:
                return 'bg-foreground-muted/10';
        }
    };

    return (
        <div className="space-y-4">
            {/* Container Selector */}
            {containers.length > 1 && (
                <div className="flex flex-wrap gap-2">
                    {containers.map((container) => (
                        <button
                            key={container.name}
                            onClick={() => setSelectedContainer(container.name)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${
                                selectedContainer === container.name
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-background text-foreground-muted hover:border-border/80 hover:bg-background-secondary'
                            }`}
                        >
                            {container.type === 'database' ? (
                                <Database className="h-4 w-4" />
                            ) : (
                                <Box className="h-4 w-4" />
                            )}
                            {container.label}
                            <Badge variant={container.type === 'database' ? 'warning' : 'default'} className="text-xs">
                                {container.type}
                            </Badge>
                        </button>
                    ))}
                </div>
            )}

            {/* Controls */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between gap-4">
                        {/* Search */}
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                placeholder="Search logs..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full rounded-md border border-border bg-background py-2 pl-10 pr-4 text-sm text-foreground placeholder-foreground-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            />
                        </div>

                        {/* Filter Dropdown */}
                        <div className="relative">
                            <select
                                value={filter}
                                onChange={(e) => setFilter(e.target.value as LogLevel)}
                                className="appearance-none rounded-md border border-border bg-background py-2 pl-3 pr-10 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            >
                                <option value="all">All Levels</option>
                                <option value="info">Info</option>
                                <option value="warn">Warning</option>
                                <option value="error">Error</option>
                            </select>
                            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        </div>

                        {/* Action Buttons */}
                        <div className="flex items-center gap-2">
                            {isStreaming && (
                                <Badge variant="success" className="flex items-center gap-1">
                                    <Activity className="h-3 w-3 animate-pulse" />
                                    Live
                                </Badge>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={toggleStreaming}
                            >
                                {isStreaming ? (
                                    <>
                                        <Pause className="mr-1 h-3 w-3" />
                                        Pause
                                    </>
                                ) : (
                                    <>
                                        <Play className="mr-1 h-3 w-3" />
                                        Resume
                                    </>
                                )}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleDownloadLogs}
                            >
                                <Download className="mr-1 h-3 w-3" />
                                Download
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearLogs}
                            >
                                <Trash2 className="mr-1 h-3 w-3" />
                                Clear
                            </Button>
                        </div>
                    </div>

                    {/* Auto-scroll toggle */}
                    <div className="mt-3 flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="auto-scroll"
                            checked={autoScroll}
                            onChange={(e) => setAutoScroll(e.target.checked)}
                            className="h-4 w-4 rounded border-border bg-background text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        />
                        <label
                            htmlFor="auto-scroll"
                            className="text-sm text-foreground-muted"
                        >
                            Auto-scroll to bottom
                        </label>
                    </div>
                </CardContent>
            </Card>

            {/* Logs Display */}
            <Card>
                <CardContent className="p-0">
                    <div className="h-[600px] overflow-auto rounded-lg bg-[#0d1117] font-mono text-sm">
                        {filteredLogs.length === 0 ? (
                            <div className="flex h-full items-center justify-center text-foreground-muted">
                                {searchTerm ? 'No logs match your search' : 'No logs available'}
                            </div>
                        ) : (
                            <div className="p-4" data-log-container>
                                {filteredLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="group flex items-start gap-3 border-b border-[#161b22] py-2 last:border-b-0 hover:bg-[#161b22]"
                                    >
                                        <span className="text-xs text-gray-500">
                                            {new Date(log.timestamp).toLocaleTimeString()}
                                        </span>
                                        <span
                                            className={`inline-flex min-w-[60px] items-center justify-center rounded px-2 py-0.5 text-xs font-medium uppercase ${getLevelBg(log.level)} ${getLevelColor(log.level)}`}
                                        >
                                            {log.level || 'info'}
                                        </span>
                                        <span className="flex-1 text-gray-300">{log.message}</span>
                                    </div>
                                ))}
                                <div ref={logsEndRef} />
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

// Standalone page wrapper for /services/{uuid}/logs route
export default function ServiceLogsPage({ service, containers = [] }: Props) {
    return (
        <AppLayout
            title={`${service.name} - Logs`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Services', href: '/services' },
                { label: service.name, href: `/services/${service.uuid}` },
                { label: 'Logs' },
            ]}
        >
            <Link
                href={`/services/${service.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {service.name}
            </Link>

            <LogsTab service={service} containers={containers} />
        </AppLayout>
    );
}
