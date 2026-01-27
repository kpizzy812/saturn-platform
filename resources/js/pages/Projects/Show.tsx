import { useState, useCallback, useRef, useEffect } from 'react';
import { getStatusLabel, getStatusDotClass } from '@/lib/statusUtils';
import { Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Button, Input, useConfirm } from '@/components/ui';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Plus, Settings, ChevronDown, Play, X, Activity, Variable, Gauge, Cog, ExternalLink, Copy, ChevronRight, Clock, ArrowLeft, Grid3x3, ZoomIn, ZoomOut, Maximize2, Undo2, Redo2, Terminal, Globe, Users, GitCommit, Eye, EyeOff, FileText, Database, Key, Link2, HardDrive, RefreshCw, Table, Shield, Box, Layers, GitBranch, MoreVertical, RotateCcw, StopCircle, Trash2, Command, Search } from 'lucide-react';
import type { Project, Environment } from '@/types';
import { ProjectCanvas } from '@/components/features/canvas';
import { CommandPalette } from '@/components/features/CommandPalette';
import { ContextMenu, type ContextMenuPosition, type ContextMenuNode } from '@/components/features/ContextMenu';
import { LogsViewer } from '@/components/features/LogsViewer';
import { useToast } from '@/components/ui/Toast';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

// Extracted components
import {
    type SelectedService,
    getDbLogo,
    getDbBgColor,
    ActivityPanel,
    DeploymentsTab,
    VariablesTab,
    MetricsTab,
    AppSettingsTab,
    DatabaseDataTab,
    DatabaseConnectTab,
    DatabaseCredentialsTab,
    DatabaseBackupsTab,
    DatabaseExtensionsTab,
    DatabaseSettingsTab,
    LocalSetupModal,
} from '@/components/features/Projects';

interface Props {
    project?: Project;
}

export default function ProjectShow({ project }: Props) {
    const [selectedEnv, setSelectedEnv] = useState<Environment | null>(project?.environments?.[0] || null);
    const [selectedService, setSelectedService] = useState<SelectedService | null>(null);
    const [activeAppTab, setActiveAppTab] = useState<'deployments' | 'variables' | 'metrics' | 'settings'>('deployments');
    const [activeDbTab, setActiveDbTab] = useState<'data' | 'connect' | 'credentials' | 'backups' | 'extensions' | 'settings'>('connect');
    const [activeView, setActiveView] = useState<'architecture' | 'observability' | 'logs'>('architecture');
    const [hasStagedChanges, setHasStagedChanges] = useState(false);
    const [showLocalSetup, setShowLocalSetup] = useState(false);
    const [showNewEnvModal, setShowNewEnvModal] = useState(false);
    const [newEnvName, setNewEnvName] = useState('');
    const [creatingEnv, setCreatingEnv] = useState(false);

    // Hooks - must be called before early return
    const { addToast } = useToast();
    const confirm = useConfirm();

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

    // Switch environment handler
    const handleSwitchEnv = (env: Environment) => {
        setSelectedEnv(env);
        setSelectedService(null);
    };

    // Create new environment handler
    const handleCreateEnv = async () => {
        if (!newEnvName.trim()) return;
        setCreatingEnv(true);
        try {
            const res = await fetch(`/projects/${project.uuid}/environments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ name: newEnvName.trim() }),
            });
            if (!res.ok) {
                const err = await res.json();
                addToast('error', 'Error', err.message || 'Failed to create environment');
                return;
            }
            addToast('success', 'Created', `Environment "${newEnvName.trim()}" created`);
            setNewEnvName('');
            setShowNewEnvModal(false);
            router.reload();
        } catch {
            addToast('error', 'Error', 'Failed to create environment');
        } finally {
            setCreatingEnv(false);
        }
    };

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
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${node.uuid}/start`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to deploy');
            }

            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [contextMenuNode, addToast]);

    const handleRestart = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}/restart`
            : `/api/v1/databases/${node.uuid}/restart`;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to restart');
            }

            router.reload();
        } catch (err) {
            addToast('error', 'Restart failed', err instanceof Error ? err.message : 'Failed to restart service');
        }
    }, [contextMenuNode, addToast]);

    const handleStop = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}/stop`
            : `/api/v1/databases/${node.uuid}/stop`;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to stop');
            }

            router.reload();
        } catch (err) {
            addToast('error', 'Stop failed', err instanceof Error ? err.message : 'Failed to stop service');
        }
    }, [contextMenuNode, addToast]);

    const handleDelete = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        const confirmed = await confirm({
            title: 'Delete Resource',
            description: `Are you sure you want to delete "${node.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (!confirmed) return;

        const endpoint = node.type === 'app'
            ? `/api/v1/applications/${node.uuid}`
            : `/api/v1/databases/${node.uuid}`;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(endpoint, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
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
            addToast('error', 'Delete failed', err instanceof Error ? err.message : 'Failed to delete resource');
        }
    }, [contextMenuNode, addToast]);

    // Deploy all staged changes
    const handleDeployChanges = useCallback(async () => {
        const env = project.environments?.[0];
        if (!env?.applications?.length) {
            setHasStagedChanges(false);
            return;
        }

        try {
            // Deploy all applications in the environment
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const deployPromises = env.applications.map(app =>
                fetch(`/api/v1/applications/${app.uuid}/start`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                })
            );

            await Promise.all(deployPromises);
            setHasStagedChanges(false);
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy changes');
        }
    }, [project.environments, addToast]);

    // Database backup handlers
    const handleCreateBackup = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node || node.type !== 'db') return;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/databases/${node.uuid}/backups`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to create backup');
            }

            addToast('success', 'Backup started', 'Database backup process has been initiated.');
            router.reload();
        } catch (err) {
            addToast('error', 'Backup failed', err instanceof Error ? err.message : 'Failed to create backup');
        }
    }, [contextMenuNode, addToast]);

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

    // Quick action handlers for canvas nodes
    const handleQuickDeploy = useCallback(async (uuid: string) => {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/api/v1/deploy', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ uuid }),
            });
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to deploy');
            }
            addToast('success', 'Deploy started', 'Application deployment has been initiated.');
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [addToast]);

    const handleQuickOpenUrl = useCallback((url: string) => {
        window.open(url.startsWith('http') ? url : `https://${url}`, '_blank');
    }, []);

    const handleQuickViewLogs = useCallback((uuid: string, name: string, type: 'application' | 'database') => {
        setLogsViewerService(name);
        setLogsViewerServiceUuid(uuid);
        setLogsViewerServiceType(type);
        setLogsViewerOpen(true);
    }, []);

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
            addToast('warning', 'No application selected', 'Please select an application first');
            return;
        }
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${selectedService.uuid}/start`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to deploy');
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [selectedService, addToast]);

    const handlePaletteRestart = useCallback(async () => {
        if (!selectedService) {
            addToast('warning', 'No service selected', 'Please select a service first');
            return;
        }
        const endpoint = selectedService.type === 'app'
            ? `/api/v1/applications/${selectedService.uuid}/restart`
            : `/api/v1/databases/${selectedService.uuid}/restart`;
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to restart');
            router.reload();
        } catch (err) {
            addToast('error', 'Restart failed', err instanceof Error ? err.message : 'Failed to restart service');
        }
    }, [selectedService, addToast]);

    const handlePaletteViewLogs = useCallback(() => {
        if (!selectedService) {
            addToast('warning', 'No service selected', 'Please select a service first');
            return;
        }
        setLogsViewerService(selectedService.name);
        setLogsViewerServiceUuid(selectedService.uuid);
        setLogsViewerServiceType(selectedService.type === 'db' ? 'database' : 'application');
        setLogsViewerOpen(true);
    }, [selectedService, addToast]);

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
        <>
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
                                    <DropdownItem
                                        key={env.id}
                                        onClick={() => handleSwitchEnv(env)}
                                    >
                                        {env.name}
                                        {env.id === selectedEnv?.id && (
                                            <span className="ml-2 text-primary">✓</span>
                                        )}
                                    </DropdownItem>
                                ))}
                                <DropdownDivider />
                                <DropdownItem onClick={() => setShowNewEnvModal(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    New Environment
                                </DropdownItem>
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
                    <Link
                        href={`/projects/${project.uuid}/settings`}
                        className="py-3 text-sm font-medium text-foreground-muted transition-colors hover:text-foreground"
                    >
                        Settings
                    </Link>
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
                    {activeView === 'architecture' && (
                        <>
                    {/* Left Toolbar - Premium Style */}
                    <div className="flex w-14 flex-col items-center gap-1 border-r border-border bg-background-secondary py-3">
                        {/* Add Service Button */}
                        <button
                            onClick={handleAddService}
                            className="group relative rounded-xl p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-tertiary hover:text-foreground hover:shadow-lg"
                            title="Add Service"
                        >
                            <Plus className="h-5 w-5" />
                            <span className="absolute left-full ml-2 hidden whitespace-nowrap rounded-lg bg-background-tertiary px-2 py-1 text-xs text-foreground shadow-xl group-hover:block">
                                Add Service
                            </span>
                        </button>

                        <div className="my-2 h-px w-8 bg-border" />

                        {/* Toggle Grid */}
                        <button
                            onClick={() => setShowGrid(!showGrid)}
                            className={`group relative rounded-xl p-2.5 transition-all duration-200 ${
                                showGrid
                                    ? 'bg-primary/20 text-primary shadow-lg shadow-primary/20'
                                    : 'text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                            }`}
                            title="Toggle Grid"
                        >
                            <Grid3x3 className="h-5 w-5" />
                        </button>

                        {/* Zoom In */}
                        <button
                            onClick={handleZoomIn}
                            className="group relative rounded-xl p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-tertiary hover:text-foreground"
                            title="Zoom In"
                        >
                            <ZoomIn className="h-5 w-5" />
                        </button>

                        {/* Zoom Level Indicator */}
                        <div className="text-xs font-medium text-foreground-subtle">
                            {Math.round(zoomLevel * 100)}%
                        </div>

                        {/* Zoom Out */}
                        <button
                            onClick={handleZoomOut}
                            className="group relative rounded-xl p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-tertiary hover:text-foreground"
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
                                    : 'text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                            }`}
                            title="Fullscreen"
                        >
                            <Maximize2 className="h-5 w-5" />
                        </button>

                        <div className="my-2 h-px w-8 bg-border" />

                        {/* Undo */}
                        <button
                            onClick={handleUndo}
                            disabled={!canUndo}
                            className="group relative rounded-xl p-2.5 text-foreground-subtle transition-all duration-200 hover:bg-background-tertiary hover:text-foreground-muted disabled:opacity-50"
                            title="Undo"
                        >
                            <Undo2 className="h-5 w-5" />
                        </button>

                        {/* Redo */}
                        <button
                            onClick={handleRedo}
                            disabled={!canRedo}
                            className="group relative rounded-xl p-2.5 text-foreground-subtle transition-all duration-200 hover:bg-background-tertiary hover:text-foreground-muted disabled:opacity-50"
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
                                showGrid={showGrid}
                                onQuickDeploy={handleQuickDeploy}
                                onQuickOpenUrl={handleQuickOpenUrl}
                                onQuickViewLogs={handleQuickViewLogs}
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
                            <button
                                onClick={() => setShowLocalSetup(true)}
                                className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground-muted shadow-lg transition-colors hover:bg-background-secondary hover:text-foreground"
                            >
                                <Terminal className="h-4 w-4" />
                                Set up your project locally
                            </button>
                        </div>

                        {/* Activity Panel */}
                        <ActivityPanel />
                    </div>
                        </>
                    )}

                    {activeView === 'observability' && (
                        <div className="flex-1 overflow-auto p-6">
                            <div className="mx-auto max-w-5xl space-y-6">
                                <div>
                                    <h2 className="text-lg font-semibold text-foreground">Observability</h2>
                                    <p className="mt-1 text-sm text-foreground-muted">Monitor health, performance and resource usage across all services.</p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {(selectedEnv?.applications || []).map((app) => (
                                        <div key={app.id} className="rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">{app.name}</span>
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(app.status || 'stopped')}`} />
                                            </div>
                                            <p className="mt-1 text-xs capitalize text-foreground-muted">{getStatusLabel(app.status || 'stopped')}</p>
                                            <div className="mt-3 flex items-center gap-2">
                                                <button
                                                    onClick={() => {
                                                        setLogsViewerService(app.name);
                                                        setLogsViewerServiceUuid(app.uuid);
                                                        setLogsViewerServiceType('application');
                                                        setLogsViewerOpen(true);
                                                    }}
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    View Logs
                                                </button>
                                                <span className="text-foreground-subtle">·</span>
                                                <Link href={`/applications/${app.uuid}`} className="text-xs text-primary hover:underline">
                                                    Details
                                                </Link>
                                            </div>
                                        </div>
                                    ))}
                                    {(selectedEnv?.databases || []).map((db) => (
                                        <div key={db.id} className="rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">{db.name}</span>
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(db.status || 'stopped')}`} />
                                            </div>
                                            <p className="mt-1 text-xs capitalize text-foreground-muted">{getStatusLabel(db.status || 'stopped')}</p>
                                            <div className="mt-3">
                                                <button
                                                    onClick={() => {
                                                        setLogsViewerService(db.name);
                                                        setLogsViewerServiceUuid(db.uuid);
                                                        setLogsViewerServiceType('database');
                                                        setLogsViewerOpen(true);
                                                    }}
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    View Logs
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {!(selectedEnv?.applications?.length || selectedEnv?.databases?.length) && (
                                    <div className="flex flex-col items-center justify-center py-16 text-foreground-muted">
                                        <Activity className="h-10 w-10 mb-3 opacity-50" />
                                        <p className="text-sm">No services to monitor yet.</p>
                                        <p className="mt-1 text-xs">Add applications or databases to get started.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {activeView === 'logs' && (
                        <div className="flex-1 overflow-auto p-6">
                            <div className="mx-auto max-w-5xl space-y-6">
                                <div>
                                    <h2 className="text-lg font-semibold text-foreground">Logs</h2>
                                    <p className="mt-1 text-sm text-foreground-muted">View logs for all services in this environment.</p>
                                </div>
                                <div className="space-y-3">
                                    {(selectedEnv?.applications || []).map((app) => (
                                        <button
                                            key={app.id}
                                            onClick={() => {
                                                setLogsViewerService(app.name);
                                                setLogsViewerServiceUuid(app.uuid);
                                                setLogsViewerServiceType('application');
                                                setLogsViewerOpen(true);
                                            }}
                                            className="flex w-full items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:border-primary/50 hover:bg-background-tertiary"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-500/20 text-cyan-400">
                                                    <Box className="h-4 w-4" />
                                                </div>
                                                <div className="text-left">
                                                    <p className="font-medium text-foreground">{app.name}</p>
                                                    <p className="text-xs text-foreground-muted">Application</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(app.status || 'stopped')}`} />
                                                <FileText className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                        </button>
                                    ))}
                                    {(selectedEnv?.databases || []).map((db) => (
                                        <button
                                            key={db.id}
                                            onClick={() => {
                                                setLogsViewerService(db.name);
                                                setLogsViewerServiceUuid(db.uuid);
                                                setLogsViewerServiceType('database');
                                                setLogsViewerOpen(true);
                                            }}
                                            className="flex w-full items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:border-primary/50 hover:bg-background-tertiary"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${getDbBgColor(db.database_type)}`}>
                                                    {getDbLogo(db.database_type)}
                                                </div>
                                                <div className="text-left">
                                                    <p className="font-medium text-foreground">{db.name}</p>
                                                    <p className="text-xs text-foreground-muted">Database</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(db.status || 'stopped')}`} />
                                                <FileText className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                        </button>
                                    ))}
                                </div>
                                {!(selectedEnv?.applications?.length || selectedEnv?.databases?.length) && (
                                    <div className="flex flex-col items-center justify-center py-16 text-foreground-muted">
                                        <FileText className="h-10 w-10 mb-3 opacity-50" />
                                        <p className="text-sm">No services to view logs for.</p>
                                        <p className="mt-1 text-xs">Add applications or databases to get started.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

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
                                                <div className={`h-1.5 w-1.5 rounded-full ${getStatusDotClass(selectedService.status || 'stopped')}`} />
                                                <span className="capitalize">{getStatusLabel(selectedService.status || 'stopped')}</span>
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
                                                addToast('success', 'Copied', 'URL copied to clipboard');
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

                                {/* Server & Status Info */}
                                <div className="mt-2 flex items-center gap-2 text-xs text-foreground-muted">
                                    <span className="flex items-center gap-1">
                                        <Globe className="h-3 w-3" />
                                        {(() => {
                                            const env = project.environments?.[0];
                                            if (selectedService.type === 'app') {
                                                const app = env?.applications?.find(a => String(a.id) === selectedService.id);
                                                return app?.destination?.server?.name || 'Server';
                                            }
                                            const db = env?.databases?.find(d => String(d.id) === selectedService.id);
                                            return db?.destination?.server?.name || 'Server';
                                        })()}
                                    </span>
                                    <span>·</span>
                                    <span className="flex items-center gap-1">
                                        <Users className="h-3 w-3" />
                                        {getStatusLabel(selectedService.status || 'stopped')}
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

            {/* Local Setup Modal */}
            <LocalSetupModal
                isOpen={showLocalSetup}
                onClose={() => setShowLocalSetup(false)}
                environment={selectedEnv}
            />

            {/* Logs Viewer Modal */}
            <LogsViewer
                isOpen={logsViewerOpen}
                onClose={() => setLogsViewerOpen(false)}
                serviceName={logsViewerService}
                serviceUuid={logsViewerServiceUuid}
                serviceType={logsViewerServiceType}
            />

            {/* New Environment Modal */}
            <Modal
                isOpen={showNewEnvModal}
                onClose={() => { setShowNewEnvModal(false); setNewEnvName(''); }}
                title="New Environment"
                description="Create a new environment for this project."
                size="sm"
            >
                <div className="space-y-4">
                    <div>
                        <label htmlFor="env-name" className="block text-sm font-medium text-foreground">
                            Environment Name
                        </label>
                        <Input
                            id="env-name"
                            value={newEnvName}
                            onChange={(e) => setNewEnvName(e.target.value)}
                            placeholder="e.g. staging, development"
                            className="mt-1"
                            onKeyDown={(e) => e.key === 'Enter' && handleCreateEnv()}
                            autoFocus
                        />
                    </div>
                </div>
                <ModalFooter>
                    <Button
                        variant="secondary"
                        onClick={() => { setShowNewEnvModal(false); setNewEnvName(''); }}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleCreateEnv}
                        disabled={!newEnvName.trim() || creatingEnv}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {creatingEnv ? 'Creating...' : 'Create Environment'}
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
}
