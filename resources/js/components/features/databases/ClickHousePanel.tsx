import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, FileText, Settings, RefreshCw, Eye, EyeOff, Copy, Activity, GitMerge } from 'lucide-react';
import { useDatabaseMetrics, formatMetricValue, type ClickhouseMetrics } from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

export function ClickHousePanel({ database }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
        { label: 'Query Log', content: <QueryLogTab database={database} /> },
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

    const clickhouseMetrics = metrics as ClickhouseMetrics | null;

    // Connection details from backend
    const connectionDetails = {
        host: database.connection?.internal_host || '',
        port: database.connection?.port || '9000',
        httpPort: '8123',
        database: database.name,
        username: database.clickhouse_admin_user || database.connection?.username || 'default',
        password: database.clickhouse_admin_password || database.connection?.password || '',
        connectionString: database.internal_db_url || '',
    };

    const stats = [
        { label: 'Total Tables', value: isLoading ? '...' : formatMetricValue(clickhouseMetrics?.totalTables) },
        { label: 'Total Rows', value: isLoading ? '...' : formatMetricValue(clickhouseMetrics?.totalRows) },
        { label: 'Database Size', value: isLoading ? '...' : (clickhouseMetrics?.databaseSize || 'N/A') },
        { label: 'Queries/sec', value: isLoading ? '...' : formatMetricValue(clickhouseMetrics?.queriesPerSec) },
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
                            label="Native Port"
                            value={connectionDetails.port}
                            onCopy={() => copyToClipboard(connectionDetails.port, 'port')}
                            copied={copiedField === 'port'}
                        />
                        <ConnectionField
                            label="HTTP Port"
                            value={connectionDetails.httpPort}
                            onCopy={() => copyToClipboard(connectionDetails.httpPort, 'httpPort')}
                            copied={copiedField === 'httpPort'}
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

            {/* Merge Status */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Merge Status</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Active Merges</p>
                            <p className="mt-1 text-xl font-bold text-foreground">3</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Parts Count</p>
                            <p className="mt-1 text-xl font-bold text-foreground">142</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Merge Rate</p>
                            <p className="mt-1 text-xl font-bold text-foreground">12/min</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function QueryLogTab({ database }: { database: StandaloneDatabase }) {
    const [queries] = useState([
        {
            query: 'SELECT count() FROM events WHERE date = today()',
            duration: '0.234s',
            rows: '1,234,567',
            timestamp: '2024-01-03 10:26:45',
            user: 'default'
        },
        {
            query: 'SELECT user_id, count() FROM clicks GROUP BY user_id',
            duration: '1.567s',
            rows: '45,234',
            timestamp: '2024-01-03 10:25:33',
            user: 'analytics'
        },
        {
            query: 'INSERT INTO events SELECT * FROM s3(...)',
            duration: '12.345s',
            rows: '5,000,000',
            timestamp: '2024-01-03 10:24:12',
            user: 'etl_user'
        },
    ]);

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Recent Queries</h3>
                    <Button size="sm" variant="secondary">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
                <div className="space-y-3">
                    {queries.map((query, index) => (
                        <div
                            key={index}
                            className="rounded-lg border border-border bg-background-secondary p-4"
                        >
                            <div className="mb-2 flex items-start justify-between">
                                <code className="flex-1 font-mono text-sm text-foreground">{query.query}</code>
                            </div>
                            <div className="flex items-center gap-4 text-sm text-foreground-muted">
                                <span>{query.timestamp}</span>
                                <Badge variant="secondary">{query.user}</Badge>
                                <span>Duration: {query.duration}</span>
                                <span>Rows: {query.rows}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [replication] = useState({
        enabled: true,
        replicas: [
            { host: 'ch-replica1.example.com', status: 'Healthy', delay: '0ms' },
            { host: 'ch-replica2.example.com', status: 'Healthy', delay: '2ms' },
        ],
    });

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Replication Status</h3>
                    {replication.enabled ? (
                        <div className="space-y-3">
                            {replication.replicas.map((replica, i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                                >
                                    <div className="flex-1">
                                        <code className="font-mono text-sm text-foreground">{replica.host}</code>
                                        <p className="mt-1 text-sm text-foreground-muted">Replication delay: {replica.delay}</p>
                                    </div>
                                    <Badge variant={replica.status === 'Healthy' ? 'default' : 'secondary'}>
                                        {replica.status}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-border bg-background-secondary p-6 text-center">
                            <p className="text-sm text-foreground-muted">Replication not configured</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Performance Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Threads</label>
                            <p className="text-sm text-foreground">16</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Memory Usage</label>
                            <p className="text-sm text-foreground">10 GB</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Concurrent Queries</label>
                            <p className="text-sm text-foreground">100</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Compression Method</label>
                            <p className="text-sm text-foreground">LZ4</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Merge Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Parts to Merge</label>
                            <p className="text-sm text-foreground">100</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Background Pool Size</label>
                            <p className="text-sm text-foreground">16</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Merge Tree Settings</label>
                            <p className="text-sm text-foreground">Replicated</p>
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
        { timestamp: '2024-01-03 10:24:12', level: 'INFO', message: 'Merge completed for table events' },
        { timestamp: '2024-01-03 10:25:33', level: 'WARNING', message: 'Query execution time exceeded threshold: 5.2s' },
        { timestamp: '2024-01-03 10:26:01', level: 'INFO', message: 'Replication synchronized with replica ch-replica1' },
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
