import * as React from 'react';
import { router } from '@inertiajs/react';

export interface WebhookDelivery {
    id: number;
    uuid: string;
    event: string;
    status: 'success' | 'failed' | 'pending';
    status_code: number | null;
    response_time_ms: number | null;
    attempts: number;
    payload?: string;
    response?: string;
    created_at: string;
}

export interface Webhook {
    id: number;
    uuid: string;
    name: string;
    url: string;
    secret?: string;
    events: string[];
    enabled: boolean;
    created_at: string;
    last_triggered_at: string | null;
    deliveries?: WebhookDelivery[];
}

export interface AvailableEvent {
    value: string;
    label: string;
    description: string;
}

interface UseWebhooksOptions {
    initialWebhooks?: Webhook[];
    availableEvents?: AvailableEvent[];
}

interface UseWebhooksReturn {
    webhooks: Webhook[];
    availableEvents: AvailableEvent[];
    loading: boolean;
    error: Error | null;
    createWebhook: (data: { name: string; url: string; events: string[] }) => Promise<Webhook | null>;
    updateWebhook: (uuid: string, data: Partial<Webhook>) => Promise<void>;
    deleteWebhook: (uuid: string) => Promise<void>;
    toggleWebhook: (uuid: string) => Promise<void>;
    testWebhook: (uuid: string) => Promise<void>;
    retryDelivery: (webhookUuid: string, deliveryUuid: string) => Promise<void>;
    refresh: () => void;
}

/**
 * Custom hook for managing team webhooks
 */
export function useWebhooks({
    initialWebhooks = [],
    availableEvents: initialEvents = [],
}: UseWebhooksOptions = {}): UseWebhooksReturn {
    const [webhooks, setWebhooks] = React.useState<Webhook[]>(initialWebhooks);
    const [availableEvents, setAvailableEvents] = React.useState<AvailableEvent[]>(initialEvents);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    // Update webhooks when initialWebhooks change (e.g., from Inertia page props)
    React.useEffect(() => {
        if (initialWebhooks.length > 0 || webhooks.length === 0) {
            setWebhooks(initialWebhooks);
        }
    }, [initialWebhooks]);

    React.useEffect(() => {
        if (initialEvents.length > 0) {
            setAvailableEvents(initialEvents);
        }
    }, [initialEvents]);

    // Refresh webhooks by reloading the page (Inertia way)
    const refresh = React.useCallback(() => {
        router.reload({
            only: ['webhooks'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }, []);

    // Create a new webhook
    const createWebhook = React.useCallback(async (data: { name: string; url: string; events: string[] }): Promise<Webhook | null> => {
        return new Promise((resolve) => {
            setLoading(true);
            setError(null);

            router.post('/integrations/webhooks', data, {
                preserveScroll: true,
                onSuccess: (_page) => {
                    setLoading(false);
                    // Refresh to get new data
                    router.reload({ only: ['webhooks'] });
                    resolve(null); // Webhook will be in refreshed data
                },
                onError: (errors) => {
                    setLoading(false);
                    setError(new Error(Object.values(errors).flat().join(', ')));
                    resolve(null);
                },
            });
        });
    }, []);

    // Update a webhook
    const updateWebhook = React.useCallback(async (uuid: string, data: Partial<Webhook>): Promise<void> => {
        return new Promise((resolve, reject) => {
            const previousWebhooks = [...webhooks];

            // Optimistic update
            setWebhooks((prev) =>
                prev.map((w) => (w.uuid === uuid ? { ...w, ...data } : w))
            );

            router.put(`/integrations/webhooks/${uuid}`, data as any, {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => {
                    setWebhooks(previousWebhooks);
                    setError(new Error('Failed to update webhook'));
                    reject(new Error('Failed to update webhook'));
                },
            });
        });
    }, [webhooks]);

    // Delete a webhook
    const deleteWebhook = React.useCallback(async (uuid: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            const previousWebhooks = [...webhooks];

            // Optimistic update
            setWebhooks((prev) => prev.filter((w) => w.uuid !== uuid));

            router.delete(`/integrations/webhooks/${uuid}`, {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => {
                    setWebhooks(previousWebhooks);
                    setError(new Error('Failed to delete webhook'));
                    reject(new Error('Failed to delete webhook'));
                },
            });
        });
    }, [webhooks]);

    // Toggle a webhook enabled/disabled
    const toggleWebhook = React.useCallback(async (uuid: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            const previousWebhooks = [...webhooks];

            // Optimistic update
            setWebhooks((prev) =>
                prev.map((w) => (w.uuid === uuid ? { ...w, enabled: !w.enabled } : w))
            );

            router.post(`/integrations/webhooks/${uuid}/toggle`, {}, {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => {
                    setWebhooks(previousWebhooks);
                    setError(new Error('Failed to toggle webhook'));
                    reject(new Error('Failed to toggle webhook'));
                },
            });
        });
    }, [webhooks]);

    // Test a webhook
    const testWebhook = React.useCallback(async (uuid: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            router.post(`/integrations/webhooks/${uuid}/test`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    // Refresh to get new delivery
                    router.reload({ only: ['webhooks'] });
                    resolve();
                },
                onError: () => {
                    setError(new Error('Failed to test webhook'));
                    reject(new Error('Failed to test webhook'));
                },
            });
        });
    }, []);

    // Retry a failed delivery
    const retryDelivery = React.useCallback(async (webhookUuid: string, deliveryUuid: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            router.post(`/integrations/webhooks/${webhookUuid}/deliveries/${deliveryUuid}/retry`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    // Refresh to get updated delivery status
                    router.reload({ only: ['webhooks'] });
                    resolve();
                },
                onError: () => {
                    setError(new Error('Failed to retry delivery'));
                    reject(new Error('Failed to retry delivery'));
                },
            });
        });
    }, []);

    return {
        webhooks,
        availableEvents,
        loading,
        error,
        createWebhook,
        updateWebhook,
        deleteWebhook,
        toggleWebhook,
        testWebhook,
        retryDelivery,
        refresh,
    };
}
