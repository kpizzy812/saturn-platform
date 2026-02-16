import { useState, useCallback, useRef, useEffect, useMemo } from 'react';
import { getStatusLabel, getStatusDotClass } from '@/lib/statusUtils';
import { Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Button, Input, useConfirm, useTheme, BrandIcon } from '@/components/ui';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Plus, Settings, ChevronDown, Play, X, Activity, Variable, Gauge, Cog, ExternalLink, Copy, ChevronRight, ArrowLeft, Grid3x3, ZoomIn, ZoomOut, Maximize2, Undo2, Redo2, Terminal, Globe, Users, FileText, Database, Key, Link2, HardDrive, Table, Box, Layers, GitBranch, Command, Search, Sun, Moon, ArrowUpRight, Import } from 'lucide-react';
import type { Project, Environment } from '@/types';
import { ProjectCanvas } from '@/components/features/canvas';
import { CommandPalette } from '@/components/features/CommandPalette';
import { ContextMenu, type ContextMenuPosition, type ContextMenuNode } from '@/components/features/ContextMenu';
import { LogsViewer } from '@/components/features/LogsViewer';
import { useToast } from '@/components/ui/Toast';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';

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
    DatabaseImportTab,
    LocalSetupModal,
} from '@/components/features/Projects';
import { ApprovalRequiredModal } from '@/components/features/ApprovalRequiredModal';
import { MigrateModal, EnvironmentMigrateModal } from '@/components/features/migration';
import { CloneModal } from '@/components/transfer';
import { useMigrationTargets } from '@/hooks/useMigrations';
import type { EnvironmentMigration, EnvironmentMigrationOptions } from '@/types';

interface Props {
    project?: Project;
    userRole?: string;
    canManageEnvironments?: boolean;
}

export default function ProjectShow({ project, userRole = 'member', canManageEnvironments = false }: Props) {
    const [selectedEnv, setSelectedEnv] = useState<Environment | null>(project?.environments?.[0] || null);
    const [selectedService, setSelectedService] = useState<SelectedService | null>(null);
    const [activeAppTab, setActiveAppTab] = useState<'deployments' | 'variables' | 'metrics' | 'settings'>('deployments');
    const [activeDbTab, setActiveDbTab] = useState<'data' | 'connect' | 'credentials' | 'backups' | 'import' | 'extensions' | 'settings'>('connect');
    const [activeServiceTab, setActiveServiceTab] = useState<'overview' | 'variables' | 'logs'>('overview');
    const [activeView, setActiveView] = useState<'architecture' | 'observability' | 'logs'>('architecture');
    const [changedResources, setChangedResources] = useState<Map<string, { uuid: string; type: 'app' | 'db' | 'service'; name: string; kind: 'variables' | 'config'; needsRebuild: boolean }>>(new Map());
    const hasStagedChanges = changedResources.size > 0;
    const [showLocalSetup, setShowLocalSetup] = useState(false);
    const [showNewEnvModal, setShowNewEnvModal] = useState(false);
    const [newEnvName, setNewEnvName] = useState('');
    const [creatingEnv, setCreatingEnv] = useState(false);

    // Real-time status tracking for applications, databases, and services
    const [appStatuses, setAppStatuses] = useState<Record<number, string>>({});
    const [dbStatuses, setDbStatuses] = useState<Record<number, string>>({});
    const [serviceStatuses, setServiceStatuses] = useState<Record<number, string>>({});
    const [deployingApps, setDeployingApps] = useState<Record<number, string>>({});

    // Hooks - must be called before early return
    const { addToast } = useToast();
    const confirm = useConfirm();
    const { isDark, toggleTheme } = useTheme();

    // Canvas control state
    const [showGrid, setShowGrid] = useState(true);
    const [zoomLevel, setZoomLevel] = useState(1);
    const [isFullscreen, setIsFullscreen] = useState(false);

    // Context menu state (moved here to comply with hooks rules)
    const [contextMenuPosition, setContextMenuPosition] = useState<ContextMenuPosition | null>(null);
    const [contextMenuNode, setContextMenuNode] = useState<ContextMenuNode | null>(null);

    // Approval modal state
    const [showApprovalModal, setShowApprovalModal] = useState(false);
    const [approvalPendingApp, setApprovalPendingApp] = useState<{
        uuid: string;
        name: string;
        environmentName: string;
        environmentType: string;
    } | null>(null);

    // Logs viewer state
    const [logsViewerOpen, setLogsViewerOpen] = useState(false);
    const [logsViewerService, setLogsViewerService] = useState<string>('');
    const [logsViewerServiceUuid, setLogsViewerServiceUuid] = useState<string>('');
    const [logsViewerServiceType, setLogsViewerServiceType] = useState<'application' | 'deployment' | 'database' | 'service'>('application');
    const [logsViewerContainerName, setLogsViewerContainerName] = useState<string | undefined>(undefined);

    // Migration modal state
    const [showMigrateModal, setShowMigrateModal] = useState(false);
    const [migrateSource, setMigrateSource] = useState<{
        type: 'application' | 'service' | 'database';
        uuid: string;
        name: string;
    } | null>(null);

    // Environment migration modal state (migrate all resources)
    const [showEnvMigrateModal, setShowEnvMigrateModal] = useState(false);

    // Clone modal state
    const [showCloneModal, setShowCloneModal] = useState(false);
    const [cloneSource, setCloneSource] = useState<{
        uuid: string;
        name: string;
        type: 'application' | 'database';
    } | null>(null);

    // Check if user can clone (admin or owner only)
    const canClone = userRole === 'admin' || userRole === 'owner';

    // Undo/Redo history for canvas state
    const historyRef = useRef<{ past: SelectedService[][]; future: SelectedService[][] }>({
        past: [],
        future: [],
    });
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            setAppStatuses((prev) => ({
                ...prev,
                [data.applicationId]: data.status,
            }));
            // Update selected service status if it's the same application
            if (selectedService?.type === 'app' && selectedService?.id === String(data.applicationId)) {
                setSelectedService((prev) => prev ? { ...prev, status: data.status } : null);
            }
        },
        onDatabaseStatusChange: (data) => {
            setDbStatuses((prev) => ({
                ...prev,
                [data.databaseId]: data.status,
            }));
            // Update selected service status if it's the same database
            if (selectedService?.type === 'db' && selectedService?.id === String(data.databaseId)) {
                setSelectedService((prev) => prev ? { ...prev, status: data.status } : null);
            }
        },
        onServiceStatusChange: (data) => {
            setServiceStatuses((prev) => ({
                ...prev,
                [data.serviceId]: data.status,
            }));
            // Update selected service status if it's the same service
            if (selectedService?.type === 'service' && selectedService?.id === String(data.serviceId)) {
                setSelectedService((prev) => prev ? { ...prev, status: data.status } : null);
            }
        },
        onDeploymentCreated: (data) => {
            setDeployingApps((prev) => ({
                ...prev,
                [data.applicationId]: data.status,
            }));
        },
        onDeploymentFinished: (data) => {
            setDeployingApps((prev) => {
                const next = { ...prev };
                delete next[data.applicationId];
                return next;
            });
        },
    });

    // Polling fallback for status updates (works with session auth, no WebSocket needed)
    useEffect(() => {
        if (!selectedEnv?.uuid) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const pollStatuses = async () => {
            try {
                const res = await fetch(`/web-api/environments/${selectedEnv.uuid}/statuses`, {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (!res.ok) return;

                const data = await res.json() as {
                    applications: Record<string, string>;
                    databases: Record<string, string>;
                    services: Record<string, string>;
                    deploying: Record<string, string>;
                };

                // Update app statuses
                setAppStatuses((prev) => {
                    const next = { ...prev };
                    let changed = false;
                    for (const [id, status] of Object.entries(data.applications)) {
                        if (next[Number(id)] !== status) {
                            next[Number(id)] = status;
                            changed = true;
                        }
                    }
                    return changed ? next : prev;
                });

                // Update db statuses
                setDbStatuses((prev) => {
                    const next = { ...prev };
                    let changed = false;
                    for (const [id, status] of Object.entries(data.databases)) {
                        if (next[Number(id)] !== status) {
                            next[Number(id)] = status;
                            changed = true;
                        }
                    }
                    return changed ? next : prev;
                });

                // Update service statuses
                setServiceStatuses((prev) => {
                    const next = { ...prev };
                    let changed = false;
                    for (const [id, status] of Object.entries(data.services)) {
                        if (next[Number(id)] !== status) {
                            next[Number(id)] = status;
                            changed = true;
                        }
                    }
                    return changed ? next : prev;
                });

                // Update deploying apps
                setDeployingApps((prev) => {
                    const next: Record<number, string> = {};
                    for (const [id, status] of Object.entries(data.deploying || {})) {
                        next[Number(id)] = status;
                    }
                    // Quick equality check
                    const prevKeys = Object.keys(prev);
                    const nextKeys = Object.keys(next);
                    if (prevKeys.length === nextKeys.length && prevKeys.every((k) => prev[Number(k)] === next[Number(k)])) {
                        return prev;
                    }
                    return next;
                });

                // Sync selected service panel status
                setSelectedService((prev) => {
                    if (!prev) return null;
                    const statusMap = prev.type === 'app' ? data.applications
                        : prev.type === 'db' ? data.databases
                        : data.services;
                    const newStatus = statusMap[prev.id];
                    if (newStatus && newStatus !== prev.status) {
                        return { ...prev, status: newStatus };
                    }
                    return prev;
                });
            } catch {
                // Silently ignore polling errors
            }
        };

        // Initial fetch
        pollStatuses();

        // Poll every 5 seconds
        const interval = setInterval(pollStatuses, 5000);
        return () => clearInterval(interval);
    }, [selectedEnv?.uuid]);

    // Compute environments with real-time statuses
    const envWithRealtimeStatuses = useMemo(() => {
        if (!selectedEnv) return null;

        return {
            ...selectedEnv,
            applications: selectedEnv.applications?.map((app) => ({
                ...app,
                status: appStatuses[app.id] ?? app.status,
            })),
            databases: selectedEnv.databases?.map((db) => ({
                ...db,
                status: dbStatuses[db.id] ?? db.status,
            })),
            services: selectedEnv.services?.map((service) => ({
                ...service,
                status: serviceStatuses[service.id] ?? service.status,
            })),
        };
    }, [selectedEnv, appStatuses, dbStatuses, serviceStatuses]);

    // Migration targets hook - load when migration modal is open
    const { targets: migrationTargets, isLoading: isLoadingMigrationTargets } = useMigrationTargets(
        migrateSource?.type || 'application',
        migrateSource?.uuid || '',
        showMigrateModal && !!migrateSource
    );

    // Handle migration submission
    const handleMigrate = useCallback(async (data: {
        targetEnvironmentId: number;
        targetServerId: number;
        options: EnvironmentMigrationOptions;
    }): Promise<{ migration: EnvironmentMigration; requires_approval: boolean }> => {
        if (!migrateSource) {
            throw new Error('No source selected for migration');
        }

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/api/v1/migrations', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'include',
            body: JSON.stringify({
                source_type: migrateSource.type,
                source_uuid: migrateSource.uuid,
                target_environment_id: data.targetEnvironmentId,
                target_server_id: data.targetServerId,
                options: data.options,
            }),
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to start migration');
        }

        const result = await response.json();
        addToast('success', 'Migration Started', result.requires_approval
            ? 'Migration request submitted for approval'
            : 'Migration is being processed');

        return result;
    }, [migrateSource, addToast]);

    // Open migration modal for a specific resource
    const openMigrationModal = useCallback((type: 'application' | 'service' | 'database', uuid: string, name: string) => {
        setMigrateSource({ type, uuid, name });
        setShowMigrateModal(true);
    }, []);

    // Open clone modal for a specific resource
    const openCloneModal = useCallback((type: 'application' | 'database', uuid: string, name: string) => {
        setCloneSource({ type, uuid, name });
        setShowCloneModal(true);
    }, []);

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
            const res = await fetch(`/projects/${project?.uuid}/environments`, {
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
            router.visit(`/services/create?project=${project?.uuid}&environment=${selectedEnv.uuid}`);
        } else {
            router.visit(`/services/create?project=${project?.uuid}`);
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
        void newService; // Used by callers to set service after tracking
    }, [selectedService]);

    const handleNodeClick = useCallback((id: string, type: string) => {
        if (!selectedEnv) return;

        let newService: SelectedService | null = null;

        if (type === 'app') {
            const app = selectedEnv.applications?.find(a => String(a.id) === id);
            if (app) {
                newService = {
                    id: String(app.id),
                    uuid: app.uuid,
                    type: 'app',
                    name: app.name,
                    status: app.status || 'unknown',
                    fqdn: app.fqdn ?? undefined,
                    serverUuid: app.destination?.server?.uuid,
                };
            }
        } else if (type === 'db') {
            const db = selectedEnv.databases?.find(d => String(d.id) === id);
            if (db) {
                newService = {
                    id: String(db.id),
                    uuid: db.uuid,
                    type: 'db',
                    name: db.name,
                    status: typeof db.status === 'object' ? db.status.state : (db.status || 'unknown'),
                    dbType: db.database_type,
                    serverUuid: db.destination?.server?.uuid,
                };
            }
        } else if (type === 'svc') {
            const svc = selectedEnv.services?.find(s => String(s.id) === id);
            if (svc) {
                const svcFqdn = svc.applications
                    ?.map((app: { fqdn?: string | null }) => app.fqdn)
                    .filter(Boolean)[0] || undefined;
                newService = {
                    id: String(svc.id),
                    uuid: svc.uuid,
                    type: 'service',
                    name: svc.name,
                    status: svc.status || 'unknown',
                    description: svc.description,
                    fqdn: svcFqdn,
                };
            }
        }

        if (newService) {
            trackStateChange(newService);
            setSelectedService(newService);
        }

        // Reset to default tab based on type
        if (type === 'app') {
            setActiveAppTab('deployments');
        } else if (type === 'db') {
            setActiveDbTab('connect');
        } else if (type === 'svc') {
            setActiveServiceTab('overview');
        }
    }, [selectedEnv, trackStateChange]);

    const closePanel = () => setSelectedService(null);

    // Handle right-click on nodes
    const handleNodeContextMenu = useCallback((id: string, type: string, x: number, y: number) => {
        if (!selectedEnv) return;

        let nodeData: ContextMenuNode | null = null;

        if (type === 'app') {
            const app = selectedEnv.applications?.find(a => String(a.id) === id);
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
            const db = selectedEnv.databases?.find(d => String(d.id) === id);
            if (db) {
                nodeData = {
                    id: String(db.id),
                    uuid: db.uuid,
                    type: 'db',
                    name: db.name,
                    status: (db.status || 'unknown') as any,
                };
            }
        }

        if (nodeData) {
            setContextMenuPosition({ x, y });
            setContextMenuNode(nodeData);
        }
    }, [selectedEnv]);

    const closeContextMenu = () => {
        setContextMenuPosition(null);
        setContextMenuNode(null);
    };

    const handleViewLogs = (_nodeId: string) => {
        const node = contextMenuNode;
        if (node) {
            setLogsViewerService(node.name);
            setLogsViewerServiceUuid(node.uuid);
            setLogsViewerServiceType(node.type === 'db' ? 'database' : node.type === 'service' ? 'service' : 'application');
            setLogsViewerContainerName(undefined);
            setLogsViewerOpen(true);
        }
    };

    const handleCopyId = (_nodeId: string) => {
        navigator.clipboard.writeText(_nodeId);
    };

    const handleOpenUrl = (url: string) => {
        window.open(url, '_blank');
    };

    // Execute actual deployment
    const executeDeployment = useCallback(async (appUuid: string) => {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(`/applications/${appUuid}/deploy/json`, {
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

        return response.json();
    }, []);

    // Request deployment approval
    const requestDeploymentApproval = useCallback(async () => {
        if (!approvalPendingApp) return;

        // For approval flow, just create the deployment - it will be in pending state
        // The backend handles the approval workflow
        await executeDeployment(approvalPendingApp.uuid);

        addToast('success', 'Approval Requested', 'Your deployment request has been submitted for approval');
        router.reload();
    }, [approvalPendingApp, executeDeployment, addToast]);

    // API action handlers for context menu
    const handleDeploy = useCallback(async (_nodeId: string) => {
        const node = contextMenuNode;
        if (!node) return;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

            // Check if approval is required
            const checkResponse = await fetch(`/applications/${node.uuid}/check-approval/json`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });

            if (checkResponse.ok) {
                const checkData = await checkResponse.json();

                if (checkData.requires_approval) {
                    // Show approval modal instead of deploying directly
                    setApprovalPendingApp({
                        uuid: node.uuid,
                        name: node.name,
                        environmentName: checkData.environment?.name || selectedEnv?.name || 'Unknown',
                        environmentType: checkData.environment?.type || 'development',
                    });
                    setShowApprovalModal(true);
                    return;
                }
            }

            // No approval required, deploy directly
            await executeDeployment(node.uuid);
            addToast('success', 'Deployment Started', `Deploying ${node.name}`);
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [contextMenuNode, selectedEnv, executeDeployment, addToast]);

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

            if (response.status === 419) {
                addToast('error', 'Session expired', 'Please refresh the page and try again.');
                return;
            }

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
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

            if (response.status === 419) {
                addToast('error', 'Session expired', 'Please refresh the page and try again.');
                return;
            }

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
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

            if (response.status === 419) {
                addToast('error', 'Session expired', 'Please refresh the page and try again.');
                return;
            }

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || 'Failed to delete');
            }

            setSelectedService(null);
            setContextMenuNode(null);
            router.reload();
        } catch (err) {
            addToast('error', 'Delete failed', err instanceof Error ? err.message : 'Failed to delete resource');
        }
    }, [contextMenuNode, addToast]);

    // Callback for child tabs to report staged changes per resource
    const handleVariableChanged = useCallback((isBuildTime?: boolean) => {
        if (!selectedService) return;
        setChangedResources(prev => {
            const next = new Map(prev);
            const existing = next.get(selectedService.uuid);
            next.set(selectedService.uuid, {
                uuid: selectedService.uuid,
                type: selectedService.type,
                name: selectedService.name,
                kind: 'variables',
                needsRebuild: existing?.needsRebuild || !!isBuildTime,
            });
            return next;
        });
    }, [selectedService]);

    const handleConfigChanged = useCallback(() => {
        if (!selectedService) return;
        setChangedResources(prev => {
            const next = new Map(prev);
            const existing = next.get(selectedService.uuid);
            next.set(selectedService.uuid, {
                uuid: selectedService.uuid,
                type: selectedService.type,
                name: selectedService.name,
                kind: 'config',
                needsRebuild: existing?.needsRebuild || false,
            });
            return next;
        });
    }, [selectedService]);

    const handleDiscardStagedChanges = useCallback(() => {
        setChangedResources(new Map());
    }, []);

    // Deploy only changed resources (force rebuild for build-time env changes)
    const handleDeployChanges = useCallback(async () => {
        if (changedResources.size === 0) return;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const deployPromises = Array.from(changedResources.values()).map(resource => {
                let endpoint: string;
                let body: string | undefined;
                if (resource.type === 'app' && resource.needsRebuild) {
                    // Build-time env changed — full rebuild required
                    endpoint = `/api/v1/applications/${resource.uuid}/start`;
                    body = JSON.stringify({ force: true });
                } else if (resource.type === 'app') {
                    endpoint = `/api/v1/applications/${resource.uuid}/restart`;
                } else if (resource.type === 'db') {
                    endpoint = `/api/v1/databases/${resource.uuid}/restart`;
                } else {
                    endpoint = `/api/v1/services/${resource.uuid}/restart`;
                }
                return fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                    ...(body ? { body } : {}),
                });
            });

            await Promise.all(deployPromises);
            const names = Array.from(changedResources.values()).map(r => r.name).join(', ');
            setChangedResources(new Map());
            addToast('success', 'Deploy started', `Redeploying: ${names}`);
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy changes');
        }
    }, [changedResources, addToast]);

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

            if (response.status === 419) {
                addToast('error', 'Session expired', 'Please refresh the page and try again.');
                return;
            }

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
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
            const response = await fetch(`/applications/${uuid}/deploy/json`, {
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
            addToast('success', 'Deploy started', 'Application deployment has been initiated.');
            router.reload();
        } catch (err) {
            addToast('error', 'Deploy failed', err instanceof Error ? err.message : 'Failed to deploy application');
        }
    }, [addToast]);

    const handleQuickOpenUrl = useCallback((url: string) => {
        window.open(url.startsWith('http') ? url : `https://${url}`, '_blank');
    }, []);

    const handleQuickViewLogs = useCallback((uuid: string, name: string, type: 'application' | 'database' | 'service') => {
        setLogsViewerService(name);
        setLogsViewerServiceUuid(uuid);
        setLogsViewerServiceType(type);
        setLogsViewerContainerName(undefined);
        setLogsViewerOpen(true);
    }, []);

    const handleQuickRestartService = useCallback(async (uuid: string) => {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/services/${uuid}/restart`, {
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
            addToast('success', 'Restart initiated', 'Service restart has been initiated.');
            router.reload();
        } catch (err) {
            addToast('error', 'Restart failed', err instanceof Error ? err.message : 'Failed to restart service');
        }
    }, [addToast]);

    const handleQuickStopService = useCallback(async (uuid: string) => {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/services/${uuid}/stop`, {
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
            addToast('success', 'Stop initiated', 'Service stop has been initiated.');
            router.reload();
        } catch (err) {
            addToast('error', 'Stop failed', err instanceof Error ? err.message : 'Failed to stop service');
        }
    }, [addToast]);

    const handleQuickBrowseData = useCallback((uuid: string, databaseType: string) => {
        // Redis-like databases go to Keys tab, SQL databases go to Tables
        const type = databaseType?.toLowerCase() || '';
        const isRedisLike = type.includes('redis') || type.includes('keydb') || type.includes('dragonfly');

        if (isRedisLike) {
            // Navigate to database page with Keys tab active
            router.visit(`/databases/${uuid}?tab=keys`);
        } else {
            // Navigate to table browser for SQL databases
            router.visit(`/databases/${uuid}/tables?tab=data`);
        }
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
            if (response.status === 419) { addToast('error', 'Session expired', 'Please refresh the page.'); return; }
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
            if (response.status === 419) { addToast('error', 'Session expired', 'Please refresh the page.'); return; }
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
        setLogsViewerServiceType(selectedService.type === 'db' ? 'database' : selectedService.type === 'service' ? 'service' : 'application');
        setLogsViewerContainerName(undefined);
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

    // Show loading state if project is not available (after all hooks)
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
            <div className="flex h-screen flex-col overflow-x-hidden bg-background">
                {/* Top Header — single row on desktop, two rows on mobile */}
                <header className="border-b border-border bg-background">
                    {/* Mobile: Row 1 — navigation */}
                    <div className="flex h-10 items-center justify-between px-3 md:hidden">
                        <div className="flex min-w-0 items-center gap-2">
                            <Link href="/projects" className="flex-shrink-0 text-foreground-muted hover:text-foreground">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <span className="truncate text-sm font-medium text-foreground">{project.name}</span>
                            <ChevronRight className="h-3 w-3 flex-shrink-0 text-foreground-subtle" />
                            <Dropdown>
                                <DropdownTrigger>
                                    <button className="flex items-center gap-1 text-sm font-medium text-foreground">
                                        <span className="truncate">{selectedEnv?.name || 'production'}</span>
                                        <ChevronDown className="h-3 w-3 flex-shrink-0 text-foreground-muted" />
                                    </button>
                                </DropdownTrigger>
                                <DropdownContent>
                                    {project.environments?.map((env) => (
                                        <DropdownItem key={env.id} onClick={() => handleSwitchEnv(env)}>
                                            {env.name}
                                            {env.id === selectedEnv?.id && <span className="ml-2 text-primary">✓</span>}
                                        </DropdownItem>
                                    ))}
                                </DropdownContent>
                            </Dropdown>
                        </div>
                        <button
                            onClick={() => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }))}
                            className="flex-shrink-0 rounded-md p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                        >
                            <Search className="h-4 w-4" />
                        </button>
                    </div>

                    {/* Desktop: single row */}
                    <div className="hidden h-12 items-center justify-between px-4 md:flex">
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
                            <button
                                onClick={() => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }))}
                                className="flex items-center gap-2 rounded-md border border-border bg-background-secondary px-3 py-1.5 text-sm text-foreground-muted hover:bg-background-tertiary hover:text-foreground transition-colors"
                            >
                                <Search className="h-3.5 w-3.5" />
                                <span>Search...</span>
                                <kbd className="flex items-center gap-0.5 rounded bg-background px-1.5 py-0.5 text-xs">
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
                                        <div key={env.id} className="flex items-center justify-between">
                                            <DropdownItem
                                                onClick={() => handleSwitchEnv(env)}
                                                className="flex-1"
                                            >
                                                {env.name}
                                                {env.id === selectedEnv?.id && (
                                                    <span className="ml-2 text-primary">✓</span>
                                                )}
                                            </DropdownItem>
                                            {canManageEnvironments && (
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        router.visit(`/environments/${env.uuid}/settings`);
                                                    }}
                                                    className="p-1.5 text-foreground-muted hover:text-foreground hover:bg-background-tertiary rounded"
                                                    title="Environment Settings"
                                                >
                                                    <Cog className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    <DropdownDivider />
                                    <DropdownItem onClick={() => setShowNewEnvModal(true)}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        New Environment
                                    </DropdownItem>
                                </DropdownContent>
                            </Dropdown>
                            <button
                                onClick={toggleTheme}
                                className="rounded-md p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                                title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                            >
                                {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                            </button>
                            <Link href={`/projects/${project.uuid}/settings`}>
                                <button className="rounded-md p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground">
                                    <Settings className="h-4 w-4" />
                                </button>
                            </Link>
                        </div>
                    </div>
                </header>

                {/* View Tabs */}
                <div className="flex items-center gap-3 overflow-x-auto border-b border-border bg-background px-3 md:gap-6 md:px-6">
                    <button
                        onClick={() => setActiveView('architecture')}
                        className={`whitespace-nowrap py-3 text-sm font-medium transition-colors ${
                            activeView === 'architecture'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Architecture
                    </button>
                    <button
                        onClick={() => setActiveView('observability')}
                        className={`whitespace-nowrap py-3 text-sm font-medium transition-colors ${
                            activeView === 'observability'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Observability
                    </button>
                    <button
                        onClick={() => setActiveView('logs')}
                        className={`whitespace-nowrap py-3 text-sm font-medium transition-colors ${
                            activeView === 'logs'
                                ? 'border-b-2 border-primary text-foreground'
                                : 'text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        Logs
                    </button>
                    <Link
                        href={`/projects/${project.uuid}/settings`}
                        className="whitespace-nowrap py-3 text-sm font-medium text-foreground-muted transition-colors hover:text-foreground"
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
                                    Changes require redeployment
                                </p>
                                <p className="text-xs text-foreground-muted">
                                    {Array.from(changedResources.values()).map(r => {
                                        const detail = r.kind === 'variables' ? 'env variables' : 'config';
                                        return r.needsRebuild ? `${r.name} (${detail}, rebuild)` : `${r.name} (${detail})`;
                                    }).join(', ')}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={handleDiscardStagedChanges}
                            >
                                Dismiss
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
                    {/* Left Toolbar - Premium Style (hidden on mobile) */}
                    <div className="hidden md:flex w-14 flex-col items-center gap-1 border-r border-border bg-background-secondary py-3">
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
                        {envWithRealtimeStatuses && (
                            <ProjectCanvas
                                key={envWithRealtimeStatuses.uuid}
                                applications={(envWithRealtimeStatuses.applications || []) as any}
                                databases={(envWithRealtimeStatuses.databases || []) as any}
                                services={(envWithRealtimeStatuses.services || []) as any}
                                environmentUuid={envWithRealtimeStatuses.uuid}
                                deployingApps={deployingApps}
                                onNodeClick={handleNodeClick}
                                onNodeContextMenu={handleNodeContextMenu}
                                onViewportChange={handleViewportChange}
                                showGrid={showGrid}
                                onQuickDeploy={handleQuickDeploy}
                                onQuickOpenUrl={handleQuickOpenUrl}
                                onQuickViewLogs={handleQuickViewLogs}
                                onQuickBrowseData={handleQuickBrowseData}
                                onQuickRestartService={handleQuickRestartService}
                                onQuickStopService={handleQuickStopService}
                            />
                        )}

                        {/* Mobile floating toolbar (md:hidden) */}
                        <div className="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background-secondary/95 px-2 py-1.5 shadow-lg backdrop-blur-sm md:hidden">
                            <button
                                onClick={handleAddService}
                                className="rounded-full p-2.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                title="Add Service"
                            >
                                <Plus className="h-5 w-5" />
                            </button>
                            <div className="h-5 w-px bg-border" />
                            <button
                                onClick={() => setShowGrid(!showGrid)}
                                className={`rounded-full p-2.5 transition-colors ${showGrid ? 'text-primary' : 'text-foreground-muted hover:bg-background-tertiary hover:text-foreground'}`}
                                title="Toggle Grid"
                            >
                                <Grid3x3 className="h-5 w-5" />
                            </button>
                            <div className="h-5 w-px bg-border" />
                            <button
                                onClick={handleFitView}
                                className="rounded-full p-2.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                title="Fit View"
                            >
                                <Maximize2 className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Canvas Overlay Buttons */}
                        <div className="absolute right-2 top-2 z-10 flex gap-1.5 md:right-4 md:top-4 md:gap-2">
                            {/* Migrate Environment Button - hidden on mobile */}
                            {selectedEnv && (selectedEnv as any).type !== 'production' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="hidden shadow-lg md:flex"
                                    onClick={() => setShowEnvMigrateModal(true)}
                                >
                                    <ArrowUpRight className="mr-2 h-4 w-4" />
                                    Migrate
                                </Button>
                            )}
                            <Dropdown>
                                <DropdownTrigger>
                                    <Button size="sm" className="shadow-lg">
                                        <Plus className="h-4 w-4 md:mr-2" />
                                        <span className="hidden md:inline">Create</span>
                                        <ChevronDown className="ml-1 h-3 w-3 md:ml-2" />
                                    </Button>
                                </DropdownTrigger>
                                <DropdownContent align="right" className="w-64">
                                    {/* GitHub */}
                                    <DropdownItem
                                        className="flex items-center gap-3 py-3"
                                        onClick={() => router.visit(`/applications/create?source=github&project=${project.uuid}&environment=${selectedEnv?.uuid}`)}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#24292e]">
                                            <BrandIcon name="github" className="h-4 w-4" />
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
                                            <BrandIcon name="docker" className="h-4 w-4" />
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

                        <div className="absolute bottom-4 left-4 z-10 hidden md:block">
                            <button
                                onClick={() => setShowLocalSetup(true)}
                                className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground-muted shadow-lg transition-colors hover:bg-background-secondary hover:text-foreground"
                            >
                                <Terminal className="h-4 w-4" />
                                Set up your project locally
                            </button>
                        </div>

                        {/* Activity Panel (hidden on mobile) */}
                        <div className="hidden md:block">
                            <ActivityPanel />
                        </div>
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
                                    {(envWithRealtimeStatuses?.applications || []).map((app) => {
                                        const appState = String(app.status || 'stopped').split(':')[0];
                                        const appHealth = String(app.status || '').split(':')[1];
                                        return (
                                        <div key={app.id} className="rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">{app.name}</span>
                                                <div className="flex items-center gap-1.5">
                                                    <div className={`h-2 w-2 rounded-full ${getStatusDotClass(appState)}`} />
                                                    {appHealth && appHealth !== 'unknown' && (
                                                        <div className={`h-2 w-2 rounded-full ${appHealth === 'healthy' ? 'bg-success' : appHealth === 'unhealthy' ? 'bg-red-500' : 'bg-foreground-subtle'}`} />
                                                    )}
                                                </div>
                                            </div>
                                            <p className="mt-1 text-xs text-foreground-muted">
                                                <span className="capitalize">{getStatusLabel(appState)}</span>
                                                {appHealth && appHealth !== 'unknown' && (
                                                    <span className={`ml-1.5 capitalize ${appHealth === 'healthy' ? 'text-success' : appHealth === 'unhealthy' ? 'text-red-400' : ''}`}>· {appHealth}</span>
                                                )}
                                            </p>
                                            <div className="mt-3 flex items-center gap-2">
                                                <button
                                                    onClick={() => {
                                                        setLogsViewerService(app.name);
                                                        setLogsViewerServiceUuid(app.uuid);
                                                        setLogsViewerServiceType('application');
                                                        setLogsViewerContainerName(undefined);
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
                                        );
                                    })}
                                    {(envWithRealtimeStatuses?.databases || []).map((db) => {
                                        const dbStatusStr = typeof db.status === 'object' ? `${db.status.state}:${db.status.health}` : String(db.status || 'stopped');
                                        const dbState = dbStatusStr.split(':')[0];
                                        const dbHealth = dbStatusStr.split(':')[1];
                                        return (
                                        <div key={db.id} className="rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">{db.name}</span>
                                                <div className="flex items-center gap-1.5">
                                                    <div className={`h-2 w-2 rounded-full ${getStatusDotClass(dbState)}`} />
                                                    {dbHealth && dbHealth !== 'unknown' && (
                                                        <div className={`h-2 w-2 rounded-full ${dbHealth === 'healthy' ? 'bg-success' : dbHealth === 'unhealthy' ? 'bg-red-500' : 'bg-foreground-subtle'}`} />
                                                    )}
                                                </div>
                                            </div>
                                            <p className="mt-1 text-xs text-foreground-muted">
                                                <span className="capitalize">{getStatusLabel(dbState)}</span>
                                                {dbHealth && dbHealth !== 'unknown' && (
                                                    <span className={`ml-1.5 capitalize ${dbHealth === 'healthy' ? 'text-success' : dbHealth === 'unhealthy' ? 'text-red-400' : ''}`}>· {dbHealth}</span>
                                                )}
                                            </p>
                                            <div className="mt-3">
                                                <button
                                                    onClick={() => {
                                                        setLogsViewerService(db.name);
                                                        setLogsViewerServiceUuid(db.uuid);
                                                        setLogsViewerServiceType('database');
                                                        setLogsViewerContainerName(undefined);
                                                        setLogsViewerOpen(true);
                                                    }}
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    View Logs
                                                </button>
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                                {!(envWithRealtimeStatuses?.applications?.length || envWithRealtimeStatuses?.databases?.length) && (
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
                                    {(envWithRealtimeStatuses?.applications || []).map((app) => {
                                        const logAppState = String(app.status || 'stopped').split(':')[0];
                                        const logAppHealth = String(app.status || '').split(':')[1];
                                        return (
                                        <button
                                            key={app.id}
                                            onClick={() => {
                                                setLogsViewerService(app.name);
                                                setLogsViewerServiceUuid(app.uuid);
                                                setLogsViewerServiceType('application');
                                                setLogsViewerContainerName(undefined);
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
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(logAppState)}`} />
                                                {logAppHealth && logAppHealth !== 'unknown' && (
                                                    <div className={`h-2 w-2 rounded-full ${logAppHealth === 'healthy' ? 'bg-success' : logAppHealth === 'unhealthy' ? 'bg-red-500' : 'bg-foreground-subtle'}`} />
                                                )}
                                                <FileText className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                        </button>
                                        );
                                    })}
                                    {(envWithRealtimeStatuses?.databases || []).map((db) => {
                                        const logDbStatusStr = typeof db.status === 'object' ? `${db.status.state}:${db.status.health}` : String(db.status || 'stopped');
                                        const logDbState = logDbStatusStr.split(':')[0];
                                        const logDbHealth = logDbStatusStr.split(':')[1];
                                        return (
                                        <button
                                            key={db.id}
                                            onClick={() => {
                                                setLogsViewerService(db.name);
                                                setLogsViewerServiceUuid(db.uuid);
                                                setLogsViewerServiceType('database');
                                                setLogsViewerContainerName(undefined);
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
                                                <div className={`h-2 w-2 rounded-full ${getStatusDotClass(logDbState)}`} />
                                                {logDbHealth && logDbHealth !== 'unknown' && (
                                                    <div className={`h-2 w-2 rounded-full ${logDbHealth === 'healthy' ? 'bg-success' : logDbHealth === 'unhealthy' ? 'bg-red-500' : 'bg-foreground-subtle'}`} />
                                                )}
                                                <FileText className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                        </button>
                                        );
                                    })}
                                </div>
                                {!(envWithRealtimeStatuses?.applications?.length || envWithRealtimeStatuses?.databases?.length) && (
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
                        <div className="fixed inset-0 z-30 flex flex-col bg-background md:relative md:inset-auto md:z-auto md:w-[560px] md:border-l md:border-border">
                            {/* Panel Header */}
                            <div className="border-b border-border px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {/* Database Logo or App Icon */}
                                        {selectedService.type === 'db' ? (
                                            <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${getDbBgColor(selectedService.dbType)}`}>
                                                {getDbLogo(selectedService.dbType)}
                                            </div>
                                        ) : selectedService.type === 'service' ? (
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-500/20 text-purple-400">
                                                <Layers className="h-5 w-5" />
                                            </div>
                                        ) : (
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-500/20 text-cyan-400">
                                                <Box className="h-5 w-5" />
                                            </div>
                                        )}
                                        <div>
                                            <span className="font-medium text-foreground">{selectedService.name}</span>
                                            <div className="flex items-center gap-1.5 text-xs text-foreground-muted">
                                                <div className={`h-1.5 w-1.5 rounded-full ${getStatusDotClass((selectedService.status || 'stopped').split(':')[0])}`} />
                                                <span className="capitalize">{getStatusLabel((selectedService.status || 'stopped').split(':')[0])}</span>
                                                {/* Health indicator */}
                                                {(() => {
                                                    const health = (selectedService.status || '').split(':')[1];
                                                    if (health && health !== 'unknown') {
                                                        return (
                                                            <span className="flex items-center gap-1 ml-0.5">
                                                                <div className={`h-1.5 w-1.5 rounded-full ${health === 'healthy' ? 'bg-success' : health === 'unhealthy' ? 'bg-red-500' : 'bg-foreground-subtle'}`} />
                                                                <span className={`capitalize ${health === 'healthy' ? 'text-success' : health === 'unhealthy' ? 'text-red-400' : ''}`}>{health}</span>
                                                            </span>
                                                        );
                                                    }
                                                    return null;
                                                })()}
                                                {selectedService.type === 'app' && deployingApps[Number(selectedService.id)] && (selectedService.status || '').startsWith('running') && (
                                                    <span className="flex items-center gap-1 ml-1">
                                                        <div className="h-1.5 w-1.5 rounded-full status-deploying" />
                                                        <span className="text-amber-500">Building</span>
                                                    </span>
                                                )}
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
                                            if (selectedService.type === 'app') {
                                                const app = selectedEnv?.applications?.find(a => String(a.id) === selectedService.id);
                                                return app?.destination?.server?.name || 'Server';
                                            } else if (selectedService.type === 'db') {
                                                const db = selectedEnv?.databases?.find(d => String(d.id) === selectedService.id);
                                                return db?.destination?.server?.name || 'Server';
                                            }
                                            return 'Compose Service';
                                        })()}
                                    </span>
                                    <span>·</span>
                                    <span className="flex items-center gap-1">
                                        <Users className="h-3 w-3" />
                                        {getStatusLabel(selectedService.status || 'stopped')}
                                    </span>
                                </div>
                            </div>

                            {/* Panel Tabs - Different for Apps vs Databases vs Services */}
                            {selectedService.type === 'app' ? (
                                /* Application Tabs */
                                <div className="flex overflow-x-auto border-b border-border">
                                    <button
                                        onClick={() => setActiveAppTab('deployments')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'deployments' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Activity className="h-4 w-4" />
                                        Deployments
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('variables')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'variables' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Variable className="h-4 w-4" />
                                        Variables
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('metrics')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'metrics' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Gauge className="h-4 w-4" />
                                        Metrics
                                    </button>
                                    <button
                                        onClick={() => setActiveAppTab('settings')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeAppTab === 'settings' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Cog className="h-4 w-4" />
                                        Settings
                                    </button>
                                </div>
                            ) : selectedService.type === 'service' ? (
                                /* Service (Compose) Tabs */
                                <div className="flex overflow-x-auto border-b border-border">
                                    <button
                                        onClick={() => setActiveServiceTab('overview')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeServiceTab === 'overview' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Layers className="h-4 w-4" />
                                        Overview
                                    </button>
                                    <button
                                        onClick={() => setActiveServiceTab('variables')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeServiceTab === 'variables' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Variable className="h-4 w-4" />
                                        Variables
                                    </button>
                                    <button
                                        onClick={() => setActiveServiceTab('logs')}
                                        className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors ${activeServiceTab === 'logs' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Terminal className="h-4 w-4" />
                                        Logs
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
                                    <button
                                        onClick={() => setActiveDbTab('import')}
                                        className={`flex items-center gap-2 px-4 py-2.5 text-sm transition-colors whitespace-nowrap ${activeDbTab === 'import' ? 'border-b-2 border-foreground text-foreground' : 'text-foreground-muted hover:text-foreground'}`}
                                    >
                                        <Import className="h-4 w-4" />
                                        Import
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
                                        {activeAppTab === 'variables' && <VariablesTab service={selectedService} onChangeStaged={handleVariableChanged} />}
                                        {activeAppTab === 'metrics' && <MetricsTab service={selectedService} />}
                                        {activeAppTab === 'settings' && <AppSettingsTab service={selectedService} onChangeStaged={handleConfigChanged} />}
                                    </>
                                ) : selectedService.type === 'service' ? (
                                    /* Service (Compose) Content */
                                    <>
                                        {activeServiceTab === 'overview' && (
                                            <div className="space-y-4">
                                                {selectedService.description && (
                                                    <p className="text-sm text-foreground-muted">{selectedService.description}</p>
                                                )}
                                                {/* Service containers */}
                                                {(() => {
                                                    const svc = selectedEnv?.services?.find(s => String(s.id) === selectedService.id);
                                                    const apps = svc?.applications || [];
                                                    if (apps.length === 0) return (
                                                        <p className="text-sm text-foreground-muted">No containers found. Deploy the service first.</p>
                                                    );
                                                    return (
                                                        <div className="space-y-2">
                                                            <h3 className="text-sm font-medium text-foreground">Containers</h3>
                                                            {apps.map((app: { id: number; name: string; fqdn?: string | null; status?: string }) => (
                                                                <div key={app.id} className="flex items-center justify-between rounded-lg border border-border p-3">
                                                                    <div className="flex items-center gap-2">
                                                                        <div className={`h-2 w-2 rounded-full ${getStatusDotClass((app.status || 'stopped').split(':')[0])}`} />
                                                                        <span className="text-sm font-medium text-foreground">{app.name}</span>
                                                                    </div>
                                                                    {app.fqdn && (
                                                                        <a
                                                                            href={app.fqdn.startsWith('http') ? app.fqdn : `https://${app.fqdn}`}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="flex items-center gap-1 text-xs text-foreground-muted hover:text-foreground"
                                                                        >
                                                                            {app.fqdn.replace(/^https?:\/\//, '').replace(/:\d+$/, '')}
                                                                            <ExternalLink className="h-3 w-3" />
                                                                        </a>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    );
                                                })()}
                                                {/* Quick actions */}
                                                <div className="flex gap-2 pt-2">
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() => router.visit(`/services/${selectedService.uuid}`)}
                                                    >
                                                        <Cog className="mr-1.5 h-3.5 w-3.5" />
                                                        Manage Service
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() => {
                                                            setLogsViewerService(selectedService.name);
                                                            setLogsViewerServiceUuid(selectedService.uuid);
                                                            setLogsViewerServiceType('service');
                                                            setLogsViewerContainerName(undefined);
                                                            setLogsViewerOpen(true);
                                                        }}
                                                    >
                                                        <Terminal className="mr-1.5 h-3.5 w-3.5" />
                                                        View Logs
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                        {activeServiceTab === 'variables' && <VariablesTab service={selectedService} onChangeStaged={handleVariableChanged} />}
                                        {activeServiceTab === 'logs' && (
                                            <div className="text-sm text-foreground-muted">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() => {
                                                        setLogsViewerService(selectedService.name);
                                                        setLogsViewerServiceUuid(selectedService.uuid);
                                                        setLogsViewerServiceType('service');
                                                        setLogsViewerContainerName(undefined);
                                                        setLogsViewerOpen(true);
                                                    }}
                                                >
                                                    <Terminal className="mr-1.5 h-3.5 w-3.5" />
                                                    Open Logs Viewer
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    /* Database Content */
                                    <>
                                        {activeDbTab === 'data' && <DatabaseDataTab service={selectedService} />}
                                        {activeDbTab === 'connect' && <DatabaseConnectTab service={selectedService} />}
                                        {activeDbTab === 'credentials' && <DatabaseCredentialsTab service={selectedService} />}
                                        {activeDbTab === 'backups' && <DatabaseBackupsTab service={selectedService} />}
                                        {activeDbTab === 'import' && <DatabaseImportTab service={selectedService} />}
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
                onMigrate={(_nodeId, uuid, name, type) => {
                    openMigrationModal(type === 'app' ? 'application' : 'database', uuid, name);
                }}
                onClone={(_nodeId, uuid, name, type) => {
                    openCloneModal(type === 'app' ? 'application' : 'database', uuid, name);
                }}
                canMigrate={(selectedEnv as any)?.type !== 'production'}
                canClone={canClone}
            />

            {/* Local Setup Modal */}
            <LocalSetupModal
                isOpen={showLocalSetup}
                onClose={() => setShowLocalSetup(false)}
                environment={selectedEnv}
            />

            {/* Logs Viewer Modal */}
            <LogsViewer
                key={logsViewerServiceUuid}
                isOpen={logsViewerOpen}
                onClose={() => setLogsViewerOpen(false)}
                serviceName={logsViewerService}
                serviceUuid={logsViewerServiceUuid}
                serviceType={logsViewerServiceType}
                containerName={logsViewerContainerName}
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

            {/* Approval Required Modal */}
            <ApprovalRequiredModal
                isOpen={showApprovalModal}
                onClose={() => {
                    setShowApprovalModal(false);
                    setApprovalPendingApp(null);
                }}
                onRequestApproval={requestDeploymentApproval}
                environmentName={approvalPendingApp?.environmentName || ''}
                environmentType={approvalPendingApp?.environmentType || 'development'}
                applicationName={approvalPendingApp?.name || ''}
            />

            {/* Migration Modal */}
            {migrateSource && (
                <MigrateModal
                    open={showMigrateModal}
                    onOpenChange={(open) => {
                        setShowMigrateModal(open);
                        if (!open) setMigrateSource(null);
                    }}
                    sourceType={migrateSource.type}
                    sourceUuid={migrateSource.uuid}
                    sourceName={migrateSource.name}
                    targets={migrationTargets}
                    isLoadingTargets={isLoadingMigrationTargets}
                    onMigrate={handleMigrate}
                />
            )}

            {/* Clone Modal (Admin/Owner only) */}
            {cloneSource && (
                <CloneModal
                    isOpen={showCloneModal}
                    onClose={() => {
                        setShowCloneModal(false);
                        setCloneSource(null);
                    }}
                    resource={{ uuid: cloneSource.uuid, name: cloneSource.name } as any}
                    resourceType={cloneSource.type as any}
                />
            )}

            {/* Environment Migration Modal (migrate all resources) */}
            {selectedEnv && project && (
                <EnvironmentMigrateModal
                    open={showEnvMigrateModal}
                    onOpenChange={setShowEnvMigrateModal}
                    environment={selectedEnv}
                    applications={(envWithRealtimeStatuses?.applications || []) as any}
                    databases={(envWithRealtimeStatuses?.databases || []) as any}
                    services={(envWithRealtimeStatuses?.services || []) as any}
                    projectUuid={project.uuid}
                />
            )}
        </>
    );
}
