import * as React from 'react';
import type { Server } from '@/types';

interface UseServersOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServersReturn {
    servers: Server[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createServer: (data: CreateServerData) => Promise<Server>;
}

interface UseServerOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServerReturn {
    server: Server | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateServer: (data: Partial<Server>) => Promise<void>;
    deleteServer: () => Promise<void>;
    validateServer: () => Promise<ValidationResult>;
}

interface CreateServerData {
    name: string;
    description?: string;
    ip: string;
    port?: number;
    user?: string;
    private_key_id?: number;
}

interface ValidationResult {
    is_reachable: boolean;
    is_usable: boolean;
    docker_installed: boolean;
    message?: string;
}

interface ServerResource {
    id: number;
    uuid: string;
    name: string;
    type: 'application' | 'database' | 'service';
    status: string;
}

interface UseServerResourcesOptions {
    serverUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServerResourcesReturn {
    resources: ServerResource[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

interface Domain {
    domain: string;
    ssl_status: string;
    verified_at: string | null;
}

interface UseServerDomainsOptions {
    serverUuid: string;
}

interface UseServerDomainsReturn {
    domains: Domain[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Fetch all servers for the current team
 */
export function useServers({
    autoRefresh = false,
    refreshInterval = 60000, // 60 seconds
}: UseServersOptions = {}): UseServersReturn {
    const [servers, setServers] = React.useState<Server[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchServers = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/servers', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch servers: ${response.statusText}`);
            }

            const data = await response.json();
            setServers(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch servers'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createServer = React.useCallback(async (data: CreateServerData): Promise<Server> => {
        try {
            const response = await fetch('/api/v1/servers', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to create server: ${response.statusText}`);
            }

            const server = await response.json();

            // Refresh the servers list
            await fetchServers();

            return server;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create server');
        }
    }, [fetchServers]);

    // Initial fetch
    React.useEffect(() => {
        fetchServers();
    }, [fetchServers]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchServers();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchServers]);

    return {
        servers,
        isLoading,
        error,
        refetch: fetchServers,
        createServer,
    };
}

/**
 * Fetch and manage a single server
 */
export function useServer({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000, // 60 seconds
}: UseServerOptions): UseServerReturn {
    const [server, setServer] = React.useState<Server | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchServer = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/servers/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch server: ${response.statusText}`);
            }

            const data = await response.json();
            setServer(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch server'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const updateServer = React.useCallback(async (data: Partial<Server>) => {
        try {
            const response = await fetch(`/api/v1/servers/${uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update server: ${response.statusText}`);
            }

            const updated = await response.json();
            setServer(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update server');
        }
    }, [uuid]);

    const deleteServer = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/servers/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete server: ${response.statusText}`);
            }

            setServer(null);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete server');
        }
    }, [uuid]);

    const validateServer = React.useCallback(async (): Promise<ValidationResult> => {
        try {
            const response = await fetch(`/api/v1/servers/${uuid}/validate`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to validate server: ${response.statusText}`);
            }

            const result = await response.json();

            // Refresh server data after validation
            await fetchServer();

            return result;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to validate server');
        }
    }, [uuid, fetchServer]);

    // Initial fetch
    React.useEffect(() => {
        fetchServer();
    }, [fetchServer]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchServer();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchServer]);

    return {
        server,
        isLoading,
        error,
        refetch: fetchServer,
        updateServer,
        deleteServer,
        validateServer,
    };
}

/**
 * Fetch resources deployed on a server
 */
export function useServerResources({
    serverUuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseServerResourcesOptions): UseServerResourcesReturn {
    const [resources, setResources] = React.useState<ServerResource[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchResources = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/servers/${serverUuid}/resources`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch server resources: ${response.statusText}`);
            }

            const data = await response.json();
            setResources(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch server resources'));
        } finally {
            setIsLoading(false);
        }
    }, [serverUuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchResources();
    }, [fetchResources]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchResources();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchResources]);

    return {
        resources,
        isLoading,
        error,
        refetch: fetchResources,
    };
}

/**
 * Fetch domains configured on a server
 */
export function useServerDomains({
    serverUuid,
}: UseServerDomainsOptions): UseServerDomainsReturn {
    const [domains, setDomains] = React.useState<Domain[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchDomains = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/servers/${serverUuid}/domains`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch server domains: ${response.statusText}`);
            }

            const data = await response.json();
            setDomains(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch server domains'));
        } finally {
            setIsLoading(false);
        }
    }, [serverUuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchDomains();
    }, [fetchDomains]);

    return {
        domains,
        isLoading,
        error,
        refetch: fetchDomains,
    };
}
