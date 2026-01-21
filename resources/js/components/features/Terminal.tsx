import * as React from 'react';
import { Terminal as XTerm } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { useTerminal } from '@/hooks/useTerminal';
import { Spinner } from '@/components/ui';
import '@xterm/xterm/css/xterm.css';

interface TerminalProps {
    /**
     * Server ID to connect to
     */
    serverId: number | string;

    /**
     * Terminal height (default: 600px)
     */
    height?: string | number;

    /**
     * Callback when terminal connects
     */
    onConnect?: () => void;

    /**
     * Callback when terminal disconnects
     */
    onDisconnect?: () => void;

    /**
     * Additional CSS classes
     */
    className?: string;
}

/**
 * Terminal component with xterm.js integration
 *
 * Provides a full terminal experience with:
 * - WebSocket connection to server
 * - Auto-resize on window resize
 * - Copy/paste support
 * - Dark theme matching Saturn design
 * - Reconnection on disconnect
 *
 * @example
 * ```tsx
 * <Terminal
 *   serverId={server.id}
 *   height="600px"
 *   onConnect={() => console.log('Connected!')}
 * />
 * ```
 */
export function Terminal({
    serverId,
    height = '600px',
    onConnect,
    onDisconnect,
    className = ''
}: TerminalProps) {
    const terminalRef = React.useRef<HTMLDivElement>(null);
    const xtermRef = React.useRef<XTerm | null>(null);
    const fitAddonRef = React.useRef<FitAddon | null>(null);
    const [isInitialized, setIsInitialized] = React.useState(false);

    const {
        isConnected,
        isConnecting,
        error,
        reconnectAttempt,
        sendData,
        resize,
    } = useTerminal({
        serverId,
        onData: (data) => {
            if (xtermRef.current) {
                xtermRef.current.write(data);
            }
        },
        onConnect: () => {
            if (xtermRef.current) {
                xtermRef.current.clear();
                xtermRef.current.write('\r\n\x1b[1;32mConnected to server terminal\x1b[0m\r\n\r\n');
            }
            onConnect?.();
        },
        onDisconnect: () => {
            if (xtermRef.current) {
                xtermRef.current.write('\r\n\x1b[1;31mDisconnected from server\x1b[0m\r\n');
            }
            onDisconnect?.();
        },
        onError: (err) => {
            if (xtermRef.current) {
                xtermRef.current.write(`\r\n\x1b[1;31mError: ${err.message}\x1b[0m\r\n`);
            }
        },
    });

    /**
     * Initialize xterm.js terminal
     */
    React.useEffect(() => {
        if (!terminalRef.current || xtermRef.current) {
            return;
        }

        // Create xterm instance with Saturn theme
        const terminal = new XTerm({
            cursorBlink: true,
            cursorStyle: 'block',
            fontSize: 14,
            fontFamily: 'Menlo, Monaco, "Courier New", monospace',
            theme: {
                background: '#0f1419',
                foreground: '#e6edf3',
                cursor: '#e6edf3',
                cursorAccent: '#0f1419',
                selectionBackground: '#264f78',
                black: '#0f1419',
                red: '#ff6b6b',
                green: '#51cf66',
                yellow: '#ffd43b',
                blue: '#4dabf7',
                magenta: '#cc5de8',
                cyan: '#22b8cf',
                white: '#e6edf3',
                brightBlack: '#6e7681',
                brightRed: '#ff8787',
                brightGreen: '#8ce99a',
                brightYellow: '#ffe066',
                brightBlue: '#74c0fc',
                brightMagenta: '#e599f7',
                brightCyan: '#66d9e8',
                brightWhite: '#ffffff',
            },
            allowProposedApi: true,
            scrollback: 10000,
            tabStopWidth: 4,
        });

        // Create fit addon for auto-sizing
        const fitAddon = new FitAddon();
        terminal.loadAddon(fitAddon);

        // Open terminal in DOM
        terminal.open(terminalRef.current);

        // Fit terminal to container
        fitAddon.fit();

        // Send resize event to backend
        const { cols, rows } = terminal;
        resize(cols, rows);

        // Handle terminal input
        terminal.onData((data) => {
            sendData(data);
        });

        // Handle copy/paste
        terminal.attachCustomKeyEventHandler((event) => {
            // Allow Ctrl+C and Ctrl+V for copy/paste
            if (event.ctrlKey && event.key === 'c' && terminal.hasSelection()) {
                // Copy selected text
                document.execCommand('copy');
                return false;
            }
            if (event.ctrlKey && event.key === 'v') {
                // Paste will be handled by browser's paste event
                return true;
            }
            return true;
        });

        // Handle paste event
        terminal.onSelectionChange(() => {
            if (terminal.hasSelection()) {
                const selection = terminal.getSelection();
                if (selection) {
                    // Copy to clipboard
                    navigator.clipboard.writeText(selection).catch(err => {
                        console.error('Failed to copy to clipboard:', err);
                    });
                }
            }
        });

        // Store references
        xtermRef.current = terminal;
        fitAddonRef.current = fitAddon;
        setIsInitialized(true);

        // Cleanup
        return () => {
            terminal.dispose();
            xtermRef.current = null;
            fitAddonRef.current = null;
            setIsInitialized(false);
        };
    }, []);

    /**
     * Handle window resize
     */
    React.useEffect(() => {
        if (!xtermRef.current || !fitAddonRef.current) {
            return;
        }

        const handleResize = () => {
            if (fitAddonRef.current && xtermRef.current) {
                fitAddonRef.current.fit();
                const { cols, rows } = xtermRef.current;
                resize(cols, rows);
            }
        };

        // Debounce resize events
        let resizeTimeout: NodeJS.Timeout;
        const debouncedResize = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(handleResize, 100);
        };

        window.addEventListener('resize', debouncedResize);

        return () => {
            window.removeEventListener('resize', debouncedResize);
            clearTimeout(resizeTimeout);
        };
    }, [isInitialized, resize]);

    /**
     * Handle paste events
     */
    React.useEffect(() => {
        if (!xtermRef.current) {
            return;
        }

        const handlePaste = (event: ClipboardEvent) => {
            event.preventDefault();
            const text = event.clipboardData?.getData('text');
            if (text && xtermRef.current) {
                sendData(text);
            }
        };

        const terminalElement = terminalRef.current;
        terminalElement?.addEventListener('paste', handlePaste);

        return () => {
            terminalElement?.removeEventListener('paste', handlePaste);
        };
    }, [sendData]);

    /**
     * Show connection status
     */
    const renderStatus = () => {
        if (isConnecting) {
            return (
                <div className="flex items-center justify-center" style={{ height }}>
                    <div className="text-center">
                        <Spinner className="mx-auto mb-4 h-8 w-8" />
                        <p className="text-foreground-muted">
                            Connecting to terminal...
                        </p>
                    </div>
                </div>
            );
        }

        if (error && !isConnected) {
            return (
                <div className="flex items-center justify-center" style={{ height }}>
                    <div className="text-center">
                        <p className="text-danger">Connection failed: {error.message}</p>
                        {reconnectAttempt > 0 && (
                            <p className="mt-2 text-sm text-foreground-muted">
                                Reconnecting... (attempt {reconnectAttempt})
                            </p>
                        )}
                    </div>
                </div>
            );
        }

        return null;
    };

    return (
        <div className={`relative ${className}`}>
            {/* Connection status overlay */}
            {(isConnecting || (error && !isConnected)) && (
                <div className="absolute inset-0 z-10 bg-background/90">
                    {renderStatus()}
                </div>
            )}

            {/* Terminal container */}
            <div
                ref={terminalRef}
                className="rounded-lg border border-border bg-[#0f1419] p-2"
                style={{ height }}
            />

            {/* Connection indicator */}
            {isInitialized && (
                <div className="mt-2 flex items-center justify-between text-xs text-foreground-muted">
                    <div className="flex items-center gap-2">
                        <div className={`h-2 w-2 rounded-full ${isConnected ? 'bg-success' : 'bg-danger'}`} />
                        <span>{isConnected ? 'Connected' : 'Disconnected'}</span>
                    </div>
                    <div className="text-foreground-subtle">
                        Ctrl+C to copy • Ctrl+V to paste
                    </div>
                </div>
            )}
        </div>
    );
}
