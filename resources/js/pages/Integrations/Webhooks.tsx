import * as React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, Badge, Checkbox, useToast } from '@/components/ui';
import { Webhook, Plus, Copy, Trash2, RefreshCw, CheckCircle2, XCircle, Clock, ChevronDown, ChevronRight, Send } from 'lucide-react';
import { useWebhooks, type Webhook as WebhookType, type WebhookDelivery, type AvailableEvent } from '@/hooks/useWebhooks';

interface Props {
    webhooks: WebhookType[];
    availableEvents: AvailableEvent[];
}

export default function WebhooksPage() {
    const { webhooks: initialWebhooks, availableEvents: initialEvents } = usePage<{ props: Props }>().props as unknown as Props;

    const {
        webhooks,
        availableEvents,
        loading,
        createWebhook,
        deleteWebhook,
        toggleWebhook,
        testWebhook,
        retryDelivery,
    } = useWebhooks({
        initialWebhooks: initialWebhooks || [],
        availableEvents: initialEvents || [],
    });

    const [showCreateModal, setShowCreateModal] = React.useState(false);
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [showDeliveryModal, setShowDeliveryModal] = React.useState(false);
    const [selectedWebhook, setSelectedWebhook] = React.useState<WebhookType | null>(null);
    const [selectedDelivery, setSelectedDelivery] = React.useState<WebhookDelivery | null>(null);
    const [expandedWebhooks, setExpandedWebhooks] = React.useState<Set<number>>(new Set());
    const [newWebhookName, setNewWebhookName] = React.useState('');
    const [newWebhookUrl, setNewWebhookUrl] = React.useState('');
    const [selectedEvents, setSelectedEvents] = React.useState<Set<string>>(new Set());
    const [isCreating, setIsCreating] = React.useState(false);
    const { addToast } = useToast();

    const handleCreateWebhook = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreating(true);

        try {
            await createWebhook({
                name: newWebhookName,
                url: newWebhookUrl,
                events: Array.from(selectedEvents),
            });

            setNewWebhookName('');
            setNewWebhookUrl('');
            setSelectedEvents(new Set());
            setShowCreateModal(false);
            addToast('success', 'Webhook created', 'Your webhook has been created successfully.');
        } catch {
            addToast('error', 'Error', 'Failed to create webhook.');
        } finally {
            setIsCreating(false);
        }
    };

    const handleDeleteWebhook = async () => {
        if (selectedWebhook) {
            try {
                await deleteWebhook(selectedWebhook.uuid);
                setShowDeleteModal(false);
                setSelectedWebhook(null);
                addToast('success', 'Webhook deleted', 'The webhook has been deleted successfully.');
            } catch {
                addToast('error', 'Error', 'Failed to delete webhook.');
            }
        }
    };

    const handleToggleWebhook = async (webhook: WebhookType) => {
        try {
            await toggleWebhook(webhook.uuid);
            addToast('success', webhook.enabled ? 'Webhook disabled' : 'Webhook enabled', `${webhook.name} has been ${webhook.enabled ? 'disabled' : 'enabled'}.`);
        } catch {
            addToast('error', 'Error', 'Failed to toggle webhook.');
        }
    };

    const handleTestWebhook = async (webhook: WebhookType) => {
        try {
            await testWebhook(webhook.uuid);
            addToast('success', 'Test event sent', `Test webhook delivery sent to ${webhook.name}.`);
        } catch {
            addToast('error', 'Error', 'Failed to send test webhook.');
        }
    };

    const handleRetryDelivery = async (delivery: WebhookDelivery, webhookUuid: string) => {
        try {
            await retryDelivery(webhookUuid, delivery.uuid);
            addToast('info', 'Retrying delivery', 'Webhook delivery retry in progress...');
        } catch {
            addToast('error', 'Error', 'Failed to retry delivery.');
        }
    };

    const handleCopy = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard', `${label} copied to clipboard.`);
    };

    const toggleWebhookExpansion = (webhookId: number) => {
        setExpandedWebhooks(prev => {
            const newSet = new Set(prev);
            if (newSet.has(webhookId)) {
                newSet.delete(webhookId);
            } else {
                newSet.add(webhookId);
            }
            return newSet;
        });
    };

    const toggleEvent = (event: string) => {
        setSelectedEvents(prev => {
            const newSet = new Set(prev);
            if (newSet.has(event)) {
                newSet.delete(event);
            } else {
                newSet.add(event);
            }
            return newSet;
        });
    };

    const getWebhookDeliveries = (webhook: WebhookType): WebhookDelivery[] => {
        return webhook.deliveries || [];
    };

    return (
        <>
            <Head title="Webhooks | Saturn" />
            <div className="min-h-screen bg-background p-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div className="space-y-1">
                            <h1 className="text-3xl font-bold text-foreground">Webhooks</h1>
                            <p className="text-foreground-muted">
                                Configure webhooks to receive real-time event notifications
                            </p>
                        </div>
                        <Button onClick={() => setShowCreateModal(true)} disabled={loading}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Webhook
                        </Button>
                    </div>

                    {/* Webhooks List */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Configured Webhooks</CardTitle>
                            <CardDescription>
                                Manage your webhook endpoints
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {webhooks.length === 0 ? (
                                <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-background-tertiary">
                                        <Webhook className="h-6 w-6 text-foreground-muted" />
                                    </div>
                                    <h3 className="mt-4 text-sm font-medium text-foreground">No webhooks configured</h3>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        Add your first webhook to receive event notifications
                                    </p>
                                    <Button className="mt-4" onClick={() => setShowCreateModal(true)}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Webhook
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {webhooks.map(webhook => {
                                        const isExpanded = expandedWebhooks.has(webhook.id);
                                        const webhookDeliveries = getWebhookDeliveries(webhook);

                                        return (
                                            <div
                                                key={webhook.id}
                                                className="rounded-lg border border-border bg-background"
                                            >
                                                {/* Webhook Header */}
                                                <div className="flex items-center justify-between p-4">
                                                    <button
                                                        onClick={() => toggleWebhookExpansion(webhook.id)}
                                                        className="flex flex-1 items-center gap-3 text-left"
                                                    >
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                            <Webhook className="h-5 w-5 text-primary" />
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <p className="font-medium text-foreground">{webhook.name}</p>
                                                                <Badge variant={webhook.enabled ? 'success' : 'default'}>
                                                                    {webhook.enabled ? 'Enabled' : 'Disabled'}
                                                                </Badge>
                                                            </div>
                                                            <p className="mt-1 text-sm text-foreground-muted">{webhook.url}</p>
                                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                                {webhook.events.map(event => (
                                                                    <Badge key={event} variant="default" className="text-xs">
                                                                        {event}
                                                                    </Badge>
                                                                ))}
                                                            </div>
                                                        </div>
                                                        {isExpanded ? (
                                                            <ChevronDown className="h-5 w-5 text-foreground-muted" />
                                                        ) : (
                                                            <ChevronRight className="h-5 w-5 text-foreground-muted" />
                                                        )}
                                                    </button>
                                                    <div className="ml-4 flex items-center gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleTestWebhook(webhook)}
                                                            title="Test webhook"
                                                        >
                                                            <Send className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleToggleWebhook(webhook)}
                                                            title={webhook.enabled ? 'Disable' : 'Enable'}
                                                        >
                                                            {webhook.enabled ? (
                                                                <XCircle className="h-4 w-4" />
                                                            ) : (
                                                                <CheckCircle2 className="h-4 w-4" />
                                                            )}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setSelectedWebhook(webhook);
                                                                setShowDeleteModal(true);
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-danger" />
                                                        </Button>
                                                    </div>
                                                </div>

                                                {/* Webhook Details */}
                                                {isExpanded && (
                                                    <div className="space-y-4 border-t border-border p-4">
                                                        {/* Secret Key */}
                                                        {webhook.secret && (
                                                            <div>
                                                                <p className="mb-2 text-sm font-medium text-foreground">
                                                                    Secret Key
                                                                </p>
                                                                <div className="flex items-center gap-2">
                                                                    <code className="flex-1 rounded-lg bg-background-tertiary p-3 text-sm text-foreground">
                                                                        {webhook.secret}
                                                                    </code>
                                                                    <Button
                                                                        variant="secondary"
                                                                        size="sm"
                                                                        onClick={() => handleCopy(webhook.secret!, 'Secret')}
                                                                    >
                                                                        <Copy className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                                <p className="mt-1 text-xs text-foreground-subtle">
                                                                    Use this secret to verify webhook signatures
                                                                </p>
                                                            </div>
                                                        )}

                                                        {/* Recent Deliveries */}
                                                        <div>
                                                            <p className="mb-2 text-sm font-medium text-foreground">
                                                                Recent Deliveries
                                                            </p>
                                                            {webhookDeliveries.length === 0 ? (
                                                                <div className="rounded-lg border border-border bg-background-secondary p-6 text-center">
                                                                    <Clock className="mx-auto h-8 w-8 text-foreground-muted" />
                                                                    <p className="mt-2 text-sm text-foreground-muted">
                                                                        No deliveries yet
                                                                    </p>
                                                                </div>
                                                            ) : (
                                                                <div className="space-y-2">
                                                                    {webhookDeliveries.slice(0, 5).map(delivery => (
                                                                        <button
                                                                            key={delivery.id}
                                                                            onClick={() => {
                                                                                setSelectedDelivery(delivery);
                                                                                setSelectedWebhook(webhook);
                                                                                setShowDeliveryModal(true);
                                                                            }}
                                                                            className="flex w-full items-center justify-between rounded-lg border border-border bg-background-secondary p-3 transition-colors hover:bg-background-tertiary"
                                                                        >
                                                                            <div className="flex items-center gap-3">
                                                                                {delivery.status === 'success' ? (
                                                                                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                                                ) : delivery.status === 'failed' ? (
                                                                                    <XCircle className="h-4 w-4 text-red-500" />
                                                                                ) : (
                                                                                    <Clock className="h-4 w-4 text-yellow-500" />
                                                                                )}
                                                                                <div className="text-left">
                                                                                    <p className="text-sm font-medium text-foreground">
                                                                                        {delivery.event}
                                                                                    </p>
                                                                                    <p className="text-xs text-foreground-muted">
                                                                                        {new Date(delivery.created_at).toLocaleString()}
                                                                                    </p>
                                                                                </div>
                                                                            </div>
                                                                            <div className="flex items-center gap-2">
                                                                                {delivery.status_code && (
                                                                                    <Badge
                                                                                        variant={delivery.status === 'success' ? 'success' : 'danger'}
                                                                                    >
                                                                                        {delivery.status_code}
                                                                                    </Badge>
                                                                                )}
                                                                                {delivery.status === 'failed' && (
                                                                                    <Button
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={(e) => {
                                                                                            e.stopPropagation();
                                                                                            handleRetryDelivery(delivery, webhook.uuid);
                                                                                        }}
                                                                                    >
                                                                                        <RefreshCw className="h-3 w-3" />
                                                                                    </Button>
                                                                                )}
                                                                            </div>
                                                                        </button>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Webhook Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>About Webhooks</CardTitle>
                            <CardDescription>
                                How webhooks work with Saturn
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3 text-sm">
                                <p className="text-foreground-muted">
                                    Webhooks allow you to receive real-time HTTP notifications when events occur in your Saturn projects.
                                </p>
                                <ul className="list-inside list-disc space-y-2 text-foreground-subtle">
                                    <li>Saturn sends POST requests to your webhook URL when events occur</li>
                                    <li>Each webhook includes a signature header for verification</li>
                                    <li>Failed deliveries are automatically retried up to 3 times</li>
                                    <li>You can test webhooks to verify your endpoint is working</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Create Webhook Modal */}
            <Modal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Add Webhook"
                description="Configure a new webhook endpoint"
                size="lg"
            >
                <form onSubmit={handleCreateWebhook}>
                    <div className="space-y-4">
                        <Input
                            label="Name"
                            value={newWebhookName}
                            onChange={(e) => setNewWebhookName(e.target.value)}
                            placeholder="e.g., Production Notifications"
                            required
                        />

                        <Input
                            label="Webhook URL"
                            type="url"
                            value={newWebhookUrl}
                            onChange={(e) => setNewWebhookUrl(e.target.value)}
                            placeholder="https://your-domain.com/webhook"
                            hint="Must be a valid HTTPS URL"
                            required
                        />

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Events
                            </label>
                            <p className="mb-3 text-sm text-foreground-muted">
                                Select which events should trigger this webhook
                            </p>
                            <div className="space-y-2">
                                {availableEvents.map(event => (
                                    <div
                                        key={event.value}
                                        className="rounded-lg border border-border bg-background p-3"
                                    >
                                        <Checkbox
                                            label={event.label}
                                            checked={selectedEvents.has(event.value)}
                                            onChange={() => toggleEvent(event.value)}
                                        />
                                        <p className="ml-6 mt-1 text-xs text-foreground-muted">
                                            {event.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isCreating} disabled={selectedEvents.size === 0}>
                            Create Webhook
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* Delete Webhook Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Webhook"
                description={`Are you sure you want to delete "${selectedWebhook?.name}"? This action cannot be undone.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDeleteWebhook}>
                        Delete Webhook
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Delivery Details Modal */}
            <Modal
                isOpen={showDeliveryModal}
                onClose={() => setShowDeliveryModal(false)}
                title="Delivery Details"
                description={selectedDelivery ? `${selectedDelivery.event} - ${new Date(selectedDelivery.created_at).toLocaleString()}` : ''}
                size="lg"
            >
                {selectedDelivery && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <Badge variant={selectedDelivery.status === 'success' ? 'success' : 'danger'}>
                                {selectedDelivery.status.toUpperCase()}
                            </Badge>
                            {selectedDelivery.status_code && (
                                <Badge variant="default">HTTP {selectedDelivery.status_code}</Badge>
                            )}
                            <Badge variant="default">{selectedDelivery.attempts} attempt(s)</Badge>
                            {selectedDelivery.response_time_ms && (
                                <Badge variant="default">{selectedDelivery.response_time_ms}ms</Badge>
                            )}
                        </div>

                        {selectedDelivery.payload && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-foreground">Payload</p>
                                <pre className="max-h-48 overflow-auto rounded-lg bg-background-tertiary p-4 text-xs text-foreground">
                                    {typeof selectedDelivery.payload === 'string'
                                        ? JSON.stringify(JSON.parse(selectedDelivery.payload), null, 2)
                                        : JSON.stringify(selectedDelivery.payload, null, 2)}
                                </pre>
                            </div>
                        )}

                        {selectedDelivery.response && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-foreground">Response</p>
                                <pre className="max-h-48 overflow-auto rounded-lg bg-background-tertiary p-4 text-xs text-foreground">
                                    {selectedDelivery.response}
                                </pre>
                            </div>
                        )}
                    </div>
                )}

                <ModalFooter>
                    {selectedDelivery?.status === 'failed' && selectedWebhook && (
                        <Button
                            variant="secondary"
                            onClick={() => {
                                if (selectedDelivery && selectedWebhook) {
                                    handleRetryDelivery(selectedDelivery, selectedWebhook.uuid);
                                    setShowDeliveryModal(false);
                                }
                            }}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Retry
                        </Button>
                    )}
                    <Button onClick={() => setShowDeliveryModal(false)}>Close</Button>
                </ModalFooter>
            </Modal>
        </>
    );
}
