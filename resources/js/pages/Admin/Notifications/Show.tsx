import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import type { Notification } from '@/types';
import {
    ArrowLeft,
    AlertTriangle,
    Info,
    Clock,
    Eye,
    EyeOff,
    Trash2,
    ExternalLink,
    FileJson,
} from 'lucide-react';

interface Props {
    notification?: Notification & { metadata?: Record<string, unknown> };
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

export default function AdminNotificationShow({ notification }: Props) {
    const [isRead, setIsRead] = React.useState(notification?.isRead ?? false);

    const handleMarkAsRead = React.useCallback(() => {
        if (!notification) return;
        setIsRead(true);
        router.post(`/admin/notifications/${notification.id}/read`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [notification]);

    const handleMarkAsUnread = React.useCallback(() => {
        if (!notification) return;
        setIsRead(false);
        router.post(`/admin/notifications/${notification.id}/unread`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [notification]);

    const handleDelete = React.useCallback(() => {
        if (!notification) return;
        if (confirm('Are you sure you want to delete this notification?')) {
            router.delete(`/admin/notifications/${notification.id}`, {
                onSuccess: () => {
                    router.visit('/admin/notifications');
                },
            });
        }
    }, [notification]);

    // Auto-mark as read when viewing
    React.useEffect(() => {
        if (!isRead && notification) {
            const timer = setTimeout(() => {
                handleMarkAsRead();
            }, 2000);
            return () => clearTimeout(timer);
        }
    }, [isRead, handleMarkAsRead, notification]);

    if (!notification) {
        return (
            <AdminLayout
                title="Notification Not Found"
                breadcrumbs={[
                    { label: 'Admin', href: '/admin' },
                    { label: 'System Notifications', href: '/admin/notifications' },
                    { label: 'Not Found' },
                ]}
            >
                <div className="flex flex-col items-center justify-center py-16">
                    <Info className="h-16 w-16 text-foreground-muted" />
                    <h2 className="mt-4 text-xl font-semibold text-foreground">Notification not found</h2>
                    <Link href="/admin/notifications" className="mt-4">
                        <Button variant="secondary">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Notifications
                        </Button>
                    </Link>
                </div>
            </AdminLayout>
        );
    }

    const isError = notification.title.toLowerCase().includes('failed') ||
                    notification.title.toLowerCase().includes('error');

    return (
        <AdminLayout
            title="Notification Details"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'System Notifications', href: '/admin/notifications' },
                { label: 'Details' },
            ]}
        >
            {/* Back Button */}
            <div className="mb-6">
                <Link href="/admin/notifications">
                    <Button variant="secondary" size="sm">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Notifications
                    </Button>
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Content */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Notification Card */}
                    <Card className={`border-l-4 ${isError ? 'border-danger' : 'border-primary'}`}>
                        <CardContent>
                            <div className="flex gap-4">
                                {/* Icon */}
                                <div className={`flex h-16 w-16 shrink-0 items-center justify-center rounded-full ${isError ? 'bg-danger/10' : 'bg-primary/10'}`}>
                                    {isError ? (
                                        <AlertTriangle className="h-8 w-8 text-danger" />
                                    ) : (
                                        <Info className="h-8 w-8 text-primary" />
                                    )}
                                </div>

                                {/* Content */}
                                <div className="flex-1 space-y-3">
                                    <div>
                                        <div className="flex items-start justify-between gap-2">
                                            <h1 className="text-2xl font-bold text-foreground">
                                                {notification.title}
                                            </h1>
                                            {!isRead && (
                                                <div className="flex h-3 w-3 shrink-0">
                                                    <span className="absolute h-3 w-3 animate-ping rounded-full bg-primary opacity-75"></span>
                                                    <span className="relative h-3 w-3 rounded-full bg-primary"></span>
                                                </div>
                                            )}
                                        </div>
                                        <div className="mt-1 flex items-center gap-2 text-sm text-foreground-muted">
                                            <Clock className="h-4 w-4" />
                                            <span>{getRelativeTime(notification.timestamp)}</span>
                                            <span className="text-foreground-subtle">
                                                ({new Date(notification.timestamp).toLocaleString()})
                                            </span>
                                        </div>
                                    </div>

                                    {notification.description && (
                                        <div className="whitespace-pre-wrap text-foreground-muted">
                                            {notification.description}
                                        </div>
                                    )}

                                    {/* Action URL */}
                                    {notification.actionUrl && (
                                        <div className="pt-2">
                                            <Link href={notification.actionUrl}>
                                                <Button variant="default">
                                                    <ExternalLink className="mr-2 h-4 w-4" />
                                                    View Related Resource
                                                </Button>
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Details Card */}
                    <Card>
                        <CardContent>
                            <h2 className="mb-4 text-lg font-semibold text-foreground">Details</h2>
                            <dl className="space-y-3">
                                <div className="flex justify-between border-b border-border pb-2">
                                    <dt className="text-sm font-medium text-foreground-muted">Type</dt>
                                    <dd className="text-sm text-foreground">
                                        <Badge variant={isError ? 'danger' : 'default'}>
                                            {notification.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                                        </Badge>
                                    </dd>
                                </div>
                                <div className="flex justify-between border-b border-border pb-2">
                                    <dt className="text-sm font-medium text-foreground-muted">Status</dt>
                                    <dd className="text-sm text-foreground">
                                        <Badge variant={isRead ? 'secondary' : 'default'}>
                                            {isRead ? 'Read' : 'Unread'}
                                        </Badge>
                                    </dd>
                                </div>
                                <div className="flex justify-between border-b border-border pb-2">
                                    <dt className="text-sm font-medium text-foreground-muted">Received</dt>
                                    <dd className="text-sm text-foreground">
                                        {new Date(notification.timestamp).toLocaleString()}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm font-medium text-foreground-muted">ID</dt>
                                    <dd className="text-sm font-mono text-foreground-muted">
                                        {notification.id}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Metadata Card */}
                    {notification.metadata && Object.keys(notification.metadata).length > 0 && (
                        <Card>
                            <CardContent>
                                <div className="mb-4 flex items-center gap-2">
                                    <FileJson className="h-5 w-5 text-foreground-muted" />
                                    <h2 className="text-lg font-semibold text-foreground">Metadata</h2>
                                </div>
                                <pre className="overflow-auto rounded-lg bg-background-secondary p-4 text-sm text-foreground-muted">
                                    {JSON.stringify(notification.metadata, null, 2)}
                                </pre>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Sidebar Actions */}
                <div className="space-y-6">
                    <Card>
                        <CardContent>
                            <h3 className="mb-4 text-sm font-semibold text-foreground">Actions</h3>
                            <div className="space-y-2">
                                {isRead ? (
                                    <Button
                                        variant="secondary"
                                        className="w-full justify-start"
                                        onClick={handleMarkAsUnread}
                                    >
                                        <EyeOff className="mr-2 h-4 w-4" />
                                        Mark as Unread
                                    </Button>
                                ) : (
                                    <Button
                                        variant="secondary"
                                        className="w-full justify-start"
                                        onClick={handleMarkAsRead}
                                    >
                                        <Eye className="mr-2 h-4 w-4" />
                                        Mark as Read
                                    </Button>
                                )}
                                <Button
                                    variant="secondary"
                                    className="w-full justify-start text-danger hover:bg-danger hover:text-white"
                                    onClick={handleDelete}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Related Resource */}
                    {notification.actionUrl && (
                        <Card>
                            <CardContent>
                                <h3 className="mb-4 text-sm font-semibold text-foreground">
                                    Related Resource
                                </h3>
                                <Link href={notification.actionUrl}>
                                    <div className="rounded-lg border border-primary/10 bg-primary/[0.03] p-3 transition-colors hover:border-primary/20 hover:bg-primary/10">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-foreground">
                                                View Resource
                                            </span>
                                            <ExternalLink className="h-4 w-4 text-foreground-muted" />
                                        </div>
                                    </div>
                                </Link>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
