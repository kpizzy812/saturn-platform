import { useCallback, useMemo, useState, useEffect } from 'react';
import {
    ReactFlow,
    Background,
    useNodesState,
    useEdgesState,
    addEdge,
    Connection,
    Node,
    Edge,
    BackgroundVariant,
    MarkerType,
    useReactFlow,
    ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { ServiceNode } from './nodes/ServiceNode';
import { DatabaseNode } from './nodes/DatabaseNode';
import type { Application, StandaloneDatabase, Service } from '@/types';

// Custom node types
const nodeTypes = {
    service: ServiceNode,
    database: DatabaseNode,
};

interface ProjectCanvasProps {
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
    onNodeClick?: (id: string, type: string) => void;
    onNodeContextMenu?: (id: string, type: string, x: number, y: number) => void;
    onEdgeDelete?: (edgeId: string) => void;
    onZoomIn?: () => void;
    onZoomOut?: () => void;
    onFitView?: () => void;
    onViewportChange?: (zoom: number) => void;
}

// Edge context menu component
function EdgeContextMenu({
    position,
    onDelete,
    onClose,
}: {
    position: { x: number; y: number } | null;
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
            className="fixed z-50 min-w-[140px] rounded-lg border border-white/10 bg-[#1a1a2e]/95 py-1 shadow-xl backdrop-blur-sm"
            style={{ left: position.x, top: position.y }}
            onClick={(e) => e.stopPropagation()}
        >
            <button
                onClick={onDelete}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-400 hover:bg-white/5"
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
    onNodeClick,
    onNodeContextMenu,
    onEdgeDelete,
    onZoomIn,
    onZoomOut,
    onFitView,
    onViewportChange,
}: ProjectCanvasProps) {
    const reactFlowInstance = useReactFlow();

    // Expose zoom controls to parent component via callbacks
    useEffect(() => {
        // Create wrapper functions that call ReactFlow methods
        const handleZoomIn = () => reactFlowInstance.zoomIn();
        const handleZoomOut = () => reactFlowInstance.zoomOut();
        const handleFitView = () => reactFlowInstance.fitView({ padding: 0.4 });

        // Store references on window for parent to access
        (window as any).__projectCanvasZoomIn = handleZoomIn;
        (window as any).__projectCanvasZoomOut = handleZoomOut;
        (window as any).__projectCanvasFitView = handleFitView;

        return () => {
            // Cleanup
            delete (window as any).__projectCanvasZoomIn;
            delete (window as any).__projectCanvasZoomOut;
            delete (window as any).__projectCanvasFitView;
        };
    }, [reactFlowInstance]);

    // Convert data to nodes
    const { initialNodes, initialEdges } = useMemo(() => {
        const nodes: Node[] = [];
        const edges: Edge[] = [];
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
            const nodeId = `db-${db.id}`;
            nodes.push({
                id: nodeId,
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

            // Create edge from first app to this database (simulate connection)
            if (applications.length > 0 && index < applications.length) {
                edges.push({
                    id: `edge-app-${applications[index]?.id || applications[0].id}-db-${db.id}`,
                    source: `app-${applications[index]?.id || applications[0].id}`,
                    target: nodeId,
                    type: 'smoothstep',
                    animated: false,
                    style: {
                        stroke: '#4a4a5e',
                        strokeWidth: 2,
                        strokeDasharray: '5,5',
                    },
                    markerEnd: {
                        type: MarkerType.ArrowClosed,
                        color: '#4a4a5e',
                        width: 15,
                        height: 15,
                    },
                });
            }
        });

        return { initialNodes: nodes, initialEdges: edges };
    }, [applications, databases]);

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);

    // Edge selection and context menu state
    const [selectedEdge, setSelectedEdge] = useState<string | null>(null);
    const [edgeContextMenu, setEdgeContextMenu] = useState<{ x: number; y: number; edgeId: string } | null>(null);

    const onConnect = useCallback(
        (params: Connection) => setEdges((eds) => addEdge({
            ...params,
            type: 'smoothstep',
            animated: false,
            style: { stroke: '#4a4a5e', strokeWidth: 2, strokeDasharray: '5,5' },
            markerEnd: {
                type: MarkerType.ArrowClosed,
                color: '#4a4a5e',
            },
        }, eds)),
        [setEdges]
    );

    // Handle edge click for selection
    const handleEdgeClick = useCallback(
        (_: React.MouseEvent, edge: Edge) => {
            setSelectedEdge(edge.id);
        },
        []
    );

    // Handle edge context menu (right-click)
    const handleEdgeContextMenu = useCallback(
        (event: React.MouseEvent, edge: Edge) => {
            event.preventDefault();
            setEdgeContextMenu({ x: event.clientX, y: event.clientY, edgeId: edge.id });
        },
        []
    );

    // Delete edge
    const handleDeleteEdge = useCallback((edgeId: string) => {
        setEdges((eds) => eds.filter((e) => e.id !== edgeId));
        setEdgeContextMenu(null);
        setSelectedEdge(null);
        onEdgeDelete?.(edgeId);
    }, [setEdges, onEdgeDelete]);

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
    const styledEdges = useMemo(() =>
        edges.map((edge) => ({
            ...edge,
            style: {
                ...edge.style,
                stroke: edge.id === selectedEdge ? '#7c3aed' : '#4a4a5e',
                strokeWidth: edge.id === selectedEdge ? 3 : 2,
            },
        })),
        [edges, selectedEdge]
    );

    const handleNodeClick = useCallback(
        (_: React.MouseEvent, node: Node) => {
            setSelectedEdge(null); // Deselect edge when node clicked
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
        <div className="h-full w-full bg-[#0f0f1a]">
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
                className="bg-[#0f0f1a]"
                proOptions={{ hideAttribution: true }}
                minZoom={0.3}
                maxZoom={2}
                deleteKeyCode={null}
                defaultEdgeOptions={{
                    type: 'smoothstep',
                    animated: false,
                    style: { stroke: '#4a4a5e', strokeWidth: 2, strokeDasharray: '5,5' },
                }}
            >
                <Background
                    variant={BackgroundVariant.Dots}
                    gap={20}
                    size={1}
                    color="#1a1a2e"
                />
            </ReactFlow>

            {/* Edge context menu */}
            <EdgeContextMenu
                position={edgeContextMenu ? { x: edgeContextMenu.x, y: edgeContextMenu.y } : null}
                onDelete={() => edgeContextMenu && handleDeleteEdge(edgeContextMenu.edgeId)}
                onClose={() => setEdgeContextMenu(null)}
            />

            {/* Selected edge hint */}
            {selectedEdge && (
                <div className="absolute bottom-4 left-4 z-10 rounded-lg bg-[#1a1a2e]/90 px-3 py-2 text-xs text-gray-300 backdrop-blur-sm">
                    Press <kbd className="rounded bg-white/10 px-1.5 py-0.5">Delete</kbd> or <kbd className="rounded bg-white/10 px-1.5 py-0.5">Backspace</kbd> to remove connection
                </div>
            )}
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
