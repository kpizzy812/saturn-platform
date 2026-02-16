import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select, Badge, Modal, ModalFooter, Checkbox, useConfirm } from '@/components/ui';
import { Bell, Plus, Trash2, Edit, Check, X, AlertTriangle, TrendingUp, Activity } from 'lucide-react';

interface Alert {
    id: number;
    name: string;
    metric: string;
    condition: '>' | '<' | '=';
    threshold: number;
    duration: number;
    enabled: boolean;
    channels: string[];
    triggered_count: number;
    last_triggered: string | null;
    created_at: string;
}

interface AlertHistory {
    id: number;
    alert_id: number;
    alert_name: string;
    triggered_at: string;
    resolved_at: string | null;
    value: number;
    status: 'triggered' | 'resolved';
}

interface Props {
    alerts?: Alert[];
    history?: AlertHistory[];
}

const metrics = [
    { value: 'cpu', label: 'CPU Usage (%)' },
    { value: 'memory', label: 'Memory Usage (%)' },
    { value: 'disk', label: 'Disk Usage (%)' },
    { value: 'error_rate', label: 'Error Rate (%)' },
    { value: 'response_time', label: 'Response Time (ms)' },
    { value: 'request_rate', label: 'Request Rate (req/s)' },
];

const conditions = [
    { value: '>', label: 'Greater than (>)' },
    { value: '<', label: 'Less than (<)' },
    { value: '=', label: 'Equal to (=)' },
];

const notificationChannels = [
    { id: 'email', label: 'Email' },
    { id: 'slack', label: 'Slack' },
    { id: 'discord', label: 'Discord' },
    { id: 'telegram', label: 'Telegram' },
    { id: 'pagerduty', label: 'PagerDuty' },
    { id: 'webhook', label: 'Webhook' },
];

export default function ObservabilityAlerts({ alerts: propAlerts = [], history = [] }: Props) {
    const confirm = useConfirm();
    const [alerts, _setAlerts] = useState<Alert[]>(propAlerts);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [name, setName] = useState('');
    const [metric, setMetric] = useState('cpu');
    const [condition, setCondition] = useState<'>' | '<' | '='>('>');
    const [threshold, setThreshold] = useState('80');
    const [duration, setDuration] = useState('5');
    const [enabled, setEnabled] = useState(true);
    const [selectedChannels, setSelectedChannels] = useState<string[]>(['email']);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleOpenAdd = () => {
        setEditingId(null);
        setName('');
        setMetric('cpu');
        setCondition('>');
        setThreshold('80');
        setDuration('5');
        setEnabled(true);
        setSelectedChannels(['email']);
        setErrors({});
        setShowAddModal(true);
    };

    const handleOpenEdit = (alert: Alert) => {
        setEditingId(alert.id);
        setName(alert.name);
        setMetric(alert.metric);
        setCondition(alert.condition);
        setThreshold(alert.threshold.toString());
        setDuration(alert.duration.toString());
        setEnabled(alert.enabled);
        setSelectedChannels(alert.channels);
        setErrors({});
        setShowAddModal(true);
    };

    const handleSave = () => {
        // Validate
        const newErrors: Record<string, string> = {};

        if (!name.trim()) {
            newErrors.name = 'Alert name is required';
        }

        if (!threshold || parseFloat(threshold) < 0) {
            newErrors.threshold = 'Valid threshold is required';
        }

        if (!duration || parseInt(duration) < 1) {
            newErrors.duration = 'Duration must be at least 1 minute';
        }

        if (selectedChannels.length === 0) {
            newErrors.channels = 'Select at least one notification channel';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        if (editingId) {
            // Update existing
            router.put(`/observability/alerts/${editingId}`, {
                name,
                metric,
                condition,
                threshold: parseFloat(threshold),
                duration: parseInt(duration),
                enabled,
                channels: selectedChannels,
            });
        } else {
            // Create new
            router.post('/observability/alerts', {
                name,
                metric,
                condition,
                threshold: parseFloat(threshold),
                duration: parseInt(duration),
                enabled,
                channels: selectedChannels,
            });
        }

        setShowAddModal(false);
    };

    const handleToggle = (id: number, currentEnabled: boolean) => {
        router.post(`/observability/alerts/${id}/toggle`, {
            enabled: !currentEnabled,
        });
    };

    const handleDelete = async (id: number) => {
        const confirmed = await confirm({
            title: 'Delete Alert',
            description: 'Are you sure you want to delete this alert?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/observability/alerts/${id}`);
        }
    };

    const toggleChannel = (channelId: string) => {
        if (selectedChannels.includes(channelId)) {
            setSelectedChannels(selectedChannels.filter(c => c !== channelId));
        } else {
            setSelectedChannels([...selectedChannels, channelId]);
        }
        if (errors.channels) {
            setErrors({ ...errors, channels: '' });
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const formatDuration = (minutes: number) => {
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    };

    const getMetricIcon = (metricValue: string) => {
        switch (metricValue) {
            case 'cpu':
            case 'memory':
            case 'disk':
                return <TrendingUp className="h-4 w-4" />;
            case 'error_rate':
                return <AlertTriangle className="h-4 w-4" />;
            default:
                return <Activity className="h-4 w-4" />;
        }
    };

    return (
        <AppLayout
            title="Alerts"
            breadcrumbs={[
                { label: 'Observability', href: '/observability' },
                { label: 'Alerts' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Alert Configuration</h1>
                    <p className="text-foreground-muted">Monitor metrics and get notified of issues</p>
                </div>
                <Button onClick={handleOpenAdd}>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Alert
                </Button>
            </div>

            {/* Stats */}
            <div className="mb-6 grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {alerts.length}
                        </div>
                        <div className="text-sm text-foreground-muted">Total Alerts</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-primary">
                            {alerts.filter(a => a.enabled).length}
                        </div>
                        <div className="text-sm text-foreground-muted">Active Alerts</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-danger">
                            {history.filter(h => h.status === 'triggered').length}
                        </div>
                        <div className="text-sm text-foreground-muted">Currently Triggered</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {alerts.reduce((sum, a) => sum + a.triggered_count, 0)}
                        </div>
                        <div className="text-sm text-foreground-muted">Total Triggers</div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Alert Rules */}
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Alert Rules</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {alerts.length === 0 ? (
                                <div className="py-12 text-center">
                                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                        <Bell className="h-8 w-8 text-foreground-muted" />
                                    </div>
                                    <h3 className="mt-4 text-lg font-medium text-foreground">No alerts configured</h3>
                                    <p className="mt-2 text-foreground-muted">
                                        Create your first alert to get notified of issues.
                                    </p>
                                    <Button onClick={handleOpenAdd} className="mt-6">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Alert
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {alerts.map((alert) => (
                                        <div
                                            key={alert.id}
                                            className="rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-3 mb-2">
                                                        <div className="flex items-center gap-2 text-foreground-muted">
                                                            {getMetricIcon(alert.metric)}
                                                        </div>
                                                        <h3 className="font-medium text-foreground">{alert.name}</h3>
                                                        <Badge variant={alert.enabled ? 'success' : 'default'}>
                                                            {alert.enabled ? 'Enabled' : 'Disabled'}
                                                        </Badge>
                                                    </div>
                                                    <div className="text-sm text-foreground-muted mb-2">
                                                        <span className="font-mono">
                                                            {metrics.find(m => m.value === alert.metric)?.label}
                                                            {' '}{alert.condition}{' '}{alert.threshold}
                                                        </span>
                                                        {' '}for{' '}
                                                        <span className="font-medium">{formatDuration(alert.duration)}</span>
                                                    </div>
                                                    <div className="flex flex-wrap gap-2 mb-2">
                                                        {alert.channels.map(channel => (
                                                            <Badge key={channel} variant="default" className="text-xs">
                                                                {notificationChannels.find(c => c.id === channel)?.label || channel}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                    <div className="flex items-center gap-4 text-xs text-foreground-muted">
                                                        <span>{alert.triggered_count} triggers</span>
                                                        <span>•</span>
                                                        <span>Last: {formatDate(alert.last_triggered)}</span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleToggle(alert.id, alert.enabled)}
                                                        title={alert.enabled ? 'Disable' : 'Enable'}
                                                    >
                                                        {alert.enabled ? (
                                                            <X className="h-4 w-4 text-warning" />
                                                        ) : (
                                                            <Check className="h-4 w-4 text-primary" />
                                                        )}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleOpenEdit(alert)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(alert.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-danger" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Alert History */}
                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Alert History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {history.length === 0 ? (
                                    <div className="py-6 text-center text-sm text-foreground-muted">
                                        No alert history yet
                                    </div>
                                ) : (
                                    history.map((item) => (
                                        <div
                                            key={item.id}
                                            className="rounded-lg border border-border bg-background p-3"
                                        >
                                            <div className="flex items-start justify-between gap-2 mb-2">
                                                <h4 className="text-sm font-medium text-foreground">
                                                    {item.alert_name}
                                                </h4>
                                                <Badge
                                                    variant={item.status === 'triggered' ? 'danger' : 'success'}
                                                    className="text-xs"
                                                >
                                                    {item.status}
                                                </Badge>
                                            </div>
                                            <div className="space-y-1 text-xs text-foreground-muted">
                                                <div className="flex items-center gap-2">
                                                    <span>Value:</span>
                                                    <span className="font-mono text-foreground">{item.value}</span>
                                                </div>
                                                <div>Triggered: {formatDate(item.triggered_at)}</div>
                                                {item.resolved_at && (
                                                    <div>Resolved: {formatDate(item.resolved_at)}</div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Add/Edit Modal */}
            {showAddModal && (
                <Modal
                    isOpen={showAddModal}
                    onClose={() => setShowAddModal(false)}
                    title={editingId ? 'Edit Alert' : 'Create Alert'}
                >
                    <div className="space-y-4">
                        <Input
                            label="Alert Name"
                            placeholder="High CPU Usage"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                if (errors.name) {
                                    setErrors({ ...errors, name: '' });
                                }
                            }}
                            error={errors.name}
                        />

                        <Select
                            label="Metric"
                            value={metric}
                            onChange={(e) => setMetric(e.target.value)}
                            options={metrics}
                        />

                        <div className="grid grid-cols-2 gap-4">
                            <Select
                                label="Condition"
                                value={condition}
                                onChange={(e) => setCondition(e.target.value as '>' | '<' | '=')}
                                options={conditions}
                            />

                            <Input
                                label="Threshold Value"
                                type="number"
                                placeholder="80"
                                value={threshold}
                                onChange={(e) => {
                                    setThreshold(e.target.value);
                                    if (errors.threshold) {
                                        setErrors({ ...errors, threshold: '' });
                                    }
                                }}
                                error={errors.threshold}
                                min="0"
                            />
                        </div>

                        <Input
                            label="Duration (minutes)"
                            type="number"
                            placeholder="5"
                            value={duration}
                            onChange={(e) => {
                                setDuration(e.target.value);
                                if (errors.duration) {
                                    setErrors({ ...errors, duration: '' });
                                }
                            }}
                            error={errors.duration}
                            hint="Alert triggers if condition is met for this duration"
                            min="1"
                        />

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Notification Channels
                            </label>
                            <div className="space-y-2">
                                {notificationChannels.map((channel) => (
                                    <Checkbox
                                        key={channel.id}
                                        id={channel.id}
                                        checked={selectedChannels.includes(channel.id)}
                                        onChange={() => toggleChannel(channel.id)}
                                        label={channel.label}
                                    />
                                ))}
                            </div>
                            {errors.channels && (
                                <p className="mt-1 text-sm text-danger">{errors.channels}</p>
                            )}
                        </div>

                        <Checkbox
                            id="enabled"
                            checked={enabled}
                            onChange={(e) => setEnabled(e.target.checked)}
                            label="Enable alert immediately"
                        />
                    </div>

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setShowAddModal(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave}>
                            {editingId ? 'Update' : 'Create'} Alert
                        </Button>
                    </ModalFooter>
                </Modal>
            )}
        </AppLayout>
    );
}
