import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Terminal } from '@/components/features/Terminal';
import { ArrowLeft, Server, Terminal as TerminalIcon, Maximize2, Minimize2, RefreshCw, Settings } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function ServerTerminal({ server }: Props) {
    const [isConnected, setIsConnected] = useState(false);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [terminalKey, setTerminalKey] = useState(0);
    const { addToast } = useToast();

    const handleConnect = () => {
        setIsConnected(true);
        addToast('success', 'Terminal connected successfully');
    };

    const handleDisconnect = () => {
        setIsConnected(false);
        addToast('warning', 'Terminal disconnected');
    };

    const handleReconnect = () => {
        // Force remount of terminal component
        setTerminalKey(prev => prev + 1);
        addToast('info', 'Reconnecting terminal...');
    };

    const toggleFullscreen = () => {
        setIsFullscreen(!isFullscreen);
    };

    // Calculate terminal height based on fullscreen state
    const terminalHeight = isFullscreen ? 'calc(100vh - 250px)' : '600px';

    return (
        <AppLayout
            title={`${server.name} - Terminal`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Terminal' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/servers/${server.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Server
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <TerminalIcon className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Terminal Access</h1>
                        <div className="flex items-center gap-2">
                            <Server className="h-4 w-4 text-foreground-muted" />
                            <span className="text-sm text-foreground-muted">
                                {server.ip}:{server.port} • {server.user}
                            </span>
                            {isConnected && (
                                <Badge variant="success">Connected</Badge>
                            )}
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleReconnect}
                        disabled={!isConnected}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Reconnect
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={toggleFullscreen}
                    >
                        {isFullscreen ? (
                            <>
                                <Minimize2 className="mr-2 h-4 w-4" />
                                Exit Fullscreen
                            </>
                        ) : (
                            <>
                                <Maximize2 className="mr-2 h-4 w-4" />
                                Fullscreen
                            </>
                        )}
                    </Button>
                </div>
            </div>

            {/* Warning Banner */}
            {server.is_reachable && server.is_usable ? (
                <div className="mb-4 rounded-lg border border-info/20 bg-info/5 p-4">
                    <div className="flex items-start gap-3">
                        <TerminalIcon className="h-5 w-5 text-info" />
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">Terminal Access</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                You have direct SSH access to this server. Use caution when executing commands.
                                All actions are logged and can affect deployed applications.
                            </p>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="mb-4 rounded-lg border border-danger/20 bg-danger/5 p-4">
                    <div className="flex items-start gap-3">
                        <Server className="h-5 w-5 text-danger" />
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">Server Unavailable</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                This server is not reachable. Please check the server connection and try again.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Terminal */}
            <Card>
                <CardContent className="p-4">
                    {server.is_reachable && server.is_usable ? (
                        <Terminal
                            key={terminalKey}
                            serverId={server.id}
                            height={terminalHeight}
                            onConnect={handleConnect}
                            onDisconnect={handleDisconnect}
                        />
                    ) : (
                        <div
                            className="flex items-center justify-center rounded-lg border border-border bg-background-secondary"
                            style={{ height: terminalHeight }}
                        >
                            <div className="text-center">
                                <Server className="mx-auto h-12 w-12 text-foreground-subtle" />
                                <h3 className="mt-4 font-medium text-foreground">Cannot Connect</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Server is not available for terminal access
                                </p>
                                <Link href={`/servers/${server.uuid}`} className="mt-4 inline-block">
                                    <Button variant="secondary" size="sm">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Check Server Settings
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Help Section */}
            <Card className="mt-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Terminal Tips</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <h4 className="mb-2 font-medium text-foreground">Keyboard Shortcuts</h4>
                            <ul className="space-y-1 text-sm text-foreground-muted">
                                <li><code className="rounded bg-background-tertiary px-1">Ctrl+C</code> - Copy selected text</li>
                                <li><code className="rounded bg-background-tertiary px-1">Ctrl+V</code> - Paste text</li>
                                <li><code className="rounded bg-background-tertiary px-1">Ctrl+L</code> - Clear screen</li>
                                <li><code className="rounded bg-background-tertiary px-1">Tab</code> - Auto-complete</li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="mb-2 font-medium text-foreground">Common Commands</h4>
                            <ul className="space-y-1 text-sm text-foreground-muted">
                                <li><code className="rounded bg-background-tertiary px-1">docker ps</code> - List running containers</li>
                                <li><code className="rounded bg-background-tertiary px-1">docker logs [container]</code> - View container logs</li>
                                <li><code className="rounded bg-background-tertiary px-1">htop</code> - System resource monitor</li>
                                <li><code className="rounded bg-background-tertiary px-1">df -h</code> - Disk space usage</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
