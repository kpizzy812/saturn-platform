import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Users, Play, Trash2, RefreshCw, Eye, EyeOff, Copy, Loader2 } from 'lucide-react';
import {
    useDatabaseMetrics,
    useDatabaseExtensions,
    useDatabaseUsers,
    useDatabaseLogs,
    usePostgresMaintenance,
    formatMetricValue,
    type PostgresMetrics,
} from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

export function PostgreSQLPanel({ database }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
        { label: 'Extensions', content: <ExtensionsTab database={database} /> },
        { label: 'Users', content: <UsersTab database={database} /> },
        { label: 'Settings', content: <SettingsTab database={database} /> },
        { label: 'Logs', content: <LogsTab database={database} /> },
    ];

    return <Tabs tabs={tabs} />;
}

function OverviewTab({ database }: { database: StandaloneDatabase }) {
    const [showPassword, setShowPassword] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);

    // Fetch real-time metrics from backend
    const { metrics, isLoading } = useDatabaseMetrics({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const postgresMetrics = metrics as PostgresMetrics | null;

    // Connection details from backend
    const connectionDetails = {
        host: database.connection?.internal_host || '',
        port: database.connection?.port || '5432',
        database: database.postgres_db || database.name,
        username: database.postgres_user || database.connection?.username || '',
        password: database.postgres_password || database.connection?.password || '',
        connectionString: database.internal_db_url || '',
    };

    const stats = [
        {
            label: 'Active Connections',
            value: isLoading ? '...' : formatMetricValue(
                postgresMetrics?.activeConnections,
                ` / ${postgresMetrics?.maxConnections || 100}`
            ),
        },
        {
            label: 'Database Size',
            value: isLoading ? '...' : (postgresMetrics?.databaseSize || 'N/A'),
        },
        {
            label: 'Queries/sec',
            value: isLoading ? '...' : formatMetricValue(postgresMetrics?.queriesPerSec),
        },
        {
            label: 'Cache Hit Ratio',
            value: isLoading ? '...' : (postgresMetrics?.cacheHitRatio || 'N/A'),
        },
    ];

    const copyToClipboard = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        setTimeout(() => setCopiedField(null), 2000);
    };

    return (
        <div className="space-y-6">
            {/* Connection String */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection String</h3>
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                        <code className="flex-1 font-mono text-sm text-foreground">
                            {connectionDetails.connectionString}
                        </code>
                        <button
                            onClick={() => copyToClipboard(connectionDetails.connectionString, 'connectionString')}
                            className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                    {copiedField === 'connectionString' && (
                        <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
                    )}
                </CardContent>
            </Card>

            {/* Stats */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Database Statistics</h3>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <div key={stat.label} className="rounded-lg border border-border bg-background-secondary p-4">
                                <p className="text-sm text-foreground-muted">{stat.label}</p>
                                <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Connection Details */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection Details</h3>
                    <div className="space-y-3">
                        <ConnectionField
                            label="Host"
                            value={connectionDetails.host}
                            onCopy={() => copyToClipboard(connectionDetails.host, 'host')}
                            copied={copiedField === 'host'}
                        />
                        <ConnectionField
                            label="Port"
                            value={connectionDetails.port}
                            onCopy={() => copyToClipboard(connectionDetails.port, 'port')}
                            copied={copiedField === 'port'}
                        />
                        <ConnectionField
                            label="Database"
                            value={connectionDetails.database}
                            onCopy={() => copyToClipboard(connectionDetails.database, 'database')}
                            copied={copiedField === 'database'}
                        />
                        <ConnectionField
                            label="Username"
                            value={connectionDetails.username}
                            onCopy={() => copyToClipboard(connectionDetails.username, 'username')}
                            copied={copiedField === 'username'}
                        />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                Password
                            </label>
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <code className="flex-1 font-mono text-sm text-foreground">
                                    {showPassword ? connectionDetails.password : '••••••••••••••••••••'}
                                </code>
                                <button
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => copyToClipboard(connectionDetails.password, 'password')}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            {copiedField === 'password' && (
                                <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function ExtensionsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [togglingExtension, setTogglingExtension] = useState<string | null>(null);

    // Fetch extensions from API
    const { extensions, isLoading, refetch, toggleExtension } = useDatabaseExtensions({
        uuid: database.uuid,
        autoRefresh: false,
    });

    const handleToggleExtension = async (extensionName: string, currentlyEnabled: boolean) => {
        setTogglingExtension(extensionName);
        addToast('info', `${currentlyEnabled ? 'Disabling' : 'Enabling'} ${extensionName}...`);

        const success = await toggleExtension(extensionName, !currentlyEnabled);

        if (success) {
            addToast('success', `${extensionName} ${currentlyEnabled ? 'disabled' : 'enabled'} successfully`);
        } else {
            addToast('error', `Failed to ${currentlyEnabled ? 'disable' : 'enable'} ${extensionName}`);
        }

        setTogglingExtension(null);
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">PostgreSQL Extensions</h3>
                    <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading extensions...</span>
                    </div>
                ) : extensions.length === 0 ? (
                    <p className="py-4 text-center text-foreground-muted">No extensions found</p>
                ) : (
                    <div className="space-y-3">
                        {extensions.map((ext) => (
                            <div
                                key={ext.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-foreground">{ext.name}</p>
                                        <Badge variant={ext.enabled ? 'default' : 'secondary'}>
                                            {ext.enabled ? 'Enabled' : 'Disabled'}
                                        </Badge>
                                        <span className="text-sm text-foreground-muted">v{ext.version}</span>
                                    </div>
                                    {ext.description && (
                                        <p className="mt-1 text-sm text-foreground-muted">{ext.description}</p>
                                    )}
                                </div>
                                <Button
                                    size="sm"
                                    variant={ext.enabled ? 'secondary' : 'default'}
                                    onClick={() => handleToggleExtension(ext.name, ext.enabled)}
                                    disabled={togglingExtension === ext.name}
                                >
                                    {togglingExtension === ext.name ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : ext.enabled ? (
                                        'Disable'
                                    ) : (
                                        'Enable'
                                    )}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function UsersTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const confirm = useConfirm();

    // Fetch users from API
    const { users, isLoading, refetch } = useDatabaseUsers({
        uuid: database.uuid,
        autoRefresh: false,
    });

    const [showCreateForm, setShowCreateForm] = useState(false);
    const [newUsername, setNewUsername] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [isCreating, setIsCreating] = useState(false);

    const handleCreateUser = async () => {
        if (!showCreateForm) {
            setShowCreateForm(true);
            return;
        }

        if (!newUsername || !newPassword) {
            addToast('error', 'Username and password are required');
            return;
        }

        if (newPassword.length < 8) {
            addToast('error', 'Password must be at least 8 characters');
            return;
        }

        setIsCreating(true);
        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/users/create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                },
                body: JSON.stringify({ username: newUsername, password: newPassword }),
            });
            const data = await response.json();
            if (data.success) {
                addToast('success', data.message || `User ${newUsername} created`);
                setNewUsername('');
                setNewPassword('');
                setShowCreateForm(false);
                refetch();
            } else {
                addToast('error', data.error || 'Failed to create user');
            }
        } catch {
            addToast('error', 'Failed to create user');
        } finally {
            setIsCreating(false);
        }
    };

    const handleDeleteUser = async (username: string) => {
        const confirmed = await confirm({
            title: 'Delete User',
            description: `Are you sure you want to delete user "${username}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            try {
                const response = await fetch(`/_internal/databases/${database.uuid}/users/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                    },
                    body: JSON.stringify({ username }),
                });
                const data = await response.json();
                if (data.success) {
                    addToast('success', data.message || `User ${username} deleted`);
                    refetch();
                } else {
                    addToast('error', data.error || 'Failed to delete user');
                }
            } catch {
                addToast('error', 'Failed to delete user');
            }
        }
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Database Users</h3>
                    <div className="flex gap-2">
                        <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button size="sm" onClick={handleCreateUser} disabled={isCreating}>
                            <Users className="mr-2 h-4 w-4" />
                            {showCreateForm ? (isCreating ? 'Creating...' : 'Save User') : 'Create User'}
                        </Button>
                        {showCreateForm && (
                            <Button size="sm" variant="secondary" onClick={() => { setShowCreateForm(false); setNewUsername(''); setNewPassword(''); }}>
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>

                {showCreateForm && (
                    <div className="mb-4 rounded-lg border border-border bg-background-secondary p-4 space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-1">Username</label>
                            <input
                                type="text"
                                value={newUsername}
                                onChange={(e) => setNewUsername(e.target.value)}
                                placeholder="new_user"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-1">Password</label>
                            <input
                                type="password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                placeholder="Minimum 8 characters"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                            />
                        </div>
                    </div>
                )}
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading users...</span>
                    </div>
                ) : users.length === 0 ? (
                    <p className="py-4 text-center text-foreground-muted">No users found</p>
                ) : (
                    <div className="space-y-3">
                        {users.map((user) => (
                            <div
                                key={user.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-foreground">{user.name}</p>
                                        <Badge variant="secondary">{user.role}</Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        {user.connections} active connection{user.connections !== 1 ? 's' : ''}
                                    </p>
                                </div>
                                {user.name !== 'postgres' && (
                                    <Button
                                        size="sm"
                                        variant="danger"
                                        onClick={() => handleDeleteUser(user.name)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();

    // Real-time metrics contain max_connections
    const { metrics } = useDatabaseMetrics({
        uuid: database.uuid,
        autoRefresh: false,
    });
    const pgMetrics = metrics as PostgresMetrics | null;

    // Maintenance operations
    const { runMaintenance, isLoading: maintenanceLoading } = usePostgresMaintenance(database.uuid);

    const handleVacuum = async () => {
        addToast('info', 'Running VACUUM...');
        const success = await runMaintenance('vacuum');
        if (success) {
            addToast('success', 'VACUUM completed successfully');
        } else {
            addToast('error', 'VACUUM failed');
        }
    };

    const handleAnalyze = async () => {
        addToast('info', 'Running ANALYZE...');
        const success = await runMaintenance('analyze');
        if (success) {
            addToast('success', 'ANALYZE completed successfully');
        } else {
            addToast('error', 'ANALYZE failed');
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Maintenance</h3>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-foreground">VACUUM Database</p>
                                <p className="text-sm text-foreground-muted">Reclaim storage and update statistics</p>
                            </div>
                            <Button size="sm" variant="secondary" onClick={handleVacuum} disabled={maintenanceLoading}>
                                {maintenanceLoading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Play className="mr-2 h-4 w-4" />
                                )}
                                Run VACUUM
                            </Button>
                        </div>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-foreground">ANALYZE Database</p>
                                <p className="text-sm text-foreground-muted">Update query planner statistics</p>
                            </div>
                            <Button size="sm" variant="secondary" onClick={handleAnalyze} disabled={maintenanceLoading}>
                                {maintenanceLoading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Play className="mr-2 h-4 w-4" />
                                )}
                                Run ANALYZE
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Connections</label>
                            <p className="text-sm text-foreground">{pgMetrics?.maxConnections || 'N/A'}</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Active Connections</label>
                            <p className="text-sm text-foreground">{formatMetricValue(pgMetrics?.activeConnections)}</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Database Size</label>
                            <p className="text-sm text-foreground">{pgMetrics?.databaseSize || 'N/A'}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function LogsTab({ database }: { database: StandaloneDatabase }) {
    // Fetch logs from API
    const { logs, isLoading, refetch } = useDatabaseLogs({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const getLevelVariant = (level: string): 'default' | 'secondary' | 'danger' => {
        switch (level.toUpperCase()) {
            case 'ERROR':
            case 'FATAL':
            case 'PANIC':
                return 'danger';
            case 'WARNING':
                return 'default';
            default:
                return 'secondary';
        }
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Recent Logs</h3>
                    <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>
                {isLoading && logs.length === 0 ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading logs...</span>
                    </div>
                ) : logs.length === 0 ? (
                    <p className="py-4 text-center text-foreground-muted">No logs available</p>
                ) : (
                    <div className="space-y-2">
                        {logs.map((log, index) => (
                            <div
                                key={index}
                                className="rounded-lg border border-border bg-background-secondary p-3 font-mono text-sm"
                            >
                                <div className="flex items-start gap-3">
                                    <span className="text-foreground-muted">{log.timestamp}</span>
                                    <Badge variant={getLevelVariant(log.level)}>
                                        {log.level}
                                    </Badge>
                                    <span className="flex-1 text-foreground">{log.message}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ConnectionField({ label, value, onCopy, copied }: { label: string; value: string; onCopy: () => void; copied: boolean }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                {label}
            </label>
            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                <code className="flex-1 font-mono text-sm text-foreground">{value}</code>
                <button
                    onClick={onCopy}
                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                >
                    <Copy className="h-4 w-4" />
                </button>
            </div>
            {copied && (
                <p className="mt-2 text-sm text-success">Copied to clipboard!</p>
            )}
        </div>
    );
}
