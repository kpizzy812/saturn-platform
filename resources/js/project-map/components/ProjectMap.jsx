import React, { useCallback, useState } from 'react';
import {
    ReactFlow,
    Background,
    Controls,
    MiniMap,
    useNodesState,
    useEdgesState,
    addEdge,
    Panel,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import ServiceNode from '../nodes/ServiceNode';
import DatabaseNode from '../nodes/DatabaseNode';
import AddServicePanel from './AddServicePanel';
import ServiceDetailsPanel from './ServiceDetailsPanel';

// Custom node types - unified approach
// Backend sends type: 'database' with data.dbType for specific DB type
// Backend sends type: 'application' or 'service' for apps/services
const nodeTypes = {
    application: ServiceNode,
    service: ServiceNode,
    database: DatabaseNode,
};

// Railway-style edge configuration
const defaultEdgeOptions = {
    type: 'smoothstep',
    animated: true,
    style: {
        stroke: '#D946EF',
        strokeWidth: 2,
    },
};

export default function ProjectMap({ initialData }) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialData?.nodes || []);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialData?.edges || []);
    const [selectedNode, setSelectedNode] = useState(null);
    const [showAddPanel, setShowAddPanel] = useState(false);

    // Handle new connections between nodes
    const onConnect = useCallback(
        (params) => setEdges((eds) => addEdge({
            ...params,
            ...defaultEdgeOptions,
        }, eds)),
        [setEdges]
    );

    // Handle node selection
    const onNodeClick = useCallback((event, node) => {
        setSelectedNode(node);
        setShowAddPanel(false);
    }, []);

    // Handle background click to deselect
    const onPaneClick = useCallback(() => {
        setSelectedNode(null);
    }, []);

    // Handle node drag end - save position
    const onNodeDragStop = useCallback((event, node) => {
        const customEvent = new CustomEvent('projectmap:nodeposition', {
            detail: {
                nodeId: node.id,
                position: node.position,
            }
        });
        document.dispatchEvent(customEvent);
    }, []);

    // Add new service
    const handleAddService = useCallback((serviceType) => {
        const newNode = {
            id: `temp-${Date.now()}`,
            type: serviceType,
            position: { x: 250, y: 250 },
            data: {
                label: `New ${serviceType}`,
                status: 'pending',
                isNew: true,
            },
        };
        setNodes((nds) => [...nds, newNode]);
        setShowAddPanel(false);

        const customEvent = new CustomEvent('projectmap:addservice', {
            detail: { type: serviceType }
        });
        document.dispatchEvent(customEvent);
    }, [setNodes]);

    // Delete node
    const handleDeleteNode = useCallback((nodeId) => {
        setNodes((nds) => nds.filter((node) => node.id !== nodeId));
        setEdges((eds) => eds.filter((edge) => edge.source !== nodeId && edge.target !== nodeId));
        setSelectedNode(null);

        const customEvent = new CustomEvent('projectmap:deletenode', {
            detail: { nodeId }
        });
        document.dispatchEvent(customEvent);
    }, [setNodes, setEdges]);

    // MiniMap node color based on status
    const nodeColor = useCallback((node) => {
        switch (node.data?.status) {
            case 'running': return '#22C55E';
            case 'stopped': return '#6B7280';
            case 'error': return '#EF4444';
            case 'deploying': return '#F59E0B';
            default: return '#D946EF';
        }
    }, []);

    return (
        <div className="w-full h-full rounded-xl overflow-hidden border border-[#2D2B33] bg-[#131415]">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onNodeClick={onNodeClick}
                onPaneClick={onPaneClick}
                onNodeDragStop={onNodeDragStop}
                nodeTypes={nodeTypes}
                defaultEdgeOptions={defaultEdgeOptions}
                fitView
                fitViewOptions={{ padding: 0.2 }}
                proOptions={{ hideAttribution: true }}
                className="project-map-flow"
            >
                <Background
                    color="#3D3B43"
                    gap={24}
                    size={1}
                    variant="dots"
                />
                <Controls
                    showInteractive={false}
                />
                <MiniMap
                    nodeColor={nodeColor}
                    maskColor="rgba(19, 20, 21, 0.85)"
                />

                {/* Top Panel - Add Service Button */}
                <Panel position="top-right" className="flex gap-2">
                    <button
                        onClick={() => setShowAddPanel(!showAddPanel)}
                        className="flex items-center gap-2 px-4 py-2 bg-[#D946EF] hover:bg-[#E879F9] text-white text-[13px] font-medium rounded-lg transition-all duration-150 shadow-lg hover:-translate-y-0.5"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Service
                    </button>
                </Panel>

                {/* Add Service Panel */}
                {showAddPanel && (
                    <Panel position="top-right" className="mt-14">
                        <AddServicePanel
                            onSelect={handleAddService}
                            onClose={() => setShowAddPanel(false)}
                        />
                    </Panel>
                )}
            </ReactFlow>

            {/* Service Details Slide Panel */}
            {selectedNode && (
                <ServiceDetailsPanel
                    node={selectedNode}
                    onClose={() => setSelectedNode(null)}
                    onDelete={() => handleDeleteNode(selectedNode.id)}
                />
            )}
        </div>
    );
}
