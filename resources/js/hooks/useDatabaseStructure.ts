import * as React from 'react';
import type { DatabaseStructure } from '@/types';

interface UseDatabaseStructureOptions {
    uuid: string;
    enabled?: boolean;
}

interface UseDatabaseStructureReturn {
    structure: DatabaseStructure | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

/**
 * Hook to fetch database structure (tables, collections, key patterns)
 * Used for partial transfer selection
 */
export function useDatabaseStructure({
    uuid,
    enabled = true,
}: UseDatabaseStructureOptions): UseDatabaseStructureReturn {
    const [structure, setStructure] = React.useState<DatabaseStructure | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchStructure = React.useCallback(async () => {
        if (!enabled || !uuid) return;

        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/_internal/databases/${uuid}/structure`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch database structure: ${response.statusText}`);
            }

            const data = await response.json();
            setStructure(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch database structure'));
        } finally {
            setIsLoading(false);
        }
    }, [uuid, enabled]);

    React.useEffect(() => {
        if (enabled) {
            fetchStructure();
        }
    }, [fetchStructure, enabled]);

    return {
        structure,
        isLoading,
        error,
        refetch: fetchStructure,
    };
}
