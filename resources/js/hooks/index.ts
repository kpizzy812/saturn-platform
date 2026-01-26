// Custom hooks re-exports
export { useNotifications } from './useNotifications';
export { useRealtimeStatus } from './useRealtimeStatus';
export { useLogStream } from './useLogStream';
export type { LogEntry } from './useLogStream';

// API data fetching hooks
export {
    useApplications,
    useApplication,
} from './useApplications';

export {
    useDeployments,
    useDeployment,
} from './useDeployments';

export {
    useDatabases,
    useDatabase,
    useDatabaseBackups,
} from './useDatabases';

export {
    useDatabaseMetrics,
    formatMetricValue,
} from './useDatabaseMetrics';
export type {
    PostgresMetrics,
    MysqlMetrics,
    RedisMetrics,
    MongoMetrics,
    ClickhouseMetrics,
    DatabaseMetrics,
} from './useDatabaseMetrics';

export { useDatabaseMetricsHistory } from './useDatabaseMetricsHistory';
export type { DatabaseMetricsHistory } from './useDatabaseMetricsHistory';

export {
    useDatabaseExtensions,
    useDatabaseUsers,
    useDatabaseLogs,
} from './useDatabaseDetails';
export type {
    DatabaseExtension,
    DatabaseUser,
    DatabaseLog,
} from './useDatabaseDetails';

export {
    useServices,
    useService,
    useServiceEnvs,
} from './useServices';

export {
    useProjects,
    useProject,
    useProjectEnvironments,
} from './useProjects';

export {
    useServers,
    useServer,
    useServerResources,
    useServerDomains,
} from './useServers';

export {
    useBillingInfo,
    usePaymentMethods,
    useInvoices,
    useUsageDetails,
    useSubscription,
} from './useBilling';
export type {
    BillingInfo,
    UsageMetric,
    PaymentMethod,
    Invoice,
    UsageDetails,
    ServiceUsage,
} from './useBilling';

export {
    useClickhouseQueries,
    useClickhouseMergeStatus,
    useClickhouseReplication,
    useClickhouseSettings,
} from './useClickhouseExtended';
export type {
    ClickhouseQuery,
    ClickhouseMergeStatus,
    ClickhouseReplica,
    ClickhouseReplicationStatus,
    ClickhouseSettings,
} from './useClickhouseExtended';

export {
    useMongoCollections,
    useMongoIndexes,
    useMongoReplicaSet,
} from './useMongodbExtended';
export type {
    MongoCollection,
    MongoIndex,
    MongoReplicaMember,
    MongoReplicaSet,
} from './useMongodbExtended';

export {
    useRedisKeys,
    useRedisMemory,
    useRedisFlush,
    usePostgresMaintenance,
} from './useRedisExtended';
export type {
    RedisKey,
    RedisMemoryInfo,
} from './useRedisExtended';

export {
    useMysqlSettings,
    useRedisPersistence,
    useMongoStorageSettings,
    formatRdbSaveRules,
} from './useDatabaseSettings';
export type {
    MysqlSettings,
    RdbSaveRule,
    RedisPersistence,
    MongoStorageSettings,
} from './useDatabaseSettings';
