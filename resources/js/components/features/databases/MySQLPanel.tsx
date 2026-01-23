import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, Users, Settings, FileText, Play, Trash2, RefreshCw, Eye, EyeOff, Copy, ToggleLeft } from 'lucide-react';
import { useDatabaseMetrics, formatMetricValue, type MysqlMetrics } from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

export function MySQLPanel({ database }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
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

    const mysqlMetrics = metrics as MysqlMetrics | null;

    // Connection details from backend
    const connectionDetails = {
        host: database.connection?.internal_host || '',
        port: database.connection?.port || '3306',
        database: database.mysql_database || database.name,
        username: database.mysql_user || database.connection?.username || 'root',
        password: database.mysql_password || database.mysql_root_password || database.connection?.password || '',
        connectionString: database.internal_db_url || '',
    };

    const stats = [
        {
            label: 'Active Connections',
            value: isLoading ? '...' : formatMetricValue(
                mysqlMetrics?.activeConnections,
                ` / ${mysqlMetrics?.maxConnections || 150}`
            ),
        },
        {
            label: 'Database Size',
            value: isLoading ? '...' : (mysqlMetrics?.databaseSize || 'N/A'),
        },
        {
            label: 'Queries/sec',
            value: isLoading ? '...' : formatMetricValue(mysqlMetrics?.queriesPerSec),
        },
        {
            label: 'Slow Queries',
            value: isLoading ? '...' : formatMetricValue(mysqlMetrics?.slowQueries),
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

            {/* InnoDB Status */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">InnoDB Status</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Buffer Pool Size</p>
                            <p className="mt-1 text-xl font-bold text-foreground">128 MB</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Buffer Pool Usage</p>
                            <p className="mt-1 text-xl font-bold text-foreground">89%</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Page Reads/sec</p>
                            <p className="mt-1 text-xl font-bold text-foreground">234</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function UsersTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [users] = useState([
        { name: 'root', host: 'localhost', privileges: 'ALL PRIVILEGES' },
        { name: 'app_user', host: '%', privileges: 'SELECT, INSERT, UPDATE, DELETE' },
        { name: 'readonly', host: '%', privileges: 'SELECT' },
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
                            key={`${user.name}@${user.host}`}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                        >
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-foreground">{user.name}@{user.host}</p>
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Privileges: {user.privileges}
                                </p>
                            </div>
                            {user.name !== 'root' && (
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
    const [slowQueryLog, setSlowQueryLog] = useState(false);
    const [binaryLogging, setBinaryLogging] = useState(true);

    const handleToggleSlowQuery = () => {
        setSlowQueryLog(!slowQueryLog);
        addToast('info', `Slow query log ${!slowQueryLog ? 'enabled' : 'disabled'}`);
    };

    const handleToggleBinaryLogging = () => {
        setBinaryLogging(!binaryLogging);
        addToast('info', `Binary logging ${!binaryLogging ? 'enabled' : 'disabled'}`);
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Logging Settings</h3>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <p className="font-medium text-foreground">Slow Query Log</p>
                                <p className="text-sm text-foreground-muted">Log queries taking longer than 2 seconds</p>
                            </div>
                            <Button
                                size="sm"
                                variant={slowQueryLog ? 'default' : 'secondary'}
                                onClick={handleToggleSlowQuery}
                            >
                                <ToggleLeft className="mr-2 h-4 w-4" />
                                {slowQueryLog ? 'Enabled' : 'Disabled'}
                            </Button>
                        </div>
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <p className="font-medium text-foreground">Binary Logging</p>
                                <p className="text-sm text-foreground-muted">Required for replication and point-in-time recovery</p>
                            </div>
                            <Button
                                size="sm"
                                variant={binaryLogging ? 'default' : 'secondary'}
                                onClick={handleToggleBinaryLogging}
                            >
                                <ToggleLeft className="mr-2 h-4 w-4" />
                                {binaryLogging ? 'Enabled' : 'Disabled'}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Performance Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Connections</label>
                            <p className="text-sm text-foreground">150</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">InnoDB Buffer Pool Size</label>
                            <p className="text-sm text-foreground">128 MB</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Query Cache Size</label>
                            <p className="text-sm text-foreground">16 MB</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function LogsTab({ database }: { database: StandaloneDatabase }) {
    const [logs] = useState([
        { timestamp: '2024-01-03 10:23:45', level: 'INFO', message: 'Server started successfully' },
        { timestamp: '2024-01-03 10:24:12', level: 'INFO', message: 'InnoDB: Buffer pool(s) load completed' },
        { timestamp: '2024-01-03 10:25:33', level: 'WARNING', message: 'Slow query detected: SELECT * FROM large_table (2.5s)' },
        { timestamp: '2024-01-03 10:26:01', level: 'INFO', message: 'Binary log rotated' },
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
