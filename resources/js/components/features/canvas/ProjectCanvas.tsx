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
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { ServiceNode } from './nodes/ServiceNode';
import { DatabaseNode } from './nodes/DatabaseNode';
import type { Application, StandaloneDatabase, Service } from '@/types';
import axios from 'axios';

// Custom node types - use type assertion for compatibility with @xyflow/react
const nodeTypes: NodeTypes = {
    service: ServiceNode,
    database: DatabaseNode,
} as NodeTypes;

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
}

interface ProjectCanvasProps {
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
    environmentUuid?: string;
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
    onDelete,
    onClose,
}: {
    position: { x: number; y: number } | null;
    link: ResourceLink | null;
    onDelete: () => void;
    onClose: () => void;
}) {
    useEffect(() => {
        if (!position) return;
        const handleClickOutside = () => onClose();
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, [position, onClose]);

    if (!position) return null;

    return (
        <div
            className="fixed z-50 min-w-[200px] rounded-lg border border-border bg-background-secondary/95 py-1 shadow-xl backdrop-blur-sm"
            style={{ left: position.x, top: position.y }}
            onClick={(e) => e.stopPropagation()}
        >
            {link && (
                <div className="border-b border-border px-3 py-2">
                    <div className="text-xs text-foreground-muted">Injects</div>
                    <div className="font-mono text-sm text-green-500">{link.env_key}</div>
                </div>
            )}
            <button
                onClick={onDelete}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-danger hover:bg-background-tertiary"
            >
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                </svg>
                Delete Connection
            </button>
        </div>
    );
}

function ProjectCanvasInner({
    applications,
    databases,
    services,
    environmentUuid,
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
}: ProjectCanvasProps) {
    const reactFlowInstance = useReactFlow();
    const [resourceLinks, setResourceLinks] = useState<ResourceLink[]>(initialResourceLinks);
    const [isLoading, setIsLoading] = useState(false);
    const linksLoadedRef = useRef(false);

    // Load resource links from API
    useEffect(() => {
        if (!environmentUuid || linksLoadedRef.current) return;

        const loadLinks = async () => {
            try {
                const response = await axios.get(`/api/v1/environments/${environmentUuid}/links`);
                setResourceLinks(response.data);
                linksLoadedRef.current = true;
            } catch (error) {
                console.error('Failed to load resource links:', error);
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

    // Convert data to nodes
    const initialNodes = useMemo(() => {
        const nodes: Node[] = [];
        const horizontalSpacing = 280;
        const verticalSpacing = 180;
        const startX = 100;
        const startY = 100;

        // Application nodes - horizontal layout
        applications.forEach((app, index) => {
            nodes.push({
                id: `app-${app.id}`,
                type: 'service',
                position: { x: startX + index * horizontalSpacing, y: startY },
                data: {
                    label: app.name,
                    status: app.status,
                    type: 'application',
                    fqdn: app.fqdn,
                    buildPack: app.build_pack,
                },
            });
        });

        // Database nodes - below applications
        databases.forEach((db, index) => {
            nodes.push({
                id: `db-${db.id}`,
                type: 'database',
                position: { x: startX + index * horizontalSpacing, y: startY + verticalSpacing },
                data: {
                    label: db.name,
                    status: db.status,
                    type: 'database',
                    databaseType: db.database_type,
                    volume: `${db.name.toLowerCase().replace(/\s+/g, '-')}-volume`,
                },
            });
        });

        return nodes;
    }, [applications, databases]);

    // Convert resource links to edges
    const linkedEdges = useMemo(() => {
        return resourceLinks.map((link) => ({
            id: `link-${link.id}`,
            source: `app-${link.source_id}`,
            target: `db-${link.target_id}`,
            type: 'smoothstep',
            animated: link.auto_inject,
            data: { linkId: link.id, link },
            style: {
                stroke: link.auto_inject ? '#22c55e' : '#4a4a5e',
                strokeWidth: 2,
                strokeDasharray: link.auto_inject ? undefined : '5,5',
            },
            markerEnd: {
                type: MarkerType.ArrowClosed,
                color: link.auto_inject ? '#22c55e' : '#4a4a5e',
                width: 15,
                height: 15,
            },
            label: link.env_key,
            labelStyle: { fontSize: 10 },
            labelBgStyle: { fillOpacity: 0.9 },
            labelShowBg: true,
            labelBgPadding: [4, 4] as [number, number],
            labelBgBorderRadius: 4,
        }));
    }, [resourceLinks]);

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(linkedEdges);

    // Update edges when resourceLinks change
    useEffect(() => {
        setEdges(linkedEdges);
    }, [linkedEdges, setEdges]);

    // Edge selection and context menu state
    const [selectedEdge, setSelectedEdge] = useState<string | null>(null);
    const [edgeContextMenu, setEdgeContextMenu] = useState<{ x: number; y: number; edgeId: string; link: ResourceLink | null } | null>(null);

    // Create new link via API
    const onConnect = useCallback(
        async (params: Connection) => {
            if (!environmentUuid || !params.source || !params.target) return;

            // Parse source (app) and target (db) IDs
            const sourceMatch = params.source.match(/^app-(\d+)$/);
            const targetMatch = params.target.match(/^db-(\d+)$/);

            if (!sourceMatch || !targetMatch) {
                console.error('Invalid connection: must be from app to db');
                return;
            }

            const sourceId = parseInt(sourceMatch[1]);
            const targetId = parseInt(targetMatch[1]);

            // Find the database to get its type
            const database = databases.find((db) => db.id === targetId);
            if (!database) {
                console.error('Database not found');
                return;
            }

            setIsLoading(true);

            try {
                const response = await axios.post(`/api/v1/environments/${environmentUuid}/links`, {
                    source_id: sourceId,
                    target_type: getDatabaseTargetType(database.database_type),
                    target_id: targetId,
                    auto_inject: true,
                });

                const newLink: ResourceLink = response.data;

                // Add to local state
                setResourceLinks((prev) => [...prev, newLink]);

                // Notify parent
                onLinkCreated?.(newLink);

                // Show success notification (if toast is available)
                console.log(`Connected! ${newLink.env_key} will be injected on next deploy.`);
            } catch (error: any) {
                console.error('Failed to create link:', error);
                if (error.response?.data?.message) {
                    console.error(error.response.data.message);
                }
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
            setEdgeContextMenu({ x: event.clientX, y: event.clientY, edgeId: edge.id, link: link || null });
        },
        []
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
                    await axios.delete(`/api/v1/environments/${environmentUuid}/links/${linkId}`);

                    // Remove from local state
                    setResourceLinks((prev) => prev.filter((l) => l.id !== linkId));

                    // Notify parent
                    onLinkDeleted?.(linkId);
                    onEdgeDelete?.(edgeId);
                } catch (error) {
                    console.error('Failed to delete link:', error);
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

    // Update edge styles when selected
    const styledEdges = useMemo(
        () =>
            edges.map((edge) => ({
                ...edge,
                style: {
                    ...edge.style,
                    stroke: edge.id === selectedEdge ? '#7c3aed' : (edge.style?.stroke || '#4a4a5e'),
                    strokeWidth: edge.id === selectedEdge ? 3 : 2,
                },
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
                fitView
                fitViewOptions={{ padding: 0.4 }}
                className="bg-background-secondary dark:bg-[#0f0f1a]"
                proOptions={{ hideAttribution: true }}
                minZoom={0.3}
                maxZoom={2}
                deleteKeyCode={null}
                defaultEdgeOptions={{
                    type: 'smoothstep',
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
                onDelete={() => edgeContextMenu && handleDeleteEdge(edgeContextMenu.edgeId)}
                onClose={() => setEdgeContextMenu(null)}
            />

            {/* Selected edge hint */}
            {selectedEdge && (
                <div className="absolute bottom-4 left-4 z-10 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs text-foreground-muted backdrop-blur-sm">
                    Press <kbd className="rounded bg-background-tertiary px-1.5 py-0.5">Delete</kbd> or{' '}
                    <kbd className="rounded bg-background-tertiary px-1.5 py-0.5">Backspace</kbd> to remove connection
                </div>
            )}

            {/* Loading indicator */}
            {isLoading && (
                <div className="absolute right-4 top-4 z-10 flex items-center gap-2 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs text-foreground-muted backdrop-blur-sm">
                    <div className="h-3 w-3 animate-spin rounded-full border-2 border-foreground-subtle border-t-foreground"></div>
                    Saving...
                </div>
            )}

            {/* Legend */}
            <div className="absolute bottom-4 right-4 z-10 rounded-lg border border-border bg-background-secondary/90 px-3 py-2 text-xs backdrop-blur-sm">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5">
                        <div className="h-0.5 w-4 bg-success"></div>
                        <span className="text-foreground-muted">Auto-inject active</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <div className="h-0.5 w-4 border-t-2 border-dashed border-foreground-subtle"></div>
                        <span className="text-foreground-muted">No connection</span>
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
