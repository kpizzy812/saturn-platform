import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Modal } from '@/components/ui/Modal';
import { useConfirm } from '@/components/ui';
import {
    AlertTriangle,
    Plus,
    Trash2,
    Edit2,
    Bell,
    Mail,
    MessageSquare,
    Clock,
    Activity,
} from 'lucide-react';
import { router } from '@inertiajs/react';
import type { Server } from '@/types';

interface Props {
    server: Server;
    alertRules?: AlertRule[];
    alertHistory?: AlertHistoryItem[];
}

interface AlertRule {
    id: number;
    name: string;
    metric: 'cpu' | 'memory' | 'disk' | 'network';
    condition: 'above' | 'below';
    threshold: number;
    duration: number;
    severity: 'critical' | 'warning' | 'info';
    notificationChannels: ('email' | 'slack' | 'discord')[];
    enabled: boolean;
    created_at: string;
}

interface AlertHistoryItem {
    id: number;
    rule_name: string;
    severity: 'critical' | 'warning' | 'info';
    message: string;
    triggered_at: string;
    resolved_at: string | null;
}

function AlertRuleCard({ rule, onEdit, onDelete }: {
    rule: AlertRule;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const severityConfig = {
        critical: { badge: 'danger' as const, bg: 'bg-danger/10', text: 'text-danger' },
        warning: { badge: 'warning' as const, bg: 'bg-warning/10', text: 'text-warning' },
        info: { badge: 'info' as const, bg: 'bg-info/10', text: 'text-info' },
    };

    const metricIcons = {
        cpu: <Activity className="h-4 w-4" />,
        memory: <Activity className="h-4 w-4" />,
        disk: <Activity className="h-4 w-4" />,
        network: <Activity className="h-4 w-4" />,
    };

    const config = severityConfig[rule.severity];

    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${config.bg}`}>
                            <div className={config.text}>{metricIcons[rule.metric]}</div>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-foreground">{rule.name}</h3>
                                <Badge variant={config.badge} size="sm">{rule.severity}</Badge>
                                {!rule.enabled && (
                                    <Badge variant="secondary" size="sm">Disabled</Badge>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Alert when {rule.metric} is {rule.condition} {rule.threshold}% for {rule.duration} minutes
                            </p>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                {rule.notificationChannels.map((channel) => (
                                    <div
                                        key={channel}
                                        className="flex items-center gap-1.5 rounded-md bg-background-tertiary px-2 py-1 text-xs text-foreground-muted"
                                    >
                                        {channel === 'email' && <Mail className="h-3 w-3" />}
                                        {channel === 'slack' && <MessageSquare className="h-3 w-3" />}
                                        {channel === 'discord' && <MessageSquare className="h-3 w-3" />}
                                        <span className="capitalize">{channel}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={onEdit}
                        >
                            <Edit2 className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            onClick={onDelete}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function AlertHistoryCard({ alert }: { alert: AlertHistoryItem }) {
    const severityConfig = {
        critical: { badge: 'danger' as const, bg: 'bg-danger/10', text: 'text-danger' },
        warning: { badge: 'warning' as const, bg: 'bg-warning/10', text: 'text-warning' },
        info: { badge: 'info' as const, bg: 'bg-info/10', text: 'text-info' },
    };

    const config = severityConfig[alert.severity];
    const isResolved = alert.resolved_at !== null;

    return (
        <div className="flex items-start gap-3 rounded-lg border border-border/50 bg-background-secondary/30 p-4">
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${config.bg}`}>
                <AlertTriangle className={`h-4 w-4 ${config.text}`} />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <h4 className="font-medium text-foreground">{alert.rule_name}</h4>
                            <Badge variant={config.badge} size="sm">{alert.severity}</Badge>
                            {isResolved && (
                                <Badge variant="success" size="sm">Resolved</Badge>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-foreground-muted">{alert.message}</p>
                    </div>
                </div>
                <div className="mt-3 flex items-center gap-4 text-xs text-foreground-subtle">
                    <div className="flex items-center gap-1.5">
                        <Clock className="h-3 w-3" />
                        <span>Triggered: {new Date(alert.triggered_at).toLocaleString()}</span>
                    </div>
                    {isResolved && (
                        <div className="flex items-center gap-1.5">
                            <Clock className="h-3 w-3" />
                            <span>Resolved: {new Date(alert.resolved_at).toLocaleString()}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function SentinelAlerts({ server, alertRules = [], alertHistory = [] }: Props) {
    const confirm = useConfirm();
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<AlertRule | null>(null);

    // Form state
    const [formData, setFormData] = useState({
        name: '',
        metric: 'cpu' as AlertRule['metric'],
        condition: 'above' as AlertRule['condition'],
        threshold: 80,
        duration: 5,
        severity: 'warning' as AlertRule['severity'],
        notificationChannels: ['email'] as AlertRule['notificationChannels'],
        enabled: true,
    });

    const handleCreateRule = () => {
        // In production, this would POST to the API
        router.post(`/api/v1/servers/${server.uuid}/sentinel/alerts`, formData, {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                setFormData({
                    name: '',
                    metric: 'cpu',
                    condition: 'above',
                    threshold: 80,
                    duration: 5,
                    severity: 'warning',
                    notificationChannels: ['email'],
                    enabled: true,
                });
            },
        });
    };

    const handleEditRule = (rule: AlertRule) => {
        setEditingRule(rule);
        setFormData({
            name: rule.name,
            metric: rule.metric,
            condition: rule.condition,
            threshold: rule.threshold,
            duration: rule.duration,
            severity: rule.severity,
            notificationChannels: rule.notificationChannels,
            enabled: rule.enabled,
        });
        setIsCreateModalOpen(true);
    };

    const handleDeleteRule = async (ruleId: number) => {
        const confirmed = await confirm({
            title: 'Delete Alert Rule',
            description: 'Are you sure you want to delete this alert rule?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/api/v1/servers/${server.uuid}/sentinel/alerts/${ruleId}`);
        }
    };

    return (
        <AppLayout
            title={`Sentinel Alerts - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Sentinel', href: `/servers/${server.uuid}/sentinel` },
                { label: 'Alerts' },
            ]}
        >
            <div className="mx-auto max-w-6xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Alert Management</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Configure alerts and view notification history
                        </p>
                    </div>
                    <Button onClick={() => setIsCreateModalOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Alert Rule
                    </Button>
                </div>

                {/* Alert Rules */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Alert Rules</CardTitle>
                        <CardDescription>
                            {alertRules.length} rule{alertRules.length !== 1 ? 's' : ''} configured
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {alertRules.length > 0 ? (
                            <div className="space-y-3">
                                {alertRules.map((rule) => (
                                    <AlertRuleCard
                                        key={rule.id}
                                        rule={rule}
                                        onEdit={() => handleEditRule(rule)}
                                        onDelete={() => handleDeleteRule(rule.id)}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                    <Bell className="h-8 w-8 text-foreground-muted" />
                                </div>
                                <h3 className="mt-4 text-lg font-medium text-foreground">No Alert Rules</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Create your first alert rule to get notified
                                </p>
                                <Button
                                    onClick={() => setIsCreateModalOpen(true)}
                                    className="mt-4"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Alert Rule
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Alert History */}
                <Card>
                    <CardHeader>
                        <CardTitle>Alert History</CardTitle>
                        <CardDescription>
                            Recent alerts triggered on this server
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {alertHistory.length > 0 ? (
                            <div className="space-y-3">
                                {alertHistory.map((alert) => (
                                    <AlertHistoryCard key={alert.id} alert={alert} />
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                    <Clock className="h-8 w-8 text-foreground-muted" />
                                </div>
                                <h3 className="mt-4 text-lg font-medium text-foreground">No Alert History</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    No alerts have been triggered yet
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Create/Edit Modal */}
                <Modal
                    isOpen={isCreateModalOpen}
                    onClose={() => {
                        setIsCreateModalOpen(false);
                        setEditingRule(null);
                    }}
                    title={editingRule ? 'Edit Alert Rule' : 'Create Alert Rule'}
                >
                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Rule Name
                            </label>
                            <Input
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="e.g., High CPU Usage"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Metric
                                </label>
                                <Select
                                    value={formData.metric}
                                    onChange={(e) => setFormData({ ...formData, metric: e.target.value as AlertRule['metric'] })}
                                >
                                    <option value="cpu">CPU Usage</option>
                                    <option value="memory">Memory Usage</option>
                                    <option value="disk">Disk Usage</option>
                                    <option value="network">Network Usage</option>
                                </Select>
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Condition
                                </label>
                                <Select
                                    value={formData.condition}
                                    onChange={(e) => setFormData({ ...formData, condition: e.target.value as AlertRule['condition'] })}
                                >
                                    <option value="above">Above</option>
                                    <option value="below">Below</option>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Threshold (%)
                                </label>
                                <Input
                                    type="number"
                                    value={formData.threshold}
                                    onChange={(e) => setFormData({ ...formData, threshold: parseInt(e.target.value) })}
                                    min="0"
                                    max="100"
                                />
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Duration (minutes)
                                </label>
                                <Input
                                    type="number"
                                    value={formData.duration}
                                    onChange={(e) => setFormData({ ...formData, duration: parseInt(e.target.value) })}
                                    min="1"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Severity
                            </label>
                            <Select
                                value={formData.severity}
                                onChange={(e) => setFormData({ ...formData, severity: e.target.value as AlertRule['severity'] })}
                            >
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                            </Select>
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-4">
                            <Button
                                variant="secondary"
                                onClick={() => {
                                    setIsCreateModalOpen(false);
                                    setEditingRule(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button onClick={handleCreateRule}>
                                {editingRule ? 'Update Rule' : 'Create Rule'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            </div>
        </AppLayout>
    );
}
