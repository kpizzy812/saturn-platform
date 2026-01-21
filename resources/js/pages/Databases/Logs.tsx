import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import { ArrowLeft, Download, Pause, Play, Trash2, Database, Terminal } from 'lucide-react';
import type { StandaloneDatabase } from '@/types';
import { useLogStream } from '@/hooks/useLogStream';

interface Props {
    database: StandaloneDatabase;
}

export default function DatabaseLogs({ database }: Props) {
    const {
        logs,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        downloadLogs,
    } = useLogStream({
        resourceType: 'database',
        resourceId: database.uuid,
        enableWebSocket: true,
        pollingInterval: 2000,
        maxLogEntries: 1000,
        autoScroll: true,
        filterLevel: 'all',
    });

    return (
        <AppLayout
            title={`${database.name} - Logs`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Logs' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {database.name}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Database Logs</h1>
                    <p className="text-foreground-muted">Real-time log streaming for {database.name}</p>
                </div>
                <div className="flex items-center gap-2">
                    <ConnectionStatus
                        isConnected={isConnected}
                        isPolling={isPolling}
                        isStreaming={isStreaming}
                    />
                    <Button variant="secondary" size="sm" onClick={toggleStreaming}>
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
                    <Button variant="secondary" size="sm" onClick={clearLogs}>
                        <Trash2 className="mr-2 h-4 w-4" />
                        Clear
                    </Button>
                    <Button variant="secondary" size="sm" onClick={downloadLogs}>
                        <Download className="mr-2 h-4 w-4" />
                        Download
                    </Button>
                </div>
            </div>

            {/* Error State */}
            {error && (
                <div className="mb-6 rounded-lg border border-red-500/50 bg-red-500/10 p-4">
                    <div className="flex items-start gap-3">
                        <Database className="h-5 w-5 flex-shrink-0 text-red-500" />
                        <div>
                            <h3 className="mb-1 font-semibold text-red-500">Error Loading Logs</h3>
                            <p className="text-sm text-red-400">{error.message}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Logs Container */}
            <Card>
                <CardContent className="p-0">
                    <div className="border-b border-border bg-background-secondary px-4 py-2">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4 text-sm text-foreground-muted">
                                <span>{logs.length} entries</span>
                                {isStreaming && (
                                    <span className="flex items-center gap-2">
                                        <span className="h-2 w-2 animate-pulse rounded-full bg-success"></span>
                                        Live
                                    </span>
                                )}
                            </div>
                            <span className="text-sm text-foreground-muted">
                                {isPolling ? 'Using polling fallback' : 'WebSocket connected'}
                            </span>
                        </div>
                    </div>

                    {/* Log Entries */}
                    <div
                        data-log-container
                        className="h-[600px] overflow-y-auto bg-background font-mono text-sm"
                    >
                        {loading && logs.length === 0 ? (
                            <div className="flex h-full items-center justify-center">
                                <div className="text-center">
                                    <Database className="mx-auto mb-4 h-12 w-12 animate-pulse text-foreground-muted" />
                                    <p className="text-foreground-muted">Loading logs...</p>
                                </div>
                            </div>
                        ) : logs.length === 0 ? (
                            <div className="flex h-full items-center justify-center">
                                <div className="text-center">
                                    <Terminal className="mx-auto mb-4 h-12 w-12 text-foreground-subtle" />
                                    <h3 className="mb-2 font-medium text-foreground">No logs yet</h3>
                                    <p className="text-foreground-muted">
                                        Logs will appear here when the database starts generating output
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="p-4">
                                {logs.map((log, index) => (
                                    <LogEntry
                                        key={log.id || index}
                                        timestamp={log.timestamp}
                                        message={log.message}
                                        level={log.level}
                                        source={log.source}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Footer Info */}
            <div className="mt-4 rounded-lg border border-border bg-background-secondary px-4 py-3">
                <div className="flex items-center gap-2 text-sm text-foreground-muted">
                    <Database className="h-4 w-4" />
                    <span>
                        Showing real-time logs from {database.name} ({database.database_type})
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}

interface ConnectionStatusProps {
    isConnected: boolean;
    isPolling: boolean;
    isStreaming: boolean;
}

function ConnectionStatus({ isConnected, isPolling, isStreaming }: ConnectionStatusProps) {
    if (isConnected) {
        return (
            <Badge variant="default">
                <span className="mr-1.5 h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                WebSocket Connected
            </Badge>
        );
    }

    if (isPolling) {
        return (
            <Badge variant="warning">
                <span className="mr-1.5 h-2 w-2 animate-pulse rounded-full bg-yellow-500"></span>
                Polling
            </Badge>
        );
    }

    if (!isStreaming) {
        return (
            <Badge variant="secondary">
                Paused
            </Badge>
        );
    }

    return (
        <Badge variant="secondary">
            Disconnected
        </Badge>
    );
}

interface LogEntryProps {
    timestamp: string;
    message: string;
    level?: 'info' | 'error' | 'warning' | 'debug';
    source?: string;
}

function LogEntry({ timestamp, message, level = 'info', source }: LogEntryProps) {
    const getLevelColor = () => {
        switch (level) {
            case 'error':
                return 'text-red-400';
            case 'warning':
                return 'text-yellow-400';
            case 'debug':
                return 'text-purple-400';
            default:
                return 'text-foreground-muted';
        }
    };

    const getLevelLabel = () => {
        return `[${level.toUpperCase().padEnd(5)}]`;
    };

    return (
        <div className="flex gap-2 border-b border-border/50 py-1 hover:bg-background-secondary/50">
            <span className="flex-shrink-0 text-foreground-subtle">
                {new Date(timestamp).toLocaleTimeString()}
            </span>
            <span className={`flex-shrink-0 ${getLevelColor()}`}>
                {getLevelLabel()}
            </span>
            {source && (
                <span className="flex-shrink-0 text-info">
                    [{source}]
                </span>
            )}
            <span className="flex-1 text-foreground">{message}</span>
        </div>
    );
}
