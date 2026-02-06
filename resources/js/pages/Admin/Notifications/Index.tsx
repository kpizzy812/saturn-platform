import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import {
    Bell,
    Check,
    CheckCheck,
    Trash2,
    AlertTriangle,
    Info,
    RefreshCw,
    Clock,
    ChevronRight,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Notification } from '@/types';

interface Props {
    notifications: Notification[];
    unreadCount: number;
}

function getRelativeTime(timestamp: string): string {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}

function NotificationRow({
    notification,
    onMarkAsRead,
    onDelete,
}: {
    notification: Notification;
    onMarkAsRead: (id: string) => void;
    onDelete: (id: string) => void;
}) {
    const isError = notification.title.toLowerCase().includes('failed') ||
                    notification.title.toLowerCase().includes('error');

    return (
        <div
            className={cn(
                'flex items-start gap-4 rounded-lg border p-4 transition-colors',
                notification.isRead
                    ? 'border-border bg-background'
                    : 'border-primary/30 bg-primary/5'
            )}
        >
            {/* Icon */}
            <div className={cn(
                'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg',
                isError ? 'bg-danger/10' : 'bg-primary/10'
            )}>
                {isError ? (
                    <AlertTriangle className="h-5 w-5 text-danger" />
                ) : (
                    <Info className="h-5 w-5 text-primary" />
                )}
            </div>

            {/* Content - Clickable */}
            <Link
                href={`/admin/notifications/${notification.id}`}
                className="min-w-0 flex-1 cursor-pointer"
            >
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <h3 className={cn(
                            'text-sm transition-colors hover:text-primary',
                            notification.isRead ? 'text-foreground-muted' : 'font-medium text-foreground'
                        )}>
                            {notification.title}
                        </h3>
                        {notification.description && (
                            <p className="mt-1 text-sm text-foreground-muted line-clamp-2">
                                {notification.description}
                            </p>
                        )}
                        <div className="mt-2 flex items-center gap-2 text-xs text-foreground-subtle">
                            <Clock className="h-3 w-3" />
                            <span>{getRelativeTime(notification.timestamp)}</span>
                            <span className="text-foreground-subtle/50">
                                ({new Date(notification.timestamp).toLocaleString()})
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Unread indicator */}
                        {!notification.isRead && (
                            <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" />
                        )}
                        <ChevronRight className="h-4 w-4 text-foreground-muted" />
                    </div>
                </div>
            </Link>

            {/* Actions */}
            <div className="flex flex-shrink-0 items-center gap-1">
                {!notification.isRead && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onMarkAsRead(notification.id);
                        }}
                        title="Mark as read"
                    >
                        <Check className="h-4 w-4" />
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        onDelete(notification.id);
                    }}
                    className="text-danger hover:bg-danger/10 hover:text-danger"
                    title="Delete"
                >
                    <Trash2 className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

export default function AdminNotificationsIndex({ notifications, unreadCount }: Props) {
    const handleMarkAsRead = React.useCallback((id: string) => {
        router.post(`/admin/notifications/${id}/read`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const handleMarkAllAsRead = React.useCallback(() => {
        router.post('/admin/notifications/read-all', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const handleDelete = React.useCallback((id: string) => {
        router.delete(`/admin/notifications/${id}`, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const handleClearAll = React.useCallback(() => {
        if (confirm('Are you sure you want to delete all system notifications?')) {
            router.delete('/admin/notifications', {
                preserveState: true,
                preserveScroll: true,
            });
        }
    }, []);

    const handleRefresh = React.useCallback(() => {
        router.reload({ only: ['notifications', 'unreadCount'] });
    }, []);

    return (
        <AdminLayout
            title="System Notifications"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'System Notifications' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">System Notifications</h1>
                    <p className="mt-1 text-foreground-muted">
                        Internal platform alerts and job failures
                        {unreadCount > 0 && (
                            <Badge variant="default" className="ml-2">
                                {unreadCount} unread
                            </Badge>
                        )}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Link href="/admin/notifications/overview">
                        <Button variant="secondary" size="sm">
                            <Bell className="mr-2 h-4 w-4" />
                            Channel Overview
                        </Button>
                    </Link>
                    <Button variant="secondary" size="sm" onClick={handleRefresh}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                    {unreadCount > 0 && (
                        <Button variant="secondary" size="sm" onClick={handleMarkAllAsRead}>
                            <CheckCheck className="mr-2 h-4 w-4" />
                            Mark All Read
                        </Button>
                    )}
                    {notifications.length > 0 && (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleClearAll}
                            className="text-danger hover:bg-danger hover:text-white"
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Clear All
                        </Button>
                    )}
                </div>
            </div>

            {/* Notifications List */}
            {notifications.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-12">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <Bell className="h-8 w-8 text-primary" />
                        </div>
                        <h3 className="mt-4 text-lg font-medium text-foreground">No system notifications</h3>
                        <p className="mt-2 text-center text-foreground-muted">
                            All systems are running smoothly. Job failures and system alerts will appear here.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {notifications.map((notification) => (
                        <NotificationRow
                            key={notification.id}
                            notification={notification}
                            onMarkAsRead={handleMarkAsRead}
                            onDelete={handleDelete}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
