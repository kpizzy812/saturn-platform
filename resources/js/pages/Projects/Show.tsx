import { useState, useCallback, useRef } from 'react';
import { Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Badge, Button, Tabs } from '@/components/ui';
import { Plus, Settings, ChevronDown, Play, X, Activity, Variable, Gauge, Cog, ExternalLink, Copy, ChevronRight, Clock, Hash, ArrowLeft, Grid3x3, ZoomIn, ZoomOut, Maximize2, Undo2, Redo2, Terminal, Globe, Users, GitCommit, Eye, FileText, Database, Key, Link2, HardDrive, RefreshCw, Table, Shield, Box, Layers, GitBranch, MoreVertical, RotateCcw, StopCircle, Trash2, Command, Search } from 'lucide-react';
import type { Project, Environment, Application, StandaloneDatabase } from '@/types';
import { ProjectCanvas } from '@/components/features/canvas';
import { CommandPalette } from '@/components/features/CommandPalette';
import { ContextMenu, type ContextMenuPosition, type ContextMenuNode } from '@/components/features/ContextMenu';
import { LogsViewer } from '@/components/features/LogsViewer';
import { ToastProvider } from '@/components/ui/Toast';
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
    type: 'app' | 'db';
    name: string;
    status: string;
    fqdn?: string;
    dbType?: string;
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
    const trackStateChange = useCallback((newService: SelectedService | null) => {
        historyRef.current = {
            past: [...historyRef.current.past, selectedService ? [selectedService] : []],
            future: [],
        };
        setCanUndo(true);
        setCanRedo(false);
    }, [selectedService]);

    const handleNodeClick = useCallback((id: string, type: string) => {
        const env = project.environments?.[0];
        if (!env) return;

        if (type === 'app') {
            const app = env.applications?.find(a => String(a.id) === id);
            if (app) {
                setSelectedService({
                    id: String(app.id),
                    type: 'app',
                    name: app.name,
                    status: app.status || 'unknown',
                    fqdn: app.fqdn,
                });
            }
        } else if (type === 'db') {
            const db = env.databases?.find(d => String(d.id) === id);
            if (db) {
                setSelectedService({
                    id: String(db.id),
                    type: 'db',
                    name: db.name,
                    status: db.status || 'unknown',
                    dbType: db.database_type,
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
                    type: 'app',
                    name: app.name,
                    status: app.status || 'unknown',
                    fqdn: app.fqdn,
                };
            }
        } else if (type === 'db') {
            const db = env.databases?.find(d => String(d.id) === id);
            if (db) {
                nodeData = {
                    id: String(db.id),
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

    const handleViewLogs = (nodeId: string) => {
        const node = contextMenuNode;
        if (node) {
            setLogsViewerService(node.name);
            setLogsViewerOpen(true);
        }
    };

    const handleCopyId = (nodeId: string) => {
        navigator.clipboard.writeText(nodeId);
    };

    const handleOpenUrl = (url: string) => {
        window.open(url, '_blank');
    };

    // Canvas zoom controls
    const handleZoomIn = useCallback(() => {
        if ((window as any).__projectCanvasZoomIn) {
            (window as any).__projectCanvasZoomIn();
        }
    }, []);

    const handleZoomOut = useCallback(() => {
        if ((window as any).__projectCanvasZoomOut) {
            (window as any).__projectCanvasZoomOut();
        }
    }, []);

    const handleFitView = useCallback(() => {
        if ((window as any).__projectCanvasFitView) {
            (window as any).__projectCanvasFitView();
        }
    }, []);

    const handleViewportChange = useCallback((zoom: number) => {
        setZoomLevel(zoom);
    }, []);

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
            <CommandPalette services={commandPaletteServices} />
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
                                onClick={() => setHasStagedChanges(false)}
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
                                    <DropdownItem className="flex items-center gap-3 py-3">
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
                                    <DropdownItem className="flex items-center gap-3 py-3">
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
                                    <DropdownItem className="flex items-center gap-3 py-3">
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
                                    <DropdownItem className="flex items-center gap-3 py-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-600">
                                            <Box className="h-4 w-4 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">Empty Service</p>
                                            <p className="text-xs text-foreground-muted">Configure later</p>
                                        </div>
                                    </DropdownItem>

                                    {/* Template */}
                                    <DropdownItem className="flex items-center gap-3 py-3">
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
                                        <button className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground">
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
                                        {activeAppTab === 'variables' && <VariablesTab />}
                                        {activeAppTab === 'metrics' && <MetricsTab />}
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
                onDeploy={(id) => console.log('Deploy', id)}
                onRestart={(id) => console.log('Restart', id)}
                onStop={(id) => console.log('Stop', id)}
                onViewLogs={handleViewLogs}
                onOpenSettings={(id) => {
                    // Open the panel with settings tab
                    if (contextMenuNode) {
                        setSelectedService({
                            id: contextMenuNode.id,
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
                onDelete={(id) => console.log('Delete', id)}
                onCopyId={handleCopyId}
                onOpenUrl={handleOpenUrl}
            />

            {/* Logs Viewer Modal */}
            <LogsViewer
                isOpen={logsViewerOpen}
                onClose={() => setLogsViewerOpen(false)}
                serviceName={logsViewerService}
            />
        </ToastProvider>
    );
}

function DeploymentsTab({ service }: { service: SelectedService }) {
    const deployments = [
        {
            id: 1,
            hash: 'a1b2c3d',
            status: 'active',
            time: '2 hours ago',
            message: 'Merge pull request #44 from saturn/feature-branch',
            author: 'John Doe',
            avatar: 'https://github.com/github.png',
            progress: null,
        },
        {
            id: 2,
            hash: 'e4f5g6h',
            status: 'building',
            time: '00:08',
            message: 'Add authentication middleware',
            author: 'Jane Smith',
            avatar: 'https://github.com/github.png',
            progress: 'Taking a snapshot of the code...',
        },
        {
            id: 3,
            hash: 'i7j8k9l',
            status: 'removed',
            time: '1 day ago',
            message: 'Database migration fixes',
            author: 'Bob Johnson',
            avatar: 'https://github.com/github.png',
            progress: null,
        },
        {
            id: 4,
            hash: 'm0n1o2p',
            status: 'crashed',
            time: '2 days ago',
            message: 'Upgrade dependencies',
            author: 'Alice Brown',
            avatar: 'https://github.com/github.png',
            progress: null,
        },
    ];

    const getBadgeStyle = (status: string) => {
        switch (status) {
            case 'active':
                return 'bg-green-500/10 text-green-500 border border-green-500/20';
            case 'building':
            case 'deploying':
                return 'bg-blue-500/10 text-blue-500 border border-blue-500/20';
            case 'crashed':
                return 'bg-red-500/10 text-red-500 border border-red-500/20';
            case 'removed':
                return 'bg-gray-500/10 text-gray-500 border border-gray-500/20';
            default:
                return 'bg-gray-500/10 text-gray-500 border border-gray-500/20';
        }
    };

    const isInProgress = (status: string) => ['building', 'deploying'].includes(status);
    const canRollback = (status: string) => ['active', 'crashed'].includes(status);

    return (
        <div className="space-y-4">
            {/* Deploy Button */}
            <Button className="w-full">
                <Play className="mr-2 h-4 w-4" />
                Deploy Now
            </Button>

            {/* Deployments List */}
            <div className="space-y-4">
                {deployments.map((deploy, index) => (
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
                                        {deploy.status}
                                    </span>
                                    <span className="text-xs text-foreground-muted">{deploy.time}</span>
                                </div>

                                {/* Three-dot Actions Menu */}
                                <Dropdown>
                                    <DropdownTrigger>
                                        <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground transition-colors">
                                            <MoreVertical className="h-4 w-4" />
                                        </button>
                                    </DropdownTrigger>
                                    <DropdownContent align="right" className="w-48">
                                        <DropdownItem className="flex items-center gap-2">
                                            <Eye className="h-4 w-4" />
                                            <span>View Logs</span>
                                        </DropdownItem>

                                        {deploy.status === 'active' && (
                                            <DropdownItem className="flex items-center gap-2">
                                                <RefreshCw className="h-4 w-4" />
                                                <span>Restart</span>
                                            </DropdownItem>
                                        )}

                                        <DropdownItem className="flex items-center gap-2">
                                            <Play className="h-4 w-4" />
                                            <span>Redeploy</span>
                                        </DropdownItem>

                                        {canRollback(deploy.status) && index !== 0 && (
                                            <DropdownItem className="flex items-center gap-2">
                                                <RotateCcw className="h-4 w-4" />
                                                <span>Rollback to this</span>
                                            </DropdownItem>
                                        )}

                                        {isInProgress(deploy.status) && (
                                            <>
                                                <DropdownDivider />
                                                <DropdownItem className="flex items-center gap-2 text-red-500 hover:text-red-400">
                                                    <StopCircle className="h-4 w-4" />
                                                    <span>Cancel</span>
                                                </DropdownItem>
                                            </>
                                        )}

                                        {deploy.status !== 'active' && !isInProgress(deploy.status) && (
                                            <>
                                                <DropdownDivider />
                                                <DropdownItem className="flex items-center gap-2 text-red-500 hover:text-red-400">
                                                    <Trash2 className="h-4 w-4" />
                                                    <span>Remove</span>
                                                </DropdownItem>
                                            </>
                                        )}
                                    </DropdownContent>
                                </Dropdown>
                            </div>

                            {/* Commit Info */}
                            <div className="flex items-start gap-3">
                                <img
                                    src={deploy.avatar}
                                    alt={deploy.author}
                                    className="h-6 w-6 rounded-full"
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-foreground">{deploy.message}</p>
                                    <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                        <GitCommit className="h-3 w-3" />
                                        <code>{deploy.hash}</code>
                                        <span>•</span>
                                        <span>{deploy.author}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Deployment Progress */}
                            {deploy.progress && (
                                <div className="rounded-md bg-background p-3">
                                    <div className="flex items-center gap-2">
                                        <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500" />
                                        <p className="text-xs text-foreground-muted">{deploy.progress}</p>
                                    </div>
                                </div>
                            )}

                            {/* Quick Actions Row */}
                            <div className="flex gap-2">
                                <Button variant="secondary" size="sm" className="flex-1">
                                    <Eye className="mr-2 h-3 w-3" />
                                    Logs
                                </Button>
                                {deploy.status === 'active' && (
                                    <Button variant="secondary" size="sm" className="flex-1">
                                        <RefreshCw className="mr-2 h-3 w-3" />
                                        Restart
                                    </Button>
                                )}
                                {isInProgress(deploy.status) && (
                                    <Button variant="danger" size="sm">
                                        <StopCircle className="mr-2 h-3 w-3" />
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function VariablesTab() {
    const variables = [
        { key: 'DATABASE_URL', value: '••••••••', isSecret: true },
        { key: 'NODE_ENV', value: 'production', isSecret: false },
        { key: 'PORT', value: '3000', isSecret: false },
    ];

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Environment Variables</h3>
                <Button size="sm" variant="secondary">
                    <Plus className="mr-1 h-3 w-3" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {variables.map((v) => (
                    <div key={v.key} className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3">
                        <div>
                            <code className="text-sm font-medium text-foreground">{v.key}</code>
                            <p className="text-sm text-foreground-muted">{v.value}</p>
                        </div>
                        <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground">
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}

function MetricsTab() {
    const [timeRange, setTimeRange] = useState<'1h' | '6h' | '24h' | '7d' | '30d'>('24h');

    // Demo metrics data
    const cpuData = [35, 42, 38, 45, 52, 48, 55, 62, 58, 45, 40, 38];
    const memoryData = [65, 68, 70, 72, 71, 74, 76, 75, 73, 72, 70, 69];
    const networkIn = [12, 18, 25, 32, 28, 35, 42, 38, 30, 25, 20, 15];
    const networkOut = [8, 12, 18, 22, 20, 28, 35, 30, 25, 18, 14, 10];

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

    return (
        <div className="space-y-4">
            {/* Time Range Selector */}
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Resource Usage</h3>
                <div className="flex gap-1 rounded-lg bg-background-secondary p-1">
                    {(['1h', '6h', '24h', '7d', '30d'] as const).map((range) => (
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

            {/* CPU Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                        <span className="text-sm font-medium text-foreground">CPU Usage</span>
                    </div>
                    <div className="flex items-baseline gap-1">
                        <span className="text-2xl font-bold text-foreground">{cpuData[cpuData.length - 1]}%</span>
                        <span className="text-xs text-foreground-muted">of 1 vCPU</span>
                    </div>
                </div>
                {renderMiniChart(cpuData, '#3b82f6')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: 46%</span>
                    <span>Max: 62%</span>
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
                        <span className="text-2xl font-bold text-foreground">{memoryData[memoryData.length - 1]}%</span>
                        <span className="text-xs text-foreground-muted">of 512 MB</span>
                    </div>
                </div>
                {renderMiniChart(memoryData, '#10b981')}
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Avg: 71%</span>
                    <span>Max: 76%</span>
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
                            In: {networkIn[networkIn.length - 1]} KB/s
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="h-2 w-2 rounded-full bg-pink-500" />
                            Out: {networkOut[networkOut.length - 1]} KB/s
                        </span>
                    </div>
                </div>
                <div className="relative">
                    {renderMiniChart(networkIn, '#a855f7', 50)}
                    <div className="absolute inset-0">
                        {renderMiniChart(networkOut, '#ec4899', 50)}
                    </div>
                </div>
                <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                    <span>Total In: 2.4 GB</span>
                    <span>Total Out: 1.8 GB</span>
                </div>
            </div>

            {/* Disk Usage */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <HardDrive className="h-4 w-4 text-foreground-muted" />
                        <span className="text-sm font-medium text-foreground">Disk Usage</span>
                    </div>
                    <span className="text-sm text-foreground">1.2 GB / 5 GB</span>
                </div>
                <div className="h-2 w-full rounded-full bg-background">
                    <div className="h-2 w-[24%] rounded-full bg-orange-500" />
                </div>
                <p className="mt-2 text-xs text-foreground-muted">24% used</p>
            </div>

            {/* Request Stats */}
            <div className="rounded-lg border border-border bg-background-secondary p-4">
                <h4 className="mb-3 text-sm font-medium text-foreground">Request Stats (24h)</h4>
                <div className="grid grid-cols-3 gap-4">
                    <div className="text-center">
                        <p className="text-2xl font-bold text-foreground">12.4k</p>
                        <p className="text-xs text-foreground-muted">Total Requests</p>
                    </div>
                    <div className="text-center">
                        <p className="text-2xl font-bold text-green-500">99.2%</p>
                        <p className="text-xs text-foreground-muted">Success Rate</p>
                    </div>
                    <div className="text-center">
                        <p className="text-2xl font-bold text-foreground">45ms</p>
                        <p className="text-xs text-foreground-muted">Avg Latency</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

function AppSettingsTab({ service }: { service: SelectedService }) {
    const [cronEnabled, setCronEnabled] = useState(false);
    const [healthCheckEnabled, setHealthCheckEnabled] = useState(true);

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
                                <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground">
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
                                    <button className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground">
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            )}
                            <Button variant="secondary" size="sm" className="mt-2">
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
                                <button className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground">
                                    −
                                </button>
                                <span className="min-w-[24px] text-center text-sm font-medium text-foreground">1</span>
                                <button className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground">
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
                                    defaultValue="0 * * * *"
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
                                        defaultValue="/health"
                                        className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        placeholder="/health"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Timeout (s)</label>
                                        <input
                                            type="number"
                                            defaultValue="10"
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Interval (s)</label>
                                        <input
                                            type="number"
                                            defaultValue="30"
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

function DatabaseDataTab({ service }: { service: SelectedService }) {
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
    const getConnectionString = () => {
        switch (service.dbType?.toLowerCase()) {
            case 'postgresql':
                return 'postgresql://postgres:••••••••@postgres.railway.internal:5432/railway';
            case 'redis':
                return 'redis://default:••••••••@redis.railway.internal:6379';
            case 'mysql':
                return 'mysql://root:••••••••@mysql.railway.internal:3306/railway';
            case 'mongodb':
                return 'mongodb://mongo:••••••••@mongodb.railway.internal:27017/railway';
            default:
                return 'database://user:password@host:port/database';
        }
    };

    return (
        <div className="space-y-6">
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
                            {getConnectionString()}
                        </code>
                        <button className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground">
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
                            {service.dbType?.toLowerCase()}.saturn.railway.app:5432
                        </code>
                        <button className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground">
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Connection Variables */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Variables</h3>
                <div className="space-y-2">
                    {[
                        { key: 'DATABASE_URL', value: '(connection string)' },
                        { key: 'PGHOST', value: 'postgres.railway.internal' },
                        { key: 'PGPORT', value: '5432' },
                        { key: 'PGUSER', value: 'postgres' },
                        { key: 'PGDATABASE', value: 'railway' },
                    ].map((v) => (
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
    return (
        <div className="space-y-6">
            {/* Current Credentials */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Current Credentials</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-foreground-muted">Username</p>
                                <code className="text-sm text-foreground">postgres</code>
                            </div>
                            <button className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground">
                                <Copy className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-foreground-muted">Password</p>
                                <code className="text-sm text-foreground">••••••••••••••••</code>
                            </div>
                            <div className="flex gap-1">
                                <button className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground">
                                    <Eye className="h-4 w-4" />
                                </button>
                                <button className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground">
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
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
    const backups = [
        { id: 1, date: '2024-01-15 14:30', size: '24.5 MB', type: 'manual' },
        { id: 2, date: '2024-01-14 00:00', size: '23.8 MB', type: 'scheduled' },
        { id: 3, date: '2024-01-13 00:00', size: '22.1 MB', type: 'scheduled' },
    ];

    return (
        <div className="space-y-6">
            {/* Actions */}
            <div className="flex gap-2">
                <Button size="sm">
                    <Plus className="mr-1 h-3 w-3" />
                    Create Backup
                </Button>
                <Button size="sm" variant="secondary">
                    <Clock className="mr-1 h-3 w-3" />
                    Schedule
                </Button>
            </div>

            {/* Backup List */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Backups</h3>
                <div className="space-y-2">
                    {backups.map((backup) => (
                        <div
                            key={backup.id}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                        >
                            <div className="flex items-center gap-3">
                                <HardDrive className="h-4 w-4 text-foreground-muted" />
                                <div>
                                    <p className="text-sm text-foreground">{backup.date}</p>
                                    <p className="text-xs text-foreground-muted">
                                        {backup.size} • {backup.type}
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-1">
                                <Button size="sm" variant="ghost">
                                    Restore
                                </Button>
                                <Button size="sm" variant="ghost" className="text-red-500 hover:text-red-400">
                                    Delete
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
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

function DatabaseExtensionsTab({ service }: { service: SelectedService }) {
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
