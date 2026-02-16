import * as React from 'react';
import { X, Wifi, WifiOff, Terminal } from 'lucide-react';
import { LogsContainer, type LogLine } from '@/components/features/LogsContainer';
import { useLogStream } from '@/hooks/useLogStream';

interface LogsViewerProps {
    isOpen: boolean;
    onClose: () => void;
    serviceName: string;
    serviceUuid?: string;
    serviceType?: 'application' | 'deployment' | 'database' | 'service';
    /** Container name filter for services with multiple containers */
    containerName?: string;
    deploymentId?: string;
}

/**
 * Modal logs viewer component that uses LogsContainer for rendering
 *
 * Features:
 * - Real-time log streaming via WebSocket
 * - Polling fallback when WebSocket unavailable
 * - Virtual scrolling for large log files
 * - Persistent autoscroll preference
 * - Search and level filtering
 * - Download and copy functionality
 */
export function LogsViewer({
    isOpen,
    onClose,
    serviceName,
    serviceUuid,
    serviceType = 'application',
    containerName,
}: LogsViewerProps) {
    const [selectedLevel, _setSelectedLevel] = React.useState<string>('all');

    // Use the real log stream hook when serviceUuid is provided
    const {
        logs: streamLogs,
        isStreaming,
        isConnected,
        isPolling,
        loading: _loading,
        error,
        clearLogs,
        refresh,
    } = useLogStream({
        resourceType: serviceType,
        resourceId: serviceUuid || 'demo',
        container: containerName,
        enableWebSocket: !!serviceUuid,
        pollingInterval: serviceUuid ? 2000 : 0,
        maxLogEntries: 500,
        autoScroll: true,
        filterLevel: selectedLevel === 'all' ? 'all' : selectedLevel as 'info' | 'error' | 'warning' | 'debug',
    });

    // Transform logs from hook format to LogLine format
    const displayLogs: LogLine[] = React.useMemo(() => {
        return streamLogs.map(log => ({
            id: log.id,
            content: log.message,
            timestamp: log.timestamp,
            level: log.level === 'warning' ? 'warn' : log.level,
            source: log.source,
        }));
    }, [streamLogs]);

    // Handle escape key to close modal
    React.useEffect(() => {
        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, [isOpen, onClose]);

    // Prevent body scroll when modal is open
    React.useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const connectionStatus = isConnected ? 'WebSocket' : isPolling ? 'Polling' : 'Disconnected';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/70"
                onClick={onClose}
                aria-hidden="true"
            />

            {/* Modal */}
            <div className="relative z-10 flex h-[85vh] w-[90vw] max-w-6xl flex-col rounded-xl border border-border backdrop-blur-xl bg-background/95 shadow-2xl animate-fade-in overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <div className="flex items-center gap-3">
                        <Terminal className="h-5 w-5 text-foreground-muted" />
                        <h2 className="text-lg font-semibold text-foreground">{serviceName} Logs</h2>

                        {/* Streaming status */}
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

                        {/* Demo mode indicator */}
                        {!serviceUuid && (
                            <span className="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs bg-yellow-500/20 text-yellow-400">
                                Demo Mode
                            </span>
                        )}
                    </div>

                    <button
                        onClick={onClose}
                        className="rounded p-1 text-foreground-muted hover:bg-background-secondary hover:text-foreground transition-colors"
                        aria-label="Close logs"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Error Banner */}
                {error && (
                    <div className="flex items-center gap-2 border-b border-red-500/20 bg-red-500/10 px-4 py-2 text-sm text-red-400">
                        <span>{error.message}</span>
                        <button
                            onClick={() => { clearLogs(); refresh(); }}
                            className="ml-auto rounded px-2 py-1 text-xs hover:bg-red-500/20 transition-colors"
                        >
                            Retry
                        </button>
                    </div>
                )}

                {/* Logs Container */}
                <div className="flex-1 overflow-hidden">
                    <LogsContainer
                        logs={displayLogs}
                        storageKey={`viewer-${serviceType}-${serviceUuid || 'demo'}`}
                        height="100%"
                        isStreaming={isStreaming}
                        isConnected={isConnected}
                        showSearch
                        showLevelFilter
                        showDownload
                        showCopy
                        showLineNumbers
                        emptyMessage={serviceUuid ? 'No logs available' : 'Provide a service UUID for real logs'}
                        loadingMessage="Loading logs..."
                        logsClassName="h-full"
                    />
                </div>
            </div>
        </div>
    );
}

export default LogsViewer;
