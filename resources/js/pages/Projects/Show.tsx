import { useState, useCallback, useRef, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Badge, Button } from '@/components/ui';
import { Plus, Settings, ChevronDown, Play, X, Activity, Variable, Gauge, Cog, ExternalLink, Copy, ChevronRight, Clock, ArrowLeft, Grid3x3, ZoomIn, ZoomOut, Maximize2, Undo2, Redo2, Terminal, Globe, Users, GitCommit, Eye, EyeOff, FileText, Database, Key, Link2, HardDrive, RefreshCw, Table, Shield, Box, Layers, GitBranch, MoreVertical, RotateCcw, StopCircle, Trash2, Command, Search } from 'lucide-react';
import type { Project, Environment } from '@/types';
import { ProjectCanvas } from '@/components/features/canvas';
import { CommandPalette } from '@/components/features/CommandPalette';
import { ContextMenu, type ContextMenuPosition, type ContextMenuNode } from '@/components/features/ContextMenu';
import { LogsViewer } from '@/components/features/LogsViewer';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import { useDatabase, useDatabaseBackups } from '@/hooks/useDatabases';
import { useDeployments } from '@/hooks/useDeployments';
import { ToastProvider, useToast } from '@/components/ui/Toast';
import { useConfirm } from '@/components/ui/ConfirmationModal';
import type { Deployment } from '@/types';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

// Database Logo Components
const PostgreSQLLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 1.5c4.687 0 8.5 3.813 8.5 8.5s-3.813 8.5-8.5 8.5-8.5-3.813-8.5-8.5 3.813-8.5 8.5-8.5zm-1.5 3.5c-2.21 0-4 1.79-4 4v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-4c0-2.21-1.79-4-4-4zm0 2c1.1 0 2 .9 2 2v2h-4v-2c0-1.1.9-2 2-2z"/>
    </svg>
);

const RedisLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 12l10 5 10-5" fillOpacity="0.8"/>
        <path d="M2 17l10 5 10-5" fillOpacity="0.6"/>
    </svg>
);

const MySQLLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <ellipse cx="12" cy="5" rx="8" ry="3"/>
        <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
        <ellipse cx="12" cy="12" rx="8" ry="3" fillOpacity="0.5"/>
    </svg>
);

const MongoDBLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2s-1 2-1 5 1 5 1 7v6h2v-6c0-2 1-4 1-7s-1-5-1-5h-2z"/>
        <ellipse cx="12" cy="10" rx="4" ry="6" fillOpacity="0.3"/>
    </svg>
);

const ClickHouseLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <rect x="4" y="4" width="4" height="16" rx="0.5"/>
        <rect x="10" y="8" width="4" height="12" rx="0.5"/>
        <rect x="16" y="6" width="4" height="14" rx="0.5"/>
    </svg>
);

const getDbLogo = (dbType?: string) => {
    const logoClass = "h-5 w-5";
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return <PostgreSQLLogo className={logoClass} />;
    }
    if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
        return <RedisLogo className={logoClass} />;
    }
    if (type.includes('mysql')) {
        return <MySQLLogo className={logoClass} />;
    }
    if (type.includes('mariadb')) {
        return <MySQLLogo className={logoClass} />;
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return <MongoDBLogo className={logoClass} />;
    }
    if (type.includes('clickhouse')) {
        return <ClickHouseLogo className={logoClass} />;
    }
    return <Database className={logoClass} />;
};

const getDbBgColor = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return 'bg-blue-500/20 text-blue-400';
    }
    if (type.includes('redis')) {
        return 'bg-red-500/20 text-red-400';
    }
    if (type.includes('keydb')) {
        return 'bg-rose-500/20 text-rose-400';
    }
    if (type.includes('dragonfly')) {
        return 'bg-purple-500/20 text-purple-400';
    }
    if (type.includes('mysql')) {
        return 'bg-orange-500/20 text-orange-400';
    }
    if (type.includes('mariadb')) {
        return 'bg-amber-500/20 text-amber-400';
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return 'bg-green-500/20 text-green-400';
    }
    if (type.includes('clickhouse')) {
        return 'bg-yellow-500/20 text-yellow-400';
    }
    return 'bg-violet-500/20 text-violet-400';
};

interface Props {
    project?: Project;
}

type SelectedService = {
    id: string;
    uuid: string;
    type: 'app' | 'db';
    name: string;
    status: string;
    fqdn?: string;
    dbType?: string;
    serverUuid?: string;
};

export default function ProjectShow({ project }: Props) {
    const [selectedEnv] = useState<Environment | null>(project?.environments?.[0] || null);
    const [selectedService, setSelectedService] = useState<SelectedService | null>(null);
    const [activeAppTab, setActiveAppTab] = useState<'deployments' | 'variables' | 'metrics' | 'settings'>('deployments');
    const [activeDbTab, setActiveDbTab] = useState<'data' | 'connect' | 'credentials' | 'backups' | 'extensions' | 'settings'>('connect');
    const [activeView, setActiveView] = useState<'architecture' | 'observability' | 'logs' | 'settings'>('architecture');
    const [hasStagedChanges, setHasStagedChanges] = useState(false);

    // Show loading state if project is not available
    if (!project) {
        return (
            <div className="flex h-screen items-center justify-center bg-background">
                <div className="text-center">
                    <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                    <p className="mt-4 text-foreground-muted">Loading project...</p>
                </div>
            </div>
        );
    }

    // Canvas control state
    const [showGrid, setShowGrid] = useState(true);
    const [zoomLevel, setZoomLevel] = useState(1);
    const [isFullscreen, setIsFullscreen] = useState(false);

    // Context menu state
    const [contextMenuPosition, setContextMenuPosition] = useState<ContextMenuPosition | null>(null);
    const [contextMenuNode, setContextMenuNode] = useState<ContextMenuNode | null>(null);

    // Logs viewer state
    const [logsViewerOpen, setLogsViewerOpen] = useState(false);
    const [logsViewerService, setLogsViewerService] = useState<string>('');
    const [logsViewerServiceUuid, setLogsViewerServiceUuid] = useState<string>('');
    const [logsViewerServiceType, setLogsViewerServiceType] = useState<'application' | 'database'>('application');

    // Undo/Redo history for canvas state
    const historyRef = useRef<{ past: SelectedService[][]; future: SelectedService[][] }>({
        past: [],
        future: [],
    });
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);

    // Add Service handler - navigate to service creation page
    const handleAddService = () => {
        if (selectedEnv) {
            router.visit(`/services/create?project=${project.uuid}&environment=${selectedEnv.uuid}`);
        } else {
            router.visit(`/services/create?project=${project.uuid}`);
        }
    };

    // Undo handler
    const handleUndo = () => {
        const { past, future } = historyRef.current;
        if (past.length === 0) return;

        const previous = past[past.length - 1];
        const newPast = past.slice(0, past.length - 1);

        historyRef.current = {
            past: newPast,
            future: [selectedService ? [selectedService] : [], ...future],
        };

        // Restore previous state
        if (previous.length > 0) {
            setSelectedService(previous[0]);
        } else {
            setSelectedService(null);
        }

        setCanUndo(newPast.length > 0);
        setCanRedo(true);
    };

    // Redo handler
    const handleRedo = () => {
        const { past, future } = historyRef.current;
        if (future.length === 0) return;

        const next = future[0];
        const newFuture = future.slice(1);

        historyRef.current = {
            past: [...past, selectedService ? [selectedService] : []],
            future: newFuture,
        };

        // Apply next state
        if (next.length > 0) {
            setSelectedService(next[0]);
        } else {
            setSelectedService(null);
        }

        setCanUndo(true);
        setCanRedo(newFuture.length > 0);
    };

    // Track state changes for undo/redo
    const trackStateChange = useCallback((_newService: SelectedService | null) => {
        historyRef.current = {
            past: [...historyRef.current.past, selectedService ? [selectedService] : []],
            future: [],
        };
        setCanUndo(true);
        setCanRedo(false);
    }, [selectedService]);
    // Note: trackStateChange is prepared for future use but currently unused
    void trackStateChange;

    const handleNodeClick = useCallback((id: string, type: string) => {
        const env = project.environments?.[0];
        if (!env) return;

        if (type === 'app') {
            const app = env.applications?.find(a => String(a.id) === id);
            if (app) {
                setSelectedService({
                    id: String(app.id),
                    uuid: app.uuid,
                    type: 'app',
                    name: app.name,
                    status: app.status || 'unknown',
                    fqdn: app.fqdn ?? undefined,
                    serverUuid: app.destination?.server?.uuid,
                });
            }
        } else if (type === 'db') {
            const db = env.databases?.find(d => String(d.id) === id);
            if (db) {
                setSelectedService({
                    id: String(db.id),
                    uuid: db.uuid,
                    type: 'db',
                    name: db.name,
                    status: db.status || 'unknown',
                    dbType: db.database_type,
                    serverUuid: db.destination?.server?.uuid,
                });
            }
        }
        // Reset to default tab based on type
        if (type === 'app') {
            setActiveAppTab('deployments');
        } else {
            setActiveDbTab('connect');
        }
    }, [project.environments]);

    const closePanel = () => setSelectedService(null);

    // Handle right-click on nodes
    const handleNodeContextMenu = useCallback((id: string, type: string, x: number, y: number) => {
        const env = project.environments?.[0];
        if (!env) return;

        let nodeData: ContextMenuNode | null = null;

        if (type === 'app') {
            const app = env.applications?.find(a => String(a.id) === id);
            if (app) {
                nodeData = {
                    id: String(app.id),
                    uuid: app.uuid,
                    type: 'app',
                    name: app.name,
                    status: app.status || 'unknown',
                    fqdn: app.fqdn ?? undefined,
                };
            }
        } else if (type === 'db') {
            const db = env.databases?.find(d => String(d.id) === id);
            if (db) {
                nodeData = {
                    id: String(db.id),
                    uuid: db.uuid,
                    type: 'db',
                    name: db.name,
                    status: db.status || 'unknown',
                };
            }
        }

        if (nodeData) {
            setContextMenuPosition({ x, y });
            setContextMenuNode(nodeData);
        }
    }, [project.environments]);

    const closeContextMenu = () => {
        setContextMenuPosition(null);
        setContextMenuNode(null);
    };

    const handleViewLogs = (_nodeId: string) => {
        const node = contextMenuNode;
        if (node) {
            setLogsViewerService(node.name);
            setLogsViewerServiceUuid(node.uuid);
            setLogsViewerServiceType(node.type === 'db' ? 'database' : 'application');
            setLogsViewerOpen(true);
        }
    };

    const handleCopyId = (_nodeId: string) => {
        navigator.clipboard.writeText(_nodeId);
    };

    const handleOpenUrl = (url: string) => {
        window.open(url, '_blank');
    };

    // API action handlers for context menu
    const handleDeploy = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        try {
            const response = await fetch(`/api/v1/applications/${node.uuid}/start`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to deploy');
            }

            router.reload();
        } catch (err) {
            console.error('Deploy error:', err);
            alert(err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [contextMenuNode]);

    const handleRestart = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}/restart`
            : `/api/v1/databases/${node.uuid}/restart`;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to restart');
            }

            router.reload();
        } catch (err) {
            console.error('Restart error:', err);
            alert(err instanceof Error ? err.message : 'Failed to restart');
        }
    }, [contextMenuNode]);

    const handleStop = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}/stop`
            : `/api/v1/databases/${node.uuid}/stop`;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to stop');
            }

            router.reload();
        } catch (err) {
            console.error('Stop error:', err);
            alert(err instanceof Error ? err.message : 'Failed to stop');
        }
    }, [contextMenuNode]);

    const handleDelete = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const confirmed = window.confirm(`Are you sure you want to delete "${node.name}"? This action cannot be undone.`);
        if (!confirmed) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}`
            : `/api/v1/databases/${node.uuid}`;

        try {
            const response = await fetch(endpoint, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to delete');
            }

            setSelectedService(null);
            setContextMenuNode(null);
            router.reload();
        } catch (err) {
            console.error('Delete error:', err);
            alert(err instanceof Error ? err.message : 'Failed to delete');
        }
    }, [contextMenuNode]);

    // Deploy all staged changes
    const handleDeployChanges = useCallback(async () => {
        const env = project.environments?.[0];
        if (!env?.applications?.length) {
            setHasStagedChanges(false);
            return;
        }

        try {
            // Deploy all applications in the environment
            const deployPromises = env.applications.map(app =>
                fetch(`/api/v1/applications/${app.uuid}/start`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                })
            );

            await Promise.all(deployPromises);
            setHasStagedChanges(false);
            router.reload();
        } catch (err) {
            console.error('Deploy changes error:', err);
            alert(err instanceof Error ? err.message : 'Failed to deploy changes');
        }
    }, [project.environments]);

    // Database backup handlers
    const handleCreateBackup = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node || node.type !== 'db') return;

        try {
            const response = await fetch(`/api/v1/databases/${node.uuid}/backups`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to create backup');
            }

            alert('Backup started successfully');
            router.reload();
        } catch (err) {
            console.error('Create backup error:', err);
            alert(err instanceof Error ? err.message : 'Failed to create backup');
        }
    }, [contextMenuNode]);

    const handleRestoreBackup = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node || node.type !== 'db') return;

        // For now, navigate to the backups tab where user can select a backup to restore
        setSelectedService({
            id: node.id,
            uuid: node.uuid,
            type: 'db',
            name: node.name,
            status: node.status,
        });
        setActiveDbTab('backups');
    }, [contextMenuNode]);

    // Canvas zoom controls
    const handleZoomIn = useCallback(() => {
        if (window.__projectCanvasZoomIn) {
            window.__projectCanvasZoomIn();
        }
    }, []);

    const handleZoomOut = useCallback(() => {
        if (window.__projectCanvasZoomOut) {
            window.__projectCanvasZoomOut();
        }
    }, []);

    const handleFitView = useCallback(() => {
        if (window.__projectCanvasFitView) {
            window.__projectCanvasFitView();
        }
    }, []);
    // Note: handleFitView is available for canvas controls
    void handleFitView;

    const handleViewportChange = useCallback((zoom: number) => {
        setZoomLevel(zoom);
    }, []);

    // Command palette handlers
    const handlePaletteDeploy = useCallback(async () => {
        if (!selectedService || selectedService.type !== 'app') {
            alert('Please select an application first');
            return;
        }
        try {
            const response = await fetch(`/api/v1/applications/${selectedService.uuid}/start`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to deploy');
            router.reload();
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to deploy');
        }
    }, [selectedService]);

    const handlePaletteRestart = useCallback(async () => {
        if (!selectedService) {
            alert('Please select a service first');
            return;
        }
        const endpoint = selectedService.type === 'app'
            ? `/api/v1/applications/${selectedService.uuid}/restart`
            : `/api/v1/databases/${selectedService.uuid}/restart`;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to restart');
            router.reload();
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to restart');
        }
    }, [selectedService]);

    const handlePaletteViewLogs = useCallback(() => {
        if (!selectedService) {
            alert('Please select a service first');
            return;
        }
        setLogsViewerService(selectedService.name);
        setLogsViewerServiceUuid(selectedService.uuid);
        setLogsViewerServiceType(selectedService.type === 'db' ? 'database' : 'application');
        setLogsViewerOpen(true);
    }, [selectedService]);

    // Prepare services for command palette
    const commandPaletteServices = [
        ...(selectedEnv?.applications || []).map((app) => ({
            id: String(app.id),
            name: app.name,
            type: 'application',
        })),
        ...(selectedEnv?.databases || []).map((db) => ({
            id: String(db.id),
            name: db.name,
            type: 'database',
        })),
    ];

    return (
        <ToastProvider>
            <Head title={`${project.name} | Saturn`} />
            <CommandPalette
                services={commandPaletteServices}
                onDeploy={handlePaletteDeploy}
                onRestart={handlePaletteRestart}
                onViewLogs={handlePaletteViewLogs}
                onAddService={handleAddService}
            />
            <div className="flex h-screen flex-col bg-background">
                {/* Top Header */}
                <header className="flex h-12 items-center justify-between border-b border-border bg-background px-4">
                    <div className="flex items-center gap-3">
                        <Link href="/projects" className="flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Projects
                        </Link>
                        <ChevronRight className="h-4 w-4 text-foreground-subtle" />
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-foreground">{project.name}</span>
                            <ChevronRight className="h-4 w-4 text-foreground-subtle" />
                            <span className="text-foreground-muted">{selectedEnv?.name || 'production'}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Command Palette Trigger */}
                        <button
                            onClick={() => {
                                // Dispatch a keyboard event to open the palette
                                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }));
                            }}
                            className="flex items-center gap-2 rounded-md border border-border bg-background-secondary px-3 py-1.5 text-sm text-foreground-muted hover:bg-background-tertiary hover:text-foreground transition-colors"
                        >
                            <Search className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Search...</span>
                            <kbd className="hidden sm:flex items-center gap-0.5 rounded bg-background px-1.5 py-0.5 text-xs">
                                <Command className="h-3 w-3" />K
                            </kbd>
                        </button>

                        <Dropdown>
                            <DropdownTrigger>
                                <button className="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm text-foreground-muted hover:bg-background-secondary hover:text-foreground">
                                    <span>{selectedEnv?.name || 'production'}</span>
                                    <ChevronDown className="h-4 w-4" />
                                </button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                {project.environments?.map((env) => (
                                    <DropdownItem key={env.id}>{env.name}</DropdownItem>
                                ))}
                                <DropdownDivider />
                                <DropdownItem>New Environment</DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                        <Link href={`/projects/${project.uuid}/settings`}>
                            <button className="rounded-md p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground">
                                <Settings className="h-4 w-4" />
                            </button>
                        </Link>
                    </div>
                </header>

                {/* View Tabs */}
                <div className="flex items-center gap-6 border-b border-border bg-background px-6">
                    <button
                        onClick={() => setActiveView('architecture')}
                        className={`py-3 text-sm font-medium transition-colors ${
                            activeView === 'architecture'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Architecture
                    </button>
                    <button
                        onClick={() => setActiveView('observability')}
                        className={`py-3 text-sm font-medium transition-colors ${
                            activeView === 'observability'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Observability
                    </button>
                    <button
                        onClick={() => setActiveView('logs')}
                        className={`py-3 text-sm font-medium transition-colors ${
                            activeView === 'logs'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Logs
                    </button>
                    <button
                        onClick={() => setActiveView('settings')}
                        className={`py-3 text-sm font-medium transition-colors ${
                            activeView === 'settings'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Settings
                    </button>
                </div>

                {/* Staged Changes Banner */}
                {hasStagedChanges && (
                    <div className="flex items-center justify-between border-b border-yellow-500/20 bg-yellow-500/5 px-6 py-3">
                        <div className="flex items-center gap-3">
                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-yellow-500/20">
                                <GitBranch className="h-3.5 w-3.5 text-yellow-500" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    You have staged changes
                                </p>
                                <p className="text-xs text-foreground-muted">
                                    3 environment variables modified, 1 service configuration updated
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => setHasStagedChanges(false)}
                            >
                                Discard
                            </Button>
                            <Button
                                size="sm"
                                onClick={handleDeployChanges}
                            >
                                <Play className="mr-2 h-3 w-3" />
                                Deploy Changes
                            </Button>
                        </div>
                    </div>
                )}

                {/* Main Content Area */}
                <div className="flex flex-1 overflow-hidden">
                    {/* Left Toolbar - Premium Style */}
                    <div className="flex w-14 flex-col items-center gap-1 border-r border-white/[0.06] bg-[#0d0d15] py-3">
                        {/* Add Service Button */}
                        <button
                            onClick={handleAddService}
                            className="group relative rounded-xl p-2.5 text-gray-400 transition-all duration-200 hover:bg-white/[0.06] hover:text-white hover:shadow-lg"
                            title="Add Service"
                        >
                            <Plus className="h-5 w-5" />
                            <span className="absolute left-full ml-2 hidden whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-xs text-white shadow-xl group-hover:block">
                                Add Service
                            </span>
                        </button>

                        <div className="my-2 h-px w-8 bg-white/[0.06]" />

                        {/* Toggle Grid */}
                        <button
                            onClick={() => setShowGrid(!showGrid)}
                            className={`group relative rounded-xl p-2.5 transition-all duration-200 ${
                                showGrid
                                    ? 'bg-primary/20 text-primary shadow-lg shadow-primary/20'
                                    : 'text-gray-400 hover:bg-white/[0.06] hover:text-white'
                            }`}
                            title="Toggle Grid"
                        >
                            <Grid3x3 className="h-5 w-5" />
                        </button>

                        {/* Zoom In */}
                        <button
                            onClick={handleZoomIn}
                            className="group relative rounded-xl p-2.5 text-gray-400 transition-all duration-200 hover:bg-white/[0.06] hover:text-white"
                            title="Zoom In"
                        >
                            <ZoomIn className="h-5 w-5" />
                        </button>

                        {/* Zoom Level Indicator */}
                        <div className="text-xs font-medium text-gray-500">
                            {Math.round(zoomLevel * 100)}%
                        </div>

                        {/* Zoom Out */}
                        <button
                            onClick={handleZoomOut}
                            className="group relative rounded-xl p-2.5 text-gray-400 transition-all duration-200 hover:bg-white/[0.06] hover:text-white"
                            title="Zoom Out"
                        >
                            <ZoomOut className="h-5 w-5" />
                        </button>

                        {/* Fullscreen */}
                        <button
                            onClick={() => {
                                if (!document.fullscreenElement) {
                                    document.documentElement.requestFullscreen();
                                    setIsFullscreen(true);
                                } else {
                                    document.exitFullscreen();
                                    setIsFullscreen(false);
                                }
                            }}
                            className={`group relative rounded-xl p-2.5 transition-all duration-200 ${
                                isFullscreen
                                    ? 'bg-primary/20 text-primary shadow-lg shadow-primary/20'
                                    : 'text-gray-400 hover:bg-white/[0.06] hover:text-white'
                            }`}
                            title="Fullscreen"
                        >
                            <Maximize2 className="h-5 w-5" />
                        </button>

                        <div className="my-2 h-px w-8 bg-white/[0.06]" />

                        {/* Undo */}
                        <button
                            onClick={handleUndo}
                            disabled={!canUndo}
                            className="group relative rounded-xl p-2.5 text-gray-500 transition-all duration-200 hover:bg-white/[0.06] hover:text-gray-300"
                            title="Undo"
                        >
                            <Undo2 className="h-5 w-5" />
                        </button>

                        {/* Redo */}
                        <button
                            onClick={handleRedo}
                            disabled={!canRedo}
                            className="group relative rounded-xl p-2.5 text-gray-500 transition-all duration-200 hover:bg-white/[0.06] hover:text-gray-300"
                            title="Redo"
                        >
                            <Redo2 className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Canvas Area */}
                    <div className="relative flex-1 overflow-hidden">
                        {selectedEnv && (
                            <ProjectCanvas
                                applications={selectedEnv.applications || []}
                                databases={selectedEnv.databases || []}
                                services={selectedEnv.services || []}
                                environmentUuid={selectedEnv.uuid}
                                onNodeClick={handleNodeClick}
                                onNodeContextMenu={handleNodeContextMenu}
                                onViewportChange={handleViewportChange}
                            />
                        )}

                        {/* Canvas Overlay Buttons */}
                        <div className="absolute right-4 top-4 z-10">
                            <Dropdown>
                                <DropdownTrigger>
                                    <Button size="sm" className="shadow-lg">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create
                                        <ChevronDown className="ml-2 h-3 w-3" />
                                    </Button>
                                </DropdownTrigger>
                                <DropdownContent align="right" className="w-64">
                                    {/* GitHub */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit(`/applications/create?source=github&project=${project.uuid}&environment=${selectedEnv?.uuid}`)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#24292e]">
                                            <svg viewBox="0 0 24 24" className="h-4 w-4" fill="#fff">
                                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">GitHub Repo</p>
                                            <p className="text-xs text-foreground-muted">Deploy from a repository</p>
                                        </div>
                                    </DropdownItem>

                                    {/* Docker Image */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit(`/applications/create?source=docker&project=${project.uuid}&environment=${selectedEnv?.uuid}`)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#2496ED]">
                                            <Box className="h-4 w-4 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">Docker Image</p>
                                            <p className="text-xs text-foreground-muted">Deploy from Docker Hub</p>
                                        </div>
                                    </DropdownItem>

                                    <DropdownDivider />

                                    {/* Database */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit(`/databases/create?project=${project.uuid}&environment=${selectedEnv?.uuid}`)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#336791]">
                                            <Database className="h-4 w-4 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">Database</p>
                                            <p className="text-xs text-foreground-muted">PostgreSQL, MySQL, Redis...</p>
                                        </div>
                                    </DropdownItem>

                                    <DropdownDivider />

                                    {/* Empty Service */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit(`/services/create?project=${project.uuid}&environment=${selectedEnv?.uuid}`)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-600">
                                            <Box className="h-4 w-4 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">Empty Service</p>
                                            <p className="text-xs text-foreground-muted">Configure later</p>
                                        </div>
                                    </DropdownItem>

                                    {/* Template */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit('/templates')}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-500">
                                            <Layers className="h-4 w-4 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">Template</p>
                                            <p className="text-xs text-foreground-muted">1800+ starter templates</p>
                                        </div>
                                    </DropdownItem>
                                </DropdownContent>
                            </Dropdown>
                        </div>

                        <div className="absolute bottom-4 left-4 z-10">
                            <button className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground-muted shadow-lg transition-colors hover:bg-background-secondary hover:text-foreground">
                                <Terminal className="h-4 w-4" />
                                Set up your project locally
                            </button>
                        </div>

                        {/* Activity Panel */}
                        <ActivityPanel />
                    </div>

                    {/* Right Panel - Service Details */}
                    {selectedService && (
                        <div className="flex w-[560px] flex-col border-l border-border bg-background">
                            {/* Panel Header */}
                            <div className="border-b border-border px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {/* Database Logo or App Icon */}
                                        {selectedService.type === 'db' ? (
                                            <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${getDbBgColor(selectedService.dbType)}`}>
                                                {getDbLogo(selectedService.dbType)}
                                            </div>
                                        ) : (
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-500/20 text-cyan-400">
                                                <Box className="h-5 w-5" />
                                            </div>
                                        )}
                                        <div>
                                            <span className="font-medium text-foreground">{selectedService.name}</span>
                                            <div className="flex items-center gap-1.5 text-xs text-foreground-muted">
                                                <div className={`h-1.5 w-1.5 rounded-full ${selectedService.status === 'running' ? 'bg-green-500' : 'bg-gray-500'}`} />
                                                <span className="capitalize">{selectedService.status}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button onClick={closePanel} className="rounded p-1 text-foreground-muted hover:bg-background-secondary hover:text-foreground">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>

                                {/* Domain/URL Info */}
                                {selectedService.fqdn && (
                                    <div className="mt-3 flex items-center gap-2 rounded-lg bg-background-secondary p-2">
                                        <Globe className="h-4 w-4 text-foreground-muted" />
                                        <code className="flex-1 truncate text-sm text-foreground">{selectedService.fqdn.replace(/^https?:\/\//, '')}</code>
                                        <button
                                            onClick={() => {
                                                const domain = selectedService.fqdn?.replace(/^https?:\/\//, '') || '';
                                                navigator.clipboard.writeText(domain);
                                                alert('URL copied to clipboard');
                                            }}
                                            className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                            title="Copy URL"
                                        >
                                            <Copy className="h-3 w-3" />
                                        </button>
                                        <a href={selectedService.fqdn.startsWith('http') ? selectedService.fqdn : `https://${selectedService.fqdn}`} target="_blank" rel="noopener noreferrer" className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground">
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    </div>
                                )}

                                {/* Region & Replica Info */}
                                <div className="mt-2 flex items-center gap-2 text-xs text-foreground-muted">
                                    <span className="flex items-center gap-1">
                                        <Globe className="h-3 w-3" />
                                        us-east4
                                    </span>
                                    <span>·</span>
                                    <span className="flex items-center gap-1">
                                        <Users className="h-3 w-3" />
                                        1 Replica
                                    </span>
                                </div>
                            </div>

                            {/* Panel Tabs - Different for Apps vs Databases */}
                            {selectedService.type === 'app' ? (
                                /* Application Tabs */
                                <div className="flex border-b border-border">
                                    <button
                                        onClick={() => setActiveAppTab('deployments')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'deployments' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Activity className="h-4 w-4" />
                                        Deployments
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('variables')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'variables' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Variable className="h-4 w-4" />
                                        Variables
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('metrics')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'metrics' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Gauge className="h-4 w-4" />
                                        Metrics
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('settings')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'settings' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Cog className="h-4 w-4" />
                                        Settings
                                    </button>
                                </div>
                            ) : (
                                /* Database Tabs */
                                <div className="flex border-b border-border overflow-x-auto">
                                    <button
                                        onClick={() => setActiveDbTab('data')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'data' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Table className="h-4 w-4" />
                                        Data
                                    </button>
                                    <button
                                        onClick={() => setActiveDbTab('connect')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'connect' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Link2 className="h-4 w-4" />
                                        Connect
                                    </button>
                                    <button
                                        onClick={() => setActiveDbTab('credentials')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'credentials' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Key className="h-4 w-4" />
                                        Credentials
                                    </button>
                                    <button
                                        onClick={() => setActiveDbTab('backups')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'backups' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <HardDrive className="h-4 w-4" />
                                        Backups
                                    </button>
                                    {/* Extensions tab - only for PostgreSQL */}
                                    {(selectedService.dbType?.toLowerCase() === 'postgresql' || selectedService.dbType?.toLowerCase() === 'postgres') && (
                                        <button
                                            onClick={() => setActiveDbTab('extensions')}
                                            className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'extensions' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                        >
                                            <Layers className="h-4 w-4" />
                                            Extensions
                                        </button>
                                    )}
                                    <button
                                        onClick={() => setActiveDbTab('settings')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'settings' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Cog className="h-4 w-4" />
                                        Settings
                                    </button>
                                </div>
                            )}

                            {/* Panel Content */}
                            <div className="flex-1 overflow-auto p-4">
                                {selectedService.type === 'app' ? (
                                    /* Application Content */
                                    <>
                                        {activeAppTab === 'deployments' && <DeploymentsTab service={selectedService} />}
                                        {activeAppTab === 'variables' && <VariablesTab service={selectedService} />}
                                        {activeAppTab === 'metrics' && <MetricsTab service={selectedService} />}
                                        {activeAppTab === 'settings' && <AppSettingsTab service={selectedService} />}
                                    </>
                                ) : (
                                    /* Database Content */
                                    <>
                                        {activeDbTab === 'data' && <DatabaseDataTab service={selectedService} />}
                                        {activeDbTab === 'connect' && <DatabaseConnectTab service={selectedService} />}
                                        {activeDbTab === 'credentials' && <DatabaseCredentialsTab service={selectedService} />}
                                        {activeDbTab === 'backups' && <DatabaseBackupsTab service={selectedService} />}
                                        {activeDbTab === 'extensions' && <DatabaseExtensionsTab service={selectedService} />}
                                        {activeDbTab === 'settings' && <DatabaseSettingsTab service={selectedService} />}
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Context Menu for right-click on nodes */}
            <ContextMenu
                position={contextMenuPosition}
                node={contextMenuNode}
                onClose={closeContextMenu}
                onDeploy={handleDeploy}
                onRestart={handleRestart}
                onStop={handleStop}
                onViewLogs={handleViewLogs}
                onOpenSettings={(_id) => {
                    // Open the panel with settings tab
                    if (contextMenuNode) {
                        setSelectedService({
                            id: contextMenuNode.id,
                            uuid: contextMenuNode.uuid,
                            type: contextMenuNode.type,
                            name: contextMenuNode.name,
                            status: contextMenuNode.status,
                            fqdn: contextMenuNode.fqdn,
                        });
                        if (contextMenuNode.type === 'app') {
                            setActiveAppTab('settings');
                        } else {
                            setActiveDbTab('settings');
                        }
                    }
                }}
                onDelete={handleDelete}
                onCopyId={handleCopyId}
                onOpenUrl={handleOpenUrl}
                onCreateBackup={handleCreateBackup}
                onRestoreBackup={handleRestoreBackup}
            />

            {/* Logs Viewer Modal */}
            <LogsViewer
                isOpen={logsViewerOpen}
                onClose={() => setLogsViewerOpen(false)}
                serviceName={logsViewerService}
                serviceUuid={logsViewerServiceUuid}
                serviceType={logsViewerServiceType}
            />
        </ToastProvider>
    );
}

function DeploymentsTab({ service }: { service: SelectedService }) {
    const { deployments, isLoading, refetch, startDeployment, cancelDeployment } = useDeployments({
        applicationUuid: service.uuid,
        autoRefresh: true,
        refreshInterval: 5000,
    });
    const { toast } = useToast();
    const confirm = useConfirm();
    const [isDeploying, setIsDeploying] = useState(false);
    const [cancellingId, setCancellingId] = useState<string | null>(null);
    const [rollingBackId, setRollingBackId] = useState<string | null>(null);

    // Map API status to display status
    const getDisplayStatus = (status: string) => {
        switch (status) {
            case 'finished':
                return 'active';
            case 'in_progress':
            case 'queued':
                return 'building';
            case 'failed':
                return 'crashed';
            case 'cancelled':
                return 'cancelled';
            default:
                return status;
        }
    };

    const getBadgeStyle = (status: string) => {
        const displayStatus = getDisplayStatus(status);
        switch (displayStatus) {
            case 'active':
                return 'bg-green-500/10 text-green-500 border border-green-500/20';
            case 'building':
                return 'bg-blue-500/10 text-blue-500 border border-blue-500/20';
            case 'crashed':
                return 'bg-red-500/10 text-red-500 border border-red-500/20';
            case 'cancelled':
                return 'bg-orange-500/10 text-orange-500 border border-orange-500/20';
            default:
                return 'bg-gray-500/10 text-gray-500 border border-gray-500/20';
        }
    };

    const isInProgress = (status: string) => ['in_progress', 'queued'].includes(status);
    const canRollback = (status: string) => ['finished', 'failed'].includes(status);

    // Format time ago
    const formatTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins} min ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    };

    const handleDeploy = async () => {
        setIsDeploying(true);
        try {
            await startDeployment(service.uuid);
            toast({ title: 'Deployment started', variant: 'success' });
        } catch (err) {
            toast({
                title: 'Failed to deploy',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setIsDeploying(false);
        }
    };

    const handleViewLogs = (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        router.visit(`/applications/${service.uuid}/deployments/${deploymentUuid}`);
    };

    const handleRestart = async () => {
        try {
            const response = await fetch(`/api/v1/applications/${service.uuid}/restart`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to restart');
            toast({ title: 'Restart initiated', variant: 'success' });
            refetch();
        } catch (err) {
            toast({
                title: 'Failed to restart',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        }
    };

    const handleRollback = async (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        const confirmed = await confirm({
            title: 'Rollback deployment',
            description: `Are you sure you want to rollback to this deployment? This will redeploy the application with the previous configuration.`,
            confirmText: 'Rollback',
            variant: 'warning',
        });

        if (!confirmed) return;

        setRollingBackId(deploymentUuid);
        try {
            const response = await fetch(`/api/v1/applications/${service.uuid}/rollback/${deploymentUuid}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to rollback');
            }

            toast({ title: 'Rollback initiated', variant: 'success' });
            refetch();
        } catch (err) {
            toast({
                title: 'Failed to rollback',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setRollingBackId(null);
        }
    };

    const handleCancel = async (deployment: Deployment) => {
        const deploymentUuid = deployment.deployment_uuid || deployment.uuid;
        const confirmed = await confirm({
            title: 'Cancel deployment',
            description: 'Are you sure you want to cancel this deployment?',
            confirmText: 'Cancel Deployment',
            variant: 'danger',
        });

        if (!confirmed) return;

        setCancellingId(deploymentUuid);
        try {
            await cancelDeployment(deploymentUuid);
            toast({ title: 'Deployment cancelled', variant: 'success' });
        } catch (err) {
            toast({
                title: 'Failed to cancel',
                description: err instanceof Error ? err.message : 'Unknown error',
                variant: 'error',
            });
        } finally {
            setCancellingId(null);
        }
    };

    if (isLoading && deployments.length === 0) {
        return (
            <div className="space-y-4">
                <Button className="w-full" onClick={handleDeploy} disabled={isDeploying}>
                    <Play className="mr-2 h-4 w-4" />
                    {isDeploying ? 'Starting...' : 'Deploy Now'}
                </Button>
                <div className="flex items-center justify-center py-8 text-foreground-muted">
                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                    Loading deployments...
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Deploy Button */}
            <Button className="w-full" onClick={handleDeploy} disabled={isDeploying}>
                <Play className="mr-2 h-4 w-4" />
                {isDeploying ? 'Starting...' : 'Deploy Now'}
            </Button>

            {/* Deployments List */}
            <div className="space-y-4">
                {deployments.length === 0 ? (
                    <div className="text-center py-8 text-foreground-muted">
                        No deployments yet. Click "Deploy Now" to start your first deployment.
                    </div>
                ) : (
                    deployments.map((deploy, index) => {
                        const deploymentUuid = deploy.deployment_uuid || deploy.uuid;
                        const displayStatus = getDisplayStatus(deploy.status);

                        return (
                            <div key={deploy.id}>
                                {index === 0 && (
                                    <div className="mb-3 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-foreground-subtle">
                                        <FileText className="h-3 w-3" />
                                        Recent
                                    </div>
                                )}
                                {index === 2 && (
                                    <div className="mb-3 mt-6 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-foreground-subtle">
                                        <Clock className="h-3 w-3" />
                                        History
                                    </div>
                                )}
                                <div className="space-y-3 rounded-lg border border-border bg-background-secondary p-4">
                                    {/* Status Badge & Actions Menu */}
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className={`rounded-md px-2 py-1 text-xs font-medium uppercase ${getBadgeStyle(deploy.status)}`}>
                                                {displayStatus}
                                            </span>
                                            <span className="text-xs text-foreground-muted">
                                                {formatTimeAgo(deploy.created_at)}
                                            </span>
                                        </div>

                                        {/* Three-dot Actions Menu */}
                                        <Dropdown>
                                            <DropdownTrigger>
                                                <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground transition-colors">
                                                    <MoreVertical className="h-4 w-4" />
                                                </button>
                                            </DropdownTrigger>
                                            <DropdownContent align="right">
                                                <DropdownItem
                                                    onClick={() => handleViewLogs(deploy)}
                                                    icon={<Eye className="h-4 w-4" />}
                                                >
                                                    View Logs
                                                </DropdownItem>

                                                {deploy.status === 'finished' && index === 0 && (
                                                    <DropdownItem
                                                        onClick={handleRestart}
                                                        icon={<RefreshCw className="h-4 w-4" />}
                                                    >
                                                        Restart
                                                    </DropdownItem>
                                                )}

                                                <DropdownItem
                                                    onClick={handleDeploy}
                                                    icon={<Play className="h-4 w-4" />}
                                                >
                                                    Redeploy
                                                </DropdownItem>

                                                {canRollback(deploy.status) && index !== 0 && (
                                                    <DropdownItem
                                                        onClick={() => handleRollback(deploy)}
                                                        disabled={rollingBackId === deploymentUuid}
                                                        icon={<RotateCcw className={`h-4 w-4 ${rollingBackId === deploymentUuid ? 'animate-spin' : ''}`} />}
                                                    >
                                                        {rollingBackId === deploymentUuid ? 'Rolling back...' : 'Rollback to this'}
                                                    </DropdownItem>
                                                )}

                                                {isInProgress(deploy.status) && (
                                                    <>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            onClick={() => handleCancel(deploy)}
                                                            disabled={cancellingId === deploymentUuid}
                                                            danger
                                                            icon={<StopCircle className="h-4 w-4" />}
                                                        >
                                                            {cancellingId === deploymentUuid ? 'Cancelling...' : 'Cancel'}
                                                        </DropdownItem>
                                                    </>
                                                )}
                                            </DropdownContent>
                                        </Dropdown>
                                    </div>

                                    {/* Commit Info */}
                                    <div className="flex items-start gap-3">
                                        <div className="h-6 w-6 rounded-full bg-foreground-muted/20 flex items-center justify-center">
                                            <GitCommit className="h-3 w-3 text-foreground-muted" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-foreground truncate">
                                                {deploy.commit_message || 'Manual deployment'}
                                            </p>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                                <GitCommit className="h-3 w-3" />
                                                <code>{deploy.commit?.slice(0, 7) || deploymentUuid.slice(0, 7)}</code>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Deployment Progress */}
                                    {isInProgress(deploy.status) && (
                                        <div className="rounded-md bg-background p-3">
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500" />
                                                <p className="text-xs text-foreground-muted">
                                                    {deploy.status === 'queued' ? 'Waiting in queue...' : 'Deployment in progress...'}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {/* Quick Actions Row */}
                                    <div className="flex gap-2">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            className="flex-1"
                                            onClick={() => handleViewLogs(deploy)}
                                        >
                                            <Eye className="mr-2 h-3 w-3" />
                                            Logs
                                        </Button>
                                        {deploy.status === 'finished' && index === 0 && (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                className="flex-1"
                                                onClick={handleRestart}
                                            >
                                                <RefreshCw className="mr-2 h-3 w-3" />
                                                Restart
                                            </Button>
                                        )}
                                        {isInProgress(deploy.status) && (
                                            <Button
                                                variant="danger"
                                                size="sm"
                                                onClick={() => handleCancel(deploy)}
                                                disabled={cancellingId === deploymentUuid}
                                            >
                                                <StopCircle className="mr-2 h-3 w-3" />
                                                {cancellingId === deploymentUuid ? 'Cancelling...' : 'Cancel'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}

interface EnvVariable {
    id: number;
    uuid: string;
    key: string;
    value: string;
    real_value?: string;
    is_preview?: boolean;
    is_shown_once?: boolean;
}

function VariablesTab({ service }: { service: SelectedService }) {
    const { toast } = useToast();
    const [variables, setVariables] = useState<EnvVariable[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [revealedIds, setRevealedIds] = useState<Set<number>>(new Set());

    // Fetch environment variables
    useEffect(() => {
        const fetchEnvs = async () => {
            try {
                setIsLoading(true);
                const response = await fetch(`/api/v1/applications/${service.uuid}/envs`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include',
                });
                if (response.ok) {
                    const data = await response.json();
                    setVariables(data.filter((env: EnvVariable) => !env.is_preview));
                }
            } catch {
                toast({ title: 'Failed to load variables', variant: 'error' });
            } finally {
                setIsLoading(false);
            }
        };
        fetchEnvs();
    }, [service.uuid, toast]);

    const handleAddVariable = async () => {
        if (!newKey.trim()) {
            toast({ title: 'Key is required', variant: 'error' });
            return;
        }
        try {
            setIsSubmitting(true);
            const response = await fetch(`/api/v1/applications/${service.uuid}/envs`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ key: newKey, value: newValue }),
            });
            if (response.ok) {
                const created = await response.json();
                setVariables(prev => [...prev, created]);
                setShowAddModal(false);
                setNewKey('');
                setNewValue('');
                toast({ title: 'Variable created' });
            } else {
                const error = await response.json();
                toast({ title: error.message || 'Failed to create variable', variant: 'error' });
            }
        } catch {
            toast({ title: 'Failed to create variable', variant: 'error' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleCopyVariable = async (key: string, value: string) => {
        try {
            await navigator.clipboard.writeText(`${key}=${value}`);
            toast({ title: `Copied ${key} to clipboard` });
        } catch {
            toast({ title: 'Failed to copy', variant: 'error' });
        }
    };

    const toggleReveal = (id: number) => {
        setRevealedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const maskValue = (value: string) => '•'.repeat(Math.min(value.length, 12));

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-8">
                <RefreshCw className="h-5 w-5 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Environment Variables</h3>
                <Button size="sm" variant="secondary" onClick={() => setShowAddModal(true)}>
                    <Plus className="mr-1 h-3 w-3" />
                    Add
                </Button>
            </div>

            {/* Add Variable Modal */}
            {showAddModal && (
                <div className="rounded-lg border border-border bg-background p-4 space-y-3">
                    <h4 className="text-sm font-medium">Add Environment Variable</h4>
                    <div className="space-y-2">
                        <input
                            type="text"
                            placeholder="KEY_NAME"
                            value={newKey}
                            onChange={(e) => setNewKey(e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''))}
                            className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm font-mono"
                        />
                        <textarea
                            placeholder="Value"
                            value={newValue}
                            onChange={(e) => setNewValue(e.target.value)}
                            rows={2}
                            className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm font-mono"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={handleAddVariable} disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create'}
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setShowAddModal(false)}>
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            <div className="space-y-2">
                {variables.length === 0 ? (
                    <p className="text-sm text-foreground-muted py-4 text-center">No environment variables configured</p>
                ) : (
                    variables.map((v) => (
                        <div key={v.id} className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex-1 min-w-0">
                                <code className="text-sm font-medium text-foreground">{v.key}</code>
                                <p className="text-sm text-foreground-muted font-mono truncate">
                                    {revealedIds.has(v.id) ? (v.real_value || v.value) : maskValue(v.real_value || v.value)}
                                </p>
                            </div>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => toggleReveal(v.id)}
                                    className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title={revealedIds.has(v.id) ? 'Hide value' : 'Show value'}
                                >
                                    {revealedIds.has(v.id) ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => handleCopyVariable(v.key, v.real_value || v.value)}
                                    className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy to clipboard"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

function MetricsTab({ service }: { service: SelectedService }) {
    const [timeRange, setTimeRange] = useState<'1h' | '24h' | '7d' | '30d'>('24h');

    // Use the Sentinel metrics hook with the real server UUID
    const { metrics, historicalData, isLoading, error, refetch } = useSentinelMetrics({
        serverUuid: service.serverUuid || '',
        timeRange,
        autoRefresh: !!service.serverUuid,
        refreshInterval: 30000, // 30 seconds
    });

    const renderMiniChart = (data: number[], color: string, max: number = 100) => {
        const height = 40;
        const width = 200;
        const points = data.map((value, i) => {
            const x = (i / (data.length - 1)) * width;
            const y = height - (value / max) * height;
            return `${x},${y}`;
        }).join(' ');

        return (
            <svg viewBox={`0 0 ${width} ${height}`} className="h-10 w-full">
                <polyline
                    fill="none"
                    stroke={color}
                    strokeWidth="2"
                    points={points}
                />
                <polygon
                    fill={`${color}20`}
                    points={`0,${height} ${points} ${width},${height}`}
                />
            </svg>
        );
    };

    // Extract data from hook
    const cpuData = historicalData?.cpu?.data?.map(d => d.value) || [];
    const memoryData = historicalData?.memory?.data?.map(d => d.value) || [];
    const networkData = historicalData?.network?.data?.map(d => d.value) || [];

    // Show message if no server is associated
    if (!service.serverUuid) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center">
                <Gauge className="mb-4 h-12 w-12 text-foreground-muted opacity-50" />
                <p className="text-foreground-muted">Metrics unavailable</p>
                <p className="mt-1 text-sm text-foreground-subtle">
                    No server associated with this service
                </p>
            </div>
        );
    }

    if (isLoading && !metrics) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Time Range Selector */}
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Resource Usage</h3>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => refetch()}
                        className="rounded p-1 text-foreground-muted hover:text-foreground"
                        title="Refresh metrics"
                    >
                        <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                    </button>
                    <div className="flex gap-1 rounded-lg bg-background-secondary p-1">
                        {(['1h', '24h', '7d', '30d'] as const).map((range) => (
                            <button
                                key={range}
                                onClick={() => setTimeRange(range)}
                                className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                                    timeRange === range
                                        ? 'bg-primary text-white'
                                        : 'text-foreground-muted hover:text-foreground'
                                }`}
                            >
                                {range}
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Using cached/demo metrics. Real-time data unavailable.
                </div>
            )}

            {/* CPU Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                        <span className="text-sm font-medium text-foreground">CPU Usage</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-foreground">{metrics?.cpu?.current || '0%'}</span>
                        <span className="text-xs text-foreground-muted">of 1 vCPU</span>
                    </div>
                </div>
                {cpuData.length > 0 && renderMiniChart(cpuData, '#3b82f6')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: {historicalData?.cpu?.average || 'N/A'}</span>
                    <span>Max: {historicalData?.cpu?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Memory Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-emerald-500" />
                        <span className="text-sm font-medium text-foreground">Memory Usage</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-foreground">{metrics?.memory?.current || '0 GB'}</span>
                        <span className="text-xs text-foreground-muted">of {metrics?.memory?.total || '512 MB'}</span>
                    </div>
                </div>
                {memoryData.length > 0 && renderMiniChart(memoryData, '#10b981')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: {historicalData?.memory?.average || 'N/A'}</span>
                    <span>Max: {historicalData?.memory?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Network I/O */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-purple-500" />
                        <span className="text-sm font-medium text-foreground">Network I/O</span>
                    </div>
                    <div className="flex items-center gap-4 text-xs">
                        <span className="flex items-center gap-1">
                            <span className="h-2 w-2 rounded-full bg-purple-500" />
                            In: {metrics?.network?.in || '0 MB/s'}
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="h-2 w-2 rounded-full bg-pink-500" />
                            Out: {metrics?.network?.out || '0 MB/s'}
                        </span>
                    </div>
                </div>
                {networkData.length > 0 && (
                    <div className="relative">
                        {renderMiniChart(networkData, '#a855f7', 100)}
                    </div>
                )}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: {historicalData?.network?.average || 'N/A'}</span>
                    <span>Peak: {historicalData?.network?.peak || 'N/A'}</span>
                </div>
            </div>

            {/* Disk Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <HardDrive className="h-4 w-4 text-foreground-muted" />
                        <span className="text-sm font-medium text-foreground">Disk Usage</span>
                    </div>
                    <span className="text-sm text-foreground">
                        {metrics?.disk?.current || '0 GB'} / {metrics?.disk?.total || '5 GB'}
                    </span>
                </div>
                <div className="h-2 w-full rounded-full bg-background">
                    <div
                        className="h-2 rounded-full bg-orange-500 transition-all"
                        style={{ width: `${metrics?.disk?.percentage || 0}%` }}
                    />
                </div>
                <p className="mt-2 text-xs text-foreground-muted">
                    {metrics?.disk?.percentage || 0}% used
                </p>
            </div>

            {/* Request Stats */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <h4 className="mb-3 text-sm font-medium text-foreground">Request Stats ({timeRange})</h4>
                <div className="grid grid-cols-3 gap-4">
                    <div className="text-center">
                        <p className="text-2xl font-bold text-foreground">--</p>
                        <p className="text-xs text-foreground-muted">Total Requests</p>
                    </div>
                    <div className="text-center">
                        <p className="text-2xl font-bold text-green-500">--</p>
                        <p className="text-xs text-foreground-muted">Success Rate</p>
                    </div>
                    <div className="text-center">
                        <p className="text-2xl font-bold text-foreground">--</p>
                        <p className="text-xs text-foreground-muted">Avg Latency</p>
                    </div>
                </div>
                <p className="mt-3 text-center text-xs text-foreground-subtle">
                    Request metrics require application instrumentation
                </p>
            </div>
        </div>
    );
}

function AppSettingsTab({ service }: { service: SelectedService }) {
    const [cronEnabled, setCronEnabled] = useState(false);
    const [cronExpression, setCronExpression] = useState('0 * * * *');
    const [healthCheckEnabled, setHealthCheckEnabled] = useState(true);
    const [healthEndpoint, setHealthEndpoint] = useState('/health');
    const [healthTimeout, setHealthTimeout] = useState(10);
    const [healthInterval, setHealthInterval] = useState(30);
    const [replicas, setReplicas] = useState(1);
    const [isSaving, setIsSaving] = useState(false);

    const handleReplicasChange = async (newReplicas: number) => {
        if (newReplicas < 1) return;
        setReplicas(newReplicas);
    };

    const handleSaveSettings = async () => {
        if (!service.uuid) {
            alert('Cannot save: service UUID not available');
            return;
        }

        setIsSaving(true);
        try {
            const response = await fetch(`/api/v1/applications/${service.uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    health_check_enabled: healthCheckEnabled,
                    health_check_path: healthEndpoint,
                    health_check_timeout: healthTimeout,
                    health_check_interval: healthInterval,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save settings');
            }

            alert('Settings saved successfully');
        } catch (err) {
            console.error('Save settings error:', err);
            alert(err instanceof Error ? err.message : 'Failed to save settings');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Source Section */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Source</h3>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded bg-[#24292e]">
                            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="#fff">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-foreground">GitHub Repository</p>
                            <p className="text-xs text-foreground-muted">saturn/api-server • main</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Build Configuration */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Build</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Builder</span>
                            <span className="text-sm text-foreground">Dockerfile</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Root Directory</span>
                            <span className="text-sm text-foreground">/</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Networking Section - Railway Style */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Networking</h3>
                <div className="space-y-4">
                    {/* Public Networking */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-primary" />
                                <span className="text-sm font-medium text-foreground">Public Networking</span>
                            </div>
                            <Badge variant="success" size="sm">Enabled</Badge>
                        </div>

                        {/* Railway-provided domain */}
                        <div className="mb-3">
                            <p className="mb-1 text-xs text-foreground-muted">Railway-provided domain</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 rounded bg-background px-2 py-1 text-sm text-foreground">
                                    {service.name || 'api-server'}-production.up.railway.app
                                </code>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(`${service.name || 'api-server'}-production.up.railway.app`);
                                        alert('Domain copied to clipboard');
                                    }}
                                    className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy domain"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                                <a href={`https://${service.name || 'api-server'}-production.up.railway.app`} target="_blank" rel="noopener noreferrer" className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground">
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                        </div>

                        {/* Custom domains */}
                        <div>
                            <p className="mb-2 text-xs text-foreground-muted">Custom domains</p>
                            {service.fqdn && (
                                <div className="mb-2 flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    <code className="flex-1 text-sm text-foreground">{service.fqdn}</code>
                                    <Badge variant="success" size="sm">SSL</Badge>
                                    <button
                                        onClick={() => {
                                            if (window.confirm(`Delete domain ${service.fqdn}?`)) {
                                                alert('Domain deletion coming soon. Use the Settings page for now.');
                                            }
                                        }}
                                        className="rounded p-1 text-foreground-muted hover:bg-background hover:text-red-500"
                                        title="Delete domain"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            )}
                            <Button
                                variant="secondary"
                                size="sm"
                                className="mt-2"
                                onClick={() => alert('Add Domain modal coming soon. Use the Settings page for now.')}
                            >
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                Add Custom Domain
                            </Button>
                        </div>
                    </div>

                    {/* Port Configuration */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Link2 className="h-4 w-4 text-foreground-muted" />
                            <span className="text-sm font-medium text-foreground">Port Configuration</span>
                        </div>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between rounded bg-background p-2">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground">PORT</span>
                                    <span className="text-sm text-foreground-muted">→</span>
                                    <code className="text-sm font-medium text-primary">3000</code>
                                </div>
                                <Badge variant="default" size="sm">HTTP</Badge>
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-foreground-muted">
                            Railway automatically detects your app's port from the PORT environment variable.
                        </p>
                    </div>

                    {/* TCP Proxy */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Terminal className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm font-medium text-foreground">TCP Proxy</span>
                            </div>
                            <button className="relative h-5 w-9 rounded-full bg-background-tertiary transition-colors">
                                <span className="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition-all" />
                            </button>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            Enable TCP proxy for non-HTTP services (SSH, databases, etc.). This exposes a public port.
                        </p>
                        <div className="mt-3 rounded bg-background p-3 text-sm text-foreground-muted">
                            <p className="font-mono">Proxy URL: <span className="text-foreground">your-service.proxy.rlwy.net:12345</span></p>
                        </div>
                    </div>

                    {/* Private Networking */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Shield className="h-4 w-4 text-emerald-500" />
                            <span className="text-sm font-medium text-foreground">Private Networking</span>
                        </div>
                        <p className="mb-3 text-xs text-foreground-muted">
                            Use internal DNS for service-to-service communication. No egress charges.
                        </p>
                        <div className="rounded bg-background p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Internal DNS</p>
                            <code className="text-sm text-foreground">{service.name || 'api-server'}.railway.internal</code>
                        </div>
                        <div className="mt-2 rounded bg-background p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Internal Port</p>
                            <code className="text-sm text-foreground">3000</code>
                        </div>
                    </div>
                </div>
            </div>

            {/* Regions & Scaling */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Regions & Scaling</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm text-foreground-muted">Region</span>
                            </div>
                            <Dropdown>
                                <DropdownTrigger>
                                    <button className="flex items-center gap-1 text-sm text-foreground hover:text-primary">
                                        us-east4
                                        <ChevronDown className="h-3 w-3" />
                                    </button>
                                </DropdownTrigger>
                                <DropdownContent align="right">
                                    <DropdownItem>us-east4 (Virginia)</DropdownItem>
                                    <DropdownItem>us-west1 (Oregon)</DropdownItem>
                                    <DropdownItem>eu-west1 (Belgium)</DropdownItem>
                                    <DropdownItem>asia-southeast1 (Singapore)</DropdownItem>
                                </DropdownContent>
                            </Dropdown>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Users className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm text-foreground-muted">Replicas</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => handleReplicasChange(replicas - 1)}
                                    disabled={replicas <= 1}
                                    className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    −
                                </button>
                                <span className="min-w-[24px] text-center text-sm font-medium text-foreground">{replicas}</span>
                                <button
                                    onClick={() => handleReplicasChange(replicas + 1)}
                                    className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Cron Schedule */}
            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium text-foreground">Cron Schedule</h3>
                    <button
                        onClick={() => setCronEnabled(!cronEnabled)}
                        className={`relative h-5 w-9 rounded-full transition-colors ${cronEnabled ? 'bg-primary' : 'bg-background-tertiary'}`}
                    >
                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${cronEnabled ? 'left-[18px]' : 'left-0.5'}`} />
                    </button>
                </div>
                {cronEnabled && (
                    <div className="space-y-2">
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="space-y-2">
                                <label className="text-xs text-foreground-muted">Schedule (cron expression)</label>
                                <input
                                    type="text"
                                    value={cronExpression}
                                    onChange={(e) => setCronExpression(e.target.value)}
                                    className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                    placeholder="0 * * * *"
                                />
                            </div>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            This service will run on the specified cron schedule instead of continuously.
                        </p>
                    </div>
                )}
                {!cronEnabled && (
                    <p className="text-xs text-foreground-muted">
                        Enable to run this service on a schedule instead of continuously.
                    </p>
                )}
            </div>

            {/* Health Check */}
            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium text-foreground">Health Check</h3>
                    <button
                        onClick={() => setHealthCheckEnabled(!healthCheckEnabled)}
                        className={`relative h-5 w-9 rounded-full transition-colors ${healthCheckEnabled ? 'bg-primary' : 'bg-background-tertiary'}`}
                    >
                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${healthCheckEnabled ? 'left-[18px]' : 'left-0.5'}`} />
                    </button>
                </div>
                {healthCheckEnabled && (
                    <div className="space-y-2">
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <label className="text-xs text-foreground-muted">Endpoint</label>
                                    <input
                                        type="text"
                                        value={healthEndpoint}
                                        onChange={(e) => setHealthEndpoint(e.target.value)}
                                        className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        placeholder="/health"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Timeout (s)</label>
                                        <input
                                            type="number"
                                            value={healthTimeout}
                                            onChange={(e) => setHealthTimeout(parseInt(e.target.value) || 10)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Interval (s)</label>
                                        <input
                                            type="number"
                                            value={healthInterval}
                                            onChange={(e) => setHealthInterval(parseInt(e.target.value) || 30)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            Health checks ensure your service is running correctly and will restart it if it fails.
                        </p>
                    </div>
                )}
            </div>

            {/* Save Settings Button */}
            <div className="border-t border-border pt-4">
                <Button
                    onClick={handleSaveSettings}
                    disabled={isSaving}
                    className="w-full"
                >
                    {isSaving ? 'Saving...' : 'Save Settings'}
                </Button>
            </div>

            {/* Danger Zone */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-red-500">Danger Zone</h3>
                <Button variant="danger" size="sm">
                    Delete Service
                </Button>
            </div>
        </div>
    );
}

/* ============================================
   DATABASE-SPECIFIC TAB COMPONENTS
   ============================================ */

function DatabaseDataTab({ service: _service }: { service: SelectedService }) {
    const tables = [
        { name: 'users', rows: 1234, size: '2.4 MB' },
        { name: 'sessions', rows: 5678, size: '1.1 MB' },
        { name: 'applications', rows: 89, size: '156 KB' },
        { name: 'deployments', rows: 456, size: '892 KB' },
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Tables</h3>
                <Button size="sm" variant="secondary">
                    <Plus className="mr-1 h-3 w-3" />
                    Create Table
                </Button>
            </div>

            <div className="space-y-2">
                {tables.map((table) => (
                    <div
                        key={table.name}
                        className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3 transition-colors hover:bg-background-tertiary cursor-pointer"
                    >
                        <div className="flex items-center gap-3">
                            <Table className="h-4 w-4 text-foreground-muted" />
                            <div>
                                <p className="text-sm font-medium text-foreground">{table.name}</p>
                                <p className="text-xs text-foreground-muted">{table.rows.toLocaleString()} rows</p>
                            </div>
                        </div>
                        <span className="text-xs text-foreground-muted">{table.size}</span>
                    </div>
                ))}
            </div>

            <div className="rounded-lg border border-dashed border-border p-6 text-center">
                <Database className="mx-auto h-8 w-8 text-foreground-subtle" />
                <p className="mt-2 text-sm text-foreground-muted">
                    Click on a table to view and edit data
                </p>
            </div>
        </div>
    );
}

function DatabaseConnectTab({ service }: { service: SelectedService }) {
    // Fetch real database data
    const { database, isLoading, error } = useDatabase({ uuid: service.uuid });

    // Get connection strings from API data
    const internalUrl = database?.internal_db_url;
    const externalUrl = database?.external_db_url;

    // Generate environment variables based on database type
    const getEnvVariables = () => {
        const dbType = service.dbType?.toLowerCase();
        switch (dbType) {
            case 'postgresql':
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'PGHOST', value: database?.uuid || service.uuid },
                    { key: 'PGPORT', value: '5432' },
                    { key: 'PGUSER', value: database?.postgres_user || 'postgres' },
                    { key: 'PGDATABASE', value: database?.postgres_db || 'postgres' },
                ];
            case 'mysql':
            case 'mariadb':
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'MYSQL_HOST', value: database?.uuid || service.uuid },
                    { key: 'MYSQL_PORT', value: '3306' },
                    { key: 'MYSQL_USER', value: database?.mysql_user || 'root' },
                    { key: 'MYSQL_DATABASE', value: database?.mysql_database || 'mysql' },
                ];
            case 'mongodb':
                return [
                    { key: 'MONGO_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'MONGO_HOST', value: database?.uuid || service.uuid },
                    { key: 'MONGO_PORT', value: '27017' },
                ];
            case 'redis':
            case 'keydb':
            case 'dragonfly':
                return [
                    { key: 'REDIS_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'REDIS_HOST', value: database?.uuid || service.uuid },
                    { key: 'REDIS_PORT', value: '6379' },
                ];
            default:
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                ];
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to load connection details. Check API permissions.
                </div>
            )}

            {/* Private Network */}
            <div>
                <div className="mb-3 flex items-center gap-2">
                    <Shield className="h-4 w-4 text-emerald-500" />
                    <h3 className="text-sm font-medium text-foreground">Private Network</h3>
                </div>
                <p className="mb-3 text-xs text-foreground-muted">
                    Use this connection string for services within the same project.
                </p>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between gap-2">
                        <code className="flex-1 truncate text-sm text-foreground">
                            {internalUrl || 'Not configured - enable public port or check settings'}
                        </code>
                        <button
                            onClick={() => {
                                if (internalUrl) {
                                    navigator.clipboard.writeText(internalUrl);
                                    alert('Connection string copied to clipboard');
                                }
                            }}
                            className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Copy connection string"
                            disabled={!internalUrl}
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Public Network */}
            <div>
                <div className="mb-3 flex items-center gap-2">
                    <Globe className="h-4 w-4 text-foreground-muted" />
                    <h3 className="text-sm font-medium text-foreground">Public Network</h3>
                </div>
                <p className="mb-3 text-xs text-foreground-muted">
                    Use this for external access. Network egress charges apply.
                </p>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between gap-2">
                        <code className="flex-1 truncate text-sm text-foreground">
                            {externalUrl || `Public access not configured (port: ${database?.public_port || 'N/A'})`}
                        </code>
                        <button
                            onClick={() => {
                                if (externalUrl) {
                                    navigator.clipboard.writeText(externalUrl);
                                    alert('External URL copied to clipboard');
                                }
                            }}
                            className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Copy external URL"
                            disabled={!externalUrl}
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Connection Variables */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Variables</h3>
                <div className="space-y-2">
                    {getEnvVariables().map((v) => (
                        <div key={v.key} className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-2">
                            <code className="text-xs text-foreground-muted">{v.key}</code>
                            <code className="text-xs text-foreground">{v.value}</code>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function DatabaseCredentialsTab({ service }: { service: SelectedService }) {
    const [showPassword, setShowPassword] = useState(false);
    const { database, isLoading, error } = useDatabase({ uuid: service.uuid });

    // Get credentials based on database type
    const getCredentials = () => {
        const dbType = service.dbType?.toLowerCase() || '';

        if (dbType.includes('postgresql') || dbType.includes('postgres')) {
            return {
                username: database?.postgres_user || 'postgres',
                password: database?.postgres_password,
                database: database?.postgres_db || 'postgres',
            };
        }
        if (dbType.includes('mysql') || dbType.includes('mariadb')) {
            return {
                username: database?.mysql_user || 'root',
                password: database?.mysql_password || database?.mysql_root_password,
                database: database?.mysql_database || 'mysql',
            };
        }
        if (dbType.includes('mongodb') || dbType.includes('mongo')) {
            return {
                username: database?.mongo_initdb_root_username || 'root',
                password: database?.mongo_initdb_root_password,
                database: database?.mongo_initdb_database || 'admin',
            };
        }
        if (dbType.includes('redis')) {
            return {
                username: null,
                password: database?.redis_password,
                database: null,
            };
        }
        if (dbType.includes('keydb')) {
            return {
                username: null,
                password: database?.keydb_password,
                database: null,
            };
        }
        if (dbType.includes('dragonfly')) {
            return {
                username: null,
                password: database?.dragonfly_password,
                database: null,
            };
        }
        if (dbType.includes('clickhouse')) {
            return {
                username: database?.clickhouse_admin_user || 'default',
                password: database?.clickhouse_admin_password,
                database: 'default',
            };
        }
        return { username: null, password: null, database: null };
    };

    const credentials = getCredentials();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to load credentials. Check API permissions (requires read:sensitive).
                </div>
            )}

            {/* Current Credentials */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Current Credentials</h3>
                <div className="space-y-2">
                    {credentials.username !== null && (
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-foreground-muted">Username</p>
                                    <code className="text-sm text-foreground">{credentials.username}</code>
                                </div>
                                <button
                                    onClick={() => {
                                        if (credentials.username) {
                                            navigator.clipboard.writeText(credentials.username);
                                            alert('Username copied to clipboard');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy username"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-foreground-muted">Password</p>
                                <code className="text-sm text-foreground">
                                    {credentials.password
                                        ? (showPassword ? credentials.password : '••••••••••••••••')
                                        : '(not available - requires read:sensitive permission)'}
                                </code>
                            </div>
                            <div className="flex gap-1">
                                <button
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                                    title={showPassword ? 'Hide password' : 'Show password'}
                                    disabled={!credentials.password}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => {
                                        if (credentials.password) {
                                            navigator.clipboard.writeText(credentials.password);
                                            alert('Password copied to clipboard');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                                    title="Copy password"
                                    disabled={!credentials.password}
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    {credentials.database !== null && (
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-foreground-muted">Database</p>
                                    <code className="text-sm text-foreground">{credentials.database}</code>
                                </div>
                                <button
                                    onClick={() => {
                                        if (credentials.database) {
                                            navigator.clipboard.writeText(credentials.database);
                                            alert('Database name copied to clipboard');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy database name"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Regenerate Password */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Regenerate Password</h3>
                <p className="mb-3 text-xs text-foreground-muted">
                    Generate a new password. This will automatically update the DATABASE_URL variable.
                    You'll need to redeploy services that depend on this database.
                </p>
                <Button variant="secondary" size="sm">
                    <RefreshCw className="mr-2 h-3 w-3" />
                    Regenerate Password
                </Button>
            </div>

            {/* Warning */}
            <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                <p className="text-sm text-yellow-500">
                    <strong>Note:</strong> After regenerating the password, any services using this
                    database will need to be redeployed to use the new credentials.
                </p>
            </div>
        </div>
    );
}

function DatabaseBackupsTab({ service }: { service: SelectedService }) {
    const { backups, isLoading, error, createBackup, deleteBackup, restoreBackup, refetch } = useDatabaseBackups({
        databaseUuid: service.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const [isCreating, setIsCreating] = useState(false);
    const [restoringBackupUuid, setRestoringBackupUuid] = useState<string | null>(null);

    const handleCreateBackup = async () => {
        setIsCreating(true);
        try {
            await createBackup();
            alert('Backup creation started');
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to create backup');
        } finally {
            setIsCreating(false);
        }
    };

    const handleScheduleBackup = () => {
        alert('Backup scheduling modal coming soon. Configure in database settings.');
    };

    const handleRestoreBackup = async (backupUuid: string, executionUuid?: string) => {
        if (!window.confirm('Are you sure you want to restore this backup? Current data will be replaced.')) {
            return;
        }
        setRestoringBackupUuid(backupUuid);
        try {
            await restoreBackup(backupUuid, executionUuid);
            alert('Database restore initiated. This may take a few minutes.');
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Failed to restore backup');
        } finally {
            setRestoringBackupUuid(null);
        }
    };

    const handleDeleteBackup = async (backupUuid: string) => {
        if (window.confirm('Are you sure you want to delete this backup?')) {
            try {
                await deleteBackup(backupUuid);
                alert('Backup deleted');
            } catch (err) {
                alert(err instanceof Error ? err.message : 'Failed to delete backup');
            }
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to load backups. Check API permissions.
                </div>
            )}

            {/* Actions */}
            <div className="flex gap-2">
                <Button size="sm" onClick={handleCreateBackup} disabled={isCreating}>
                    {isCreating ? (
                        <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                    ) : (
                        <Plus className="mr-1 h-3 w-3" />
                    )}
                    Create Backup
                </Button>
                <Button size="sm" variant="secondary" onClick={handleScheduleBackup}>
                    <Clock className="mr-1 h-3 w-3" />
                    Schedule
                </Button>
                <Button size="sm" variant="ghost" onClick={refetch}>
                    <RefreshCw className="h-3 w-3" />
                </Button>
            </div>

            {/* Backup List */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Backup Executions</h3>
                {(() => {
                    // Flatten all executions from all backup configurations
                    const allExecutions = backups.flatMap(backup =>
                        (backup.executions || []).map(exec => ({
                            ...exec,
                            backupUuid: backup.uuid,
                            backupFrequency: backup.frequency,
                        }))
                    ).sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

                    if (allExecutions.length === 0) {
                        return (
                            <div className="rounded-lg border border-dashed border-border p-6 text-center">
                                <HardDrive className="mx-auto h-8 w-8 text-foreground-subtle" />
                                <p className="mt-2 text-sm text-foreground-muted">No backups yet</p>
                                <p className="mt-1 text-xs text-foreground-subtle">
                                    Create your first backup to protect your data
                                </p>
                            </div>
                        );
                    }

                    return (
                        <div className="space-y-2">
                            {allExecutions.map((exec) => (
                                <div
                                    key={exec.uuid}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <HardDrive className={`h-4 w-4 ${
                                            exec.status === 'success' ? 'text-emerald-500' :
                                            exec.status === 'in_progress' ? 'text-blue-500 animate-pulse' :
                                            'text-red-500'
                                        }`} />
                                        <div>
                                            <p className="text-sm text-foreground">
                                                {new Date(exec.created_at).toLocaleString()}
                                            </p>
                                            <p className="text-xs text-foreground-muted">
                                                {exec.size || 'N/A'} • {exec.status}
                                                {exec.database_name && ` • ${exec.database_name}`}
                                                {exec.restore_status && exec.restore_status !== 'pending' && (
                                                    <span className={`ml-2 ${
                                                        exec.restore_status === 'success' ? 'text-emerald-500' :
                                                        exec.restore_status === 'in_progress' ? 'text-blue-500' :
                                                        'text-red-500'
                                                    }`}>
                                                        (Restore: {exec.restore_status})
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => handleRestoreBackup(exec.backupUuid, exec.uuid)}
                                            disabled={exec.status !== 'success' || restoringBackupUuid === exec.backupUuid || exec.restore_status === 'in_progress'}
                                        >
                                            {restoringBackupUuid === exec.backupUuid || exec.restore_status === 'in_progress' ? (
                                                <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                                            ) : null}
                                            Restore
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-red-500 hover:text-red-400"
                                            onClick={() => handleDeleteBackup(exec.backupUuid)}
                                            disabled={exec.status === 'in_progress'}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    );
                })()}
            </div>

            {/* Info */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <p className="text-xs text-foreground-muted">
                    Backups are incremental and use copy-on-write technology.
                    You only pay for unique data stored.
                </p>
            </div>
        </div>
    );
}

function DatabaseExtensionsTab({ service: _service }: { service: SelectedService }) {
    const [searchQuery, setSearchQuery] = useState('');

    const extensions = [
        { name: 'pgvector', version: '0.5.1', description: 'Vector similarity search', enabled: true, popular: true },
        { name: 'postgis', version: '3.4.0', description: 'Spatial database extension', enabled: false, popular: true },
        { name: 'pg_trgm', version: '1.6', description: 'Text similarity with trigrams', enabled: true, popular: true },
        { name: 'uuid-ossp', version: '1.1', description: 'Generate universally unique identifiers', enabled: true, popular: false },
        { name: 'hstore', version: '1.8', description: 'Key-value store in PostgreSQL', enabled: false, popular: false },
        { name: 'pg_stat_statements', version: '1.10', description: 'Track query execution statistics', enabled: true, popular: true },
        { name: 'timescaledb', version: '2.13.0', description: 'Time-series database extension', enabled: false, popular: true },
        { name: 'citext', version: '1.6', description: 'Case-insensitive character string type', enabled: false, popular: false },
    ];

    const filteredExtensions = extensions.filter(ext =>
        ext.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ext.description.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const enabledExtensions = filteredExtensions.filter(ext => ext.enabled);
    const availableExtensions = filteredExtensions.filter(ext => !ext.enabled);

    return (
        <div className="space-y-6">
            {/* Search */}
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search extensions..."
                    className="w-full rounded-lg border border-border bg-background-secondary py-2 pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                />
            </div>

            {/* Enabled Extensions */}
            {enabledExtensions.length > 0 && (
                <div>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20">
                            <span className="h-2 w-2 rounded-full bg-emerald-500" />
                        </span>
                        Enabled ({enabledExtensions.length})
                    </h3>
                    <div className="space-y-2">
                        {enabledExtensions.map((ext) => (
                            <div
                                key={ext.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-foreground">{ext.name}</p>
                                        <span className="text-xs text-foreground-muted">v{ext.version}</span>
                                        {ext.popular && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                Popular
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                </div>
                                <Button size="sm" variant="secondary" className="text-red-500 hover:text-red-400">
                                    Disable
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Available Extensions */}
            {availableExtensions.length > 0 && (
                <div>
                    <h3 className="mb-3 text-sm font-medium text-foreground">
                        Available ({availableExtensions.length})
                    </h3>
                    <div className="space-y-2">
                        {availableExtensions.map((ext) => (
                            <div
                                key={ext.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-foreground">{ext.name}</p>
                                        <span className="text-xs text-foreground-muted">v{ext.version}</span>
                                        {ext.popular && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                Popular
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                </div>
                                <Button size="sm">
                                    Enable
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Info */}
            <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                <p className="text-sm text-yellow-500">
                    <strong>Note:</strong> Enabling or disabling extensions may require a database restart.
                    Some extensions may affect performance.
                </p>
            </div>
        </div>
    );
}

function DatabaseSettingsTab({ service }: { service: SelectedService }) {
    return (
        <div className="space-y-6">
            {/* Database Info */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Database</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Type</span>
                            <span className="text-sm text-foreground capitalize">{service.dbType}</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Version</span>
                            <span className="text-sm text-foreground">15.4</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Region</span>
                            <span className="text-sm text-foreground">us-east4</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Storage */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Storage</h3>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm text-foreground-muted">Volume</span>
                        <span className="text-sm text-foreground">postgresql-data</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-foreground-muted">Used</span>
                        <span className="text-sm text-foreground">2.4 GB / 10 GB</span>
                    </div>
                    <div className="mt-2 h-2 rounded-full bg-background">
                        <div className="h-2 w-1/4 rounded-full bg-primary" />
                    </div>
                </div>
            </div>

            {/* Danger Zone */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-red-500">Danger Zone</h3>
                <div className="space-y-2">
                    <Button variant="danger" size="sm">
                        Reset Database
                    </Button>
                    <Button variant="danger" size="sm" className="ml-2">
                        Delete Database
                    </Button>
                </div>
            </div>
        </div>
    );
}

/* ============================================
   ACTIVITY PANEL COMPONENT
   ============================================ */

function ActivityPanel() {
    const [isExpanded, setIsExpanded] = useState(false);

    const activities = [
        {
            id: 1,
            type: 'deployment',
            service: 'api-server',
            status: 'active',
            message: 'Deployment succeeded',
            time: '2 min ago',
            user: 'John Doe',
            details: 'Build completed in 45s, deployed to us-east4',
        },
        {
            id: 2,
            type: 'config',
            service: 'api-server',
            status: 'info',
            message: 'Variable updated',
            time: '15 min ago',
            user: 'John Doe',
            details: 'DATABASE_URL was modified',
        },
        {
            id: 3,
            type: 'deployment',
            service: 'postgres',
            status: 'active',
            message: 'Database restarted',
            time: '1 hour ago',
            user: 'System',
            details: 'Automatic restart after configuration change',
        },
        {
            id: 4,
            type: 'deployment',
            service: 'redis',
            status: 'warning',
            message: 'High memory usage',
            time: '3 hours ago',
            user: 'System',
            details: 'Memory usage exceeded 80% threshold',
        },
        {
            id: 5,
            type: 'deployment',
            service: 'api-server',
            status: 'error',
            message: 'Deployment failed',
            time: '1 day ago',
            user: 'Jane Smith',
            details: 'Build failed: npm install exited with code 1',
        },
    ];

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active':
                return 'bg-emerald-500';
            case 'warning':
                return 'bg-yellow-500';
            case 'error':
                return 'bg-red-500';
            default:
                return 'bg-blue-500';
        }
    };

    const getStatusIcon = (type: string, status: string) => {
        if (type === 'config') {
            return <Variable className="h-3.5 w-3.5" />;
        }
        switch (status) {
            case 'active':
                return <Play className="h-3.5 w-3.5" />;
            case 'error':
                return <X className="h-3.5 w-3.5" />;
            case 'warning':
                return <Activity className="h-3.5 w-3.5" />;
            default:
                return <Activity className="h-3.5 w-3.5" />;
        }
    };

    return (
        <div className="absolute bottom-4 right-4 z-10">
            <div className={`w-80 rounded-lg border border-border bg-background shadow-lg transition-all duration-200 ${isExpanded ? 'max-h-96' : 'max-h-[52px]'} overflow-hidden`}>
                {/* Header */}
                <button
                    onClick={() => setIsExpanded(!isExpanded)}
                    className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                >
                    <span className="flex items-center gap-2">
                        <Activity className="h-4 w-4" />
                        Activity
                        <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-primary/20 px-1.5 text-xs font-medium text-primary">
                            {activities.length}
                        </span>
                    </span>
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`} />
                </button>

                {/* Activity List */}
                {isExpanded && (
                    <div className="max-h-72 overflow-y-auto border-t border-border">
                        <div className="p-2">
                            {activities.map((activity, index) => (
                                <div
                                    key={activity.id}
                                    className="group relative flex gap-3 rounded-lg p-2 hover:bg-background-secondary transition-colors cursor-pointer"
                                >
                                    {/* Timeline connector */}
                                    {index < activities.length - 1 && (
                                        <div className="absolute left-[19px] top-8 h-[calc(100%-8px)] w-px bg-border" />
                                    )}

                                    {/* Status indicator */}
                                    <div className={`relative z-10 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full ${getStatusColor(activity.status)}`}>
                                        {getStatusIcon(activity.type, activity.status)}
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium text-foreground truncate">{activity.message}</p>
                                            <span className="text-xs text-foreground-muted whitespace-nowrap">{activity.time}</span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-foreground-muted truncate">{activity.service}</p>
                                        <p className="mt-1 text-xs text-foreground-subtle line-clamp-2 group-hover:text-foreground-muted transition-colors">
                                            {activity.details}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* View All Link */}
                        <div className="border-t border-border p-2">
                            <button className="flex w-full items-center justify-center gap-1 rounded-md py-2 text-sm text-foreground-muted hover:bg-background-secondary hover:text-foreground transition-colors">
                                View all activity
                                <ChevronRight className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
