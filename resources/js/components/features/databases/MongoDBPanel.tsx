import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { RefreshCw, Eye, EyeOff, Copy, Layers, TrendingUp, Loader2 } from 'lucide-react';
import {
    useDatabaseMetrics,
    useDatabaseLogs,
    formatMetricValue,
    useMongoCollections,
    useMongoIndexes,
    useMongoReplicaSet,
    useMongoStorageSettings,
    type MongoMetrics,
} from '@/hooks';
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

    // Fetch collections from API
    const { collections, isLoading, refetch } = useMongoCollections({
        uuid: database.uuid,
        autoRefresh: false,
    });

    const handleViewCollection = (name: string) => {
        addToast('info', `Opening collection: ${name}`);
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Collections Browser</h3>
                    <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading collections...</span>
                    </div>
                ) : collections.length === 0 ? (
                    <p className="py-4 text-center text-foreground-muted">No collections found</p>
                ) : (
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
                )}
            </CardContent>
        </Card>
    );
}

function IndexesTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();

    // Fetch indexes from API
    const { indexes, isLoading, refetch } = useMongoIndexes({
        uuid: database.uuid,
        autoRefresh: false,
    });

    const [showCreateForm, setShowCreateForm] = useState(false);
    const [indexCollection, setIndexCollection] = useState('');
    const [indexFields, setIndexFields] = useState('');
    const [indexUnique, setIndexUnique] = useState(false);
    const [isCreating, setIsCreating] = useState(false);

    const handleCreateIndex = async () => {
        if (!showCreateForm) {
            setShowCreateForm(true);
            return;
        }

        if (!indexCollection || !indexFields) {
            addToast('error', 'Collection and fields are required');
            return;
        }

        setIsCreating(true);
        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/mongodb/indexes/create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                },
                body: JSON.stringify({
                    collection: indexCollection,
                    fields: indexFields,
                    unique: indexUnique,
                }),
            });
            const data = await response.json();
            if (data.success) {
                addToast('success', data.message || 'Index created');
                setIndexCollection('');
                setIndexFields('');
                setIndexUnique(false);
                setShowCreateForm(false);
                refetch();
            } else {
                addToast('error', data.error || 'Failed to create index');
            }
        } catch {
            addToast('error', 'Failed to create index');
        } finally {
            setIsCreating(false);
        }
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Index Management</h3>
                    <div className="flex gap-2">
                        <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button size="sm" onClick={handleCreateIndex} disabled={isCreating}>
                            <TrendingUp className="mr-2 h-4 w-4" />
                            {showCreateForm ? (isCreating ? 'Creating...' : 'Save Index') : 'Create Index'}
                        </Button>
                        {showCreateForm && (
                            <Button size="sm" variant="secondary" onClick={() => { setShowCreateForm(false); setIndexCollection(''); setIndexFields(''); setIndexUnique(false); }}>
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>

                {showCreateForm && (
                    <div className="mb-4 rounded-lg border border-border bg-background-secondary p-4 space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-1">Collection</label>
                            <input
                                type="text"
                                value={indexCollection}
                                onChange={(e) => setIndexCollection(e.target.value)}
                                placeholder="users"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-1">Fields (field:direction)</label>
                            <input
                                type="text"
                                value={indexFields}
                                onChange={(e) => setIndexFields(e.target.value)}
                                placeholder="email:1,created_at:-1"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                            />
                            <p className="mt-1 text-xs text-foreground-muted">1 = ascending, -1 = descending</p>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input
                                type="checkbox"
                                checked={indexUnique}
                                onChange={(e) => setIndexUnique(e.target.checked)}
                                className="rounded border-border"
                            />
                            Unique index
                        </label>
                    </div>
                )}
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading indexes...</span>
                    </div>
                ) : indexes.length === 0 ? (
                    <p className="py-4 text-center text-foreground-muted">No indexes found</p>
                ) : (
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
                )}
            </CardContent>
        </Card>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    // Fetch replica set status from API
    const { replicaSet, isLoading: replicaLoading, refetch: refetchReplica } = useMongoReplicaSet({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    // Fetch storage settings from API
    const { settings: storageSettings, isLoading: storageLoading, refetch: refetchStorage } = useMongoStorageSettings({
        uuid: database.uuid,
        autoRefresh: false,
    });

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">Replica Set Status</h3>
                        <Button size="sm" variant="secondary" onClick={refetchReplica} disabled={replicaLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${replicaLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    {replicaLoading && !replicaSet ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-foreground-muted">Loading replica set status...</span>
                        </div>
                    ) : replicaSet?.enabled ? (
                        <div>
                            <div className="mb-4">
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                    Replica Set Name
                                </label>
                                <p className="text-sm text-foreground">{replicaSet.name || 'N/A'}</p>
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
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">Storage Settings</h3>
                        <Button size="sm" variant="secondary" onClick={refetchStorage} disabled={storageLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${storageLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    {storageLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-foreground-muted">Loading storage settings...</span>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                                <div>
                                    <p className="font-medium text-foreground">Storage Engine</p>
                                    <p className="text-sm text-foreground-muted">Document storage engine in use</p>
                                </div>
                                <Badge variant="secondary">{storageSettings?.storageEngine ?? 'WiredTiger'}</Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                                <div>
                                    <p className="font-medium text-foreground">Cache Size</p>
                                    <p className="text-sm text-foreground-muted">WiredTiger internal cache</p>
                                </div>
                                <span className="text-sm text-foreground">{storageSettings?.cacheSize ?? 'Default (50% RAM)'}</span>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                                <div>
                                    <p className="font-medium text-foreground">Journal</p>
                                    <p className="text-sm text-foreground-muted">Write-ahead logging for durability</p>
                                </div>
                                <Badge variant={storageSettings?.journalEnabled ? 'success' : 'secondary'}>
                                    {storageSettings?.journalEnabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                                <div>
                                    <p className="font-medium text-foreground">Directory Per DB</p>
                                    <p className="text-sm text-foreground-muted">Separate data directories per database</p>
                                </div>
                                <Badge variant={storageSettings?.directoryPerDb ? 'success' : 'secondary'}>
                                    {storageSettings?.directoryPerDb ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </div>
                    )}
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
