import * as React from 'react';
import { router } from '@inertiajs/react';

export interface SwarmNode {
    id: string;
    hostname: string;
    status: string;
    availability: string;
    manager_status: string;
    engine_version: string;
    self: boolean;
}

interface UseSwarmNodesOptions {
    serverUuid: string;
    enabled?: boolean;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseSwarmNodesReturn {
    nodes: SwarmNode[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    initSwarm: () => void;
    joinSwarm: (token: string, managerAddr: string, role: 'worker' | 'manager') => void;
    leaveSwarm: () => void;
    promoteNode: (nodeId: string) => void;
    demoteNode: (nodeId: string) => void;
}

export function useSwarmNodes({
    serverUuid,
    enabled = true,
    autoRefresh = false,
    refreshInterval = 15000,
}: UseSwarmNodesOptions): UseSwarmNodesReturn {
    const [nodes, setNodes] = React.useState<SwarmNode[]>([]);
    const [isLoading, setIsLoading] = React.useState(enabled);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchNodes = React.useCallback(async () => {
        if (!enabled) {
            setIsLoading(false);
            return;
        }

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/servers/${serverUuid}/swarm/nodes/json`, {
                headers: { Accept: 'application/json' },
                credentials: 'include',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error((data as { error?: string }).error ?? `Failed to fetch swarm nodes: ${response.statusText}`);
            }

            const data = (await response.json()) as { nodes: SwarmNode[] };
            setNodes(data.nodes ?? []);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch swarm nodes'));
        } finally {
            setIsLoading(false);
        }
    }, [serverUuid, enabled]);

    React.useEffect(() => {
        fetchNodes();
    }, [fetchNodes]);

    React.useEffect(() => {
        if (!autoRefresh || !enabled) return;

        const interval = setInterval(() => {
            fetchNodes();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, enabled, refreshInterval, fetchNodes]);

    const initSwarm = React.useCallback(() => {
        router.post(`/servers/${serverUuid}/swarm/init`);
    }, [serverUuid]);

    const joinSwarm = React.useCallback(
        (token: string, managerAddr: string, role: 'worker' | 'manager') => {
            router.post(`/servers/${serverUuid}/swarm/join`, { token, manager_addr: managerAddr, role });
        },
        [serverUuid],
    );

    const leaveSwarm = React.useCallback(() => {
        router.post(`/servers/${serverUuid}/swarm/leave`);
    }, [serverUuid]);

    const promoteNode = React.useCallback(
        (nodeId: string) => {
            router.post(`/servers/${serverUuid}/swarm/nodes/${nodeId}/promote`, {}, { onSuccess: () => fetchNodes() });
        },
        [serverUuid, fetchNodes],
    );

    const demoteNode = React.useCallback(
        (nodeId: string) => {
            router.post(`/servers/${serverUuid}/swarm/nodes/${nodeId}/demote`, {}, { onSuccess: () => fetchNodes() });
        },
        [serverUuid, fetchNodes],
    );

    return {
        nodes,
        isLoading,
        error,
        refetch: fetchNodes,
        initSwarm,
        joinSwarm,
        leaveSwarm,
        promoteNode,
        demoteNode,
    };
}
