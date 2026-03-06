import * as React from 'react';
import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { getEcho } from '@/lib/echo';
import type { Notification } from '@/types';

interface UseNotificationsOptions {
    initialNotifications?: Notification[];
    autoRefresh?: boolean;
    refreshInterval?: number;
}

interface UseNotificationsReturn {
    notifications: Notification[];
    unreadCount: number;
    loading: boolean;
    error: Error | null;
    markAsRead: (id: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (id: string) => Promise<void>;
    refresh: () => Promise<void>;
    isConnected: boolean;
}

/**
 * Custom hook for managing notifications with real-time updates
 *
 * Features:
 * - Fetch and manage notifications
 * - Mark notifications as read/unread
 * - Delete notifications
 * - Unread count tracking
 * - Auto-refresh capability
 */
export function useNotifications({
    initialNotifications = [],
    autoRefresh = false,
    refreshInterval = 30000, // 30 seconds
}: UseNotificationsOptions = {}): UseNotificationsReturn {
    const page = usePage();
    const teamId = (page.props as { team?: { id?: number } }).team?.id;

    const [notifications, setNotifications] = React.useState<Notification[]>(initialNotifications);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);
    const [isConnected, setIsConnected] = React.useState(false);

    // Update notifications when initialNotifications change (e.g., from Inertia page props)
    React.useEffect(() => {
        if (initialNotifications.length > 0) {
            setNotifications(initialNotifications);
        }
    }, [initialNotifications]);

    // Subscribe to team channel — reload notifications when events that generate
    // notifications are broadcast (DeploymentFinished, BackupCreated, ServerValidated, etc.)
    React.useEffect(() => {
        if (teamId == null) return;

        const echo = getEcho();
        if (!echo) return;

        const channel = echo.private(`team.${teamId}`);

        const reload = () => {
            router.reload({ only: ['notifications'], preserveScroll: true });
        };

        channel.listen('DeploymentFinished', reload);
        channel.listen('BackupCreated', reload);
        channel.listen('ServerValidated', reload);
        channel.listen('ScheduledTaskDone', reload);
        channel.listen('DockerCleanupDone', reload);
        channel.listen('DeploymentApprovalRequested', reload);
        channel.listen('DeploymentApprovalResolved', reload);

        setIsConnected(true);

        return () => {
            try {
                echo.leave(`team.${teamId}`);
            } catch {
                // ignore cleanup errors
            }
            setIsConnected(false);
        };
    }, [teamId]);

    // Calculate unread count
    const unreadCount = React.useMemo(
        () => notifications.filter((n) => !n.isRead).length,
        [notifications]
    );

    // Refresh notifications by reloading the page (Inertia way)
    const fetchNotifications = React.useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            // Use Inertia to reload the page and get fresh data
            router.reload({
                only: ['notifications'],
                onFinish: () => setLoading(false),
            });
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch notifications'));
            setLoading(false);
        }
    }, []);

    // Mark a single notification as read
    const markAsRead = React.useCallback(async (id: string) => {
        try {
            // Optimistic update
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, isRead: true } : n))
            );

            // Make API call
            router.post(`/notifications/${id}/read`, {}, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    // Revert on error
                    setNotifications((prev) =>
                        prev.map((n) => (n.id === id ? { ...n, isRead: false } : n))
                    );
                    setError(new Error('Failed to mark as read'));
                },
            });
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to mark as read'));
        }
    }, []);

    // Mark all notifications as read
    const markAllAsRead = React.useCallback(async () => {
        try {
            const previousNotifications = [...notifications];

            // Optimistic update
            setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));

            // Make API call
            router.post('/notifications/read-all', {}, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    // Revert on error
                    setNotifications(previousNotifications);
                    setError(new Error('Failed to mark all as read'));
                },
            });
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to mark all as read'));
        }
    }, [notifications]);

    // Delete a notification
    const deleteNotification = React.useCallback(async (id: string) => {
        try {
            const previousNotifications = [...notifications];

            // Optimistic update
            setNotifications((prev) => prev.filter((n) => n.id !== id));

            // Make API call
            router.delete(`/notifications/${id}`, {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    // Revert on error
                    setNotifications(previousNotifications);
                    setError(new Error('Failed to delete notification'));
                },
            });
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to delete notification'));
        }
    }, [notifications]);

    // Auto-refresh notifications
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            fetchNotifications();
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchNotifications]);

    return {
        notifications,
        unreadCount,
        loading,
        error,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        refresh: fetchNotifications,
        isConnected,
    };
}
