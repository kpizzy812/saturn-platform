import * as React from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Modal, useConfirm } from '@/components/ui';
import { RefreshCw, Users, Crown, User, AlertTriangle, Loader2, GitBranch, Shield, LogOut, Play } from 'lucide-react';
import type { Server as ServerType } from '@/types';
import { useSwarmNodes, type SwarmNode } from '@/hooks/useSwarmNodes';

interface Props {
    server: ServerType;
}

export function SwarmManagement({ server }: Props) {
    const isSwarmManager = server.settings?.is_swarm_manager ?? false;
    const isSwarmWorker = server.settings?.is_swarm_worker ?? false;
    const isInSwarm = isSwarmManager || isSwarmWorker;

    const { nodes, isLoading, error, refetch, initSwarm, joinSwarm, leaveSwarm, promoteNode, demoteNode } =
        useSwarmNodes({
            serverUuid: server.uuid,
            enabled: isSwarmManager,
            autoRefresh: isSwarmManager,
            refreshInterval: 15000,
        });

    const confirm = useConfirm();
    const [showJoinModal, setShowJoinModal] = React.useState(false);
    const [joinToken, setJoinToken] = React.useState('');
    const [managerAddr, setManagerAddr] = React.useState('');
    const [joinRole, setJoinRole] = React.useState<'worker' | 'manager'>('worker');

    const handleInitSwarm = async () => {
        const confirmed = await confirm({
            title: 'Initialize Docker Swarm',
            description: `Initialize a new Docker Swarm on "${server.name}"? This server will become the Swarm manager node.`,
            confirmText: 'Initialize',
            variant: 'default',
        });
        if (confirmed) initSwarm();
    };

    const handleLeaveSwarm = async () => {
        const confirmed = await confirm({
            title: 'Leave Docker Swarm',
            description: `"${server.name}" will leave the swarm. If this is the last manager, all services will be lost.`,
            confirmText: 'Leave Swarm',
            variant: 'danger',
        });
        if (confirmed) leaveSwarm();
    };

    const handleJoinSwarm = () => {
        joinSwarm(joinToken, managerAddr, joinRole);
        setShowJoinModal(false);
        setJoinToken('');
        setManagerAddr('');
        setJoinRole('worker');
    };

    const handlePromote = async (node: SwarmNode) => {
        const confirmed = await confirm({
            title: 'Promote Node',
            description: `Promote "${node.hostname}" to Swarm manager?`,
            confirmText: 'Promote',
            variant: 'default',
        });
        if (confirmed) promoteNode(node.id);
    };

    const handleDemote = async (node: SwarmNode) => {
        const confirmed = await confirm({
            title: 'Demote Node',
            description: `Demote "${node.hostname}" from manager to worker?`,
            confirmText: 'Demote',
            variant: 'danger',
        });
        if (confirmed) demoteNode(node.id);
    };

    return (
        <div className="space-y-4">
            {/* Swarm Status Header */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div
                                className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                                    isSwarmManager
                                        ? 'bg-primary/10'
                                        : isSwarmWorker
                                          ? 'bg-info/10'
                                          : 'bg-foreground-subtle/10'
                                }`}
                            >
                                <GitBranch
                                    className={`h-5 w-5 ${
                                        isSwarmManager
                                            ? 'text-primary'
                                            : isSwarmWorker
                                              ? 'text-info'
                                              : 'text-foreground-subtle'
                                    }`}
                                />
                            </div>
                            <div>
                                <p className="font-medium text-foreground">Docker Swarm</p>
                                <p className="text-sm text-foreground-muted">
                                    {isSwarmManager
                                        ? 'Manager node — can schedule tasks and manage the cluster'
                                        : isSwarmWorker
                                          ? 'Worker node — executes tasks assigned by the manager'
                                          : 'Not part of a swarm'}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {isSwarmManager && (
                                <Badge variant="success">
                                    <Crown className="mr-1 h-3 w-3" />
                                    Manager
                                </Badge>
                            )}
                            {isSwarmWorker && (
                                <Badge variant="info">
                                    <User className="mr-1 h-3 w-3" />
                                    Worker
                                </Badge>
                            )}
                            {!isInSwarm && <Badge variant="secondary">Not in Swarm</Badge>}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Actions */}
            <div className="flex flex-wrap items-center gap-2">
                {!isInSwarm && (
                    <>
                        <Button variant="secondary" size="sm" onClick={handleInitSwarm}>
                            <Play className="mr-2 h-4 w-4" />
                            Initialize Swarm
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => setShowJoinModal(true)}>
                            <Shield className="mr-2 h-4 w-4" />
                            Join Existing Swarm
                        </Button>
                    </>
                )}
                {isInSwarm && (
                    <>
                        {isSwarmManager && (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => refetch()}
                                disabled={isLoading}
                            >
                                <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                        )}
                        <Button variant="danger" size="sm" onClick={handleLeaveSwarm}>
                            <LogOut className="mr-2 h-4 w-4" />
                            Leave Swarm
                        </Button>
                    </>
                )}
            </div>

            {/* Node List — only visible to managers */}
            {isSwarmManager && (
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-4 w-4" />
                                Cluster Nodes
                                {!isLoading && nodes.length > 0 && (
                                    <Badge variant="secondary">{nodes.length}</Badge>
                                )}
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {isLoading && nodes.length === 0 ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            </div>
                        ) : error ? (
                            <div className="flex flex-col items-center justify-center py-8">
                                <AlertTriangle className="h-8 w-8 text-warning" />
                                <p className="mt-2 text-sm text-foreground-muted">{error.message}</p>
                                <Button variant="secondary" size="sm" className="mt-3" onClick={() => refetch()}>
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Retry
                                </Button>
                            </div>
                        ) : nodes.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8">
                                <Users className="h-8 w-8 text-foreground-subtle" />
                                <p className="mt-2 text-sm text-foreground-muted">No nodes found</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-primary/10 text-left">
                                            <th className="pb-2 pr-4 font-medium text-foreground-muted">Hostname</th>
                                            <th className="pb-2 pr-4 font-medium text-foreground-muted">Role</th>
                                            <th className="pb-2 pr-4 font-medium text-foreground-muted">Status</th>
                                            <th className="pb-2 pr-4 font-medium text-foreground-muted">
                                                Availability
                                            </th>
                                            <th className="pb-2 pr-4 font-medium text-foreground-muted">Engine</th>
                                            <th className="pb-2 font-medium text-foreground-muted">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-primary/5">
                                        {nodes.map((node) => (
                                            <NodeRow
                                                key={node.id}
                                                node={node}
                                                onPromote={handlePromote}
                                                onDemote={handleDemote}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Worker-only info */}
            {isSwarmWorker && !isSwarmManager && (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-8">
                        <User className="h-10 w-10 text-info" />
                        <p className="mt-3 font-medium text-foreground">Worker Node</p>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Node management is available from the Swarm manager server.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Join Swarm Modal */}
            <Modal
                isOpen={showJoinModal}
                onClose={() => setShowJoinModal(false)}
                title="Join Existing Swarm"
                description="Enter the join token and manager address to add this server to an existing Docker Swarm."
            >
                <div className="mt-4 space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-foreground">Join Token</label>
                        <textarea
                            className="w-full rounded-md border border-primary/20 bg-background px-3 py-2 font-mono text-xs text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary/40"
                            rows={3}
                            placeholder="SWMTKN-1-..."
                            value={joinToken}
                            onChange={(e) => setJoinToken(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-foreground">Manager Address</label>
                        <input
                            type="text"
                            className="w-full rounded-md border border-primary/20 bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary/40"
                            placeholder="192.168.1.1:2377"
                            value={managerAddr}
                            onChange={(e) => setManagerAddr(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-foreground">Role</label>
                        <select
                            className="w-full rounded-md border border-primary/20 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                            value={joinRole}
                            onChange={(e) => setJoinRole(e.target.value as 'worker' | 'manager')}
                        >
                            <option value="worker">Worker</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="ghost" size="sm" onClick={() => setShowJoinModal(false)}>
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            onClick={handleJoinSwarm}
                            disabled={!joinToken.trim() || !managerAddr.trim()}
                        >
                            Join Swarm
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

function NodeRow({
    node,
    onPromote,
    onDemote,
}: {
    node: SwarmNode;
    onPromote: (node: SwarmNode) => void;
    onDemote: (node: SwarmNode) => void;
}) {
    const isManager = node.manager_status !== '';
    const isLeader = node.manager_status === 'leader';

    return (
        <tr className="group">
            <td className="py-2.5 pr-4 font-medium text-foreground">
                {node.hostname}
                {node.self && (
                    <Badge variant="outline" className="ml-2 text-xs">
                        this node
                    </Badge>
                )}
            </td>
            <td className="py-2.5 pr-4">
                {isLeader ? (
                    <Badge variant="success">
                        <Crown className="mr-1 h-3 w-3" />
                        Leader
                    </Badge>
                ) : isManager ? (
                    <Badge variant="info">
                        <Shield className="mr-1 h-3 w-3" />
                        Manager
                    </Badge>
                ) : (
                    <Badge variant="secondary">
                        <User className="mr-1 h-3 w-3" />
                        Worker
                    </Badge>
                )}
            </td>
            <td className="py-2.5 pr-4">
                <NodeStatusBadge status={node.status} />
            </td>
            <td className="py-2.5 pr-4">
                <AvailabilityBadge availability={node.availability} />
            </td>
            <td className="py-2.5 pr-4 font-mono text-xs text-foreground-muted">{node.engine_version || '—'}</td>
            <td className="py-2.5">
                <div className="flex items-center gap-1">
                    {!isManager && (
                        <Button variant="ghost" size="sm" onClick={() => onPromote(node)}>
                            <Crown className="mr-1 h-3 w-3" />
                            Promote
                        </Button>
                    )}
                    {isManager && !isLeader && (
                        <Button variant="ghost" size="sm" onClick={() => onDemote(node)}>
                            <User className="mr-1 h-3 w-3" />
                            Demote
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function NodeStatusBadge({ status }: { status: string }) {
    switch (status) {
        case 'ready':
            return <Badge variant="success">Ready</Badge>;
        case 'down':
            return <Badge variant="danger">Down</Badge>;
        case 'unknown':
            return <Badge variant="warning">Unknown</Badge>;
        default:
            return <Badge variant="secondary">{status || '—'}</Badge>;
    }
}

function AvailabilityBadge({ availability }: { availability: string }) {
    switch (availability) {
        case 'active':
            return <Badge variant="success">Active</Badge>;
        case 'pause':
            return <Badge variant="warning">Paused</Badge>;
        case 'drain':
            return <Badge variant="info">Drain</Badge>;
        default:
            return <Badge variant="secondary">{availability || '—'}</Badge>;
    }
}
