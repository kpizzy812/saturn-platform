import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Input, Checkbox } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Plus, Trash2, Edit2, Eye, EyeOff, Copy, Check,
    Send, CheckCircle, XCircle, Clock, Webhook
} from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

interface WebhookConfig {
    id: number;
    url: string;
    events: string[];
    secret: string;
    active: boolean;
    createdAt: string;
}

interface WebhookDelivery {
    id: number;
    webhookId: number;
    event: string;
    status: 'success' | 'failed' | 'pending';
    statusCode: number | null;
    timestamp: string;
    responseTime: string;
}

// Mock webhooks data
const mockWebhooks: WebhookConfig[] = [
    {
        id: 1,
        url: 'https://api.example.com/webhooks/deploy',
        events: ['deployment.started', 'deployment.finished', 'deployment.failed'],
        secret: 'whsec_1234567890abcdefghijklmnop',
        active: true,
        createdAt: '2024-01-15',
    },
    {
        id: 2,
        url: 'https://slack-webhook.example.com/services/T00/B00/XXX',
        events: ['deployment.failed'],
        secret: 'whsec_abcdefghijklmnop1234567890',
        active: true,
        createdAt: '2024-02-01',
    },
    {
        id: 3,
        url: 'https://monitoring.example.com/webhooks',
        events: ['service.health_check_failed'],
        secret: 'whsec_xyz123abc456def789ghi012jkl',
        active: false,
        createdAt: '2024-02-10',
    },
];

const mockDeliveries: WebhookDelivery[] = [
    {
        id: 1,
        webhookId: 1,
        event: 'deployment.finished',
        status: 'success',
        statusCode: 200,
        timestamp: '2 minutes ago',
        responseTime: '234ms',
    },
    {
        id: 2,
        webhookId: 1,
        event: 'deployment.started',
        status: 'success',
        statusCode: 200,
        timestamp: '15 minutes ago',
        responseTime: '189ms',
    },
    {
        id: 3,
        webhookId: 2,
        event: 'deployment.failed',
        status: 'failed',
        statusCode: 500,
        timestamp: '1 hour ago',
        responseTime: '5234ms',
    },
    {
        id: 4,
        webhookId: 1,
        event: 'deployment.finished',
        status: 'success',
        statusCode: 200,
        timestamp: '3 hours ago',
        responseTime: '156ms',
    },
];

const availableEvents = [
    { value: 'deployment.started', label: 'Deployment Started' },
    { value: 'deployment.finished', label: 'Deployment Finished' },
    { value: 'deployment.failed', label: 'Deployment Failed' },
    { value: 'service.started', label: 'Service Started' },
    { value: 'service.stopped', label: 'Service Stopped' },
    { value: 'service.health_check_failed', label: 'Health Check Failed' },
];

export function WebhooksTab({ service }: Props) {
    const [webhooks, setWebhooks] = useState<WebhookConfig[]>(mockWebhooks);
    const [deliveries] = useState<WebhookDelivery[]>(mockDeliveries);
    const [showSecrets, setShowSecrets] = useState<Record<number, boolean>>({});
    const [copiedId, setCopiedId] = useState<number | null>(null);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [selectedWebhook, setSelectedWebhook] = useState<number | null>(null);
    const { addToast } = useToast();

    const toggleShowSecret = (id: number) => {
        setShowSecrets((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const handleCopy = async (text: string, id: number) => {
        await navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this webhook?')) {
            setWebhooks((prev) => prev.filter((w) => w.id !== id));
        }
    };

    const handleToggleActive = (id: number) => {
        setWebhooks((prev) =>
            prev.map((w) => (w.id === id ? { ...w, active: !w.active } : w))
        );
    };

    const handleTestWebhook = (id: number) => {
        addToast('info', `Testing webhook ${id}...`);
    };

    return (
        <div className="space-y-4">
            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Webhooks</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Configure webhooks to receive notifications about service events
                            </p>
                        </div>
                        <Button onClick={() => setIsAddModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Webhook
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Webhooks List */}
            <div className="space-y-2">
                {webhooks.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Webhook className="h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No webhooks configured</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Add a webhook to receive notifications
                            </p>
                            <Button className="mt-4" onClick={() => setIsAddModalOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Webhook
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    webhooks.map((webhook) => (
                        <Card key={webhook.id}>
                            <CardContent className="p-4">
                                <div className="space-y-3">
                                    {/* Webhook Header */}
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <code className="text-sm font-medium text-foreground">
                                                    {webhook.url}
                                                </code>
                                                <Badge variant={webhook.active ? 'success' : 'default'}>
                                                    {webhook.active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                {webhook.events.map((event) => (
                                                    <Badge key={event} variant="default" className="text-xs">
                                                        {event}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => handleTestWebhook(webhook.id)}
                                                className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                title="Test webhook"
                                            >
                                                <Send className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() => handleToggleActive(webhook.id)}
                                                className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                title={webhook.active ? 'Disable' : 'Enable'}
                                            >
                                                {webhook.active ? (
                                                    <CheckCircle className="h-4 w-4 text-primary" />
                                                ) : (
                                                    <XCircle className="h-4 w-4" />
                                                )}
                                            </button>
                                            <button
                                                onClick={() => handleDelete(webhook.id)}
                                                className="rounded p-2 text-danger transition-colors hover:bg-danger/10"
                                                title="Delete webhook"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Secret Key */}
                                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex-1">
                                                <p className="text-xs font-medium text-foreground-muted mb-1">
                                                    Secret Key
                                                </p>
                                                <code className="text-sm text-foreground">
                                                    {showSecrets[webhook.id]
                                                        ? webhook.secret
                                                        : '••••••••••••••••••••••••••••••'}
                                                </code>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() => toggleShowSecret(webhook.id)}
                                                    className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                    title={showSecrets[webhook.id] ? 'Hide secret' : 'Show secret'}
                                                >
                                                    {showSecrets[webhook.id] ? (
                                                        <EyeOff className="h-4 w-4" />
                                                    ) : (
                                                        <Eye className="h-4 w-4" />
                                                    )}
                                                </button>
                                                <button
                                                    onClick={() => handleCopy(webhook.secret, webhook.id)}
                                                    className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                    title="Copy secret"
                                                >
                                                    {copiedId === webhook.id ? (
                                                        <Check className="h-4 w-4 text-primary" />
                                                    ) : (
                                                        <Copy className="h-4 w-4" />
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Recent Deliveries */}
                                    <div>
                                        <button
                                            onClick={() =>
                                                setSelectedWebhook(
                                                    selectedWebhook === webhook.id ? null : webhook.id
                                                )
                                            }
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            {selectedWebhook === webhook.id
                                                ? 'Hide delivery history'
                                                : 'View delivery history'}
                                        </button>
                                        {selectedWebhook === webhook.id && (
                                            <div className="mt-2 space-y-2">
                                                {deliveries
                                                    .filter((d) => d.webhookId === webhook.id)
                                                    .map((delivery) => (
                                                        <div
                                                            key={delivery.id}
                                                            className="flex items-center justify-between rounded border border-border bg-background p-2 text-xs"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                {delivery.status === 'success' ? (
                                                                    <CheckCircle className="h-3 w-3 text-primary" />
                                                                ) : delivery.status === 'failed' ? (
                                                                    <XCircle className="h-3 w-3 text-danger" />
                                                                ) : (
                                                                    <Clock className="h-3 w-3 text-warning" />
                                                                )}
                                                                <span className="font-medium text-foreground">
                                                                    {delivery.event}
                                                                </span>
                                                                {delivery.statusCode && (
                                                                    <Badge
                                                                        variant={
                                                                            delivery.status === 'success'
                                                                                ? 'success'
                                                                                : 'danger'
                                                                        }
                                                                        className="text-xs"
                                                                    >
                                                                        {delivery.statusCode}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="flex items-center gap-3 text-foreground-muted">
                                                                <span>{delivery.responseTime}</span>
                                                                <span>·</span>
                                                                <span>{delivery.timestamp}</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>

            {/* Add Webhook Modal */}
            {isAddModalOpen && (
                <AddWebhookModal
                    onClose={() => setIsAddModalOpen(false)}
                    onAdd={(webhook) => {
                        setWebhooks((prev) => [...prev, webhook]);
                        setIsAddModalOpen(false);
                    }}
                />
            )}
        </div>
    );
}

interface AddWebhookModalProps {
    onClose: () => void;
    onAdd: (webhook: WebhookConfig) => void;
}

function AddWebhookModal({ onClose, onAdd }: AddWebhookModalProps) {
    const [url, setUrl] = useState('');
    const [selectedEvents, setSelectedEvents] = useState<string[]>([]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!url || selectedEvents.length === 0) return;

        const webhook: WebhookConfig = {
            id: Date.now(),
            url,
            events: selectedEvents,
            secret: `whsec_${Math.random().toString(36).substring(2, 32)}`,
            active: true,
            createdAt: new Date().toISOString().split('T')[0],
        };

        onAdd(webhook);
    };

    const toggleEvent = (event: string) => {
        setSelectedEvents((prev) =>
            prev.includes(event) ? prev.filter((e) => e !== event) : [...prev, event]
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                <h2 className="text-xl font-semibold text-foreground">Add Webhook</h2>
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Webhook URL
                        </label>
                        <Input
                            type="url"
                            value={url}
                            onChange={(e) => setUrl(e.target.value)}
                            placeholder="https://api.example.com/webhooks"
                            required
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Events to Subscribe
                        </label>
                        <div className="space-y-2 max-h-48 overflow-y-auto rounded border border-border p-3">
                            {availableEvents.map((event) => (
                                <label
                                    key={event.value}
                                    className="flex items-center gap-2 cursor-pointer"
                                >
                                    <Checkbox
                                        checked={selectedEvents.includes(event.value)}
                                        onChange={() => toggleEvent(event.value)}
                                    />
                                    <span className="text-sm text-foreground">{event.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit">Add Webhook</Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
