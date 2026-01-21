/**
 * Complete examples of using Saturn API hooks
 *
 * These examples show real-world usage patterns for all the API data fetching hooks.
 * Copy and adapt these examples to your components.
 */

import * as React from 'react';
import {
    useApplications,
    useApplication,
    useDeployments,
    useDeployment,
    useDatabases,
    useDatabase,
    useDatabaseBackups,
    useServices,
    useService,
    useServiceEnvs,
    useProjects,
    useProject,
    useServers,
    useServer,
} from '@/hooks';

// ==================== APPLICATIONS ====================

/**
 * Example 1: Applications List with Auto-refresh
 */
export function ApplicationsListExample() {
    const { applications, isLoading, error, refetch } = useApplications({
        autoRefresh: true,
        refreshInterval: 30000, // Poll every 30 seconds
    });

    if (isLoading) {
        return <div>Loading applications...</div>;
    }

    if (error) {
        return <div>Error: {error.message}</div>;
    }

    return (
        <div>
            <button onClick={() => refetch()}>Refresh</button>
            <ul>
                {applications.map((app) => (
                    <li key={app.uuid}>
                        {app.name} - {app.status}
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Example 2: Application Control Panel
 */
export function ApplicationControlPanelExample({ uuid }: { uuid: string }) {
    const {
        application,
        isLoading,
        error,
        startApplication,
        stopApplication,
        restartApplication,
        updateApplication,
    } = useApplication({ uuid, autoRefresh: true });

    const [isStarting, setIsStarting] = React.useState(false);

    const handleStart = async () => {
        setIsStarting(true);
        try {
            await startApplication();
            alert('Application started successfully');
        } catch (err) {
            alert('Failed to start application: ' + (err as Error).message);
        } finally {
            setIsStarting(false);
        }
    };

    if (isLoading) return <div>Loading...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!application) return <div>Application not found</div>;

    return (
        <div>
            <h1>{application.name}</h1>
            <p>Status: {application.status}</p>
            <div>
                <button onClick={handleStart} disabled={isStarting}>
                    {isStarting ? 'Starting...' : 'Start'}
                </button>
                <button onClick={() => stopApplication()}>Stop</button>
                <button onClick={() => restartApplication()}>Restart</button>
            </div>
        </div>
    );
}

// ==================== DEPLOYMENTS ====================

/**
 * Example 3: Deployments with Real-time Updates
 */
export function DeploymentsListExample({ applicationUuid }: { applicationUuid: string }) {
    const {
        deployments,
        isLoading,
        error,
        startDeployment,
        cancelDeployment,
    } = useDeployments({
        applicationUuid,
        autoRefresh: true,
        refreshInterval: 10000, // Poll every 10 seconds during active deployments
    });

    const [isDeploying, setIsDeploying] = React.useState(false);

    const handleDeploy = async () => {
        setIsDeploying(true);
        try {
            const deployment = await startDeployment(applicationUuid, false);
            alert(`Deployment ${deployment.uuid} started`);
        } catch (err) {
            alert('Failed to start deployment: ' + (err as Error).message);
        } finally {
            setIsDeploying(false);
        }
    };

    const handleCancel = async (deploymentUuid: string) => {
        if (!confirm('Are you sure you want to cancel this deployment?')) return;

        try {
            await cancelDeployment(deploymentUuid);
            alert('Deployment cancelled');
        } catch (err) {
            alert('Failed to cancel deployment: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading deployments...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <button onClick={handleDeploy} disabled={isDeploying}>
                {isDeploying ? 'Deploying...' : 'Deploy Now'}
            </button>
            <ul>
                {deployments.map((deployment) => (
                    <li key={deployment.uuid}>
                        {deployment.uuid} - {deployment.status}
                        {deployment.status === 'in_progress' && (
                            <button onClick={() => handleCancel(deployment.uuid)}>
                                Cancel
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Example 4: Single Deployment with Live Status
 */
export function DeploymentDetailsExample({ uuid }: { uuid: string }) {
    const { deployment, isLoading, error, cancel } = useDeployment({
        uuid,
        autoRefresh: true,
        refreshInterval: 5000, // Poll every 5 seconds
    });

    // Stop polling when deployment finishes
    React.useEffect(() => {
        if (deployment && ['finished', 'failed', 'cancelled'].includes(deployment.status)) {
            // Could set autoRefresh to false here
        }
    }, [deployment?.status]);

    if (isLoading) return <div>Loading deployment...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!deployment) return <div>Deployment not found</div>;

    return (
        <div>
            <h1>Deployment {deployment.uuid}</h1>
            <p>Status: {deployment.status}</p>
            <p>Commit: {deployment.commit}</p>
            <p>Message: {deployment.commit_message}</p>
            {deployment.status === 'in_progress' && (
                <button onClick={cancel}>Cancel Deployment</button>
            )}
        </div>
    );
}

// ==================== DATABASES ====================

/**
 * Example 5: Databases List with Create
 */
export function DatabasesListExample() {
    const { databases, isLoading, error, createDatabase } = useDatabases({
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const [isCreating, setIsCreating] = React.useState(false);

    const handleCreatePostgres = async () => {
        setIsCreating(true);
        try {
            const db = await createDatabase('postgresql', {
                name: 'my-postgres',
                description: 'Production PostgreSQL database',
                environment_id: 1,
                destination_id: 1,
            });
            alert(`Database ${db.name} created successfully`);
        } catch (err) {
            alert('Failed to create database: ' + (err as Error).message);
        } finally {
            setIsCreating(false);
        }
    };

    if (isLoading) return <div>Loading databases...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <button onClick={handleCreatePostgres} disabled={isCreating}>
                {isCreating ? 'Creating...' : 'New PostgreSQL Database'}
            </button>
            <ul>
                {databases.map((db) => (
                    <li key={db.uuid}>
                        {db.name} - {db.database_type} - {db.status}
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Example 6: Database Management
 */
export function DatabaseManagementExample({ uuid }: { uuid: string }) {
    const {
        database,
        isLoading,
        error,
        startDatabase,
        stopDatabase,
        restartDatabase,
        deleteDatabase,
    } = useDatabase({ uuid, autoRefresh: true });

    const handleRestart = async () => {
        try {
            await restartDatabase();
            alert('Database restarted successfully');
        } catch (err) {
            alert('Failed to restart database: ' + (err as Error).message);
        }
    };

    const handleDelete = async () => {
        if (!confirm('Are you sure you want to delete this database?')) return;

        try {
            await deleteDatabase();
            alert('Database deleted successfully');
            // Navigate away or update UI
        } catch (err) {
            alert('Failed to delete database: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading database...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!database) return <div>Database not found</div>;

    return (
        <div>
            <h1>{database.name}</h1>
            <p>Type: {database.database_type}</p>
            <p>Status: {database.status}</p>
            <div>
                <button onClick={() => startDatabase()}>Start</button>
                <button onClick={() => stopDatabase()}>Stop</button>
                <button onClick={handleRestart}>Restart</button>
                <button onClick={handleDelete} style={{ color: 'red' }}>
                    Delete
                </button>
            </div>
        </div>
    );
}

/**
 * Example 7: Database Backups Management
 */
export function DatabaseBackupsExample({ databaseUuid }: { databaseUuid: string }) {
    const { backups, isLoading, error, createBackup, deleteBackup } = useDatabaseBackups({
        databaseUuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const handleCreateBackup = async () => {
        try {
            await createBackup();
            alert('Backup created successfully');
        } catch (err) {
            alert('Failed to create backup: ' + (err as Error).message);
        }
    };

    const handleDeleteBackup = async (backupUuid: string) => {
        if (!confirm('Are you sure you want to delete this backup?')) return;

        try {
            await deleteBackup(backupUuid);
            alert('Backup deleted successfully');
        } catch (err) {
            alert('Failed to delete backup: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading backups...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <button onClick={handleCreateBackup}>Create Backup</button>
            <ul>
                {backups.map((backup) => (
                    <li key={backup.uuid}>
                        {backup.filename} - {backup.size} - {backup.status}
                        <button onClick={() => handleDeleteBackup(backup.uuid)}>
                            Delete
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

// ==================== SERVICES ====================

/**
 * Example 8: Service Control Panel
 */
export function ServiceControlPanelExample({ uuid }: { uuid: string }) {
    const {
        service,
        isLoading,
        error,
        startService,
        stopService,
        restartService,
    } = useService({ uuid, autoRefresh: true });

    if (isLoading) return <div>Loading service...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!service) return <div>Service not found</div>;

    return (
        <div>
            <h1>{service.name}</h1>
            <div>
                <button onClick={() => startService()}>Start</button>
                <button onClick={() => stopService()}>Stop</button>
                <button onClick={() => restartService()}>Restart</button>
            </div>
        </div>
    );
}

/**
 * Example 9: Service Environment Variables
 */
export function ServiceEnvironmentVariablesExample({ serviceUuid }: { serviceUuid: string }) {
    const { envs, isLoading, error, createEnv, updateEnv, deleteEnv } = useServiceEnvs({
        serviceUuid,
    });

    const [newKey, setNewKey] = React.useState('');
    const [newValue, setNewValue] = React.useState('');

    const handleAddEnv = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newKey || !newValue) return;

        try {
            await createEnv({
                key: newKey,
                value: newValue,
                is_preview: false,
                is_build_time: false,
            });
            setNewKey('');
            setNewValue('');
            alert('Environment variable added');
        } catch (err) {
            alert('Failed to add environment variable: ' + (err as Error).message);
        }
    };

    const handleDeleteEnv = async (envUuid: string) => {
        if (!confirm('Are you sure?')) return;

        try {
            await deleteEnv(envUuid);
            alert('Environment variable deleted');
        } catch (err) {
            alert('Failed to delete: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading environment variables...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <form onSubmit={handleAddEnv}>
                <input
                    type="text"
                    placeholder="Key"
                    value={newKey}
                    onChange={(e) => setNewKey(e.target.value)}
                />
                <input
                    type="text"
                    placeholder="Value"
                    value={newValue}
                    onChange={(e) => setNewValue(e.target.value)}
                />
                <button type="submit">Add Variable</button>
            </form>
            <ul>
                {envs.map((env) => (
                    <li key={env.uuid}>
                        {env.key} = {env.value}
                        <button onClick={() => handleDeleteEnv(env.uuid)}>Delete</button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

// ==================== PROJECTS ====================

/**
 * Example 10: Projects List with Create
 */
export function ProjectsListExample() {
    const { projects, isLoading, error, createProject } = useProjects();

    const handleCreateProject = async () => {
        const name = prompt('Project name:');
        if (!name) return;

        try {
            const project = await createProject({
                name,
                description: 'A new project',
            });
            alert(`Project ${project.name} created successfully`);
        } catch (err) {
            alert('Failed to create project: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading projects...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <button onClick={handleCreateProject}>New Project</button>
            <ul>
                {projects.map((project) => (
                    <li key={project.uuid}>
                        {project.name} - {project.environments.length} environments
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Example 11: Project with Environments
 */
export function ProjectWithEnvironmentsExample({ uuid }: { uuid: string }) {
    const { project, isLoading, error, createEnvironment, deleteEnvironment } = useProject({
        uuid,
        autoRefresh: true,
    });

    const handleCreateEnvironment = async () => {
        const name = prompt('Environment name (e.g., staging, production):');
        if (!name) return;

        try {
            const env = await createEnvironment(name, `${name} environment`);
            alert(`Environment ${env.name} created successfully`);
        } catch (err) {
            alert('Failed to create environment: ' + (err as Error).message);
        }
    };

    const handleDeleteEnvironment = async (envName: string) => {
        if (!confirm(`Delete environment "${envName}"?`)) return;

        try {
            await deleteEnvironment(envName);
            alert('Environment deleted successfully');
        } catch (err) {
            alert('Failed to delete environment: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading project...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!project) return <div>Project not found</div>;

    return (
        <div>
            <h1>{project.name}</h1>
            <p>{project.description}</p>
            <button onClick={handleCreateEnvironment}>Add Environment</button>
            <ul>
                {project.environments.map((env) => (
                    <li key={env.uuid}>
                        {env.name}
                        <button onClick={() => handleDeleteEnvironment(env.name)}>
                            Delete
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

// ==================== SERVERS ====================

/**
 * Example 12: Servers List with Validation
 */
export function ServersListExample() {
    const { servers, isLoading, error, createServer } = useServers({
        autoRefresh: true,
        refreshInterval: 60000, // Poll every 60 seconds
    });

    const handleCreateServer = async () => {
        const name = prompt('Server name:');
        const ip = prompt('Server IP address:');
        if (!name || !ip) return;

        try {
            const server = await createServer({
                name,
                ip,
                port: 22,
                user: 'root',
            });
            alert(`Server ${server.name} created successfully`);
        } catch (err) {
            alert('Failed to create server: ' + (err as Error).message);
        }
    };

    if (isLoading) return <div>Loading servers...</div>;
    if (error) return <div>Error: {error.message}</div>;

    return (
        <div>
            <button onClick={handleCreateServer}>Add Server</button>
            <ul>
                {servers.map((server) => (
                    <li key={server.uuid}>
                        {server.name} - {server.ip}
                        <span style={{ color: server.is_reachable ? 'green' : 'red' }}>
                            {server.is_reachable ? ' Online' : ' Offline'}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Example 13: Server Details with Validation
 */
export function ServerDetailsExample({ uuid }: { uuid: string }) {
    const { server, isLoading, error, validateServer } = useServer({
        uuid,
        autoRefresh: true,
        refreshInterval: 60000,
    });

    const [isValidating, setIsValidating] = React.useState(false);

    const handleValidate = async () => {
        setIsValidating(true);
        try {
            const result = await validateServer();
            if (result.is_usable) {
                alert('Server is ready to use!');
            } else {
                alert(result.message || 'Server validation failed');
            }
        } catch (err) {
            alert('Failed to validate server: ' + (err as Error).message);
        } finally {
            setIsValidating(false);
        }
    };

    if (isLoading) return <div>Loading server...</div>;
    if (error) return <div>Error: {error.message}</div>;
    if (!server) return <div>Server not found</div>;

    return (
        <div>
            <h1>{server.name}</h1>
            <p>IP: {server.ip}:{server.port}</p>
            <p>User: {server.user}</p>
            <p>Reachable: {server.is_reachable ? 'Yes' : 'No'}</p>
            <p>Usable: {server.is_usable ? 'Yes' : 'No'}</p>
            <button onClick={handleValidate} disabled={isValidating}>
                {isValidating ? 'Validating...' : 'Validate Server'}
            </button>
        </div>
    );
}
