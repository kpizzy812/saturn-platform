import { useState } from 'react';
import { Card, CardContent, Button, Badge, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, FileText, Settings, RefreshCw, Eye, EyeOff, Copy, Trash2, Key, HardDrive, Zap } from 'lucide-react';
import { useDatabaseMetrics, formatMetricValue, type RedisMetrics } from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

export function RedisPanel({ database }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
        { label: 'Keys', content: <KeysTab database={database} /> },
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

    const redisMetrics = metrics as RedisMetrics | null;

    // Connection details from backend
    const getRedisPassword = () => {
        if (database.database_type === 'redis') return database.redis_password;
        if (database.database_type === 'keydb') return database.keydb_password;
        if (database.database_type === 'dragonfly') return database.dragonfly_password;
        return database.connection?.password;
    };

    const connectionDetails = {
        host: database.connection?.internal_host || '',
        port: database.connection?.port || '6379',
        database: '0',
        password: getRedisPassword() || '',
        connectionString: database.internal_db_url || '',
    };

    const stats = [
        { label: 'Total Keys', value: isLoading ? '...' : formatMetricValue(redisMetrics?.totalKeys), icon: Key, color: 'text-info', bgColor: 'bg-info/10' },
        { label: 'Memory Used', value: isLoading ? '...' : (redisMetrics?.memoryUsed || 'N/A'), icon: HardDrive, color: 'text-warning', bgColor: 'bg-warning/10' },
        { label: 'Ops/sec', value: isLoading ? '...' : formatMetricValue(redisMetrics?.opsPerSec), icon: Zap, color: 'text-success', bgColor: 'bg-success/10' },
        { label: 'Hit Rate', value: isLoading ? '...' : (redisMetrics?.hitRate || 'N/A'), icon: Database, color: 'text-primary', bgColor: 'bg-primary/10' },
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
                                <div className="flex items-center gap-3">
                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${stat.bgColor}`}>
                                        <stat.icon className={`h-5 w-5 ${stat.color}`} />
                                    </div>
                                    <div>
                                        <p className="text-sm text-foreground-muted">{stat.label}</p>
                                        <p className="text-xl font-bold text-foreground">{stat.value}</p>
                                    </div>
                                </div>
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

            {/* Memory Info */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Memory Usage</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Used Memory</p>
                            <p className="mt-1 text-xl font-bold text-foreground">245 MB</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Peak Memory</p>
                            <p className="mt-1 text-xl font-bold text-foreground">312 MB</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Fragmentation Ratio</p>
                            <p className="mt-1 text-xl font-bold text-foreground">1.12</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function KeysTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const confirm = useConfirm();
    const [keys] = useState([
        { name: 'user:1234:session', type: 'string', ttl: '1h 23m', size: '2.4 KB' },
        { name: 'cache:posts:trending', type: 'list', ttl: '15m', size: '145 KB' },
        { name: 'queue:jobs', type: 'list', ttl: 'none', size: '89 KB' },
        { name: 'rate_limit:api:192.168.1.1', type: 'string', ttl: '5m', size: '64 B' },
        { name: 'leaderboard:global', type: 'zset', ttl: 'none', size: '234 KB' },
        { name: 'user:5678:profile', type: 'hash', ttl: 'none', size: '1.2 KB' },
    ]);

    const handleViewKey = (name: string) => {
        addToast('info', `Viewing key: ${name}`);
    };

    const handleDeleteKey = async (name: string) => {
        const confirmed = await confirm({
            title: 'Delete Key',
            description: `Are you sure you want to delete key "${name}"?`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            addToast('info', `Deleting key: ${name}`);
        }
    };

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Key Browser</h3>
                    <Button size="sm" variant="secondary">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
                <div className="mb-4">
                    <input
                        type="text"
                        placeholder="Search keys (e.g., user:* or cache:*)"
                        className="w-full rounded-lg border border-border bg-background-secondary px-4 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                    />
                </div>
                <div className="space-y-2">
                    {keys.map((key) => (
                        <div
                            key={key.name}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4"
                        >
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <code className="font-mono text-sm font-medium text-foreground">{key.name}</code>
                                    <Badge variant="secondary">{key.type}</Badge>
                                </div>
                                <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                    <span>TTL: {key.ttl}</span>
                                    <span>Size: {key.size}</span>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => handleViewKey(key.name)}
                                >
                                    <Eye className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="danger"
                                    onClick={() => handleDeleteKey(key.name)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
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
    const confirm = useConfirm();

    const handleFlushDB = async () => {
        const confirmed = await confirm({
            title: 'Flush Current Database',
            description: 'Are you sure you want to flush the current database? This will delete all keys in DB 0.',
            confirmText: 'Flush DB',
            variant: 'danger',
        });
        if (confirmed) {
            addToast('warning', 'Flushing current database...');
        }
    };

    const handleFlushAll = async () => {
        const confirmed = await confirm({
            title: 'Flush All Databases',
            description: 'Are you sure you want to flush ALL databases? This will delete ALL keys in ALL databases.',
            confirmText: 'Flush All',
            variant: 'danger',
        });
        if (confirmed) {
            addToast('warning', 'Flushing all databases...');
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Persistence Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">RDB Snapshots</label>
                            <p className="text-sm text-foreground">Enabled (save 900 1, 300 10, 60 10000)</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">AOF (Append-Only File)</label>
                            <p className="text-sm text-foreground">Enabled (appendfsync: everysec)</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Last Save</label>
                            <p className="text-sm text-foreground">2024-01-03 10:15:23 (45 minutes ago)</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Performance Settings</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Memory</label>
                            <p className="text-sm text-foreground">512 MB</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Eviction Policy</label>
                            <p className="text-sm text-foreground">allkeys-lru</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Connections</label>
                            <p className="text-sm text-foreground">10000</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-3 text-lg font-medium text-danger">Danger Zone</h3>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <p className="font-medium text-foreground">Flush Current Database</p>
                                <p className="text-sm text-foreground-muted">Delete all keys in the current database (DB 0)</p>
                            </div>
                            <Button variant="danger" size="sm" onClick={handleFlushDB}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                FLUSHDB
                            </Button>
                        </div>
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <p className="font-medium text-foreground">Flush All Databases</p>
                                <p className="text-sm text-foreground-muted">Delete all keys in ALL databases</p>
                            </div>
                            <Button variant="danger" size="sm" onClick={handleFlushAll}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                FLUSHALL
                            </Button>
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
        { timestamp: '2024-01-03 10:24:12', level: 'INFO', message: 'DB saved on disk' },
        { timestamp: '2024-01-03 10:25:33', level: 'WARNING', message: 'Memory usage at 85%' },
        { timestamp: '2024-01-03 10:26:01', level: 'INFO', message: 'Background saving started by pid 1234' },
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
