import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { formatRelativeTime } from '@/lib/utils';
import type { Notification, NotificationType } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Info,
    Users,
    CreditCard,
    Shield,
    Clock,
    CheckCheck,
    Trash2,
    ExternalLink,
    Eye,
    EyeOff,
} from 'lucide-react';

interface Props {
    notification?: Notification;
}

// Mock data for demo
const MOCK_NOTIFICATION: Notification = {
    id: '1',
    type: 'deployment_success',
    title: 'Deployment Successful',
    description: 'production-api deployed successfully to production environment. All health checks passed. The deployment took 2 minutes and 34 seconds.',
    timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
    isRead: false,
    actionUrl: '/applications/app-1',
};

const iconMap: Record<NotificationType, React.ReactNode> = {
    deployment_success: <CheckCircle2 className="h-8 w-8 text-primary" />,
    deployment_failure: <XCircle className="h-8 w-8 text-danger" />,
    team_invite: <Users className="h-8 w-8 text-info" />,
    billing_alert: <CreditCard className="h-8 w-8 text-warning" />,
    security_alert: <Shield className="h-8 w-8 text-danger" />,
    info: <Info className="h-8 w-8 text-foreground-muted" />,
};

const bgColorMap: Record<NotificationType, string> = {
    deployment_success: 'bg-primary/10',
    deployment_failure: 'bg-danger/10',
    team_invite: 'bg-info/10',
    billing_alert: 'bg-warning/10',
    security_alert: 'bg-danger/10',
    info: 'bg-background-tertiary',
};

const borderColorMap: Record<NotificationType, string> = {
    deployment_success: 'border-primary',
    deployment_failure: 'border-danger',
    team_invite: 'border-info',
    billing_alert: 'border-warning',
    security_alert: 'border-danger',
    info: 'border-border',
};

export default function NotificationDetail({ notification: propNotification }: Props) {
    const notification = propNotification || MOCK_NOTIFICATION;
    const [isRead, setIsRead] = React.useState(notification.isRead);

    const handleMarkAsRead = React.useCallback(async () => {
        setIsRead(true);
        // In production, make API call:
        // await fetch(`/api/notifications/${notification.id}/read`, { method: 'POST' });
    }, []);

    const handleMarkAsUnread = React.useCallback(async () => {
        setIsRead(false);
        // In production, make API call:
        // await fetch(`/api/notifications/${notification.id}/unread`, { method: 'POST' });
    }, []);

    const handleDelete = React.useCallback(async () => {
        // In production, make API call:
        // await fetch(`/api/notifications/${notification.id}`, { method: 'DELETE' });
        router.visit('/notifications');
    }, []);

    // Auto-mark as read when viewing
    React.useEffect(() => {
        if (!isRead) {
            const timer = setTimeout(() => {
                handleMarkAsRead();
            }, 2000);
            return () => clearTimeout(timer);
        }
    }, [isRead, handleMarkAsRead]);

    // Get action button based on notification type
    const getActionButton = () => {
        switch (notification.type) {
            case 'deployment_success':
            case 'deployment_failure':
                return (
                    <Link href={notification.actionUrl || '#'}>
                        <Button variant="default">
                            <ExternalLink className="mr-2 h-4 w-4" />
                            View Deployment
                        </Button>
                    </Link>
                );
            case 'team_invite':
                return (
                    <div className="flex gap-2">
                        <Button variant="default">Accept Invitation</Button>
                        <Button variant="secondary">Decline</Button>
                    </div>
                );
            case 'billing_alert':
                return (
                    <Link href="/settings/billing">
                        <Button variant="default">
                            <ExternalLink className="mr-2 h-4 w-4" />
                            View Invoice
                        </Button>
                    </Link>
                );
            case 'security_alert':
                return (
                    <Link href="/settings/security">
                        <Button variant="default">
                            <ExternalLink className="mr-2 h-4 w-4" />
                            Review Security
                        </Button>
                    </Link>
                );
            default:
                return null;
        }
    };

    return (
        <AppLayout
            title="Notification Details"
            breadcrumbs={[
                { label: 'Notifications', href: '/notifications' },
                { label: 'Details' },
            ]}
        >
            {/* Back Button */}
            <div className="mb-6">
                <Link href="/notifications">
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
                    <Card className={`border-l-4 ${borderColorMap[notification.type]}`}>
                        <CardContent>
                            <div className="flex gap-4">
                                {/* Icon */}
                                <div className={`flex h-16 w-16 shrink-0 items-center justify-center rounded-full ${bgColorMap[notification.type]}`}>
                                    {iconMap[notification.type]}
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
                                            <span>{formatRelativeTime(notification.timestamp)}</span>
                                            <span className="text-foreground-subtle">
                                                ({new Date(notification.timestamp).toLocaleString()})
                                            </span>
                                        </div>
                                    </div>

                                    <div className="text-foreground-muted">
                                        {notification.description}
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex items-center gap-2 pt-2">
                                        {getActionButton()}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Additional Details */}
                    <Card>
                        <CardContent>
                            <h2 className="mb-4 text-lg font-semibold text-foreground">Details</h2>
                            <dl className="space-y-3">
                                <div className="flex justify-between border-b border-border pb-2">
                                    <dt className="text-sm font-medium text-foreground-muted">Type</dt>
                                    <dd className="text-sm text-foreground">
                                        <Badge variant="default">
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
                                    <div className="rounded-lg border border-border bg-background-secondary p-3 transition-colors hover:border-primary hover:bg-background-tertiary">
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
        </AppLayout>
    );
}
