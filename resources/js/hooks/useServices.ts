import * as React from 'react';
import type { Service } from '@/types';

interface UseServicesOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServicesReturn {
    services: Service[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createService: (data: CreateServiceData) => Promise<Service>;
}

interface UseServiceOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServiceReturn {
    service: Service | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    updateService: (data: Partial<Service>) => Promise<void>;
    startService: () => Promise<void>;
    stopService: () => Promise<void>;
    restartService: () => Promise<void>;
    deleteService: () => Promise<void>;
}

interface CreateServiceData {
    name: string;
    description?: string;
    docker_compose_raw: string;
    environment_id: number;
    destination_id: number;
}

interface EnvironmentVariable {
    id: number;
    uuid: string;
    key: string;
    value: string;
    is_preview: boolean;
    is_buildtime: boolean;
}

interface UseServiceEnvsOptions {
    serviceUuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseServiceEnvsReturn {
    envs: EnvironmentVariable[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createEnv: (data: Partial<EnvironmentVariable>) => Promise<void>;
    updateEnv: (envUuid: string, data: Partial<EnvironmentVariable>) => Promise<void>;
    deleteEnv: (envUuid: string) => Promise<void>;
    bulkUpdateEnvs: (envs: Partial<EnvironmentVariable>[]) => Promise<void>;
}

/**
 * Fetch all services for the current team
 */
export function useServices({
    autoRefresh = false,
    refreshInterval = 30000,
}: UseServicesOptions = {}): UseServicesReturn {
    const [services, setServices] = React.useState<Service[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchServices = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/services', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch services: ${response.statusText}`);
            }

            const data = await response.json();
            setServices(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch services'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createService = React.useCallback(async (data: CreateServiceData): Promise<Service> => {
        try {
            const response = await fetch('/api/v1/services', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to create service: ${response.statusText}`);
            }

            const service = await response.json();

            // Refresh the services list
            await fetchServices();

            return service;
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create service');
        }
    }, [fetchServices]);

    // Initial fetch
    React.useEffect(() => {
        fetchServices();
    }, [fetchServices]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchServices();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchServices]);

    return {
        services,
        isLoading,
        error,
        refetch: fetchServices,
        createService,
    };
}

/**
 * Fetch and manage a single service
 */
export function useService({
    uuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseServiceOptions): UseServiceReturn {
    const [service, setService] = React.useState<Service | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchService = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/services/${uuid}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch service: ${response.statusText}`);
            }

            const data = await response.json();
            setService(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch service'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    const updateService = React.useCallback(async (data: Partial<Service>) => {
        try {
            const response = await fetch(`/api/v1/services/${uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to update service: ${response.statusText}`);
            }

            const updated = await response.json();
            setService(updated);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update service');
        }
    }, [uuid]);

    const startService = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/services/${uuid}/start`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to start service: ${response.statusText}`);
            }

            await fetchService();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to start service');
        }
    }, [uuid, fetchService]);

    const stopService = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/services/${uuid}/stop`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to stop service: ${response.statusText}`);
            }

            await fetchService();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to stop service');
        }
    }, [uuid, fetchService]);

    const restartService = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/services/${uuid}/restart`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to restart service: ${response.statusText}`);
            }

            await fetchService();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to restart service');
        }
    }, [uuid, fetchService]);

    const deleteService = React.useCallback(async () => {
        try {
            const response = await fetch(`/api/v1/services/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete service: ${response.statusText}`);
            }

            setService(null);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete service');
        }
    }, [uuid]);

    // Initial fetch
    React.useEffect(() => {
        fetchService();
    }, [fetchService]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchService();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchService]);

    return {
        service,
        isLoading,
        error,
        refetch: fetchService,
        updateService,
        startService,
        stopService,
        restartService,
        deleteService,
    };
}

/**
 * Fetch and manage service environment variables
 */
export function useServiceEnvs({
    serviceUuid,
    autoRefresh = false,
    refreshInterval = 30000,
}: UseServiceEnvsOptions): UseServiceEnvsReturn {
    const [envs, setEnvs] = React.useState<EnvironmentVariable[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchEnvs = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/api/v1/services/${serviceUuid}/envs`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch environment variables: ${response.statusText}`);
            }

            const data = await response.json();
            setEnvs(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch environment variables'));
        } finally {
            setIsLoading(false);
        }
    }, [serviceUuid]);

    const createEnv = React.useCallback(async (data: Partial<EnvironmentVariable>) => {
        try {
            const response = await fetch(`/api/v1/services/${serviceUuid}/envs`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error(`Failed to create environment variable: ${response.statusText}`);
            }

            await fetchEnvs();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to create environment variable');
        }
    }, [serviceUuid, fetchEnvs]);

    const updateEnv = React.useCallback(async (envUuid: string, data: Partial<EnvironmentVariable>) => {
        try {
            const response = await fetch(`/api/v1/services/${serviceUuid}/envs`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ uuid: envUuid, ...data }),
            });

            if (!response.ok) {
                throw new Error(`Failed to update environment variable: ${response.statusText}`);
            }

            await fetchEnvs();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to update environment variable');
        }
    }, [serviceUuid, fetchEnvs]);

    const deleteEnv = React.useCallback(async (envUuid: string) => {
        try {
            const response = await fetch(`/api/v1/services/${serviceUuid}/envs/${envUuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to delete environment variable: ${response.statusText}`);
            }

            await fetchEnvs();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to delete environment variable');
        }
    }, [serviceUuid, fetchEnvs]);

    const bulkUpdateEnvs = React.useCallback(async (envs: Partial<EnvironmentVariable>[]) => {
        try {
            const response = await fetch(`/api/v1/services/${serviceUuid}/envs/bulk`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ envs }),
            });

            if (!response.ok) {
                throw new Error(`Failed to bulk update environment variables: ${response.statusText}`);
            }

            await fetchEnvs();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to bulk update environment variables');
        }
    }, [serviceUuid, fetchEnvs]);

    // Initial fetch
    React.useEffect(() => {
        fetchEnvs();
    }, [fetchEnvs]);

    // Auto-refresh
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchEnvs();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchEnvs]);

    return {
        envs,
        isLoading,
        error,
        refetch: fetchEnvs,
        createEnv,
        updateEnv,
        deleteEnv,
        bulkUpdateEnvs,
    };
}
