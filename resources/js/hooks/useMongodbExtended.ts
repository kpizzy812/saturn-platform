import * as React from 'react';

export interface MongoCollection {
    name: string;
    documentCount: number;
    size: string;
    avgDocSize: string;
}

export interface MongoIndex {
    collection: string;
    name: string;
    fields: string[];
    unique: boolean;
    size: string;
}

export interface MongoReplicaMember {
    host: string;
    state: string;
    health?: number;
}

export interface MongoReplicaSet {
    enabled: boolean;
    name: string | null;
    members: MongoReplicaMember[];
}

interface UseMongodbOptions {
    uuid: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

/**
 * Hook for fetching MongoDB collections with stats.
 */
export function useMongoCollections({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseMongodbOptions) {
    const [collections, setCollections] = React.useState<MongoCollection[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchCollections = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/mongodb/collections`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch collections: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setCollections(data.collections || []);
            } else {
                setError(data.error || 'Collections not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch collections');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchCollections();
    }, [fetchCollections]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchCollections, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchCollections]);

    return { collections, isLoading, error, refetch: fetchCollections };
}

/**
 * Hook for fetching MongoDB indexes.
 */
export function useMongoIndexes({
    uuid,
    autoRefresh = false,
    refreshInterval = 60000,
}: UseMongodbOptions) {
    const [indexes, setIndexes] = React.useState<MongoIndex[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchIndexes = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/mongodb/indexes`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch indexes: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setIndexes(data.indexes || []);
            } else {
                setError(data.error || 'Indexes not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch indexes');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchIndexes();
    }, [fetchIndexes]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchIndexes, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchIndexes]);

    return { indexes, isLoading, error, refetch: fetchIndexes };
}

/**
 * Hook for fetching MongoDB replica set status.
 */
export function useMongoReplicaSet({
    uuid,
    autoRefresh = true,
    refreshInterval = 30000,
}: UseMongodbOptions) {
    const [replicaSet, setReplicaSet] = React.useState<MongoReplicaSet | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    const fetchReplicaSet = React.useCallback(async () => {
        if (!uuid) return;

        try {
            setError(null);
            const response = await fetch(`/_internal/databases/${uuid}/mongodb/replica-set`, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch replica set status: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.available) {
                setReplicaSet(data.replicaSet);
            } else {
                setError(data.error || 'Replica set status not available');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch replica set status');
        } finally {
            setIsLoading(false);
        }
    }, [uuid]);

    React.useEffect(() => {
        fetchReplicaSet();
    }, [fetchReplicaSet]);

    React.useEffect(() => {
        if (!autoRefresh || !refreshInterval) return;

        const interval = setInterval(fetchReplicaSet, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchReplicaSet]);

    return { replicaSet, isLoading, error, refetch: fetchReplicaSet };
}
