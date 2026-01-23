import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, FileText, Settings, RefreshCw, Eye, EyeOff, Copy, Layers, TrendingUp } from 'lucide-react';
import { useDatabaseMetrics, formatMetricValue, type MongoMetrics } from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

export function MongoDBPanel({ database }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
        { label: 'Collections', content: <CollectionsTab database={database} /> },
        { label: 'Indexes', content: <IndexesTab database={database} /> },
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

    const mongoMetrics = metrics as MongoMetrics | null;

    // Connection details from backend
    const connectionDetails = {
        host: database.connection?.internal_host || '',
        port: database.connection?.port || '27017',
        database: database.mongo_initdb_database || database.name,
        username: database.mongo_initdb_root_username || database.connection?.username || '',
        password: database.mongo_initdb_root_password || database.connection?.password || '',
        connectionString: database.internal_db_url || '',
    };

    const stats = [
        { label: 'Collections', value: isLoading ? '...' : formatMetricValue(mongoMetrics?.collections) },
        { label: 'Documents', value: isLoading ? '...' : formatMetricValue(mongoMetrics?.documents) },
        { label: 'Database Size', value: isLoading ? '...' : (mongoMetrics?.databaseSize || 'N/A') },
        { label: 'Index Size', value: isLoading ? '...' : (mongoMetrics?.indexSize || 'N/A') },
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

function CollectionsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [collections] = useState([
        { name: 'users', documentCount: 12453, size: '1.2 GB', avgDocSize: '96 KB' },
        { name: 'posts', documentCount: 8932, size: '845 MB', avgDocSize: '94 KB' },
        { name: 'comments', documentCount: 23549, size: '654 MB', avgDocSize: '28 KB' },
        { name: 'sessions', documentCount: 300, size: '24 MB', avgDocSize: '80 KB' },
    ]);

    const handleViewCollection = (name: string) => {
        addToast('info', `Opening collection: ${name}`);
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Collections Browser</h3>
                    <Button size="sm" variant="secondary">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
                <div className="space-y-3">
                    {collections.map((collection) => (
                        <div
                            key={collection.name}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                        >
                            <div className="flex items-center gap-4">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <Layers className="h-5 w-5 text-success" />
                                </div>
                                <div className="flex-1">
                                    <p className="font-medium text-foreground">{collection.name}</p>
                                    <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                        <span>{collection.documentCount.toLocaleString()} docs</span>
                                        <span>{collection.size}</span>
                                        <span>Avg: {collection.avgDocSize}</span>
                                    </div>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => handleViewCollection(collection.name)}
                            >
                                <Eye className="mr-2 h-4 w-4" />
                                View
                            </Button>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function IndexesTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const [indexes] = useState([
        { collection: 'users', name: '_id_', fields: ['_id'], unique: true, size: '124 MB' },
        { collection: 'users', name: 'email_1', fields: ['email'], unique: true, size: '89 MB' },
        { collection: 'posts', name: '_id_', fields: ['_id'], unique: true, size: '98 MB' },
        { collection: 'posts', name: 'user_id_1', fields: ['user_id'], unique: false, size: '45 MB' },
        { collection: 'comments', name: '_id_', fields: ['_id'], unique: true, size: '67 MB' },
        { collection: 'comments', name: 'post_id_1', fields: ['post_id'], unique: false, size: '34 MB' },
    ]);

    const handleCreateIndex = () => {
        addToast('info', 'Create index functionality coming soon');
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Index Management</h3>
                    <Button size="sm" onClick={handleCreateIndex}>
                        <TrendingUp className="mr-2 h-4 w-4" />
                        Create Index
                    </Button>
                </div>
                <div className="space-y-3">
                    {indexes.map((index, i) => (
                        <div
                            key={i}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                        >
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-foreground">{index.collection}.{index.name}</p>
                                    {index.unique && <Badge variant="default">Unique</Badge>}
                                </div>
                                <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                    <span>Fields: {index.fields.join(', ')}</span>
                                    <span>Size: {index.size}</span>
                                </div>
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
    const [replicaSet] = useState({
        enabled: true,
        name: 'rs0',
        members: [
            { host: 'mongo1.example.com:27017', state: 'PRIMARY' },
            { host: 'mongo2.example.com:27017', state: 'SECONDARY' },
            { host: 'mongo3.example.com:27017', state: 'SECONDARY' },
        ],
    });

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Replica Set Status</h3>
                    {replicaSet.enabled ? (
                        <div>
                            <div className="mb-4">
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                    Replica Set Name
                                </label>
                                <p className="text-sm text-foreground">{replicaSet.name}</p>
                            </div>
                            <div className="space-y-2">
                                <label className="mb-2 block text-sm font-medium text-foreground-muted">
                                    Members
                                </label>
                                {replicaSet.members.map((member, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                                    >
                                        <code className="font-mono text-sm text-foreground">{member.host}</code>
                                        <Badge variant={member.state === 'PRIMARY' ? 'default' : 'secondary'}>
                                            {member.state}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-border bg-background-secondary p-6 text-center">
                            <p className="text-sm text-foreground-muted">Replica set not configured</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Storage Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Storage Engine</label>
                            <p className="text-sm text-foreground">WiredTiger</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Cache Size</label>
                            <p className="text-sm text-foreground">1 GB</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Journal Enabled</label>
                            <p className="text-sm text-foreground">Yes</p>
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
        { timestamp: '2024-01-03 10:24:12', level: 'INFO', message: 'Replica set election completed' },
        { timestamp: '2024-01-03 10:25:33', level: 'WARNING', message: 'Slow query detected: db.users.find() (1.2s)' },
        { timestamp: '2024-01-03 10:26:01', level: 'INFO', message: 'Index build completed: users.email_1' },
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
