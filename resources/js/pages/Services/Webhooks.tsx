import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Input, Checkbox, Modal, ModalFooter } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Plus, Trash2, Eye, EyeOff, Copy, Check,
    Send, CheckCircle, XCircle, Clock, Webhook
} from 'lucide-react';
import type { Service } from '@/types';

interface WebhookDelivery {
    id: number;
    uuid: string;
    event: string;
    status: 'success' | 'failed' | 'pending';
    status_code: number | null;
    response_time_ms: number | null;
    attempts: number;
    created_at: string;
}

interface WebhookConfig {
    id: number;
    uuid: string;
    name: string;
    url: string;
    events: string[];
    secret: string;
    enabled: boolean;
    created_at: string;
    deliveries?: WebhookDelivery[];
}

interface AvailableEvent {
    value: string;
    label: string;
    description: string;
}

interface Props {
    service: Service;
    webhooks: WebhookConfig[];
    availableEvents: AvailableEvent[];
}

export function WebhooksTab({ service }: { service: Service }) {
    const { webhooks: initialWebhooks = [], availableEvents = [] } = usePage<{ props: Props }>().props as unknown as Props;

    const [webhooks, setWebhooks] = useState<WebhookConfig[]>(initialWebhooks);
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

    const handleDelete = (uuid: string) => {
        router.delete(`/integrations/webhooks/${uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setWebhooks((prev) => prev.filter((w) => w.uuid !== uuid));
                addToast('success', 'Webhook deleted successfully');
            },
            onError: () => {
                addToast('error', 'Failed to delete webhook');
            },
        });
    };

    const handleToggleActive = (webhook: WebhookConfig) => {
        router.post(`/integrations/webhooks/${webhook.uuid}/toggle`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setWebhooks((prev) =>
                    prev.map((w) => (w.uuid === webhook.uuid ? { ...w, enabled: !w.enabled } : w))
                );
                addToast('success', `Webhook ${webhook.enabled ? 'disabled' : 'enabled'} successfully`);
            },
            onError: () => {
                addToast('error', 'Failed to toggle webhook');
            },
        });
    };

    const handleTestWebhook = (webhook: WebhookConfig) => {
        router.post(`/integrations/webhooks/${webhook.uuid}/test`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                addToast('success', 'Test webhook sent');
                router.reload({ only: ['webhooks'] });
            },
            onError: () => {
                addToast('error', 'Failed to send test webhook');
            },
        });
    };

    const getWebhookDeliveries = (webhook: WebhookConfig): WebhookDelivery[] => {
        return webhook.deliveries || [];
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
                    webhooks.map((webhook) => {
                        const deliveries = getWebhookDeliveries(webhook);

                        return (
                            <Card key={webhook.id}>
                                <CardContent className="p-4">
                                    <div className="space-y-3">
                                        {/* Webhook Header */}
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">{webhook.name}</span>
                                                    <Badge variant={webhook.enabled ? 'success' : 'default'}>
                                                        {webhook.enabled ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </div>
                                                <code className="mt-1 block text-sm text-foreground-muted">
                                                    {webhook.url}
                                                </code>
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
                                                    onClick={() => handleTestWebhook(webhook)}
                                                    className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                    title="Test webhook"
                                                >
                                                    <Send className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => handleToggleActive(webhook)}
                                                    className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                    title={webhook.enabled ? 'Disable' : 'Enable'}
                                                >
                                                    {webhook.enabled ? (
                                                        <CheckCircle className="h-4 w-4 text-primary" />
                                                    ) : (
                                                        <XCircle className="h-4 w-4" />
                                                    )}
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(webhook.uuid)}
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
                                                    {deliveries.length === 0 ? (
                                                        <div className="rounded-lg border border-border bg-background p-4 text-center text-sm text-foreground-muted">
                                                            No deliveries yet
                                                        </div>
                                                    ) : (
                                                        deliveries.map((delivery) => (
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
                                                                    {delivery.status_code && (
                                                                        <Badge
                                                                            variant={
                                                                                delivery.status === 'success'
                                                                                    ? 'success'
                                                                                    : 'danger'
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {delivery.status_code}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <div className="flex items-center gap-3 text-foreground-muted">
                                                                    {delivery.response_time_ms && (
                                                                        <span>{delivery.response_time_ms}ms</span>
                                                                    )}
                                                                    <span>{new Date(delivery.created_at).toLocaleString()}</span>
                                                                </div>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })
                )}
            </div>

            {/* Add Webhook Modal */}
            {isAddModalOpen && (
                <AddWebhookModal
                    availableEvents={availableEvents}
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
    availableEvents: AvailableEvent[];
    onClose: () => void;
    onAdd: (webhook: WebhookConfig) => void;
}

function AddWebhookModal({ availableEvents, onClose, onAdd }: AddWebhookModalProps) {
    const [name, setName] = useState('');
    const [url, setUrl] = useState('');
    const [selectedEvents, setSelectedEvents] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const { addToast } = useToast();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!name || !url || selectedEvents.length === 0) return;

        setIsLoading(true);

        router.post('/integrations/webhooks', {
            name,
            url,
            events: selectedEvents,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                addToast('success', 'Webhook created successfully');
                router.reload({ only: ['webhooks'] });
                onClose();
            },
            onError: () => {
                addToast('error', 'Failed to create webhook');
                setIsLoading(false);
            },
        });
    };

    const toggleEvent = (event: string) => {
        setSelectedEvents((prev) =>
            prev.includes(event) ? prev.filter((e) => e !== event) : [...prev, event]
        );
    };

    return (
        <Modal
            isOpen={true}
            onClose={onClose}
            title="Add Webhook"
            description="Create a new webhook to receive notifications"
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., Production Notifications"
                    required
                />

                <Input
                    label="Webhook URL"
                    type="url"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    placeholder="https://api.example.com/webhooks"
                    required
                />

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
                                <div>
                                    <span className="text-sm text-foreground">{event.label}</span>
                                    <p className="text-xs text-foreground-muted">{event.description}</p>
                                </div>
                            </label>
                        ))}
                    </div>
                </div>

                <ModalFooter>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={isLoading} disabled={selectedEvents.length === 0}>
                        Add Webhook
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
}

// Default export for page
export default function WebhooksPage() {
    const { service } = usePage<{ props: Props }>().props as unknown as Props;

    return <WebhooksTab service={service} />;
}
