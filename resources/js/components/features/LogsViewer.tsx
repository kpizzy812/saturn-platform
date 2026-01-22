import { useState, useRef, useEffect, useMemo } from 'react';
import { X, Search, Download, Filter, RefreshCw, Pause, Play, ChevronDown, Terminal, AlertCircle, Info, AlertTriangle, Wifi, WifiOff } from 'lucide-react';
import { Button } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useLogStream, type LogEntry } from '@/hooks/useLogStream';

interface LogsViewerProps {
    isOpen: boolean;
    onClose: () => void;
    serviceName: string;
    serviceUuid?: string;
    serviceType?: 'application' | 'deployment' | 'database' | 'service';
    deploymentId?: string;
}

export function LogsViewer({ isOpen, onClose, serviceName, serviceUuid, serviceType = 'application', deploymentId }: LogsViewerProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedLevel, setSelectedLevel] = useState<string>('all');
    const [selectedDeployment, setSelectedDeployment] = useState(deploymentId || 'latest');
    const logsContainerRef = useRef<HTMLDivElement>(null);

    // Use the real log stream hook when serviceUuid is provided
    const {
        logs: streamLogs,
        isStreaming,
        isConnected,
        isPolling,
        loading,
        error,
        clearLogs,
        toggleStreaming,
        refresh,
        downloadLogs: downloadStreamLogs,
    } = useLogStream({
        resourceType: serviceType,
        resourceId: serviceUuid || 'demo',
        enableWebSocket: !!serviceUuid,
        pollingInterval: serviceUuid ? 2000 : 0, // Disable polling if no UUID
        maxLogEntries: 500,
        autoScroll: true,
        filterLevel: selectedLevel === 'all' ? 'all' : selectedLevel as 'info' | 'error' | 'warning' | 'debug',
    });

    // Auto-scroll to bottom when new logs arrive
    useEffect(() => {
        if (isStreaming && logsContainerRef.current) {
            logsContainerRef.current.scrollTop = logsContainerRef.current.scrollHeight;
        }
    }, [streamLogs, isStreaming]);

    // Map logs from hook format to display format
    const displayLogs = useMemo(() => {
        return streamLogs.map(log => ({
            ...log,
            level: (log.level === 'warning' ? 'warn' : log.level) as 'info' | 'warn' | 'error' | 'debug',
        }));
    }, [streamLogs]);

    // Filter logs by search query and level
    const filteredLogs = useMemo(() => {
        return displayLogs.filter(log => {
            const matchesSearch = searchQuery === '' ||
                log.message.toLowerCase().includes(searchQuery.toLowerCase());
            const matchesLevel = selectedLevel === 'all' || log.level === selectedLevel;
            return matchesSearch && matchesLevel;
        });
    }, [displayLogs, searchQuery, selectedLevel]);

    const getLevelColor = (level: string) => {
        switch (level) {
            case 'error': return 'text-red-400';
            case 'warn': return 'text-yellow-400';
            case 'debug': return 'text-foreground-subtle';
            default: return 'text-blue-400';
        }
    };

    const getLevelIcon = (level: string) => {
        switch (level) {
            case 'error': return <AlertCircle className="h-3.5 w-3.5" />;
            case 'warn': return <AlertTriangle className="h-3.5 w-3.5" />;
            default: return <Info className="h-3.5 w-3.5" />;
        }
    };

    const handleDownload = () => {
        if (serviceUuid) {
            downloadStreamLogs();
        } else {
            // Fallback for demo mode
            const content = filteredLogs.map(log =>
                `[${log.timestamp}] [${(log.level || 'info').toUpperCase()}] ${log.message}`
            ).join('\n');

            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${serviceName}-logs-${new Date().toISOString().slice(0, 10)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
    };

    if (!isOpen) return null;

    const connectionStatus = isConnected ? 'WebSocket' : isPolling ? 'Polling' : 'Disconnected';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/70" onClick={onClose} />

            {/* Modal */}
            <div className="relative z-10 flex h-[85vh] w-[90vw] max-w-6xl flex-col rounded-xl border border-border backdrop-blur-xl bg-background/95 shadow-2xl animate-fade-in">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <div className="flex items-center gap-3">
                        <Terminal className="h-5 w-5 text-foreground-muted" />
                        <h2 className="text-lg font-semibold text-foreground">{serviceName} Logs</h2>
                        <span className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${isStreaming ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${isStreaming ? 'bg-green-400 animate-pulse' : 'bg-gray-400'}`} />
                            {isStreaming ? 'Live' : 'Paused'}
                        </span>
                        {/* Connection status indicator */}
                        {serviceUuid && (
                            <span className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${isConnected ? 'bg-blue-500/20 text-blue-400' : 'bg-orange-500/20 text-orange-400'}`}>
                                {isConnected ? <Wifi className="h-3 w-3" /> : <WifiOff className="h-3 w-3" />}
                                {connectionStatus}
                            </span>
                        )}
                        {!serviceUuid && (
                            <span className="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs bg-yellow-500/20 text-yellow-400">
                                Demo Mode
                            </span>
                        )}
                    </div>
                    <button onClick={onClose} className="rounded p-1 text-foreground-muted hover:bg-background-secondary hover:text-foreground">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Toolbar */}
                <div className="flex items-center gap-3 border-b border-border px-4 py-2">
                    {/* Deployment Selector */}
                    <Dropdown>
                        <DropdownTrigger>
                            <button className="flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-sm text-foreground hover:bg-background-secondary">
                                Deployment: {selectedDeployment === 'latest' ? 'Latest' : selectedDeployment.slice(0, 7)}
                                <ChevronDown className="h-3.5 w-3.5" />
                            </button>
                        </DropdownTrigger>
                        <DropdownContent>
                            <DropdownItem onClick={() => setSelectedDeployment('latest')}>Latest (Active)</DropdownItem>
                            <DropdownItem onClick={() => setSelectedDeployment('a1b2c3d')}>a1b2c3d (2 hours ago)</DropdownItem>
                            <DropdownItem onClick={() => setSelectedDeployment('e4f5g6h')}>e4f5g6h (1 day ago)</DropdownItem>
                        </DropdownContent>
                    </Dropdown>

                    {/* Level Filter */}
                    <Dropdown>
                        <DropdownTrigger>
                            <button className="flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-sm text-foreground hover:bg-background-secondary">
                                <Filter className="h-3.5 w-3.5" />
                                {selectedLevel === 'all' ? 'All Levels' : selectedLevel.toUpperCase()}
                                <ChevronDown className="h-3.5 w-3.5" />
                            </button>
                        </DropdownTrigger>
                        <DropdownContent>
                            <DropdownItem onClick={() => setSelectedLevel('all')}>All Levels</DropdownItem>
                            <DropdownDivider />
                            <DropdownItem onClick={() => setSelectedLevel('info')}>
                                <span className="text-blue-400">INFO</span>
                            </DropdownItem>
                            <DropdownItem onClick={() => setSelectedLevel('warn')}>
                                <span className="text-yellow-400">WARN</span>
                            </DropdownItem>
                            <DropdownItem onClick={() => setSelectedLevel('error')}>
                                <span className="text-red-400">ERROR</span>
                            </DropdownItem>
                            <DropdownItem onClick={() => setSelectedLevel('debug')}>
                                <span className="text-foreground-subtle">DEBUG</span>
                            </DropdownItem>
                        </DropdownContent>
                    </Dropdown>

                    {/* Search */}
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search logs..."
                            className="w-full rounded-md border border-border bg-background py-1.5 pl-9 pr-4 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                        />
                    </div>

                    {/* Actions */}
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={toggleStreaming}
                    >
                        {isStreaming ? <Pause className="mr-1 h-3.5 w-3.5" /> : <Play className="mr-1 h-3.5 w-3.5" />}
                        {isStreaming ? 'Pause' : 'Resume'}
                    </Button>

                    <Button size="sm" variant="secondary" onClick={() => { clearLogs(); refresh(); }} disabled={loading}>
                        <RefreshCw className={`mr-1 h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>

                    <Button size="sm" variant="secondary" onClick={handleDownload}>
                        <Download className="mr-1 h-3.5 w-3.5" />
                        Export
                    </Button>
                </div>

                {/* Error Banner */}
                {error && (
                    <div className="flex items-center gap-2 border-b border-red-500/20 bg-red-500/10 px-4 py-2 text-sm text-red-400">
                        <AlertCircle className="h-4 w-4" />
                        <span>{error.message}</span>
                        <Button size="sm" variant="ghost" onClick={refresh} className="ml-auto">
                            Retry
                        </Button>
                    </div>
                )}

                {/* Logs */}
                <div
                    ref={logsContainerRef}
                    data-log-container
                    className="flex-1 overflow-y-auto font-mono text-sm"
                >
                    {loading && filteredLogs.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center text-foreground-muted">
                            <RefreshCw className="mb-4 h-12 w-12 animate-spin opacity-50" />
                            <p>Loading logs...</p>
                        </div>
                    ) : filteredLogs.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center text-foreground-muted">
                            <Terminal className="mb-4 h-12 w-12 opacity-50" />
                            <p>No logs found</p>
                            {searchQuery && <p className="mt-1 text-sm">Try adjusting your search or filters</p>}
                            {!serviceUuid && <p className="mt-2 text-sm text-yellow-500">Provide a service UUID for real logs</p>}
                        </div>
                    ) : (
                        <div className="p-2">
                            {filteredLogs.map((log) => (
                                <div
                                    key={log.id}
                                    className="group flex items-start gap-2 rounded px-2 py-1 hover:bg-white/5"
                                >
                                    <span className="shrink-0 text-foreground-subtle">{log.timestamp}</span>
                                    <span className={`shrink-0 flex items-center gap-1 ${getLevelColor(log.level || 'info')}`}>
                                        {getLevelIcon(log.level || 'info')}
                                        <span className="w-12 text-xs uppercase">[{log.level || 'info'}]</span>
                                    </span>
                                    <span className="text-foreground">{log.message}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-border px-4 py-2 text-xs text-foreground-muted">
                    <span>{filteredLogs.length} log entries</span>
                    <span>Tip: Use <kbd className="rounded bg-background-secondary px-1">Ctrl+F</kbd> to search in logs</span>
                </div>
            </div>
        </div>
    );
}

export default LogsViewer;
