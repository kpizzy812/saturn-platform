# Saturn API Hooks - Quick Reference

One-page quick reference for all Saturn API hooks.

## Import

```typescript
import {
    useApplications, useApplication,
    useDeployments, useDeployment,
    useDatabases, useDatabase, useDatabaseBackups,
    useServices, useService, useServiceEnvs,
    useProjects, useProject, useProjectEnvironments,
    useServers, useServer, useServerResources, useServerDomains,
} from '@/hooks';
```

## Applications

```typescript
// List all applications
const { applications, isLoading, error, refetch } = useApplications({
    autoRefresh: true,
    refreshInterval: 30000,
});

// Single application with control
const {
    application,
    isLoading,
    error,
    refetch,
    updateApplication,
    startApplication,
    stopApplication,
    restartApplication,
} = useApplication({ uuid });
```

## Deployments

```typescript
// List deployments (all or by application)
const {
    deployments,
    isLoading,
    error,
    refetch,
    startDeployment,
    cancelDeployment,
} = useDeployments({
    applicationUuid, // optional
    autoRefresh: true,
    refreshInterval: 10000,
});

// Single deployment
const {
    deployment,
    isLoading,
    error,
    refetch,
    cancel,
} = useDeployment({ uuid, autoRefresh: true, refreshInterval: 5000 });

// Start deployment
await startDeployment(applicationUuid, force = false);

// Cancel deployment
await cancelDeployment(deploymentUuid);
```

## Databases

```typescript
// List all databases
const {
    databases,
    isLoading,
    error,
    refetch,
    createDatabase,
} = useDatabases({ autoRefresh: true });

// Create database
await createDatabase('postgresql', {
    name: 'my-db',
    environment_id: 1,
    destination_id: 1,
});

// Single database with control
const {
    database,
    isLoading,
    error,
    refetch,
    updateDatabase,
    startDatabase,
    stopDatabase,
    restartDatabase,
    deleteDatabase,
} = useDatabase({ uuid });

// Database backups
const {
    backups,
    isLoading,
    error,
    refetch,
    createBackup,
    deleteBackup,
} = useDatabaseBackups({ databaseUuid });

await createBackup();
await deleteBackup(backupUuid);
```

## Services

```typescript
// List all services
const {
    services,
    isLoading,
    error,
    refetch,
    createService,
} = useServices();

// Create service
await createService({
    name: 'my-service',
    docker_compose_raw: 'version: "3.8"...',
    environment_id: 1,
    destination_id: 1,
});

// Single service with control
const {
    service,
    isLoading,
    error,
    refetch,
    updateService,
    startService,
    stopService,
    restartService,
    deleteService,
} = useService({ uuid });

// Service environment variables
const {
    envs,
    isLoading,
    error,
    refetch,
    createEnv,
    updateEnv,
    deleteEnv,
    bulkUpdateEnvs,
} = useServiceEnvs({ serviceUuid });

await createEnv({ key: 'VAR', value: 'val', is_preview: false, is_build_time: false });
await updateEnv(envUuid, { value: 'new_val' });
await deleteEnv(envUuid);
await bulkUpdateEnvs([{ uuid: 'env-1', value: 'val1' }, { uuid: 'env-2', value: 'val2' }]);
```

## Projects

```typescript
// List all projects
const {
    projects,
    isLoading,
    error,
    refetch,
    createProject,
} = useProjects();

await createProject({ name: 'My Project', description: 'Description' });

// Single project
const {
    project,
    isLoading,
    error,
    refetch,
    updateProject,
    deleteProject,
    createEnvironment,
    deleteEnvironment,
} = useProject({ uuid });

await updateProject({ name: 'New Name' });
await deleteProject();
await createEnvironment('staging', 'Staging environment');
await deleteEnvironment('staging');

// Project environments
const {
    environments,
    isLoading,
    error,
    refetch,
} = useProjectEnvironments({ projectUuid });
```

## Servers

```typescript
// List all servers
const {
    servers,
    isLoading,
    error,
    refetch,
    createServer,
} = useServers({ autoRefresh: true, refreshInterval: 60000 });

await createServer({
    name: 'Production',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
});

// Single server
const {
    server,
    isLoading,
    error,
    refetch,
    updateServer,
    deleteServer,
    validateServer,
} = useServer({ uuid });

const validation = await validateServer();
// Returns: { is_reachable: boolean, is_usable: boolean, docker_installed: boolean, message?: string }

// Server resources
const {
    resources,
    isLoading,
    error,
    refetch,
} = useServerResources({ serverUuid });

// Server domains
const {
    domains,
    isLoading,
    error,
    refetch,
} = useServerDomains({ serverUuid });
```

## Common Patterns

### Basic Usage

```typescript
const { data, isLoading, error, refetch } = useResource();

if (isLoading) return <Spinner />;
if (error) return <Error message={error.message} />;
return <DataView data={data} />;
```

### With Auto-refresh

```typescript
const { data } = useResource({
    autoRefresh: true,
    refreshInterval: 30000, // 30 seconds
});
```

### Mutation with Error Handling

```typescript
const { mutate } = useResource({ uuid });

const handleMutate = async () => {
    try {
        await mutate(data);
        toast.success('Success!');
    } catch (error) {
        toast.error(error.message);
    }
};
```

### Conditional Auto-refresh

```typescript
const { deployment } = useDeployment({
    uuid,
    autoRefresh: deployment?.status === 'in_progress',
    refreshInterval: 5000,
});
```

## Refresh Interval Guidelines

- **Active deployments**: 5-10 seconds
- **Live status monitoring**: 10-30 seconds
- **Periodic updates**: 30-60 seconds
- **Static data**: No auto-refresh

## Error Handling

All mutation functions throw errors. Always use try-catch:

```typescript
try {
    await startApplication();
} catch (error) {
    console.error('Failed:', error);
    // Handle error
}
```

## TypeScript Types

```typescript
import type {
    Application,
    Deployment,
    Database,
    Service,
    Project,
    Server,
} from '@/types';
```

## API Endpoints

All hooks call `/api/v1/*` endpoints. See `SATURN_AUDIT.md` for complete API documentation.

## Files

- `useApplications.ts` - Application hooks
- `useDeployments.ts` - Deployment hooks
- `useDatabases.ts` - Database hooks
- `useServices.ts` - Service hooks
- `useProjects.ts` - Project hooks
- `useServers.ts` - Server hooks
- `index.ts` - Central exports
- `README.md` - Full documentation
- `EXAMPLES.tsx` - Working examples
- `API_HOOKS_SUMMARY.md` - Implementation details
- `QUICK_REFERENCE.md` - This file
