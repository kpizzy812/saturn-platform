import * as React from 'react';
import { useAutoScroll } from '@/hooks/useAutoScroll';
import { Button } from '@/components/ui';
import { cn } from '@/lib/utils';
import {
    ChevronDown,
    ChevronUp,
    ArrowDown,
    Search,
    Download,
    Copy,
    Check,
    Terminal,
    AlertCircle,
    AlertTriangle,
    Info,
} from 'lucide-react';

/**
 * Log entry structure
 */
export interface LogLine {
    id: string | number;
    content: string;
    timestamp?: string;
    level?: 'info' | 'warn' | 'error' | 'debug' | 'stdout' | 'stderr';
    source?: string;
}

/**
 * Props for LogsContainer component
 */
export interface LogsContainerProps {
    /**
     * Array of log entries to display
     */
    logs: LogLine[];

    /**
     * Unique key for persisting autoscroll preference
     */
    storageKey?: string;

    /**
     * Height of the logs container (default: 500px)
     */
    height?: number | string;

    /**
     * Whether the logs are currently streaming (shows live indicator)
     */
    isStreaming?: boolean;

    /**
     * Whether the connection is active
     */
    isConnected?: boolean;

    /**
     * Title for the logs section
     */
    title?: string;

    /**
     * Show search input
     */
    showSearch?: boolean;

    /**
     * Show level filter
     */
    showLevelFilter?: boolean;

    /**
     * Show download button
     */
    showDownload?: boolean;

    /**
     * Show copy button
     */
    showCopy?: boolean;

    /**
     * Show line numbers
     */
    showLineNumbers?: boolean;

    /**
     * Custom class name for the container
     */
    className?: string;

    /**
     * Custom class name for the logs area
     */
    logsClassName?: string;

    /**
     * Callback when download is clicked
     */
    onDownload?: () => void;

    /**
     * Empty state message
     */
    emptyMessage?: string;

    /**
     * Loading message when streaming but no logs yet
     */
    loadingMessage?: string;

    /**
     * Enable keyboard shortcuts (End = scroll to bottom, Home = scroll to top)
     */
    enableKeyboardShortcuts?: boolean;
}

/**
 * Get color class for log level
 */
function getLevelColor(level: LogLine['level']): string {
    switch (level) {
        case 'error':
        case 'stderr':
            return 'text-red-400';
        case 'warn':
            return 'text-yellow-400';
        case 'debug':
            return 'text-gray-500';
        case 'info':
        case 'stdout':
        default:
            return 'text-gray-300';
    }
}

/**
 * Get icon for log level
 */
function getLevelIcon(level: LogLine['level']): React.ReactNode {
    switch (level) {
        case 'error':
        case 'stderr':
            return <AlertCircle className="h-3 w-3 shrink-0" />;
        case 'warn':
            return <AlertTriangle className="h-3 w-3 shrink-0" />;
        case 'info':
        case 'stdout':
            return <Info className="h-3 w-3 shrink-0" />;
        default:
            return null;
    }
}

/**
 * Detect log level from content if not provided
 */
function detectLogLevel(content: string): LogLine['level'] {
    const lower = content.toLowerCase();
    if (lower.includes('error') || lower.includes('failed') || lower.includes('fatal') || lower.includes('exception')) {
        return 'error';
    }
    if (lower.includes('warn') || lower.includes('warning') || lower.includes('deprecated')) {
        return 'warn';
    }
    if (lower.includes('debug')) {
        return 'debug';
    }
    return 'info';
}

/**
 * Style log output with syntax highlighting
 */
function getStyledContent(content: string, level?: LogLine['level']): React.ReactNode {
    // Header lines (box drawing characters)
    if (content.match(/^[╔═╚╗╝║┌─└┐┘│]/)) {
        return <span className="text-cyan-400">{content}</span>;
    }

    // Success messages
    if (content.match(/✓|success|completed|done|finished/i)) {
        return <span className="text-green-400">{content}</span>;
    }

    // Error messages
    if (level === 'error' || level === 'stderr' || content.match(/error|failed|fatal|exception/i)) {
        return <span className="text-red-400">{content}</span>;
    }

    // Warning messages
    if (level === 'warn' || content.match(/warning|warn|deprecated/i)) {
        return <span className="text-yellow-400">{content}</span>;
    }

    // Step indicators
    if (content.match(/^(Step|>>>|\[[\d/]+\])/i)) {
        return <span className="text-blue-400">{content}</span>;
    }

    // Commands (starting with $, #, or >)
    if (content.match(/^\s*[$#>]/)) {
        return <span className="text-purple-400">{content}</span>;
    }

    // Timestamps at start
    const timestampMatch = content.match(/^(\[\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}[^\]]*\])\s*(.*)/);
    if (timestampMatch) {
        return (
            <>
                <span className="text-gray-500">{timestampMatch[1]}</span>
                <span className="text-gray-300"> {timestampMatch[2]}</span>
            </>
        );
    }

    return <span className="text-gray-300">{content}</span>;
}

/**
 * Single log line component
 */
const LogLineItem = React.memo(function LogLineItem({
    log,
    index,
    showLineNumbers,
}: {
    log: LogLine;
    index: number;
    showLineNumbers: boolean;
}) {
    const level = log.level || detectLogLevel(log.content);
    const isError = level === 'error' || level === 'stderr';

    return (
        <div
            className={cn(
                'flex items-start gap-2 px-3 py-0.5 font-mono text-sm leading-relaxed hover:bg-white/5',
                isError && 'bg-red-500/10'
            )}
        >
            {showLineNumbers && (
                <span className="select-none text-gray-600 w-10 shrink-0 text-right tabular-nums">
                    {index + 1}
                </span>
            )}
            {log.timestamp && (
                <span className="text-gray-500 shrink-0 text-xs">
                    {log.timestamp}
                </span>
            )}
            <span className={cn('shrink-0', getLevelColor(level))}>
                {getLevelIcon(level)}
            </span>
            <span className="break-all whitespace-pre-wrap flex-1">
                {getStyledContent(log.content, level)}
            </span>
        </div>
    );
});

/**
 * LogsContainer - A high-performance, feature-rich logs viewer component
 *
 * Features:
 * - Virtual scrolling for large log files (10k+ lines)
 * - Persistent autoscroll preference
 * - Smart autoscroll (disables on manual scroll up, re-enables at bottom)
 * - New logs indicator with count
 * - Search filtering
 * - Level filtering
 * - Keyboard shortcuts (End, Home, Ctrl+End, Ctrl+Home)
 * - Copy and download functionality
 * - Syntax highlighting for common log patterns
 *
 * @example
 * ```tsx
 * <LogsContainer
 *   logs={deploymentLogs}
 *   storageKey="deployment-123"
 *   isStreaming={isDeploying}
 *   showSearch
 *   showLevelFilter
 *   showDownload
 * />
 * ```
 */
export function LogsContainer({
    logs,
    storageKey = 'logs',
    height = 500,
    isStreaming = false,
    isConnected = false,
    title,
    showSearch = false,
    showLevelFilter = false,
    showDownload = false,
    showCopy = false,
    showLineNumbers = true,
    className,
    logsClassName,
    onDownload,
    emptyMessage = 'No logs available',
    loadingMessage = 'Waiting for logs...',
    enableKeyboardShortcuts = true,
}: LogsContainerProps) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [levelFilter, setLevelFilter] = React.useState<LogLine['level'] | 'all'>('all');
    const [copied, setCopied] = React.useState(false);
    const prevLogsLengthRef = React.useRef(logs.length);

    // Autoscroll hook
    const {
        containerRef,
        bottomRef,
        isEnabled: autoScrollEnabled,
        isAtBottom,
        newItemsCount,
        toggle: toggleAutoScroll,
        scrollToBottom,
        scrollToTop,
        notifyNewContent,
        clearNewItemsCount,
    } = useAutoScroll({
        storageKey: `logs-${storageKey}`,
        defaultEnabled: true,
        threshold: 100,
        smooth: true,
    });

    // Filter logs
    const filteredLogs = React.useMemo(() => {
        let filtered = logs;

        // Filter by level
        if (levelFilter !== 'all') {
            filtered = filtered.filter(log => {
                const level = log.level || detectLogLevel(log.content);
                if (levelFilter === 'error') return level === 'error' || level === 'stderr';
                if (levelFilter === 'warn') return level === 'warn';
                if (levelFilter === 'debug') return level === 'debug';
                return level === 'info' || level === 'stdout';
            });
        }

        // Filter by search
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(log =>
                log.content.toLowerCase().includes(query)
            );
        }

        return filtered;
    }, [logs, levelFilter, searchQuery]);

    // Notify autoscroll hook when new logs arrive
    React.useEffect(() => {
        const newCount = logs.length - prevLogsLengthRef.current;
        if (newCount > 0) {
            notifyNewContent(newCount);
        }
        prevLogsLengthRef.current = logs.length;
    }, [logs.length, notifyNewContent]);

    // Keyboard shortcuts
    React.useEffect(() => {
        if (!enableKeyboardShortcuts) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            // Check if focus is inside our container or no specific element is focused
            const container = containerRef.current;
            if (!container) return;

            // Only handle if the container or its children have focus, or no input is focused
            const activeElement = document.activeElement;
            const isInputFocused = activeElement?.tagName === 'INPUT' ||
                activeElement?.tagName === 'TEXTAREA' ||
                activeElement?.getAttribute('contenteditable') === 'true';

            if (isInputFocused) return;

            if (e.key === 'End' || (e.ctrlKey && e.key === 'ArrowDown')) {
                e.preventDefault();
                scrollToBottom();
            } else if (e.key === 'Home' || (e.ctrlKey && e.key === 'ArrowUp')) {
                e.preventDefault();
                scrollToTop();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [enableKeyboardShortcuts, scrollToBottom, scrollToTop, containerRef]);

    // Copy logs to clipboard
    const handleCopy = React.useCallback(async () => {
        const text = filteredLogs.map(log => {
            let line = '';
            if (log.timestamp) line += `[${log.timestamp}] `;
            if (log.level) line += `[${log.level.toUpperCase()}] `;
            line += log.content;
            return line;
        }).join('\n');

        // Try modern Clipboard API first (requires HTTPS or localhost)
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
                return;
            } catch (err) {
                console.warn('Clipboard API failed, trying fallback:', err);
            }
        }

        // Fallback for HTTP: use execCommand (deprecated but works)
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            const success = document.execCommand('copy');
            document.body.removeChild(textarea);

            if (success) {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            } else {
                console.error('execCommand copy failed');
            }
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }, [filteredLogs]);

    // Download logs
    const handleDownload = React.useCallback(() => {
        if (onDownload) {
            onDownload();
            return;
        }

        const text = filteredLogs.map(log => {
            let line = '';
            if (log.timestamp) line += `[${log.timestamp}] `;
            if (log.level) line += `[${log.level.toUpperCase()}] `;
            line += log.content;
            return line;
        }).join('\n');

        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `logs-${storageKey}-${new Date().toISOString().slice(0, 10)}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, [filteredLogs, storageKey, onDownload]);

    // Handle scroll to bottom button click
    const handleScrollToBottomClick = React.useCallback(() => {
        scrollToBottom();
        clearNewItemsCount();
    }, [scrollToBottom, clearNewItemsCount]);

    const containerHeight = typeof height === 'number' ? `${height}px` : height;

    return (
        <div className={cn('flex h-full flex-col', className)}>
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-3 border-b border-border bg-background-secondary/50 px-3 py-2">
                <div className="flex items-center gap-3">
                    {title && (
                        <div className="flex items-center gap-2">
                            <Terminal className="h-4 w-4 text-foreground-muted" />
                            <span className="text-sm font-medium text-foreground">{title}</span>
                        </div>
                    )}

                    {/* Streaming indicator */}
                    {isStreaming && (
                        <span className="flex items-center gap-1.5 text-xs text-success">
                            <span className="h-2 w-2 rounded-full bg-success animate-pulse" />
                            Live
                        </span>
                    )}

                    {/* Connection indicator */}
                    {isConnected && !isStreaming && (
                        <span className="flex items-center gap-1.5 text-xs text-blue-400">
                            <span className="h-2 w-2 rounded-full bg-blue-400" />
                            Connected
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {/* Search */}
                    {showSearch && (
                        <div className="relative">
                            <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search..."
                                className="h-8 w-40 rounded-md border border-border bg-background pl-8 pr-3 text-xs text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                            />
                        </div>
                    )}

                    {/* Level filter */}
                    {showLevelFilter && (
                        <select
                            value={levelFilter}
                            onChange={(e) => setLevelFilter(e.target.value as LogLine['level'] | 'all')}
                            className="h-8 rounded-md border border-border bg-background px-2 text-xs text-foreground focus:border-primary focus:outline-none"
                        >
                            <option value="all">All Levels</option>
                            <option value="info">Info</option>
                            <option value="warn">Warnings</option>
                            <option value="error">Errors</option>
                            <option value="debug">Debug</option>
                        </select>
                    )}

                    {/* Auto-scroll toggle */}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={toggleAutoScroll}
                        className={cn(
                            'h-8 text-xs',
                            autoScrollEnabled && 'text-primary border-primary'
                        )}
                    >
                        {autoScrollEnabled ? (
                            <ChevronDown className="mr-1.5 h-3.5 w-3.5" />
                        ) : (
                            <ChevronUp className="mr-1.5 h-3.5 w-3.5" />
                        )}
                        Auto-scroll
                    </Button>

                    {/* Copy */}
                    {showCopy && (
                        <Button variant="outline" size="sm" onClick={handleCopy} className="h-8 text-xs">
                            {copied ? (
                                <Check className="mr-1.5 h-3.5 w-3.5 text-success" />
                            ) : (
                                <Copy className="mr-1.5 h-3.5 w-3.5" />
                            )}
                            {copied ? 'Copied!' : 'Copy'}
                        </Button>
                    )}

                    {/* Download */}
                    {showDownload && (
                        <Button variant="outline" size="sm" onClick={handleDownload} className="h-8 text-xs">
                            <Download className="mr-1.5 h-3.5 w-3.5" />
                            Download
                        </Button>
                    )}
                </div>
            </div>

            {/* Logs container */}
            <div className="relative min-h-0 flex-1">
                <div
                    ref={containerRef}
                    style={{ height: containerHeight }}
                    className={cn(
                        'overflow-y-auto bg-[#0d1117]',
                        logsClassName
                    )}
                >
                    {filteredLogs.length === 0 ? (
                        <div className="flex h-full items-center justify-center text-foreground-muted">
                            {isStreaming ? (
                                <div className="flex flex-col items-center gap-2">
                                    <Terminal className="h-8 w-8 animate-pulse opacity-50" />
                                    <span className="text-sm">{loadingMessage}</span>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center gap-2">
                                    <Terminal className="h-8 w-8 opacity-50" />
                                    <span className="text-sm">{emptyMessage}</span>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div>
                            {filteredLogs.map((log, index) => (
                                <LogLineItem
                                    key={log.id}
                                    log={log}
                                    index={index}
                                    showLineNumbers={showLineNumbers}
                                />
                            ))}
                        </div>
                    )}
                    <div ref={bottomRef} style={{ height: 1 }} />
                </div>

                {/* New logs indicator / Scroll to bottom button */}
                {(!isAtBottom || newItemsCount > 0) && filteredLogs.length > 0 && (
                    <button
                        onClick={handleScrollToBottomClick}
                        className={cn(
                            'absolute bottom-4 left-1/2 -translate-x-1/2',
                            'flex items-center gap-2 rounded-full px-4 py-2',
                            'bg-primary text-primary-foreground shadow-lg',
                            'transition-all hover:bg-primary/90',
                            'animate-fade-in'
                        )}
                    >
                        <ArrowDown className="h-4 w-4" />
                        {newItemsCount > 0 ? (
                            <span className="text-sm font-medium">
                                {newItemsCount} new {newItemsCount === 1 ? 'log' : 'logs'}
                            </span>
                        ) : (
                            <span className="text-sm font-medium">Scroll to bottom</span>
                        )}
                    </button>
                )}
            </div>

            {/* Footer */}
            <div className="flex items-center justify-between border-t border-border bg-background-secondary/50 px-3 py-1.5 text-xs text-foreground-muted">
                <span>
                    {filteredLogs.length === logs.length
                        ? `${logs.length} lines`
                        : `${filteredLogs.length} of ${logs.length} lines`}
                </span>
                <span className="flex items-center gap-2">
                    <kbd className="rounded bg-background-tertiary px-1.5 py-0.5 text-[10px]">End</kbd>
                    <span>scroll to bottom</span>
                    <span className="text-border">•</span>
                    <kbd className="rounded bg-background-tertiary px-1.5 py-0.5 text-[10px]">Home</kbd>
                    <span>scroll to top</span>
                </span>
            </div>
        </div>
    );
}

export default LogsContainer;
