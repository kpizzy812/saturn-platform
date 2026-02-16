import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input } from '@/components/ui';
import { Terminal, Download, Pause, Play, Trash2, Search, Filter, ChevronDown, Box } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useLogStream, LogEntry } from '@/hooks/useLogStream';
import type { Application } from '@/types';

interface Props {
    application: Application;
    projectUuid?: string;
    environmentUuid?: string;
}

export default function ApplicationLogs({ application, projectUuid, environmentUuid }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [filterLevel, setFilterLevel] = React.useState<'info' | 'error' | 'warning' | 'debug' | 'all'>('all');
    const [selectedContainer, setSelectedContainer] = React.useState<string | undefined>(undefined);
    const logsContainerRef = React.useRef<HTMLDivElement>(null);

    const {
        logs,
        availableContainers,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        downloadLogs,
    } = useLogStream({
        resourceType: 'application',
        resourceId: application.uuid,
        container: selectedContainer,
        filterLevel: filterLevel === 'all' ? undefined : filterLevel,
    });

    // Filter logs by search query
    const filteredLogs = React.useMemo(() => {
        if (!searchQuery) return logs;
        const query = searchQuery.toLowerCase();
        return logs.filter(log => log.message.toLowerCase().includes(query));
    }, [logs, searchQuery]);

    const getLevelColor = (level?: LogEntry['level']) => {
        switch (level) {
            case 'error':
                return 'text-red-400';
            case 'warning':
                return 'text-yellow-400';
            case 'debug':
                return 'text-foreground-muted';
            default:
                return 'text-blue-400';
        }
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Logs' },
    ];

    return (
        <AppLayout title="Application Logs" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Terminal className="h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-foreground">Application Logs</h1>
                            <span className={`flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs ${
                                isStreaming
                                    ? 'bg-green-500/20 text-green-400'
                                    : 'bg-gray-500/20 text-gray-400'
                            }`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${
                                    isStreaming ? 'bg-green-400 animate-pulse' : 'bg-gray-400'
                                }`} />
                                {isStreaming ? 'Live' : 'Paused'}
                            </span>
                            {isPolling && (
                                <span className="text-xs text-foreground-muted">(Polling mode)</span>
                            )}
                        </div>
                        <p className="text-foreground-muted">
                            Real-time logs from your application
                        </p>
                    </div>
                </div>
            </div>

            {/* Container Selector (shown when multiple containers exist) */}
            {availableContainers.length > 1 && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {availableContainers.map((name) => (
                        <button
                            key={name}
                            onClick={() => setSelectedContainer(
                                selectedContainer === name ? undefined : name
                            )}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${
                                selectedContainer === name
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-background text-foreground-muted hover:border-border/80 hover:bg-background-secondary'
                            }`}
                        >
                            <Box className="h-4 w-4" />
                            {name}
                        </button>
                    ))}
                </div>
            )}

            {/* Toolbar */}
            <Card className="mb-4">
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        {/* Search */}
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search logs..."
                                className="pl-10"
                            />
                        </div>

                        {/* Level Filter */}
                        <Dropdown>
                            <DropdownTrigger>
                                <Button variant="secondary" size="sm">
                                    <Filter className="mr-2 h-4 w-4" />
                                    {filterLevel === 'all' ? 'All Levels' : filterLevel.toUpperCase()}
                                    <ChevronDown className="ml-2 h-3.5 w-3.5" />
                                </Button>
                            </DropdownTrigger>
                            <DropdownContent>
                                <DropdownItem onClick={() => setFilterLevel('all')}>All Levels</DropdownItem>
                                <DropdownDivider />
                                <DropdownItem onClick={() => setFilterLevel('info')}>
                                    <span className="text-blue-400">INFO</span>
                                </DropdownItem>
                                <DropdownItem onClick={() => setFilterLevel('warning')}>
                                    <span className="text-yellow-400">WARNING</span>
                                </DropdownItem>
                                <DropdownItem onClick={() => setFilterLevel('error')}>
                                    <span className="text-red-400">ERROR</span>
                                </DropdownItem>
                                <DropdownItem onClick={() => setFilterLevel('debug')}>
                                    <span className="text-foreground-muted">DEBUG</span>
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>

                        {/* Actions */}
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={toggleStreaming}
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
                            size="sm"
                            variant="secondary"
                            onClick={clearLogs}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Clear
                        </Button>

                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={downloadLogs}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Logs Display */}
            <Card>
                <CardContent className="p-0">
                    <div
                        ref={logsContainerRef}
                        data-log-container
                        className="h-[600px] overflow-y-auto font-mono text-sm bg-background-secondary"
                    >
                        {loading && (
                            <div className="flex h-full items-center justify-center text-foreground-muted">
                                <Terminal className="mr-2 h-5 w-5 animate-pulse" />
                                Loading logs...
                            </div>
                        )}

                        {error && (
                            <div className="flex h-full flex-col items-center justify-center text-error">
                                <Terminal className="mb-2 h-8 w-8" />
                                <p>Failed to load logs: {error.message}</p>
                            </div>
                        )}

                        {!loading && !error && filteredLogs.length === 0 && (
                            <div className="flex h-full flex-col items-center justify-center text-foreground-muted">
                                <Terminal className="mb-4 h-12 w-12 opacity-50" />
                                <p>No logs found</p>
                                {searchQuery && <p className="mt-1 text-sm">Try adjusting your search or filters</p>}
                            </div>
                        )}

                        {!loading && !error && filteredLogs.length > 0 && (
                            <div className="p-4">
                                {filteredLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="group flex items-start gap-3 py-1.5 hover:bg-white/5 rounded"
                                    >
                                        <span className="shrink-0 text-foreground-muted text-xs">
                                            {log.timestamp}
                                        </span>
                                        <span className={`shrink-0 w-16 text-xs font-semibold uppercase ${getLevelColor(log.level)}`}>
                                            [{log.level || 'info'}]
                                        </span>
                                        <span className="text-foreground break-all">{log.message}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Footer Info */}
            <div className="mt-4 flex items-center justify-between text-xs text-foreground-muted">
                <span>{filteredLogs.length} log entries</span>
                <span>
                    {isConnected ? 'Connected via WebSocket' : isPolling ? 'Using polling fallback' : 'Disconnected'}
                </span>
            </div>
        </AppLayout>
    );
}
