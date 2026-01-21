import { useState, useEffect, useRef } from 'react';
import { X, Search, Download, Filter, RefreshCw, Pause, Play, ChevronDown, Terminal, AlertCircle, Info, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

interface LogEntry {
    id: string;
    timestamp: string;
    level: 'info' | 'warn' | 'error' | 'debug';
    message: string;
    source?: string;
}

interface LogsViewerProps {
    isOpen: boolean;
    onClose: () => void;
    serviceName: string;
    deploymentId?: string;
}

// Demo log entries
const generateDemoLogs = (): LogEntry[] => [
    { id: '1', timestamp: '2024-01-15 14:32:05.123', level: 'info', message: 'Server listening on port 3000', source: 'api-server' },
    { id: '2', timestamp: '2024-01-15 14:32:05.456', level: 'info', message: 'Connected to PostgreSQL database', source: 'api-server' },
    { id: '3', timestamp: '2024-01-15 14:32:06.789', level: 'info', message: 'Redis connection established', source: 'api-server' },
    { id: '4', timestamp: '2024-01-15 14:32:10.012', level: 'info', message: 'GET /health 200 - 2ms', source: 'api-server' },
    { id: '5', timestamp: '2024-01-15 14:32:15.345', level: 'warn', message: 'Slow query detected: SELECT * FROM users - 1523ms', source: 'api-server' },
    { id: '6', timestamp: '2024-01-15 14:32:20.678', level: 'info', message: 'POST /api/auth/login 200 - 45ms', source: 'api-server' },
    { id: '7', timestamp: '2024-01-15 14:32:25.901', level: 'error', message: 'Failed to fetch user profile: Connection timeout', source: 'api-server' },
    { id: '8', timestamp: '2024-01-15 14:32:26.234', level: 'info', message: 'Retrying connection...', source: 'api-server' },
    { id: '9', timestamp: '2024-01-15 14:32:27.567', level: 'info', message: 'Connection restored', source: 'api-server' },
    { id: '10', timestamp: '2024-01-15 14:32:30.890', level: 'debug', message: 'Cache hit for key: user:123', source: 'api-server' },
    { id: '11', timestamp: '2024-01-15 14:32:35.123', level: 'info', message: 'GET /api/users/123 200 - 12ms', source: 'api-server' },
    { id: '12', timestamp: '2024-01-15 14:32:40.456', level: 'warn', message: 'Rate limit approaching for IP: 192.168.1.1', source: 'api-server' },
    { id: '13', timestamp: '2024-01-15 14:32:45.789', level: 'info', message: 'Background job completed: sendEmailNotifications', source: 'api-server' },
    { id: '14', timestamp: '2024-01-15 14:32:50.012', level: 'error', message: 'Unhandled promise rejection: TypeError: Cannot read property of undefined', source: 'api-server' },
    { id: '15', timestamp: '2024-01-15 14:32:55.345', level: 'info', message: 'Health check passed', source: 'api-server' },
];

export function LogsViewer({ isOpen, onClose, serviceName, deploymentId }: LogsViewerProps) {
    const [logs, setLogs] = useState<LogEntry[]>(generateDemoLogs());
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedLevel, setSelectedLevel] = useState<string>('all');
    const [isStreaming, setIsStreaming] = useState(true);
    const [selectedDeployment, setSelectedDeployment] = useState(deploymentId || 'latest');
    const logsContainerRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom when new logs arrive
    useEffect(() => {
        if (isStreaming && logsContainerRef.current) {
            logsContainerRef.current.scrollTop = logsContainerRef.current.scrollHeight;
        }
    }, [logs, isStreaming]);

    // Simulate streaming logs
    useEffect(() => {
        if (!isOpen || !isStreaming) return;

        const interval = setInterval(() => {
            const newLog: LogEntry = {
                id: String(Date.now()),
                timestamp: new Date().toISOString().replace('T', ' ').slice(0, 23),
                level: Math.random() > 0.8 ? 'warn' : Math.random() > 0.95 ? 'error' : 'info',
                message: `Request processed - ${Math.floor(Math.random() * 100)}ms`,
                source: serviceName,
            };
            setLogs(prev => [...prev.slice(-100), newLog]); // Keep last 100 logs
        }, 2000);

        return () => clearInterval(interval);
    }, [isOpen, isStreaming, serviceName]);

    const filteredLogs = logs.filter(log => {
        const matchesSearch = searchQuery === '' ||
            log.message.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesLevel = selectedLevel === 'all' || log.level === selectedLevel;
        return matchesSearch && matchesLevel;
    });

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
        const content = filteredLogs.map(log =>
            `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`
        ).join('\n');

        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${serviceName}-logs-${new Date().toISOString().slice(0, 10)}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    };

    if (!isOpen) return null;

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
                        onClick={() => setIsStreaming(!isStreaming)}
                    >
                        {isStreaming ? <Pause className="mr-1 h-3.5 w-3.5" /> : <Play className="mr-1 h-3.5 w-3.5" />}
                        {isStreaming ? 'Pause' : 'Resume'}
                    </Button>

                    <Button size="sm" variant="secondary" onClick={() => setLogs(generateDemoLogs())}>
                        <RefreshCw className="mr-1 h-3.5 w-3.5" />
                        Refresh
                    </Button>

                    <Button size="sm" variant="secondary" onClick={handleDownload}>
                        <Download className="mr-1 h-3.5 w-3.5" />
                        Export
                    </Button>
                </div>

                {/* Logs */}
                <div
                    ref={logsContainerRef}
                    className="flex-1 overflow-y-auto font-mono text-sm"
                >
                    {filteredLogs.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center text-foreground-muted">
                            <Terminal className="mb-4 h-12 w-12 opacity-50" />
                            <p>No logs found</p>
                            {searchQuery && <p className="mt-1 text-sm">Try adjusting your search or filters</p>}
                        </div>
                    ) : (
                        <div className="p-2">
                            {filteredLogs.map((log) => (
                                <div
                                    key={log.id}
                                    className="group flex items-start gap-2 rounded px-2 py-1 hover:bg-white/5"
                                >
                                    <span className="shrink-0 text-foreground-subtle">{log.timestamp}</span>
                                    <span className={`shrink-0 flex items-center gap-1 ${getLevelColor(log.level)}`}>
                                        {getLevelIcon(log.level)}
                                        <span className="w-12 text-xs uppercase">[{log.level}]</span>
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
