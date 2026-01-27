import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Checkbox, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    ArrowLeft,
    Database,
    Settings,
    Cpu,
    Shield,
    RotateCw,
    Trash2,
    AlertCircle,
    Save,
} from 'lucide-react';
import type { StandaloneDatabase, DatabaseType } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

const databaseTypeConfig: Record<DatabaseType, { color: string; bgColor: string; displayName: string; version: string }> = {
    postgresql: { color: 'text-blue-500', bgColor: 'bg-blue-500/10', displayName: 'PostgreSQL', version: '15.4' },
    mysql: { color: 'text-orange-500', bgColor: 'bg-orange-500/10', displayName: 'MySQL', version: '8.0.34' },
    mariadb: { color: 'text-orange-600', bgColor: 'bg-orange-600/10', displayName: 'MariaDB', version: '10.11.4' },
    mongodb: { color: 'text-green-500', bgColor: 'bg-green-500/10', displayName: 'MongoDB', version: '7.0.1' },
    redis: { color: 'text-red-500', bgColor: 'bg-red-500/10', displayName: 'Redis', version: '7.2.0' },
    keydb: { color: 'text-red-600', bgColor: 'bg-red-600/10', displayName: 'KeyDB', version: '6.3.4' },
    dragonfly: { color: 'text-purple-500', bgColor: 'bg-purple-500/10', displayName: 'Dragonfly', version: '1.12.0' },
    clickhouse: { color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', displayName: 'ClickHouse', version: '23.8.2' },
};

export default function DatabaseSettings({ database }: Props) {
    const config = databaseTypeConfig[database.database_type] || databaseTypeConfig.postgresql;
    const { addToast } = useToast();
    const confirm = useConfirm();

    // Parse CPU limit to slider value (0 = unlimited → show as 1 core)
    const parseCpuToSlider = (val: string | undefined) => {
        const n = parseFloat(val || '0');
        return n > 0 ? Math.round(n) : 2;
    };
    // Parse memory limit to GB slider (e.g. "0" → 4, "1073741824" → 1, "4g" → 4)
    const parseMemoryToSlider = (val: string | undefined) => {
        if (!val || val === '0') return 4;
        const num = parseInt(val);
        if (isNaN(num)) return 4;
        // If the value is in bytes (very large number), convert to GB
        if (num > 1000000) return Math.round(num / (1024 * 1024 * 1024));
        return num;
    };

    // State for settings - initialized from real backend data
    const [name, setName] = useState(database.name);
    const [description, setDescription] = useState(database.description || '');
    const [cpuAllocation, setCpuAllocation] = useState(parseCpuToSlider(database.limits_cpus));
    const [memoryAllocation, setMemoryAllocation] = useState(parseMemoryToSlider(database.limits_memory));
    const [storageSize, setStorageSize] = useState(50);
    const [autoScalingEnabled, setAutoScalingEnabled] = useState(false);
    const [sslEnabled, setSslEnabled] = useState(database.enable_ssl || false);
    const [allowedIps, setAllowedIps] = useState('0.0.0.0/0');
    const [hasChanges, setHasChanges] = useState(false);
    const [restartRequired, setRestartRequired] = useState(false);

    // Configuration parameters - parse from postgres_conf or use defaults
    const parsePostgresConf = (conf: string | null | undefined): Record<string, string> => {
        const defaults: Record<string, string> = {
            max_connections: '100',
            shared_buffers: '256MB',
            effective_cache_size: '1GB',
            maintenance_work_mem: '64MB',
            checkpoint_completion_target: '0.9',
            wal_buffers: '16MB',
            default_statistics_target: '100',
            random_page_cost: '1.1',
            effective_io_concurrency: '200',
            work_mem: '4MB',
        };
        if (!conf) return defaults;
        // Parse key=value pairs from postgres_conf string
        const lines = conf.split('\n').filter(l => l.trim() && !l.trim().startsWith('#'));
        for (const line of lines) {
            const [key, ...valueParts] = line.split('=');
            if (key && valueParts.length > 0) {
                const k = key.trim();
                if (k in defaults) {
                    defaults[k] = valueParts.join('=').trim().replace(/'/g, '');
                }
            }
        }
        return defaults;
    };

    const [configParams, setConfigParams] = useState(parsePostgresConf(database.postgres_conf));

    const handleSaveGeneral = () => {
        router.patch(`/databases/${database.uuid}`, {
            name,
            description,
        }, {
            onSuccess: () => {
                addToast('success', 'General settings saved successfully');
                setHasChanges(false);
            },
            onError: () => {
                addToast('error', 'Failed to save settings');
            },
        });
    };

    const handleSaveResources = () => {
        router.patch(`/databases/${database.uuid}`, {
            limits_cpus: String(cpuAllocation),
            limits_memory: `${memoryAllocation}g`,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                addToast('success', 'Resource settings saved successfully! Restart required to apply changes.');
                setHasChanges(false);
                setRestartRequired(true);
            },
            onError: () => {
                addToast('error', 'Failed to save resource settings');
            },
        });
    };

    const handleSaveConfiguration = () => {
        // Serialize config params to postgres_conf format
        const confLines = Object.entries(configParams)
            .map(([key, value]) => `${key} = '${value}'`)
            .join('\n');

        router.patch(`/databases/${database.uuid}`, {
            postgres_conf: confLines,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                addToast('success', 'Configuration saved successfully! Restart required to apply changes.');
                setHasChanges(false);
                setRestartRequired(true);
            },
            onError: () => {
                addToast('error', 'Failed to save configuration');
            },
        });
    };

    const handleSaveSecurity = () => {
        router.patch(`/databases/${database.uuid}`, {
            enable_ssl: sslEnabled,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                addToast('success', 'Security settings saved successfully');
                setHasChanges(false);
            },
            onError: () => {
                addToast('error', 'Failed to save security settings');
            },
        });
    };

    const handleRestart = async () => {
        const confirmed = await confirm({
            title: 'Restart Database',
            description: `Are you sure you want to restart ${database.name}? This will cause brief downtime.`,
            confirmText: 'Restart',
            variant: 'warning',
        });
        if (confirmed) {
            router.post(`/databases/${database.uuid}/restart`, {}, {
                onSuccess: () => {
                    addToast('success', 'Database restarting...');
                    setRestartRequired(false);
                },
                onError: () => {
                    addToast('error', 'Failed to restart database');
                },
            });
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Database',
            description: `Are you sure you want to delete ${database.name}? This action cannot be undone and all data will be lost.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            const confirmedAgain = await confirm({
                title: 'Final Confirmation',
                description: 'Please confirm again. All data will be permanently deleted!',
                confirmText: 'Delete Permanently',
                variant: 'danger',
            });
            if (confirmedAgain) {
                router.delete(`/databases/${database.uuid}`);
            }
        }
    };

    const handleConfigChange = (key: string, value: string) => {
        setConfigParams({ ...configParams, [key]: value });
        setHasChanges(true);
        setRestartRequired(true);
    };

    const tabs = [
        {
            label: 'General',
            content: (
                <div className="space-y-6">
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-foreground">General Information</h3>
                            <div className="space-y-4">
                                <Input
                                    label="Database Name"
                                    value={name}
                                    onChange={(e) => {
                                        setName(e.target.value);
                                        setHasChanges(true);
                                    }}
                                />
                                <Input
                                    label="Description"
                                    value={description}
                                    onChange={(e) => {
                                        setDescription(e.target.value);
                                        setHasChanges(true);
                                    }}
                                    placeholder="Optional description"
                                />
                                <InfoField label="Database Type" value={config.displayName} />
                                <InfoField label="Version" value={database.version || config.version} />
                                <InfoField label="Status" value={database.status} />
                                <InfoField label="Created" value={new Date(database.created_at).toLocaleDateString()} />
                                <InfoField label="Last Updated" value={new Date(database.updated_at).toLocaleDateString()} />
                            </div>
                        </CardContent>
                    </Card>
                    {hasChanges && (
                        <div className="flex justify-end">
                            <Button onClick={handleSaveGeneral}>
                                <Save className="mr-2 h-4 w-4" />
                                Save Changes
                            </Button>
                        </div>
                    )}
                </div>
            ),
        },
        {
            label: 'Resources',
            content: (
                <div className="space-y-6">
                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-foreground">Resource Allocation</h3>
                                <Cpu className="h-5 w-5 text-foreground-muted" />
                            </div>
                            <div className="space-y-6">
                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <label className="text-sm font-medium text-foreground">CPU Cores</label>
                                        <span className="text-sm font-medium text-foreground">{cpuAllocation} cores</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="1"
                                        max="16"
                                        step="1"
                                        value={cpuAllocation}
                                        onChange={(e) => {
                                            setCpuAllocation(parseInt(e.target.value));
                                            setHasChanges(true);
                                        }}
                                        className="w-full"
                                    />
                                    <div className="mt-1 flex justify-between text-xs text-foreground-muted">
                                        <span>1 core</span>
                                        <span>16 cores</span>
                                    </div>
                                </div>

                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <label className="text-sm font-medium text-foreground">Memory (RAM)</label>
                                        <span className="text-sm font-medium text-foreground">{memoryAllocation} GB</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="1"
                                        max="64"
                                        step="1"
                                        value={memoryAllocation}
                                        onChange={(e) => {
                                            setMemoryAllocation(parseInt(e.target.value));
                                            setHasChanges(true);
                                        }}
                                        className="w-full"
                                    />
                                    <div className="mt-1 flex justify-between text-xs text-foreground-muted">
                                        <span>1 GB</span>
                                        <span>64 GB</span>
                                    </div>
                                </div>

                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <label className="text-sm font-medium text-foreground">Storage</label>
                                        <span className="text-sm font-medium text-foreground">{storageSize} GB</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="10"
                                        max="500"
                                        step="10"
                                        value={storageSize}
                                        onChange={(e) => {
                                            setStorageSize(parseInt(e.target.value));
                                            setHasChanges(true);
                                        }}
                                        className="w-full"
                                    />
                                    <div className="mt-1 flex justify-between text-xs text-foreground-muted">
                                        <span>10 GB</span>
                                        <span>500 GB</span>
                                    </div>
                                </div>

                                <Checkbox
                                    label="Enable auto-scaling (automatically adjust resources based on load)"
                                    checked={autoScalingEnabled}
                                    onChange={(e) => {
                                        setAutoScalingEnabled(e.target.checked);
                                        setHasChanges(true);
                                    }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                    {hasChanges && (
                        <div className="flex justify-end">
                            <Button onClick={handleSaveResources}>
                                <Save className="mr-2 h-4 w-4" />
                                Save Changes
                            </Button>
                        </div>
                    )}
                </div>
            ),
        },
        {
            label: 'Configuration',
            content: (
                <div className="space-y-6">
                    {(database.database_type === 'postgresql' || database.database_type === 'mysql' || database.database_type === 'mariadb') && (
                        <Card>
                            <CardContent className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-foreground">Configuration Parameters</h3>
                                    <Settings className="h-5 w-5 text-foreground-muted" />
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {Object.entries(configParams).map(([key, value]) => (
                                        <Input
                                            key={key}
                                            label={key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                            value={value}
                                            onChange={(e) => handleConfigChange(key, e.target.value)}
                                        />
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                    {hasChanges && (
                        <div className="flex justify-end">
                            <Button onClick={handleSaveConfiguration}>
                                <Save className="mr-2 h-4 w-4" />
                                Save Configuration
                            </Button>
                        </div>
                    )}
                </div>
            ),
        },
        {
            label: 'Security',
            content: (
                <div className="space-y-6">
                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-foreground">Security Settings</h3>
                                <Shield className="h-5 w-5 text-foreground-muted" />
                            </div>
                            <div className="space-y-4">
                                <Checkbox
                                    label="Enable SSL/TLS encryption for connections"
                                    checked={sslEnabled}
                                    onChange={(e) => {
                                        setSslEnabled(e.target.checked);
                                        setHasChanges(true);
                                    }}
                                />
                                <Input
                                    label="Allowed IP Ranges"
                                    value={allowedIps}
                                    onChange={(e) => {
                                        setAllowedIps(e.target.value);
                                        setHasChanges(true);
                                    }}
                                    hint="Comma-separated list of IP ranges (e.g., 192.168.1.0/24, 10.0.0.1). Use 0.0.0.0/0 to allow all."
                                />
                            </div>
                        </CardContent>
                    </Card>
                    {hasChanges && (
                        <div className="flex justify-end">
                            <Button onClick={handleSaveSecurity}>
                                <Save className="mr-2 h-4 w-4" />
                                Save Security Settings
                            </Button>
                        </div>
                    )}
                </div>
            ),
        },
        {
            label: 'Danger Zone',
            content: (
                <Card className="border-red-500/50">
                    <CardContent className="p-6">
                        <h3 className="mb-3 text-lg font-medium text-red-500">Danger Zone</h3>
                        <p className="mb-4 text-sm text-foreground-muted">
                            Once you delete a database, there is no going back. All data will be permanently deleted. Please be certain.
                        </p>
                        <Button variant="danger" size="sm" onClick={handleDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Database
                        </Button>
                    </CardContent>
                </Card>
            ),
        },
    ];

    return (
        <AppLayout
            title={`${database.name} - Settings`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Settings' }
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
                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${config.bgColor}`}>
                        <Database className={`h-6 w-6 ${config.color}`} />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Database Settings</h1>
                        <p className="text-foreground-muted">Configure database parameters and resources</p>
                    </div>
                </div>
                {restartRequired && (
                    <Button variant="secondary" size="sm" onClick={handleRestart}>
                        <RotateCw className="mr-2 h-4 w-4" />
                        Restart Required
                    </Button>
                )}
            </div>

            {/* Restart Warning */}
            {restartRequired && (
                <div className="mb-6 rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 flex-shrink-0 text-yellow-500" />
                        <div>
                            <h3 className="mb-1 font-semibold text-yellow-500">Restart Required</h3>
                            <p className="text-sm text-yellow-400">
                                Configuration changes require a database restart to take effect. Click "Restart Required" above to restart now.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Settings Tabs */}
            <Tabs tabs={tabs} />
        </AppLayout>
    );
}

interface InfoFieldProps {
    label: string;
    value: string;
}

function InfoField({ label, value }: InfoFieldProps) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-foreground-muted">{label}</label>
            <p className="text-sm text-foreground">{value}</p>
        </div>
    );
}
