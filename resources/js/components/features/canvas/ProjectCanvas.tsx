import { useCallback, useMemo, useState, useEffect, useRef } from 'react';
import {
    ReactFlow,
    Background,
    useNodesState,
    useEdgesState,
    Connection,
    Node,
    Edge,
    BackgroundVariant,
    MarkerType,
    useReactFlow,
    ReactFlowProvider,
    type NodeTypes,
    type EdgeTypes,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { ServiceNode } from './nodes/ServiceNode';
import { DatabaseNode } from './nodes/DatabaseNode';
import { ComposeServiceNode } from './nodes/ComposeServiceNode';
import { VariableBadgeEdge } from './edges/VariableBadgeEdge';
import type { Application, StandaloneDatabase, Service } from '@/types';
import { useToast } from '@/components/ui/Toast';
import axios from 'axios';

// Custom node types - use type assertion for compatibility with @xyflow/react
const nodeTypes: NodeTypes = {
    service: ServiceNode,
    database: DatabaseNode,
    compose: ComposeServiceNode,
} as NodeTypes;

// Custom edge types
const edgeTypes: EdgeTypes = {
    variableBadge: VariableBadgeEdge,
} as EdgeTypes;

// Resource Link from API
interface ResourceLink {
    id: number;
    environment_id: number;
    source_type: string;
    source_id: number;
    source_name?: string;
    target_type: string;
    target_id: number;
    target_name?: string;
    inject_as: string | null;
    env_key: string;
    auto_inject: boolean;
    use_external_url?: boolean;
}

interface ProjectCanvasProps {
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
    environmentUuid?: string;
    /** Map of application IDs to deployment status ('queued' | 'in_progress') */
    deployingApps?: Record<number, string>;
    /** Initial resource links for testing purposes */
    initialResourceLinks?: ResourceLink[];
    onNodeClick?: (id: string, type: string) => void;
    onNodeContextMenu?: (id: string, type: string, x: number, y: number) => void;
    onEdgeDelete?: (edgeId: string) => void;
    onZoomIn?: () => void;
    onZoomOut?: () => void;
    onFitView?: () => void;
    onViewportChange?: (zoom: number) => void;
    onLinkCreated?: (link: ResourceLink) => void;
    showGrid?: boolean;
    onLinkDeleted?: (linkId: number) => void;
    /** Quick action callbacks for node hover buttons */
    onQuickDeploy?: (uuid: string) => void;
    onQuickOpenUrl?: (url: string) => void;
    onQuickViewLogs?: (uuid: string, name: string, type: 'application' | 'database' | 'service') => void;
    onQuickBrowseData?: (uuid: string, databaseType: string) => void;
    /** Quick action callbacks for compose services */
    onQuickRestartService?: (uuid: string) => void;
    onQuickStopService?: (uuid: string) => void;
}

// Map database_type to API target_type
function getDatabaseTargetType(databaseType: string): string {
    const typeMap: Record<string, string> = {
        'standalone-postgresql': 'postgresql',
        'standalone-mysql': 'mysql',
        'standalone-mariadb': 'mariadb',
        'standalone-redis': 'redis',
        'standalone-keydb': 'keydb',
        'standalone-dragonfly': 'dragonfly',
        'standalone-mongodb': 'mongodb',
        'standalone-clickhouse': 'clickhouse',
    };
    return typeMap[databaseType] || 'postgresql';
}

// Edge context menu component
function EdgeContextMenu({
    position,
    link,
    reverseLink,
    onDelete,
    onClose,
    onToggleExternalUrl,
}: {
    position: { x: number; y: number } | null;
    link: ResourceLink | null;
    reverseLink?: ResourceLink | null;
    onDelete: () => void;
    onClose: () => void;
    onToggleExternalUrl?: () => void;
}) {
    useEffect(() => {
        if (!position) return;
        const handleClickOutside = () => onClose();
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, [position, onClose]);

    if (!position) return null;

    const isBidirectional = !!reverseLink;
    const isAppToApp = link?.target_type === 'application';
    const isExternal = link?.use_external_url ?? false;

    return (
        <div
            className="fixed z-50 min-w-[220px] rounded-lg border border-border bg-background-secondary/95 py-1 shadow-xl backdrop-blur-sm"
            style={{ left: position.x, top: position.y }}
            onClick={(e) => e.stopPropagation()}
        >
            {link && (
                <div className="border-b border-border px-3 py-2.5">
                    <div className="text-[10px] uppercase tracking-wider text-foreground-muted font-medium mb-2">
                        {isBidirectional ? 'Injects (bidirectional)' : 'Injects'}
                    </div>
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2">
                            <svg className="h-3 w-3 text-primary flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                            <code className="font-mono text-xs text-success">{link.env_key}</code>
                        </div>
                        {reverseLink && (
                            <div className="flex items-center gap-2">
                                <svg className="h-3 w-3 text-pink-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path d="M19 12H5M12 19l-7-7 7-7" />
                                </svg>
                                <code className="font-mono text-xs text-success">{reverseLink.env_key}</code>
                            </div>
                        )}
                    </div>
                </div>
            )}
            {/* External/Internal URL toggle for app-to-app edges */}
            {isAppToApp && onToggleExternalUrl && (
                <button
                    onClick={onToggleExternalUrl}
                    className="flex w-full items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-background-tertiary transition-colors"
                >
                    {isExternal ? (
                        <>
                            {/* Server/Container icon - switch to internal */}
                            <svg className="h-4 w-4 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="2" y="2" width="20" height="8" rx="2" />
                                <rect x="2" y="14" width="20" height="8" rx="2" />
                                <circle cx="6" cy="6" r="1" fill="currentColor" />
                                <circle cx="6" cy="18" r="1" fill="currentColor" />
                            </svg>
                            Use internal URL (Docker)
                        </>
                    ) : (
                        <>
                            {/* Globe icon - switch to external */}
                            <svg className="h-4 w-4 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
                            </svg>
                            Use external URL (domain)
                        </>
                    )}
                </button>
            )}
            <button
                onClick={onDelete}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-danger hover:bg-background-tertiary transition-colors"
            >
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                </svg>
                Delete Connection{isBidirectional ? 's' : ''}
            </button>
        </div>
    );
}

// LocalStorage key for node positions
const getPositionsStorageKey = (environmentUuid: string) => `canvas-node-positions-${environmentUuid}`;

// Load saved node positions from localStorage
function loadSavedPositions(environmentUuid?: string): Record<string, { x: number; y: number }> {
    if (!environmentUuid) return {};
    try {
        const saved = localStorage.getItem(getPositionsStorageKey(environmentUuid));
        return saved ? JSON.parse(saved) : {};
    } catch {
        return {};
    }
}

// Save node positions to localStorage
function saveNodePositions(environmentUuid: string, nodes: Node[]) {
    const positions: Record<string, { x: number; y: number }> = {};
    nodes.forEach((node) => {
        positions[node.id] = node.position;
    });
    try {
        localStorage.setItem(getPositionsStorageKey(environmentUuid), JSON.stringify(positions));
    } catch (e) {
        // Silently ignore localStorage errors
    }
}

function ProjectCanvasInner({
    applications,
    databases,
    services,
    environmentUuid,
    deployingApps,
    initialResourceLinks = [],
    onNodeClick,
    onNodeContextMenu,
    onEdgeDelete,
    onZoomIn,
    onZoomOut,
    onFitView,
    onViewportChange,
    onLinkCreated,
    onLinkDeleted,
    showGrid = true,
    onQuickDeploy,
    onQuickOpenUrl,
    onQuickViewLogs,
    onQuickBrowseData,
    onQuickRestartService,
    onQuickStopService,
}: ProjectCanvasProps) {
    const reactFlowInstance = useReactFlow();
    const { addToast } = useToast();
    const [resourceLinks, setResourceLinks] = useState<ResourceLink[]>(initialResourceLinks);
    const [isLoading, setIsLoading] = useState(false);
    const linksLoadedForEnvRef = useRef<string | null>(null);
    const saveTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Load resource links from API when environment changes
    useEffect(() => {
        if (!environmentUuid) return;

        // Skip if already loaded for this environment
        if (linksLoadedForEnvRef.current === environmentUuid) return;

        const loadLinks = async () => {
            try {
                const response = await axios.get(`/environments/${environmentUuid}/links/json`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });
                setResourceLinks(response.data);
                linksLoadedForEnvRef.current = environmentUuid;
            } catch (error) {
                console.error('[Saturn] Failed to load resource links:', error);
            }
        };

        loadLinks();
    }, [environmentUuid]);

    // Expose zoom controls to parent component via callbacks
    useEffect(() => {
        const handleZoomIn = () => reactFlowInstance.zoomIn();
        const handleZoomOut = () => reactFlowInstance.zoomOut();
        const handleFitView = () => reactFlowInstance.fitView({ padding: 0.4 });

        window.__projectCanvasZoomIn = handleZoomIn;
        window.__projectCanvasZoomOut = handleZoomOut;
        window.__projectCanvasFitView = handleFitView;

        return () => {
            delete window.__projectCanvasZoomIn;
            delete window.__projectCanvasZoomOut;
            delete window.__projectCanvasFitView;
        };
    }, [reactFlowInstance]);

    // Load saved positions once on mount
    const savedPositions = useMemo(() => loadSavedPositions(environmentUuid), [environmentUuid]);

    // Convert data to nodes, using saved positions if available
    const initialNodes = useMemo(() => {
        const nodes: Node[] = [];
        const horizontalSpacing = 280;
        const verticalSpacing = 180;
        const startX = 100;
        const startY = 100;

        // Application nodes - horizontal layout
        applications.forEach((app, index) => {
            const nodeId = `app-${app.id}`;
            const defaultPosition = { x: startX + index * horizontalSpacing, y: startY };
            const position = savedPositions[nodeId] || defaultPosition;

            nodes.push({
                id: nodeId,
                type: 'service',
                position,
                data: {
                    label: app.name,
                    status: app.status,
                    type: 'application',
                    fqdn: app.fqdn,
                    buildPack: app.build_pack,
                    uuid: app.uuid,
                    isDeployingBuild: !!deployingApps?.[app.id],
                    onQuickDeploy: onQuickDeploy ? () => onQuickDeploy(app.uuid) : undefined,
                    onQuickOpenUrl: onQuickOpenUrl && app.fqdn ? () => onQuickOpenUrl(app.fqdn!) : undefined,
                    onQuickViewLogs: onQuickViewLogs ? () => onQuickViewLogs(app.uuid, app.name, 'application') : undefined,
                },
            });
        });

        // Database nodes - below applications
        databases.forEach((db, index) => {
            const nodeId = `db-${db.id}`;
            const defaultPosition = { x: startX + index * horizontalSpacing, y: startY + verticalSpacing };
            const position = savedPositions[nodeId] || defaultPosition;

            nodes.push({
                id: nodeId,
                type: 'database',
                position,
                data: {
                    label: db.name,
                    status: db.status,
                    type: 'database',
                    databaseType: db.database_type,
                    volume: `${db.name.toLowerCase().replace(/\s+/g, '-')}-volume`,
                    uuid: db.uuid,
                    onQuickViewLogs: onQuickViewLogs ? () => onQuickViewLogs(db.uuid, db.name, 'database') : undefined,
                    onQuickBrowseData: onQuickBrowseData ? () => onQuickBrowseData(db.uuid, db.database_type) : undefined,
                },
            });
        });

        // Docker Compose service nodes - below databases
        services.forEach((svc, index) => {
            const nodeId = `svc-${svc.id}`;
            const defaultPosition = { x: startX + index * horizontalSpacing, y: startY + verticalSpacing * 2 };
            const position = savedPositions[nodeId] || defaultPosition;

            nodes.push({
                id: nodeId,
                type: 'compose',
                position,
                data: {
                    label: svc.name,
                    status: svc.status || 'unknown',
                    type: 'service',
                    description: svc.description,
                    uuid: svc.uuid,
                    onQuickRestart: onQuickRestartService ? () => onQuickRestartService(svc.uuid) : undefined,
                    onQuickStop: onQuickStopService ? () => onQuickStopService(svc.uuid) : undefined,
                    onQuickViewLogs: onQuickViewLogs ? () => onQuickViewLogs(svc.uuid, svc.name, 'service') : undefined,
                },
            });
        });

        return nodes;
    }, [applications, databases, services, savedPositions, deployingApps, onQuickDeploy, onQuickOpenUrl, onQuickViewLogs, onQuickBrowseData, onQuickRestartService, onQuickStopService]);

    // Convert resource links to edges, merging bidirectional app-to-app pairs into one edge
    const linkedEdges = useMemo(() => {
        const result: Edge[] = [];
        const processedPairs = new Set<string>();

        for (const link of resourceLinks) {
            const isAppTarget = link.target_type === 'application';

            // For app-to-app links, check for reverse link (bidirectional pair)
            if (isAppTarget) {
                const pairKey = [Math.min(link.source_id, link.target_id), Math.max(link.source_id, link.target_id)].join('-');
                if (processedPairs.has(pairKey)) continue;

                const reverseLink = resourceLinks.find(
                    (l) => l.target_type === 'application' && l.source_id === link.target_id && l.target_id === link.source_id
                );

                if (reverseLink) {
                    // Bidirectional: merge into one edge
                    processedPairs.add(pairKey);
                    result.push({
                        id: `link-${link.id}`,
                        source: `app-${link.source_id}`,
                        target: `app-${link.target_id}`,
                        type: 'variableBadge',
                        animated: link.auto_inject || reverseLink.auto_inject,
                        data: { linkId: link.id, link, reverseLinkId: reverseLink.id, reverseLink },
                        style: {
                            strokeDasharray: (link.auto_inject || reverseLink.auto_inject) ? undefined : '5,5',
                        },
                    });
                    continue;
                }
            }

            // Non-bidirectional (app→db or unpaired app→app)
            const targetNodeId = isAppTarget ? `app-${link.target_id}` : `db-${link.target_id}`;

            result.push({
                id: `link-${link.id}`,
                source: `app-${link.source_id}`,
                target: targetNodeId,
                type: 'variableBadge',
                animated: link.auto_inject,
                data: { linkId: link.id, link },
                style: {
                    strokeDasharray: link.auto_inject ? undefined : '5,5',
                },
                markerEnd: {
                    type: MarkerType.ArrowClosed,
                    color: isAppTarget ? '#7c3aed' : (link.auto_inject ? '#22c55e' : '#4a4a5e'),
                    width: 15,
                    height: 15,
                },
            });
        }

        return result;
    }, [resourceLinks]);

    const [nodes, setNodes, onNodesChangeBase] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(linkedEdges);

    // Wrap onNodesChange to save positions with debounce
    const onNodesChange = useCallback(
        (changes: Parameters<typeof onNodesChangeBase>[0]) => {
            onNodesChangeBase(changes);

            // Check if any position changes occurred
            const hasPositionChange = changes.some(
                (change) => change.type === 'position' && change.position
            );

            if (hasPositionChange && environmentUuid) {
                // Debounce saving to avoid excessive writes
                if (saveTimeoutRef.current) {
                    clearTimeout(saveTimeoutRef.current);
                }
                saveTimeoutRef.current = setTimeout(() => {
                    // Get updated nodes after state change
                    setNodes((currentNodes) => {
                        saveNodePositions(environmentUuid, currentNodes);
                        return currentNodes;
                    });
                }, 300);
            }
        },
        [onNodesChangeBase, environmentUuid, setNodes]
    );

    // Update edges when resourceLinks change
    useEffect(() => {
        setEdges(linkedEdges);
    }, [linkedEdges, setEdges]);

    // Update node data (status, etc.) when applications/databases/services change
    // This is more efficient than replacing all nodes - it preserves positions and only updates data
    useEffect(() => {
        setNodes((currentNodes) => {
            // If no existing nodes, initialize with initialNodes
            if (currentNodes.length === 0) {
                return initialNodes;
            }

            // Create lookup maps for quick access
            const appMap = new Map(applications.map((app) => [`app-${app.id}`, app]));
            const dbMap = new Map(databases.map((db) => [`db-${db.id}`, db]));
            const svcMap = new Map(services.map((svc) => [`svc-${svc.id}`, svc]));

            // Track which nodes still exist in the data
            const existingNodeIds = new Set([
                ...applications.map((app) => `app-${app.id}`),
                ...databases.map((db) => `db-${db.id}`),
                ...services.map((svc) => `svc-${svc.id}`),
            ]);

            // Update existing nodes and filter out removed ones
            const updatedNodes = currentNodes
                .filter((node) => existingNodeIds.has(node.id))
                .map((node) => {
                    const app = appMap.get(node.id);
                    if (app) {
                        const isDeploying = !!deployingApps?.[app.id];
                        // Only update if status or deploying state changed
                        if (node.data.status !== app.status || node.data.isDeployingBuild !== isDeploying) {
                            return {
                                ...node,
                                data: {
                                    ...node.data,
                                    status: app.status,
                                    label: app.name,
                                    fqdn: app.fqdn,
                                    isDeployingBuild: isDeploying,
                                },
                            };
                        }
                        return node;
                    }

                    const db = dbMap.get(node.id);
                    if (db) {
                        if (node.data.status !== db.status) {
                            return {
                                ...node,
                                data: {
                                    ...node.data,
                                    status: db.status,
                                    label: db.name,
                                },
                            };
                        }
                        return node;
                    }

                    const svc = svcMap.get(node.id);
                    if (svc) {
                        if (node.data.status !== (svc.status || 'unknown')) {
                            return {
                                ...node,
                                data: {
                                    ...node.data,
                                    status: svc.status || 'unknown',
                                    label: svc.name,
                                },
                            };
                        }
                        return node;
                    }

                    return node;
                });

            // Find and add new nodes that don't exist yet
            const currentNodeIds = new Set(currentNodes.map((n) => n.id));
            const newNodes = initialNodes.filter((node) => !currentNodeIds.has(node.id));

            if (newNodes.length > 0) {
                return [...updatedNodes, ...newNodes];
            }

            return updatedNodes;
        });
    }, [applications, databases, services, deployingApps, initialNodes, setNodes]);

    // Edge selection and context menu state
    const [selectedEdge, setSelectedEdge] = useState<string | null>(null);
    const [edgeContextMenu, setEdgeContextMenu] = useState<{
        x: number;
        y: number;
        edgeId: string;
        link: ResourceLink | null;
        reverseLink?: ResourceLink | null;
    } | null>(null);

    // Create new link via API
    const onConnect = useCallback(
        async (params: Connection) => {
            if (!environmentUuid || !params.source || !params.target) return;

            // Normalize direction: ReactFlow may swap source/target depending on
            // which handle the user drags from. We need to identify app and db nodes
            // regardless of handle direction.
            let sourceNode = params.source;
            let targetNode = params.target;

            const sourceIsApp = /^app-\d+$/.test(sourceNode);
            const sourceIsDb = /^db-\d+$/.test(sourceNode);
            const targetIsApp = /^app-\d+$/.test(targetNode);
            const targetIsDb = /^db-\d+$/.test(targetNode);
            const sourceIsSvc = /^svc-\d+$/.test(sourceNode);
            const targetIsSvc = /^svc-\d+$/.test(targetNode);

            // Service nodes don't support linking yet
            if (sourceIsSvc || targetIsSvc) {
                addToast('info', 'Not supported', 'Compose service connections are not supported yet.');
                return;
            }

            // For app-to-db: always make app the source
            if (sourceIsDb && targetIsApp) {
                [sourceNode, targetNode] = [targetNode, sourceNode];
            }

            // Re-parse after normalization
            const appSourceMatch = sourceNode.match(/^app-(\d+)$/);
            if (!appSourceMatch) {
                return;
            }
            const sourceId = parseInt(appSourceMatch[1]);

            const dbTargetMatch = targetNode.match(/^db-(\d+)$/);
            const appTargetMatch = targetNode.match(/^app-(\d+)$/);

            if (!dbTargetMatch && !appTargetMatch) {
                return;
            }

            let targetType: string;
            let targetId: number;

            if (dbTargetMatch) {
                targetId = parseInt(dbTargetMatch[1]);
                const database = databases.find((db) => db.id === targetId);
                if (!database) {
                    return;
                }
                targetType = getDatabaseTargetType(database.database_type);
            } else {
                targetId = parseInt(appTargetMatch![1]);
                // Prevent self-linking
                if (sourceId === targetId) {
                    return;
                }
                targetType = 'application';
            }

            setIsLoading(true);

            try {
                const response = await axios.post(`/environments/${environmentUuid}/links/json`, {
                    source_id: sourceId,
                    target_type: targetType,
                    target_id: targetId,
                    auto_inject: true,
                }, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });

                // Backend returns array for bidirectional app-to-app links, single object for db links
                const data = response.data;
                const newLinks: ResourceLink[] = Array.isArray(data) ? data : [data];

                // Add to local state
                setResourceLinks((prev) => [...prev, ...newLinks]);

                // Notify parent
                newLinks.forEach((link) => onLinkCreated?.(link));

                const keys = newLinks.map((l) => l.env_key).join(', ');
                addToast('success', 'Connected!', `${keys} will be injected on next deploy.`);
            } catch (error: any) {
                console.error('[Saturn] Failed to create link:', error);
            } finally {
                setIsLoading(false);
            }
        },
        [environmentUuid, databases, onLinkCreated]
    );

    // Handle edge click for selection
    const handleEdgeClick = useCallback((_: React.MouseEvent, edge: Edge) => {
        setSelectedEdge(edge.id);
    }, []);

    // Handle edge context menu (right-click)
    const handleEdgeContextMenu = useCallback(
        (event: React.MouseEvent, edge: Edge) => {
            event.preventDefault();
            const link = edge.data?.link as ResourceLink | undefined;
            const reverseLink = edge.data?.reverseLink as ResourceLink | undefined;
            setEdgeContextMenu({
                x: event.clientX,
                y: event.clientY,
                edgeId: edge.id,
                link: link || null,
                reverseLink: reverseLink || null,
            });
        },
        []
    );

    // Toggle external/internal URL for app-to-app links
    const handleToggleExternalUrl = useCallback(
        async () => {
            if (!edgeContextMenu || !environmentUuid) return;

            const edge = edges.find((e) => e.id === edgeContextMenu.edgeId);
            const link = edge?.data?.link as ResourceLink | undefined;
            const reverseLink = edge?.data?.reverseLink as ResourceLink | undefined;
            if (!link) return;

            const newValue = !link.use_external_url;

            try {
                // Update forward link
                const response = await axios.patch(
                    `/environments/${environmentUuid}/links/${link.id}/json`,
                    { use_external_url: newValue },
                    {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                    }
                );

                // Update reverse link if bidirectional
                if (reverseLink) {
                    await axios.patch(
                        `/environments/${environmentUuid}/links/${reverseLink.id}/json`,
                        { use_external_url: newValue },
                        {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                        }
                    );
                }

                // Update local state
                setResourceLinks((prev) =>
                    prev.map((l) => {
                        if (l.id === link.id || (reverseLink && l.id === reverseLink.id)) {
                            return { ...l, use_external_url: newValue };
                        }
                        return l;
                    })
                );

                setEdgeContextMenu(null);
                addToast('success', 'Updated', `Now using ${newValue ? 'external domain' : 'internal Docker'} URL.`);
            } catch (error) {
                console.error('[Saturn] Failed to toggle external URL:', error);
                addToast('error', 'Error', 'Failed to update link settings.');
            }
        },
        [edgeContextMenu, edges, environmentUuid, addToast]
    );

    // Delete edge/link
    const handleDeleteEdge = useCallback(
        async (edgeId: string) => {
            const edge = edges.find((e) => e.id === edgeId);
            const linkId = edge?.data?.linkId as number | undefined;

            setEdgeContextMenu(null);
            setSelectedEdge(null);

            if (linkId && environmentUuid) {
                try {
                    await axios.delete(`/environments/${environmentUuid}/links/${linkId}/json`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                    });

                    // For bidirectional edges, also remove the reverse link from local state
                    const reverseLinkId = edge?.data?.reverseLinkId as number | undefined;
                    const idsToRemove = new Set([linkId, ...(reverseLinkId ? [reverseLinkId] : [])]);
                    setResourceLinks((prev) => prev.filter((l) => !idsToRemove.has(l.id)));

                    // Notify parent
                    onLinkDeleted?.(linkId);
                    if (reverseLinkId) onLinkDeleted?.(reverseLinkId);
                    onEdgeDelete?.(edgeId);
                } catch (error) {
                    console.error('[Saturn] Failed to delete link:', error);
                }
            } else {
                // For non-persisted edges, just remove from UI
                setEdges((eds) => eds.filter((e) => e.id !== edgeId));
                onEdgeDelete?.(edgeId);
            }
        },
        [edges, environmentUuid, setEdges, onEdgeDelete, onLinkDeleted]
    );

    // Keyboard handler for delete
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.key === 'Delete' || event.key === 'Backspace') && selectedEdge) {
                handleDeleteEdge(selectedEdge);
            }
            if (event.key === 'Escape') {
                setSelectedEdge(null);
                setEdgeContextMenu(null);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [selectedEdge, handleDeleteEdge]);

    // Update edge styles when selected - pass selected state to edge component
    const styledEdges = useMemo(
        () =>
            edges.map((edge) => ({
                ...edge,
                selected: edge.id === selectedEdge,
            })),
        [edges, selectedEdge]
    );

    const handleNodeClick = useCallback(
        (_: React.MouseEvent, node: Node) => {
            setSelectedEdge(null);
            const [type, id] = node.id.split('-');
            onNodeClick?.(id, type);
        },
        [onNodeClick]
    );

    const handleNodeContextMenu = useCallback(
        (event: React.MouseEvent, node: Node) => {
            event.preventDefault();
            const [type, id] = node.id.split('-');
            onNodeContextMenu?.(id, type, event.clientX, event.clientY);
        },
        [onNodeContextMenu]
    );

    const handlePaneClick = useCallback(() => {
        setSelectedEdge(null);
        setEdgeContextMenu(null);
    }, []);

    const handleMove = useCallback(
        (_: any, viewport: { zoom: number }) => {
            onViewportChange?.(viewport.zoom);
        },
        [onViewportChange]
    );

    return (
        <div className="h-full w-full bg-background-secondary dark:bg-[#0f0f1a]">
            <ReactFlow
                nodes={nodes}
                edges={styledEdges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onNodeClick={handleNodeClick}
                onNodeContextMenu={handleNodeContextMenu}
                onEdgeClick={handleEdgeClick}
                onEdgeContextMenu={handleEdgeContextMenu}
                onPaneClick={handlePaneClick}
                onMove={handleMove}
                nodeTypes={nodeTypes}
                edgeTypes={edgeTypes}
                fitView
                fitViewOptions={{ padding: 0.4 }}
                className="bg-background-secondary dark:bg-[#0f0f1a]"
                proOptions={{ hideAttribution: true }}
                minZoom={0.3}
                maxZoom={2}
                deleteKeyCode={null}
                defaultEdgeOptions={{
                    type: 'variableBadge',
                    animated: false,
                    style: { strokeWidth: 2, strokeDasharray: '5,5' },
                }}
            >
                {/* Dot grid background - similar to Figma/Miro style */}
                {showGrid && (
                    <>
                        <Background
                            id="dots-small"
                            variant={BackgroundVariant.Dots}
                            gap={20}
                            size={1}
                            className="[&>pattern>circle]:fill-foreground-disabled dark:[&>pattern>circle]:fill-[#2d2d42]"
                        />
                        <Background
                            id="dots-large"
                            variant={BackgroundVariant.Dots}
                            gap={100}
                            size={2}
                            className="[&>pattern>circle]:fill-foreground-subtle dark:[&>pattern>circle]:fill-[#3d3d52]"
                        />
                    </>
                )}
            </ReactFlow>

            {/* Edge context menu */}
            <EdgeContextMenu
                position={edgeContextMenu ? { x: edgeContextMenu.x, y: edgeContextMenu.y } : null}
                link={edgeContextMenu?.link || null}
                reverseLink={edgeContextMenu?.reverseLink || null}
                onDelete={() => edgeContextMenu && handleDeleteEdge(edgeContextMenu.edgeId)}
                onClose={() => setEdgeContextMenu(null)}
                onToggleExternalUrl={handleToggleExternalUrl}
            />

            {/* Selected edge hint - above legend and external "Set up locally" button */}
            {selectedEdge && (
                <div className="absolute bottom-32 left-4 z-10 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs text-foreground-muted backdrop-blur-sm">
                    {/* Desktop: keyboard hint */}
                    <span className="hidden md:inline">
                        Press <kbd className="rounded bg-background-tertiary px-1.5 py-0.5">Delete</kbd> or{' '}
                        <kbd className="rounded bg-background-tertiary px-1.5 py-0.5">Backspace</kbd> to remove connection
                    </span>
                    {/* Mobile: tap-to-delete button */}
                    <button
                        className="md:hidden flex items-center gap-2 text-danger"
                        onClick={() => handleDeleteEdge(selectedEdge)}
                    >
                        <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                        </svg>
                        Tap to remove connection
                    </button>
                </div>
            )}

            {/* Loading indicator */}
            {isLoading && (
                <div className="absolute right-4 top-4 z-10 flex items-center gap-2 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs text-foreground-muted backdrop-blur-sm">
                    <div className="h-3 w-3 animate-spin rounded-full border-2 border-foreground-subtle border-t-foreground"></div>
                    Saving...
                </div>
            )}

            {/* Legend - above external "Set up locally" button (bottom-4) */}
            <div className="hidden md:block absolute bottom-20 left-4 z-10 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs backdrop-blur-sm">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5">
                        <div className="h-0.5 w-4 bg-success"></div>
                        <span className="text-foreground-muted">DB connection</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <div className="h-0.5 w-4 bg-[#7c3aed]"></div>
                        <span className="text-foreground-muted">App connection</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <div className="h-0.5 w-4 border-t-2 border-dashed border-foreground-subtle"></div>
                        <span className="text-foreground-muted">Inactive</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Wrapper component with ReactFlowProvider
export function ProjectCanvas(props: ProjectCanvasProps) {
    return (
        <ReactFlowProvider>
            <ProjectCanvasInner {...props} />
        </ReactFlowProvider>
    );
}
