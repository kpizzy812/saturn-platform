import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, Users, Settings, FileText, Play, Trash2, RefreshCw, Eye, EyeOff, Copy } from 'lucide-react';
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
        { label: 'Active Connections', value: '12 / 100' },
        { label: 'Database Size', value: '2.4 GB' },
        { label: 'Queries/sec', value: '847' },
        { label: 'Cache Hit Ratio', value: '98.5%' },
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
    const [extensions] = useState([
        { name: 'pg_stat_statements', version: '1.10', enabled: true, description: 'Track execution statistics of SQL statements' },
        { name: 'pgcrypto', version: '1.3', enabled: true, description: 'Cryptographic functions' },
        { name: 'uuid-ossp', version: '1.1', enabled: true, description: 'Generate universally unique identifiers (UUIDs)' },
        { name: 'hstore', version: '1.8', enabled: false, description: 'Store sets of key/value pairs' },
        { name: 'pg_trgm', version: '1.6', enabled: false, description: 'Text similarity measurement and index searching' },
        { name: 'postgis', version: '3.3', enabled: false, description: 'PostGIS geometry and geography spatial types' },
    ]);

    const handleToggleExtension = (extensionName: string, currentlyEnabled: boolean) => {
        addToast('info', `${currentlyEnabled ? 'Disabling' : 'Enabling'} ${extensionName}...`);
        // In real app, make API call here
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">PostgreSQL Extensions</h3>
                    <Button size="sm" variant="secondary">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
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
                                <p className="mt-1 text-sm text-foreground-muted">{ext.description}</p>
                            </div>
                            <Button
                                size="sm"
                                variant={ext.enabled ? 'secondary' : 'default'}
                                onClick={() => handleToggleExtension(ext.name, ext.enabled)}
                            >
                                {ext.enabled ? 'Disable' : 'Enable'}
                            </Button>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function UsersTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [users] = useState([
        { name: 'postgres', role: 'Superuser', connections: 5 },
        { name: 'app_user', role: 'Standard', connections: 12 },
        { name: 'readonly', role: 'Read-only', connections: 3 },
    ]);

    const handleCreateUser = () => {
        addToast('info', 'Create user functionality coming soon');
    };

    const handleDeleteUser = (username: string) => {
        if (confirm(`Are you sure you want to delete user ${username}?`)) {
            addToast('info', `Deleting user ${username}...`);
        }
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Database Users</h3>
                    <Button size="sm" onClick={handleCreateUser}>
                        <Users className="mr-2 h-4 w-4" />
                        Create User
                    </Button>
                </div>
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
            </CardContent>
        </Card>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();

    const handleVacuum = () => {
        addToast('info', 'Running VACUUM...');
    };

    const handleAnalyze = () => {
        addToast('info', 'Running ANALYZE...');
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
                            <Button size="sm" variant="secondary" onClick={handleVacuum}>
                                <Play className="mr-2 h-4 w-4" />
                                Run VACUUM
                            </Button>
                        </div>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-foreground">ANALYZE Database</p>
                                <p className="text-sm text-foreground-muted">Update query planner statistics</p>
                            </div>
                            <Button size="sm" variant="secondary" onClick={handleAnalyze}>
                                <Play className="mr-2 h-4 w-4" />
                                Run ANALYZE
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection Pooling</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Connections</label>
                            <p className="text-sm text-foreground">100</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Pool Size</label>
                            <p className="text-sm text-foreground">20</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Idle Timeout</label>
                            <p className="text-sm text-foreground">10 minutes</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function LogsTab({ database }: { database: StandaloneDatabase }) {
    const [logs] = useState([
        { timestamp: '2024-01-03 10:23:45', level: 'INFO', message: 'Database started successfully' },
        { timestamp: '2024-01-03 10:24:12', level: 'INFO', message: 'Checkpoint completed' },
        { timestamp: '2024-01-03 10:25:33', level: 'WARNING', message: 'Connection pool near capacity (18/20)' },
        { timestamp: '2024-01-03 10:26:01', level: 'INFO', message: 'Query execution completed in 245ms' },
    ]);

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Recent Logs</h3>
                    <Button size="sm" variant="secondary">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
                <div className="space-y-2">
                    {logs.map((log, index) => (
                        <div
                            key={index}
                            className="rounded-lg border border-border bg-background-secondary p-3 font-mono text-sm"
                        >
                            <div className="flex items-start gap-3">
                                <span className="text-foreground-muted">{log.timestamp}</span>
                                <Badge variant={log.level === 'WARNING' ? 'default' : 'secondary'}>
                                    {log.level}
                                </Badge>
                                <span className="flex-1 text-foreground">{log.message}</span>
                            </div>
                        </div>
                    ))}
                </div>
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
