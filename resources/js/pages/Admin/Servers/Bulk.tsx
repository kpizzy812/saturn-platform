import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Tabs } from '@/components/ui/Tabs';
import { useConfirm } from '@/components/ui';
import {
    Search,
    Server,
    Tag,
    X,
    ArrowLeft,
    Settings2,
} from 'lucide-react';

// --- Types ---

interface ServerSettings {
    concurrent_builds: number;
    deployment_queue_limit: number;
    dynamic_timeout: number;
    is_build_server: boolean;
    force_docker_cleanup: boolean;
    docker_cleanup_frequency: string;
    docker_cleanup_threshold: number;
    delete_unused_volumes: boolean;
    delete_unused_networks: boolean;
    disable_application_image_retention: boolean;
    is_metrics_enabled: boolean;
    is_sentinel_enabled: boolean;
    is_terminal_enabled: boolean;
    sentinel_metrics_history_days: number;
    sentinel_metrics_refresh_rate_seconds: number;
    sentinel_push_interval_seconds: number;
}

interface ServerInfo {
    id: number;
    uuid: string;
    name: string;
    ip: string;
    is_reachable: boolean;
    is_usable: boolean;
    team_name: string;
    settings: ServerSettings;
}

interface Props {
    servers: {
        data: ServerInfo[];
        total: number;
    };
    allTags?: string[];
    filters?: {
        search?: string;
        status?: string;
        tag?: string;
    };
}

// --- Bulk edit field definitions ---

type FieldType = 'integer' | 'boolean' | 'string';

interface FieldDef {
    key: keyof ServerSettings;
    label: string;
    type: FieldType;
    min?: number;
    max?: number;
    hint?: string;
}

const buildFields: FieldDef[] = [
    { key: 'concurrent_builds', label: 'Concurrent Builds', type: 'integer', min: 1, max: 100 },
    { key: 'deployment_queue_limit', label: 'Deployment Queue Limit', type: 'integer', min: 0, max: 1000, hint: '0 = unlimited' },
    { key: 'dynamic_timeout', label: 'Dynamic Timeout (s)', type: 'integer', min: 0, max: 86400, hint: 'Seconds, 0 = disabled' },
    { key: 'is_build_server', label: 'Build Server', type: 'boolean' },
];

const dockerFields: FieldDef[] = [
    { key: 'force_docker_cleanup', label: 'Force Docker Cleanup', type: 'boolean' },
    { key: 'docker_cleanup_frequency', label: 'Cleanup Frequency (cron)', type: 'string', hint: 'Cron expression, e.g. 0 0 * * *' },
    { key: 'docker_cleanup_threshold', label: 'Cleanup Threshold (%)', type: 'integer', min: 0, max: 100 },
    { key: 'delete_unused_volumes', label: 'Delete Unused Volumes', type: 'boolean' },
    { key: 'delete_unused_networks', label: 'Delete Unused Networks', type: 'boolean' },
    { key: 'disable_application_image_retention', label: 'Disable Image Retention', type: 'boolean' },
];

const monitoringFields: FieldDef[] = [
    { key: 'is_metrics_enabled', label: 'Metrics Enabled', type: 'boolean' },
    { key: 'is_sentinel_enabled', label: 'Sentinel Enabled', type: 'boolean' },
    { key: 'is_terminal_enabled', label: 'Terminal Enabled', type: 'boolean' },
    { key: 'sentinel_metrics_history_days', label: 'Metrics History (days)', type: 'integer', min: 1, max: 365 },
    { key: 'sentinel_metrics_refresh_rate_seconds', label: 'Metrics Refresh Rate (s)', type: 'integer', min: 5, max: 3600 },
    { key: 'sentinel_push_interval_seconds', label: 'Push Interval (s)', type: 'integer', min: 5, max: 3600 },
];

// --- Components ---

function StatusBadge({ is_reachable, is_usable }: { is_reachable: boolean; is_usable: boolean }) {
    if (!is_reachable) return <Badge variant="danger" size="sm">Unreachable</Badge>;
    if (!is_usable) return <Badge variant="warning" size="sm">Degraded</Badge>;
    return <Badge variant="success" size="sm">Healthy</Badge>;
}

function OnOffBadge({ value, label }: { value: boolean; label: string }) {
    return (
        <Badge variant={value ? 'success' : 'default'} size="sm">
            {label}
        </Badge>
    );
}

interface BulkFieldProps {
    field: FieldDef;
    included: boolean;
    onIncludeChange: (included: boolean) => void;
    value: string | number | boolean;
    onValueChange: (value: string | number | boolean) => void;
}

function BulkField({ field, included, onIncludeChange, value, onValueChange }: BulkFieldProps) {
    return (
        <div className="flex items-center gap-4 rounded-lg border border-border/50 p-3">
            <Checkbox
                checked={included}
                onCheckedChange={onIncludeChange}
            />
            <div className="flex flex-1 items-center gap-4">
                <div className="w-56">
                    <span className={`text-sm ${included ? 'text-foreground' : 'text-foreground-muted'}`}>
                        {field.label}
                    </span>
                    {field.hint && (
                        <p className="text-xs text-foreground-subtle">{field.hint}</p>
                    )}
                </div>
                <div className="flex-1">
                    {field.type === 'boolean' ? (
                        <Checkbox
                            checked={value as boolean}
                            onCheckedChange={(checked) => onValueChange(checked)}
                            disabled={!included}
                            label={value ? 'Enabled' : 'Disabled'}
                        />
                    ) : field.type === 'integer' ? (
                        <Input
                            type="number"
                            value={String(value)}
                            onChange={(e) => onValueChange(parseInt(e.target.value) || 0)}
                            min={field.min}
                            max={field.max}
                            disabled={!included}
                            className="w-32"
                        />
                    ) : (
                        <Input
                            type="text"
                            value={String(value)}
                            onChange={(e) => onValueChange(e.target.value)}
                            disabled={!included}
                            className="w-64"
                        />
                    )}
                </div>
            </div>
        </div>
    );
}

// --- Main Page ---

export default function AdminServersBulk({ servers: serversData, allTags = [], filters = {} }: Props) {
    const items = serversData?.data ?? [];
    const _total = serversData?.total ?? 0;
    const confirm = useConfirm();

    // Filters
    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = React.useState(filters.status ?? 'all');
    const [tagFilter, setTagFilter] = React.useState(filters.tag ?? '');

    // Server selection
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());

    // Bulk edit state: which fields are included, and their values
    const allFields = [...buildFields, ...dockerFields, ...monitoringFields];
    const defaultValues: Record<string, string | number | boolean> = {};
    allFields.forEach((f) => {
        if (f.type === 'boolean') defaultValues[f.key] = false;
        else if (f.type === 'integer') defaultValues[f.key] = f.min ?? 0;
        else defaultValues[f.key] = '';
    });

    const [includedFields, setIncludedFields] = React.useState<Set<string>>(new Set());
    const [fieldValues, setFieldValues] = React.useState<Record<string, string | number | boolean>>(defaultValues);
    const [submitting, setSubmitting] = React.useState(false);

    // Debounced search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            search: filters.search,
            status: filters.status,
            tag: filters.tag,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value && value !== 'all') {
                params.set(key, value);
            }
        });

        router.get(`/admin/servers/bulk?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatusChange = (status: string) => {
        setStatusFilter(status);
        applyFilters({ status: status === 'all' ? undefined : status });
    };

    const handleTagChange = (tag: string) => {
        setTagFilter(tag);
        applyFilters({ tag: tag || undefined });
    };

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setTagFilter('');
        router.get('/admin/servers/bulk');
    };

    // Selection handlers
    const toggleServer = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const toggleAll = () => {
        if (selectedIds.size === items.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(items.map((s) => s.id)));
        }
    };

    const allSelected = items.length > 0 && selectedIds.size === items.length;

    // Bulk edit handlers
    const toggleIncluded = (key: string, included: boolean) => {
        setIncludedFields((prev) => {
            const next = new Set(prev);
            if (included) next.add(key);
            else next.delete(key);
            return next;
        });
    };

    const updateFieldValue = (key: string, value: string | number | boolean) => {
        setFieldValues((prev) => ({ ...prev, [key]: value }));
    };

    const handleApply = async () => {
        if (selectedIds.size === 0 || includedFields.size === 0) return;

        const settingsPayload: Record<string, string | number | boolean> = {};
        includedFields.forEach((key) => {
            settingsPayload[key] = fieldValues[key];
        });

        const confirmed = await confirm({
            title: 'Apply Bulk Settings',
            description: `Update ${includedFields.size} setting(s) for ${selectedIds.size} server(s)? This will overwrite current values.`,
            confirmText: `Apply to ${selectedIds.size} servers`,
            variant: 'danger',
        });

        if (!confirmed) return;

        setSubmitting(true);
        router.post('/admin/servers/bulk', {
            server_ids: Array.from(selectedIds),
            settings: settingsPayload,
        }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    const hasActiveFilters = filters.search || filters.status || filters.tag;

    // Render field group for a tab
    const renderFieldGroup = (fields: FieldDef[]) => (
        <div className="space-y-3">
            {fields.map((field) => (
                <BulkField
                    key={field.key}
                    field={field}
                    included={includedFields.has(field.key)}
                    onIncludeChange={(inc) => toggleIncluded(field.key, inc)}
                    value={fieldValues[field.key]}
                    onValueChange={(val) => updateFieldValue(field.key, val)}
                />
            ))}
        </div>
    );

    return (
        <AdminLayout
            title="Bulk Server Settings"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Servers', href: '/admin/servers' },
                { label: 'Bulk Settings' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Bulk Server Settings</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Select servers and apply settings changes in bulk
                        </p>
                    </div>
                    <Link href="/admin/servers">
                        <Button variant="secondary" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Servers
                        </Button>
                    </Link>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search servers..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    {['all', 'reachable', 'degraded', 'unreachable'].map((s) => (
                                        <Button
                                            key={s}
                                            variant={statusFilter === s ? 'primary' : 'secondary'}
                                            size="sm"
                                            onClick={() => handleStatusChange(s)}
                                        >
                                            {s === 'all' ? 'All' : s === 'reachable' ? 'Healthy' : s.charAt(0).toUpperCase() + s.slice(1)}
                                        </Button>
                                    ))}
                                </div>
                            </div>

                            {allTags.length > 0 && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Filter by tag:</span>
                                    {allTags.map((tag) => (
                                        <Button
                                            key={tag}
                                            variant={tagFilter === tag ? 'primary' : 'ghost'}
                                            size="sm"
                                            onClick={() => handleTagChange(tagFilter === tag ? '' : tag)}
                                            className="h-7"
                                        >
                                            <Tag className="mr-1 h-3 w-3" />
                                            {tag}
                                        </Button>
                                    ))}
                                </div>
                            )}

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Active filters:</span>
                                    {filters.search && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Search: {filters.search}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => { setSearchQuery(''); applyFilters({ search: undefined }); }} />
                                        </Badge>
                                    )}
                                    {filters.status && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Status: {filters.status}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleStatusChange('all')} />
                                        </Badge>
                                    )}
                                    {filters.tag && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Tag: {filters.tag}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleTagChange('')} />
                                        </Badge>
                                    )}
                                    <Button variant="ghost" size="sm" onClick={clearFilters}>
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Selection info */}
                <div className="mb-4 flex items-center gap-3">
                    <Settings2 className="h-4 w-4 text-foreground-muted" />
                    <span className="text-sm text-foreground-muted">
                        {selectedIds.size} of {items.length} servers selected
                    </span>
                    {selectedIds.size > 0 && (
                        <Button variant="ghost" size="sm" onClick={() => setSelectedIds(new Set())}>
                            Deselect all
                        </Button>
                    )}
                </div>

                {/* Server Table */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border/50">
                                        <th className="p-3 text-left">
                                            <Checkbox
                                                checked={allSelected}
                                                onCheckedChange={toggleAll}
                                            />
                                        </th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Server</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">IP</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Status</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Builds</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Docker Cleanup</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Metrics</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Sentinel</th>
                                        <th className="p-3 text-left font-medium text-foreground-muted">Terminal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="py-12 text-center">
                                                <Server className="mx-auto h-12 w-12 text-foreground-muted" />
                                                <p className="mt-4 text-sm text-foreground-muted">No servers found</p>
                                            </td>
                                        </tr>
                                    ) : (
                                        items.map((server) => (
                                            <tr
                                                key={server.id}
                                                className={`border-b border-border/30 transition-colors ${
                                                    selectedIds.has(server.id) ? 'bg-primary/5' : 'hover:bg-white/[0.02]'
                                                }`}
                                            >
                                                <td className="p-3">
                                                    <Checkbox
                                                        checked={selectedIds.has(server.id)}
                                                        onCheckedChange={() => toggleServer(server.id)}
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Link
                                                        href={`/admin/servers/${server.uuid}`}
                                                        className="font-medium text-foreground hover:text-primary"
                                                    >
                                                        {server.name}
                                                    </Link>
                                                    <p className="text-xs text-foreground-subtle">{server.team_name}</p>
                                                </td>
                                                <td className="p-3 font-mono text-xs text-foreground-muted">{server.ip}</td>
                                                <td className="p-3">
                                                    <StatusBadge is_reachable={server.is_reachable} is_usable={server.is_usable} />
                                                </td>
                                                <td className="p-3 text-xs text-foreground-muted">
                                                    {server.settings.concurrent_builds}
                                                    {server.settings.is_build_server && (
                                                        <Badge variant="primary" size="sm" className="ml-1">Build</Badge>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    <OnOffBadge value={server.settings.force_docker_cleanup} label="Cleanup" />
                                                </td>
                                                <td className="p-3">
                                                    <OnOffBadge value={server.settings.is_metrics_enabled} label="Metrics" />
                                                </td>
                                                <td className="p-3">
                                                    <OnOffBadge value={server.settings.is_sentinel_enabled} label="Sentinel" />
                                                </td>
                                                <td className="p-3">
                                                    <OnOffBadge value={server.settings.is_terminal_enabled} label="Terminal" />
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Edit Panel */}
                {selectedIds.size > 0 && (
                    <Card variant="glass">
                        <CardContent className="p-6">
                            <div className="mb-4">
                                <h2 className="text-lg font-semibold text-foreground">Bulk Edit Settings</h2>
                                <p className="text-sm text-foreground-muted">
                                    Check &quot;Include&quot; next to each setting you want to change. Only included settings will be applied.
                                </p>
                            </div>

                            <Tabs
                                tabs={[
                                    {
                                        label: 'Build & Deployment',
                                        content: renderFieldGroup(buildFields),
                                    },
                                    {
                                        label: 'Docker Cleanup',
                                        content: renderFieldGroup(dockerFields),
                                    },
                                    {
                                        label: 'Monitoring & Sentinel',
                                        content: renderFieldGroup(monitoringFields),
                                    },
                                ]}
                            />

                            <div className="mt-6 flex items-center justify-between border-t border-border/50 pt-4">
                                <p className="text-sm text-foreground-muted">
                                    {includedFields.size} setting(s) will be applied to {selectedIds.size} server(s)
                                </p>
                                <Button
                                    variant="primary"
                                    onClick={handleApply}
                                    disabled={submitting || includedFields.size === 0}
                                >
                                    {submitting ? 'Applying...' : `Apply to ${selectedIds.size} servers`}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
