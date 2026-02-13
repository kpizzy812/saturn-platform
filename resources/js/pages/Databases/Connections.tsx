import { useState, useEffect, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Checkbox, Modal, ModalFooter } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    ArrowLeft,
    Database,
    Copy,
    Eye,
    EyeOff,
    RefreshCw,
    Settings,
    Users,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Globe,
    Server,
    Info,
} from 'lucide-react';
import { BrandIcon } from '@/components/ui/BrandIcon';
import type { StandaloneDatabase, DatabaseType } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

interface ActiveConnection {
    id: number;
    pid: number;
    user: string;
    database: string;
    state: 'active' | 'idle' | 'idle in transaction';
    query: string;
    duration: string;
    clientAddr: string;
}

const databaseTypeConfig: Record<DatabaseType, { color: string; bgColor: string; displayName: string }> = {
    postgresql: { color: 'text-blue-500', bgColor: 'bg-blue-500/10', displayName: 'PostgreSQL' },
    mysql: { color: 'text-orange-500', bgColor: 'bg-orange-500/10', displayName: 'MySQL' },
    mariadb: { color: 'text-orange-600', bgColor: 'bg-orange-600/10', displayName: 'MariaDB' },
    mongodb: { color: 'text-green-500', bgColor: 'bg-green-500/10', displayName: 'MongoDB' },
    redis: { color: 'text-red-500', bgColor: 'bg-red-500/10', displayName: 'Redis' },
    keydb: { color: 'text-red-600', bgColor: 'bg-red-600/10', displayName: 'KeyDB' },
    dragonfly: { color: 'text-purple-500', bgColor: 'bg-purple-500/10', displayName: 'Dragonfly' },
    clickhouse: { color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', displayName: 'ClickHouse' },
};

export default function DatabaseConnections({ database }: Props) {
    const config = databaseTypeConfig[database.database_type] || databaseTypeConfig.postgresql;
    const [showPassword, setShowPassword] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);
    const [maxConnections, setMaxConnections] = useState(database.connection_pool_max || 100);
    const [poolingEnabled, setPoolingEnabled] = useState(database.connection_pool_enabled || false);
    const [poolSize, setPoolSize] = useState(database.connection_pool_size || 20);
    const [showKillModal, setShowKillModal] = useState(false);
    const [connectionToKill, setConnectionToKill] = useState<ActiveConnection | null>(null);
    const { addToast } = useToast();

    // Use real connection details from backend
    const conn = database.connection;
    const connectionDetails = {
        host: conn?.external_host || 'localhost',
        internalHost: conn?.internal_host || database.uuid,
        port: conn?.port || getDefaultPort(database.database_type),
        publicPort: conn?.public_port,
        database: conn?.database || database.name,
        username: conn?.username || 'root',
        password: conn?.password || '',
    };

    // Use real URLs from backend if available
    const internalUrl = database.internal_db_url;
    const externalUrl = database.external_db_url;

    // Fallback connection string if external_db_url not provided
    const connectionString = externalUrl ||
        `${database.database_type}://${connectionDetails.username}:${connectionDetails.password}@${connectionDetails.host}:${connectionDetails.publicPort || connectionDetails.port}/${connectionDetails.database}`;

    // Fetch active connections from backend
    const [activeConnections, setActiveConnections] = useState<ActiveConnection[]>([]);
    const [connectionsLoading, setConnectionsLoading] = useState(true);

    const fetchConnections = useCallback(async () => {
        setConnectionsLoading(true);
        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/connections`);
            if (!response.ok) {
                setActiveConnections([]);
                return;
            }
            const data = await response.json();
            if (data.available && data.connections) {
                setActiveConnections(data.connections);
            } else {
                setActiveConnections([]);
            }
        } catch {
            setActiveConnections([]);
        } finally {
            setConnectionsLoading(false);
        }
    }, [database.uuid]);

    // Fetch on mount
    useEffect(() => {
        fetchConnections();
    }, [fetchConnections]);

    const copyToClipboard = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        setTimeout(() => setCopiedField(null), 2000);
    };

    const generateEnvFormat = () => {
        const envVarName = getEnvVarName(database.database_type);
        const lines = [];

        // Add the main connection URL (recommended way)
        if (internalUrl) {
            lines.push(`# Internal connection (recommended for apps in same environment)`);
            lines.push(`${envVarName}=${internalUrl}`);
            lines.push('');
        }

        // Add individual connection details
        lines.push(`# Individual connection details`);
        lines.push(`DB_HOST=${connectionDetails.internalHost}`);
        lines.push(`DB_PORT=${connectionDetails.port}`);
        lines.push(`DB_DATABASE=${connectionDetails.database || ''}`);
        lines.push(`DB_USERNAME=${connectionDetails.username || ''}`);
        lines.push(`DB_PASSWORD=${connectionDetails.password || ''}`);

        return lines.join('\n');
    };

    // Get the appropriate env var name for the database type
    const getEnvVarName = (dbType: DatabaseType): string => {
        switch (dbType) {
            case 'postgresql':
            case 'mysql':
            case 'mariadb':
                return 'DATABASE_URL';
            case 'redis':
            case 'keydb':
            case 'dragonfly':
                return 'REDIS_URL';
            case 'mongodb':
                return 'MONGODB_URL';
            case 'clickhouse':
                return 'CLICKHOUSE_URL';
            default:
                return 'DATABASE_URL';
        }
    };

    const handleSaveSettings = () => {
        router.patch(`/databases/${database.uuid}`, {
            connection_pool_enabled: poolingEnabled,
            connection_pool_size: poolSize,
            connection_pool_max: maxConnections,
        }, {
            preserveScroll: true,
            onSuccess: () => addToast('success', 'Connection pooling settings saved successfully!'),
            onError: () => addToast('error', 'Failed to save connection settings'),
        });
    };

    const handleKillConnection = (connection: ActiveConnection) => {
        setConnectionToKill(connection);
        setShowKillModal(true);
    };

    const confirmKillConnection = async () => {
        if (connectionToKill) {
            try {
                const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(`/_internal/databases/${database.uuid}/connections/kill`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ pid: connectionToKill.pid }),
                });
                if (!response.ok) {
                    addToast('error', `Server returned ${response.status}`);
                    setShowKillModal(false);
                    setConnectionToKill(null);
                    return;
                }
                const data = await response.json();
                if (data.success) {
                    addToast('success', `Killed connection PID ${connectionToKill.pid}`);
                    fetchConnections(); // Refresh connections list
                } else {
                    addToast('error', data.error || 'Failed to kill connection');
                }
            } catch {
                addToast('error', 'Failed to kill connection');
            }
            setShowKillModal(false);
            setConnectionToKill(null);
        }
    };

    return (
        <AppLayout
            title={`${database.name} - Connections`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Connections' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Database
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${config.bgColor} ${config.color}`}>
                        <BrandIcon name={database.database_type} className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Connection Management</h1>
                        <p className="text-foreground-muted">Manage connection strings and active connections</p>
                    </div>
                </div>
            </div>

            {/* Internal URL - Railway-like Experience */}
            {internalUrl && (
                <Card className="mb-6 border-green-500/30 bg-green-500/5">
                    <CardContent className="p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-green-500/20">
                                <Server className="h-5 w-5 text-green-500" />
                            </div>
                            <div className="flex-1">
                                <div className="mb-2 flex items-center gap-2">
                                    <h3 className="text-lg font-medium text-foreground">Internal URL</h3>
                                    <Badge variant="success" size="sm">Recommended</Badge>
                                </div>
                                <p className="mb-4 text-sm text-foreground-muted">
                                    Use this URL to connect from other apps in the same environment. It's automatically injected as DATABASE_URL when you link resources.
                                </p>
                                <div className="flex items-center gap-2 rounded-lg border border-green-500/30 bg-background p-3">
                                    <code className="flex-1 font-mono text-sm text-foreground break-all">
                                        {internalUrl}
                                    </code>
                                    <button
                                        onClick={() => copyToClipboard(internalUrl, 'internalUrl')}
                                        className="flex-shrink-0 rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                        title="Copy internal URL"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                                {copiedField === 'internalUrl' && (
                                    <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
                                )}
                                <div className="mt-3 flex items-start gap-2 rounded-lg bg-background-secondary p-3">
                                    <Info className="h-4 w-4 flex-shrink-0 text-blue-400 mt-0.5" />
                                    <p className="text-xs text-foreground-muted">
                                        This URL uses the container hostname and is only accessible from within the Docker network.
                                        Apps deployed in the same environment can connect using this URL.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* External URL - for external access */}
            {externalUrl && connectionDetails.publicPort && (
                <Card className="mb-6 border-blue-500/30">
                    <CardContent className="p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-500/20">
                                <Globe className="h-5 w-5 text-blue-500" />
                            </div>
                            <div className="flex-1">
                                <h3 className="mb-2 text-lg font-medium text-foreground">External URL</h3>
                                <p className="mb-4 text-sm text-foreground-muted">
                                    Use this URL to connect from outside the Docker network (local development, external tools).
                                </p>
                                <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                    <code className="flex-1 font-mono text-sm text-foreground break-all">
                                        {externalUrl}
                                    </code>
                                    <button
                                        onClick={() => copyToClipboard(externalUrl, 'externalUrl')}
                                        className="flex-shrink-0 rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                        title="Copy external URL"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                                {copiedField === 'externalUrl' && (
                                    <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Connection String */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection String</h3>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground-muted">
                                Full Connection String
                            </label>
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <code className="flex-1 font-mono text-sm text-foreground break-all">
                                    {connectionString}
                                </code>
                                <button
                                    onClick={() => copyToClipboard(connectionString, 'connectionString')}
                                    className="flex-shrink-0 rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    title="Copy connection string"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            {copiedField === 'connectionString' && (
                                <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground-muted">
                                Environment Variables Format
                            </label>
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <code className="flex-1 font-mono text-sm text-foreground whitespace-pre">
                                    {generateEnvFormat()}
                                </code>
                                <button
                                    onClick={() => copyToClipboard(generateEnvFormat(), 'env')}
                                    className="flex-shrink-0 self-start rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    title="Copy environment variables"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            {copiedField === 'env' && (
                                <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Connection Details */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Connection Details</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <ConnectionField
                            label="Internal Host (Docker network)"
                            value={connectionDetails.internalHost}
                            onCopy={() => copyToClipboard(connectionDetails.internalHost, 'internalHost')}
                            copied={copiedField === 'internalHost'}
                        />
                        <ConnectionField
                            label="External Host"
                            value={connectionDetails.host}
                            onCopy={() => copyToClipboard(connectionDetails.host, 'host')}
                            copied={copiedField === 'host'}
                        />
                        <ConnectionField
                            label="Internal Port"
                            value={connectionDetails.port}
                            onCopy={() => copyToClipboard(connectionDetails.port, 'port')}
                            copied={copiedField === 'port'}
                        />
                        {connectionDetails.publicPort && (
                            <ConnectionField
                                label="Public Port"
                                value={String(connectionDetails.publicPort)}
                                onCopy={() => copyToClipboard(String(connectionDetails.publicPort), 'publicPort')}
                                copied={copiedField === 'publicPort'}
                            />
                        )}
                        <ConnectionField
                            label="Database"
                            value={connectionDetails.database || '-'}
                            onCopy={() => copyToClipboard(connectionDetails.database || '', 'database')}
                            copied={copiedField === 'database'}
                        />
                        <ConnectionField
                            label="Username"
                            value={connectionDetails.username || '-'}
                            onCopy={() => copyToClipboard(connectionDetails.username || '', 'username')}
                            copied={copiedField === 'username'}
                        />
                        <div className="md:col-span-2">
                            <label className="mb-2 block text-sm font-medium text-foreground-muted">
                                Password
                            </label>
                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                <code className="flex-1 font-mono text-sm text-foreground">
                                    {showPassword ? connectionDetails.password : '••••••••••••••••••••'}
                                </code>
                                <button
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    title={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => copyToClipboard(connectionDetails.password, 'password')}
                                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    title="Copy password"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                            {copiedField === 'password' && (
                                <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Connection Pooling Settings */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">Connection Pooling</h3>
                        <Settings className="h-5 w-5 text-foreground-muted" />
                    </div>
                    <div className="space-y-4">
                        <Checkbox
                            label="Enable connection pooling"
                            checked={poolingEnabled}
                            onChange={(e) => setPoolingEnabled(e.target.checked)}
                        />
                        {poolingEnabled && (
                            <>
                                <Input
                                    type="number"
                                    label="Pool Size"
                                    value={poolSize}
                                    onChange={(e) => setPoolSize(parseInt(e.target.value) || 0)}
                                    hint="Maximum number of connections in the pool"
                                />
                                <Input
                                    type="number"
                                    label="Max Connections"
                                    value={maxConnections}
                                    onChange={(e) => setMaxConnections(parseInt(e.target.value) || 0)}
                                    hint="Maximum number of concurrent connections allowed"
                                />
                            </>
                        )}
                        <div className="flex justify-end">
                            <Button onClick={handleSaveSettings}>
                                Save Settings
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Active Connections */}
            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium text-foreground">Active Connections</h3>
                            <p className="text-sm text-foreground-muted">
                                {activeConnections.length} active connection{activeConnections.length !== 1 ? 's' : ''}
                            </p>
                        </div>
                        <Button variant="secondary" size="sm" onClick={fetchConnections} disabled={connectionsLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${connectionsLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border">
                                <tr>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">PID</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">User</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">Database</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">State</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">Query</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">Duration</th>
                                    <th className="pb-3 text-left text-xs font-semibold text-foreground-muted">Client</th>
                                    <th className="pb-3 text-right text-xs font-semibold text-foreground-muted">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/50">
                                {activeConnections.map((connection) => (
                                    <tr key={connection.id} className="transition-colors hover:bg-background-secondary/50">
                                        <td className="py-3 font-mono text-sm text-foreground">{connection.pid}</td>
                                        <td className="py-3 text-sm text-foreground">{connection.user}</td>
                                        <td className="py-3 font-mono text-sm text-foreground-muted">{connection.database}</td>
                                        <td className="py-3">
                                            <ConnectionStateBadge state={connection.state} />
                                        </td>
                                        <td className="py-3 max-w-xs">
                                            <code className="block font-mono text-xs text-foreground-muted line-clamp-1">
                                                {connection.query}
                                            </code>
                                        </td>
                                        <td className="py-3 text-sm text-foreground-muted">{connection.duration}</td>
                                        <td className="py-3 font-mono text-xs text-foreground-muted">{connection.clientAddr}</td>
                                        <td className="py-3 text-right">
                                            <Button
                                                variant="danger"
                                                size="sm"
                                                onClick={() => handleKillConnection(connection)}
                                            >
                                                Kill
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Kill Connection Modal */}
            <Modal
                isOpen={showKillModal}
                onClose={() => setShowKillModal(false)}
                title="Kill Connection"
                description="Are you sure you want to kill this connection? This action cannot be undone."
            >
                {connectionToKill && (
                    <div className="space-y-3 rounded-lg border border-border bg-background-secondary p-4">
                        <div className="flex justify-between">
                            <span className="text-sm text-foreground-muted">PID:</span>
                            <span className="font-mono text-sm text-foreground">{connectionToKill.pid}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-foreground-muted">User:</span>
                            <span className="text-sm text-foreground">{connectionToKill.user}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-foreground-muted">State:</span>
                            <ConnectionStateBadge state={connectionToKill.state} />
                        </div>
                    </div>
                )}
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowKillModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={confirmKillConnection}>
                        Kill Connection
                    </Button>
                </ModalFooter>
            </Modal>
        </AppLayout>
    );
}

function getDefaultPort(databaseType: DatabaseType): string {
    const ports: Record<DatabaseType, string> = {
        postgresql: '5432',
        mysql: '3306',
        mariadb: '3306',
        mongodb: '27017',
        redis: '6379',
        keydb: '6379',
        dragonfly: '6379',
        clickhouse: '9000',
    };
    return ports[databaseType];
}

interface ConnectionFieldProps {
    label: string;
    value: string;
    onCopy: () => void;
    copied: boolean;
}

function ConnectionField({ label, value, onCopy, copied }: ConnectionFieldProps) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-foreground-muted">
                {label}
            </label>
            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                <code className="flex-1 font-mono text-sm text-foreground">{value}</code>
                <button
                    onClick={onCopy}
                    className="rounded p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                    title={`Copy ${label.toLowerCase()}`}
                >
                    <Copy className="h-4 w-4" />
                </button>
            </div>
            {copied && (
                <p className="mt-2 text-sm text-green-500">Copied to clipboard!</p>
            )}
        </div>
    );
}

function ConnectionStateBadge({ state }: { state: 'active' | 'idle' | 'idle in transaction' }) {
    const variants = {
        active: { icon: CheckCircle2, color: 'text-green-500', bg: 'bg-green-500/10', label: 'Active' },
        idle: { icon: XCircle, color: 'text-foreground-muted', bg: 'bg-foreground-muted/10', label: 'Idle' },
        'idle in transaction': { icon: AlertTriangle, color: 'text-yellow-500', bg: 'bg-yellow-500/10', label: 'In Transaction' },
    };

    const variant = variants[state];
    const Icon = variant.icon;

    return (
        <div className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 ${variant.bg}`}>
            <Icon className={`h-3 w-3 ${variant.color}`} />
            <span className={`text-xs font-medium ${variant.color}`}>{variant.label}</span>
        </div>
    );
}
