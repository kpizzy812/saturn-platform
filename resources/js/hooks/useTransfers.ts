import * as React from 'react';
import type { ResourceTransfer, TransferTargets, TransferMode } from '@/types';

interface UseTransfersOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
    statusFilter?: string;
}

interface UseTransfersReturn {
    transfers: ResourceTransfer[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    createTransfer: (data: CreateTransferData) => Promise<ResourceTransfer>;
    cancelTransfer: (uuid: string) => Promise<void>;
}

interface CreateTransferData {
    source_uuid: string;
    source_type: string;
    target_environment_id: number;
    target_server_id: number;
    transfer_mode: TransferMode;
    target_uuid?: string;
    transfer_options?: {
        tables?: string[];
        collections?: string[];
        key_patterns?: string[];
    };
}

interface UseTransferTargetsOptions {
    sourceType: string;
    sourceUuid: string;
}

interface UseTransferTargetsReturn {
    targets: TransferTargets | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Hook to fetch and manage resource transfers
 */
export function useTransfers({
    autoRefresh = false,
    refreshInterval = 10000,
    statusFilter,
}: UseTransfersOptions = {}): UseTransfersReturn {
    const [transfers, setTransfers] = React.useState<ResourceTransfer[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchTransfers = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const params = new URLSearchParams();
            if (statusFilter) params.set('status', statusFilter);

            const response = await fetch(`/transfers?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch transfers: ${response.statusText}`);
            }

            const data = await response.json();
            setTransfers(data.data || data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch transfers'));
        } finally {
            setIsLoading(false);
        }
    }, [statusFilter]);

    const createTransfer = React.useCallback(async (data: CreateTransferData): Promise<ResourceTransfer> => {
        const response = await fetch('/transfers', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Failed to create transfer: ${response.statusText}`);
        }

        const transfer = await response.json();
        await fetchTransfers();
        return transfer;
    }, [fetchTransfers]);

    const cancelTransfer = React.useCallback(async (uuid: string) => {
        const response = await fetch(`/transfers/${uuid}/cancel`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Failed to cancel transfer: ${response.statusText}`);
        }

        await fetchTransfers();
    }, [fetchTransfers]);

    // Initial fetch
    React.useEffect(() => {
        fetchTransfers();
    }, [fetchTransfers]);

    // Auto-refresh for in-progress transfers
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchTransfers();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchTransfers]);

    return {
        transfers,
        isLoading,
        error,
        refetch: fetchTransfers,
        createTransfer,
        cancelTransfer,
    };
}

/**
 * Hook to fetch available transfer targets for a source
 */
export function useTransferTargets({
    sourceType,
    sourceUuid,
}: UseTransferTargetsOptions): UseTransferTargetsReturn {
    const [targets, setTargets] = React.useState<TransferTargets | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchTargets = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const params = new URLSearchParams({
                source_type: sourceType,
                source_uuid: sourceUuid,
            });

            const response = await fetch(`/_internal/transfers/targets?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch transfer targets: ${response.statusText}`);
            }

            const data = await response.json();
            setTargets(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch transfer targets'));
        } finally {
            setIsLoading(false);
        }
    }, [sourceType, sourceUuid]);

    React.useEffect(() => {
        fetchTargets();
    }, [fetchTargets]);

    return {
        targets,
        isLoading,
        error,
        refetch: fetchTargets,
    };
}
