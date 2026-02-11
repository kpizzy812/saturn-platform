import { vi } from 'vitest';

// Mock all database hooks
export const mockUseDatabaseMetrics = vi.fn(() => ({
    metrics: null,
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseDatabaseLogs = vi.fn(() => ({
    logs: [],
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseDatabaseExtensions = vi.fn(() => ({
    extensions: [
        { name: 'pg_stat_statements', enabled: true, version: '1.9', description: 'Track execution statistics' },
        { name: 'pgcrypto', enabled: false, version: '1.3', description: 'Cryptographic functions' },
        { name: 'uuid-ossp', enabled: true, version: '1.1', description: 'UUID generation functions' },
        { name: 'hstore', enabled: false, version: '1.8', description: 'Key-value pair data type' },
        { name: 'pg_trgm', enabled: true, version: '1.6', description: 'Text similarity using trigrams' },
        { name: 'postgis', enabled: false, version: '3.3', description: 'Geospatial objects and functions' },
    ],
    isLoading: false,
    refetch: vi.fn(),
    toggleExtension: vi.fn(async () => true),
}));

export const mockUseDatabaseUsers = vi.fn(() => ({
    users: [
        { name: 'postgres', role: 'Superuser', connections: 5 },
        { name: 'app_user', role: 'Standard', connections: 12 },
        { name: 'readonly', role: 'Read-only', connections: 0 },
    ],
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUsePostgresMaintenance = vi.fn(() => ({
    runMaintenance: vi.fn(async () => true),
    isLoading: false,
}));

export const mockUseMysqlSettings = vi.fn(() => ({
    settings: {
        slowQueryLog: true,
        binaryLogging: false,
        maxConnections: 150,
        innodbBufferPoolSize: '128M',
        queryCacheSize: 'N/A (deprecated in MySQL 8.0)',
        queryTimeout: 28800,
    },
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseMongoCollections = vi.fn(() => ({
    collections: [],
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseMongoIndexes = vi.fn(() => ({
    indexes: [],
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseMongoReplicaSet = vi.fn(() => ({
    replicaSet: null,
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseMongoStorageSettings = vi.fn(() => ({
    settings: {
        storageEngine: 'WiredTiger',
        cacheSize: 'Default (50% RAM)',
        journalEnabled: true,
        directoryPerDb: false,
    },
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseRedisKeys = vi.fn(() => ({
    keys: [],
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseRedisMemory = vi.fn(() => ({
    memory: {
        usedMemory: '1.2 MB',
        peakMemory: '2.5 MB',
        fragmentationRatio: '1.05',
        maxMemory: '2 GB',
        evictionPolicy: 'noeviction',
    },
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseRedisFlush = vi.fn(() => ({
    flush: vi.fn(async () => true),
    isLoading: false,
}));

export const mockUseRedisPersistence = vi.fn(() => ({
    persistence: {
        rdbEnabled: true,
        rdbSaveRules: '3600:1,300:100,60:10000',
        rdbLastSaveTime: '2024-01-01 12:00:00',
        rdbLastBgsaveStatus: 'ok',
        aofEnabled: false,
        aofFsync: 'everysec',
    },
    isLoading: false,
    refetch: vi.fn(),
}));

export const mockUseRedisKeyValue = vi.fn(() => ({
    keyValue: null,
    isLoading: false,
    fetchKeyValue: vi.fn(async () => null),
}));

export const mockUseRedisSetKeyValue = vi.fn(() => ({
    setKeyValue: vi.fn(async () => true),
    isLoading: false,
}));

export const mockFormatMetricValue = vi.fn((value, suffix = '') => {
    if (value === null || value === undefined) return 'N/A';
    return `${value}${suffix}`;
});

export const mockFormatRdbSaveRules = vi.fn((rules) => rules || 'N/A');
