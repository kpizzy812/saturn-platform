import { useState, useEffect } from 'react';
import { Card, CardContent, Button, Badge, Tabs, useConfirm, Modal } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Database, RefreshCw, Eye, EyeOff, Copy, Trash2, Key, HardDrive, Zap, Loader2, Plus, Edit2, Save, X } from 'lucide-react';
import {
    useDatabaseMetrics,
    useDatabaseLogs,
    formatMetricValue,
    useRedisKeys,
    useRedisMemory,
    useRedisFlush,
    useRedisPersistence,
    useRedisKeyValue,
    useRedisSetKeyValue,
    formatRdbSaveRules,
    type RedisMetrics,
    type RedisKeyValue,
} from '@/hooks';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
    initialTab?: number;
}

export function RedisPanel({ database, initialTab = 0 }: Props) {
    const tabs = [
        { label: 'Overview', content: <OverviewTab database={database} /> },
        { label: 'Keys', content: <KeysTab database={database} /> },
        { label: 'Settings', content: <SettingsTab database={database} /> },
        { label: 'Logs', content: <LogsTab database={database} /> },
    ];

    return <Tabs tabs={tabs} defaultIndex={initialTab} />;
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

            {/* Memory Info - from extended API */}
            <MemoryInfoCard database={database} />
        </div>
    );
}

function MemoryInfoCard({ database }: { database: StandaloneDatabase }) {
    const { memory, isLoading, refetch } = useRedisMemory({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    return (
        <Card>
            <CardContent className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-foreground">Memory Usage</h3>
                    <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>
                {isLoading && !memory ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading memory info...</span>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Used Memory</p>
                            <p className="mt-1 text-xl font-bold text-foreground">{memory?.usedMemory || 'N/A'}</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Peak Memory</p>
                            <p className="mt-1 text-xl font-bold text-foreground">{memory?.peakMemory || 'N/A'}</p>
                        </div>
                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                            <p className="text-sm text-foreground-muted">Fragmentation Ratio</p>
                            <p className="mt-1 text-xl font-bold text-foreground">{memory?.fragmentationRatio || 'N/A'}</p>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function KeysTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const confirm = useConfirm();
    const [pattern, setPattern] = useState('*');
    const [searchInput, setSearchInput] = useState('');
    const [selectedKey, setSelectedKey] = useState<string | null>(null);
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Fetch keys from API
    const { keys, isLoading, refetch } = useRedisKeys({
        uuid: database.uuid,
        pattern,
        limit: 100,
        autoRefresh: false,
    });

    const handleSearch = () => {
        setPattern(searchInput || '*');
    };

    const handleViewKey = (name: string) => {
        setSelectedKey(name);
    };

    const handleDeleteKey = async (name: string) => {
        const confirmed = await confirm({
            title: 'Delete Key',
            description: `Are you sure you want to delete key "${name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            try {
                const response = await fetch(`/_internal/databases/${database.uuid}/redis/keys/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                    },
                    body: JSON.stringify({ key: name }),
                });
                const data = await response.json();
                if (data.success) {
                    addToast('success', data.message || `Key "${name}" deleted`);
                    refetch();
                } else {
                    addToast('error', data.error || 'Failed to delete key');
                }
            } catch {
                addToast('error', 'Failed to delete key');
            }
        }
    };

    return (
        <>
            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">Key Browser</h3>
                        <div className="flex gap-2">
                            <Button size="sm" onClick={() => setShowCreateModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Create Key
                            </Button>
                            <Button size="sm" variant="secondary" onClick={refetch} disabled={isLoading}>
                                <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                        </div>
                    </div>
                    <div className="mb-4 flex gap-2">
                        <input
                            type="text"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                            placeholder="Search keys (e.g., user:* or cache:*)"
                            className="flex-1 rounded-lg border border-border bg-background-secondary px-4 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                        />
                        <Button size="sm" onClick={handleSearch}>
                            Search
                        </Button>
                    </div>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-foreground-muted">Loading keys...</span>
                        </div>
                    ) : keys.length === 0 ? (
                        <p className="py-4 text-center text-foreground-muted">No keys found matching pattern: {pattern}</p>
                    ) : (
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
                    )}
                </CardContent>
            </Card>

            {/* Key Value Viewer/Editor Modal */}
            {selectedKey && (
                <KeyValueModal
                    database={database}
                    keyName={selectedKey}
                    onClose={() => setSelectedKey(null)}
                    onSave={() => {
                        refetch();
                        setSelectedKey(null);
                    }}
                />
            )}

            {/* Create Key Modal */}
            {showCreateModal && (
                <CreateKeyModal
                    database={database}
                    onClose={() => setShowCreateModal(false)}
                    onCreated={() => {
                        refetch();
                        setShowCreateModal(false);
                    }}
                />
            )}
        </>
    );
}

// Key Value Viewer/Editor Modal Component
function KeyValueModal({
    database,
    keyName,
    onClose,
    onSave,
}: {
    database: StandaloneDatabase;
    keyName: string;
    onClose: () => void;
    onSave: () => void;
}) {
    const { addToast } = useToast();
    const { keyValue, isLoading, fetchKeyValue } = useRedisKeyValue(database.uuid);
    const { setKeyValue, isLoading: isSaving } = useRedisSetKeyValue(database.uuid);
    const [isEditing, setIsEditing] = useState(false);
    const [editedValue, setEditedValue] = useState<string>('');
    const [editedTtl, setEditedTtl] = useState<number>(-1);

    // Load key value on mount and when keyName changes
    useEffect(() => {
        const loadKey = async () => {
            const result = await fetchKeyValue(keyName);
            if (result) {
                setEditedTtl(result.ttl);
                if (result.type === 'string') {
                    setEditedValue(result.value as string);
                } else {
                    setEditedValue(JSON.stringify(result.value, null, 2));
                }
            }
        };
        loadKey();
    }, [fetchKeyValue, keyName]);

    const handleSave = async () => {
        if (!keyValue) return;

        let parsedValue: string | string[] | { member: string; score: string }[] | Record<string, string>;

        try {
            if (keyValue.type === 'string') {
                parsedValue = editedValue;
            } else {
                parsedValue = JSON.parse(editedValue);
            }
        } catch {
            addToast('error', 'Invalid JSON format');
            return;
        }

        const success = await setKeyValue(keyName, keyValue.type, parsedValue, editedTtl);
        if (success) {
            addToast('success', 'Key updated successfully');
            setIsEditing(false);
            onSave();
        } else {
            addToast('error', 'Failed to update key');
        }
    };

    const formatValue = (kv: RedisKeyValue): string => {
        if (kv.type === 'string') {
            return kv.value as string;
        }
        return JSON.stringify(kv.value, null, 2);
    };

    const getTypeColor = (type: string): string => {
        switch (type) {
            case 'string': return 'text-blue-500';
            case 'list': return 'text-green-500';
            case 'set': return 'text-purple-500';
            case 'zset': return 'text-orange-500';
            case 'hash': return 'text-pink-500';
            default: return 'text-foreground-muted';
        }
    };

    return (
        <Modal isOpen={true} onClose={onClose} title={`Key: ${keyName}`} size="lg">
            <div className="space-y-4">
                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                        <span className="ml-2 text-foreground-muted">Loading key value...</span>
                    </div>
                ) : keyValue ? (
                    <>
                        {/* Key Info */}
                        <div className="flex flex-wrap items-center gap-4 rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-foreground-muted">Type:</span>
                                <Badge variant="secondary" className={getTypeColor(keyValue.type)}>
                                    {keyValue.type}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-foreground-muted">Length:</span>
                                <span className="text-sm font-medium text-foreground">{keyValue.length}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-foreground-muted">TTL:</span>
                                {isEditing ? (
                                    <input
                                        type="number"
                                        value={editedTtl}
                                        onChange={(e) => setEditedTtl(parseInt(e.target.value) || -1)}
                                        className="w-24 rounded border border-border bg-background px-2 py-1 text-sm"
                                        placeholder="-1 for no expiry"
                                    />
                                ) : (
                                    <span className="text-sm font-medium text-foreground">
                                        {keyValue.ttl === -1 ? 'No expiry' : `${keyValue.ttl}s`}
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Value Editor/Viewer */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <label className="text-sm font-medium text-foreground">Value</label>
                                {!isEditing && (
                                    <Button size="sm" variant="secondary" onClick={() => {
                                        setEditedValue(formatValue(keyValue));
                                        setEditedTtl(keyValue.ttl);
                                        setIsEditing(true);
                                    }}>
                                        <Edit2 className="mr-1 h-3 w-3" />
                                        Edit
                                    </Button>
                                )}
                            </div>
                            {isEditing ? (
                                <textarea
                                    value={editedValue}
                                    onChange={(e) => setEditedValue(e.target.value)}
                                    rows={12}
                                    className="w-full rounded-lg border border-border bg-background-secondary p-3 font-mono text-sm text-foreground focus:border-primary focus:outline-none"
                                    placeholder="Enter value..."
                                />
                            ) : (
                                <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-background-secondary p-3 font-mono text-sm text-foreground">
                                    {formatValue(keyValue)}
                                </pre>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-2">
                            {isEditing ? (
                                <>
                                    <Button variant="secondary" onClick={() => setIsEditing(false)} disabled={isSaving}>
                                        <X className="mr-1 h-4 w-4" />
                                        Cancel
                                    </Button>
                                    <Button onClick={handleSave} disabled={isSaving}>
                                        {isSaving ? (
                                            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Save className="mr-1 h-4 w-4" />
                                        )}
                                        Save Changes
                                    </Button>
                                </>
                            ) : (
                                <Button variant="secondary" onClick={onClose}>
                                    Close
                                </Button>
                            )}
                        </div>
                    </>
                ) : (
                    <p className="py-4 text-center text-foreground-muted">Key not found or error loading value</p>
                )}
            </div>
        </Modal>
    );
}

// Create Key Modal Component
function CreateKeyModal({
    database,
    onClose,
    onCreated,
}: {
    database: StandaloneDatabase;
    onClose: () => void;
    onCreated: () => void;
}) {
    const { addToast } = useToast();
    const { setKeyValue, isLoading } = useRedisSetKeyValue(database.uuid);
    const [keyName, setKeyName] = useState('');
    const [keyType, setKeyType] = useState<'string' | 'list' | 'set' | 'zset' | 'hash'>('string');
    const [value, setValue] = useState('');
    const [ttl, setTtl] = useState<number>(-1);

    const handleCreate = async () => {
        if (!keyName.trim()) {
            addToast('error', 'Key name is required');
            return;
        }

        let parsedValue: string | string[] | { member: string; score: string }[] | Record<string, string>;

        try {
            if (keyType === 'string') {
                parsedValue = value;
            } else {
                parsedValue = JSON.parse(value || '[]');
            }
        } catch {
            addToast('error', 'Invalid JSON format for value');
            return;
        }

        const success = await setKeyValue(keyName, keyType, parsedValue, ttl);
        if (success) {
            addToast('success', `Key "${keyName}" created successfully`);
            onCreated();
        } else {
            addToast('error', 'Failed to create key');
        }
    };

    const getPlaceholder = (): string => {
        switch (keyType) {
            case 'string': return 'Enter string value';
            case 'list': return '["item1", "item2", "item3"]';
            case 'set': return '["member1", "member2", "member3"]';
            case 'zset': return '[{"member": "item1", "score": "1"}, {"member": "item2", "score": "2"}]';
            case 'hash': return '{"field1": "value1", "field2": "value2"}';
            default: return '';
        }
    };

    return (
        <Modal isOpen={true} onClose={onClose} title="Create New Key" size="default">
            <div className="space-y-4">
                {/* Key Name */}
                <div>
                    <label className="mb-1 block text-sm font-medium text-foreground">Key Name</label>
                    <input
                        type="text"
                        value={keyName}
                        onChange={(e) => setKeyName(e.target.value)}
                        placeholder="e.g., user:123 or cache:products"
                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                    />
                </div>

                {/* Key Type */}
                <div>
                    <label className="mb-1 block text-sm font-medium text-foreground">Type</label>
                    <select
                        value={keyType}
                        onChange={(e) => setKeyType(e.target.value as typeof keyType)}
                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                    >
                        <option value="string">String</option>
                        <option value="list">List</option>
                        <option value="set">Set</option>
                        <option value="zset">Sorted Set</option>
                        <option value="hash">Hash</option>
                    </select>
                </div>

                {/* Value */}
                <div>
                    <label className="mb-1 block text-sm font-medium text-foreground">Value</label>
                    <textarea
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        rows={6}
                        placeholder={getPlaceholder()}
                        className="w-full rounded-lg border border-border bg-background-secondary p-3 font-mono text-sm text-foreground focus:border-primary focus:outline-none"
                    />
                    {keyType !== 'string' && (
                        <p className="mt-1 text-xs text-foreground-muted">Enter value as JSON</p>
                    )}
                </div>

                {/* TTL */}
                <div>
                    <label className="mb-1 block text-sm font-medium text-foreground">TTL (seconds)</label>
                    <input
                        type="number"
                        value={ttl}
                        onChange={(e) => setTtl(parseInt(e.target.value) || -1)}
                        placeholder="-1 for no expiry"
                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                    />
                    <p className="mt-1 text-xs text-foreground-muted">Use -1 for no expiration</p>
                </div>

                {/* Actions */}
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" onClick={onClose} disabled={isLoading}>
                        Cancel
                    </Button>
                    <Button onClick={handleCreate} disabled={isLoading}>
                        {isLoading ? (
                            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                        ) : (
                            <Plus className="mr-1 h-4 w-4" />
                        )}
                        Create Key
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function SettingsTab({ database }: { database: StandaloneDatabase }) {
    const { addToast } = useToast();
    const confirm = useConfirm();

    // Fetch persistence settings from API
    const { persistence, isLoading: persistenceLoading, refetch: refetchPersistence } = useRedisPersistence({
        uuid: database.uuid,
        autoRefresh: false,
    });

    // Fetch memory info for performance settings
    const { memory, isLoading: memoryLoading } = useRedisMemory({
        uuid: database.uuid,
        autoRefresh: false,
    });

    // Flush operations
    const { flush, isLoading: flushLoading } = useRedisFlush(database.uuid);

    const handleFlushDB = async () => {
        const confirmed = await confirm({
            title: 'Flush Current Database',
            description: 'Are you sure you want to flush the current database? This will delete all keys in DB 0.',
            confirmText: 'Flush DB',
            variant: 'danger',
        });
        if (confirmed) {
            const success = await flush('db');
            if (success) {
                addToast('success', 'Database flushed successfully');
            } else {
                addToast('error', 'Failed to flush database');
            }
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
            const success = await flush('all');
            if (success) {
                addToast('success', 'All databases flushed successfully');
            } else {
                addToast('error', 'Failed to flush databases');
            }
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-foreground">Persistence Settings</h3>
                        <Button size="sm" variant="secondary" onClick={refetchPersistence} disabled={persistenceLoading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${persistenceLoading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    {persistenceLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-foreground-muted">Loading persistence settings...</span>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium text-foreground">RDB Snapshots</p>
                                        <p className="text-sm text-foreground-muted">
                                            {persistence?.rdbEnabled
                                                ? `Save rules: ${formatRdbSaveRules(persistence.rdbSaveRules)}`
                                                : 'Point-in-time snapshots disabled'}
                                        </p>
                                        {persistence?.rdbLastSaveTime && (
                                            <p className="mt-1 text-xs text-foreground-muted">
                                                Last save: {persistence.rdbLastSaveTime} ({persistence.rdbLastBgsaveStatus})
                                            </p>
                                        )}
                                    </div>
                                    <Badge variant={persistence?.rdbEnabled ? 'success' : 'secondary'}>
                                        {persistence?.rdbEnabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>
                            </div>
                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium text-foreground">AOF (Append-Only File)</p>
                                        <p className="text-sm text-foreground-muted">
                                            {persistence?.aofEnabled
                                                ? `Fsync policy: ${persistence.aofFsync}`
                                                : 'Write-ahead log disabled'}
                                        </p>
                                    </div>
                                    <Badge variant={persistence?.aofEnabled ? 'success' : 'secondary'}>
                                        {persistence?.aofEnabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Performance Settings</h3>
                    {memoryLoading ? (
                        <div className="flex items-center py-4">
                            <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-sm text-foreground-muted">Loading settings...</span>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">Max Memory</label>
                                <p className="text-sm text-foreground">{memory?.maxMemory || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">Eviction Policy</label>
                                <p className="text-sm text-foreground">{memory?.evictionPolicy || 'N/A'}</p>
                            </div>
                        </div>
                    )}
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
                            <Button variant="danger" size="sm" onClick={handleFlushDB} disabled={flushLoading}>
                                {flushLoading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Trash2 className="mr-2 h-4 w-4" />
                                )}
                                FLUSHDB
                            </Button>
                        </div>
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <p className="font-medium text-foreground">Flush All Databases</p>
                                <p className="text-sm text-foreground-muted">Delete all keys in ALL databases</p>
                            </div>
                            <Button variant="danger" size="sm" onClick={handleFlushAll} disabled={flushLoading}>
                                {flushLoading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Trash2 className="mr-2 h-4 w-4" />
                                )}
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
