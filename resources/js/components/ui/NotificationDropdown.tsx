import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { Bell, Check, ChevronRight, Rocket, AlertTriangle, Users, CreditCard, Shield, Info } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownDivider } from './Dropdown';
import { cn } from '@/lib/utils';
import type { Notification } from '@/types';

interface NotificationDropdownProps {
    unreadCount: number;
    notifications: Notification[];
}

const notificationIcons: Record<string, React.ReactNode> = {
    deployment_success: <Rocket className="h-4 w-4 text-success" />,
    deployment_failure: <AlertTriangle className="h-4 w-4 text-danger" />,
    team_invite: <Users className="h-4 w-4 text-primary" />,
    billing_alert: <CreditCard className="h-4 w-4 text-warning" />,
    security_alert: <Shield className="h-4 w-4 text-danger" />,
    backup_success: <Check className="h-4 w-4 text-success" />,
    backup_failure: <AlertTriangle className="h-4 w-4 text-danger" />,
    server_alert: <AlertTriangle className="h-4 w-4 text-warning" />,
    info: <Info className="h-4 w-4 text-primary" />,
};

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

function NotificationItemCompact({ notification, onMarkAsRead }: { notification: Notification; onMarkAsRead: (id: string) => void }) {
    const icon = notificationIcons[notification.type] || notificationIcons.info;

    const handleClick = (e: React.MouseEvent) => {
        if (!notification.isRead) {
            onMarkAsRead(notification.id);
        }
        if (notification.actionUrl) {
            router.visit(notification.actionUrl);
        } else {
            router.visit(`/notifications/${notification.id}`);
        }
    };

    return (
        <button
            onClick={handleClick}
            className={cn(
                'flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
                'hover:bg-white/[0.08]',
                !notification.isRead && 'bg-primary/5'
            )}
        >
            <div className="mt-0.5 flex-shrink-0">{icon}</div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className={cn(
                        'truncate text-sm',
                        notification.isRead ? 'text-foreground-muted' : 'font-medium text-foreground'
                    )}>
                        {notification.title}
                    </p>
                    {!notification.isRead && (
                        <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" />
                    )}
                </div>
                {notification.description && (
                    <p className="mt-0.5 truncate text-xs text-foreground-subtle">
                        {notification.description}
                    </p>
                )}
                <p className="mt-1 text-xs text-foreground-subtle">
                    {getRelativeTime(notification.timestamp)}
                </p>
            </div>
        </button>
    );
}

export function NotificationDropdown({ unreadCount, notifications }: NotificationDropdownProps) {
    const handleMarkAsRead = React.useCallback((id: string) => {
        router.post(`/notifications/${id}/read`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const handleMarkAllAsRead = React.useCallback(() => {
        router.post('/notifications/read-all', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    return (
        <Dropdown>
            <DropdownTrigger>
                <button className="relative rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            </DropdownTrigger>
            <DropdownContent align="right" width="lg" className="max-h-[420px] overflow-hidden p-0">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-white/[0.06] px-4 py-3">
                    <h3 className="font-semibold text-foreground">Notifications</h3>
                    {unreadCount > 0 && (
                        <button
                            onClick={handleMarkAllAsRead}
                            className="text-xs font-medium text-primary hover:text-primary-hover"
                        >
                            Mark all as read
                        </button>
                    )}
                </div>

                {/* Notifications List */}
                <div className="max-h-[280px] overflow-y-auto p-1.5">
                    {notifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-background-tertiary">
                                <Bell className="h-6 w-6 text-foreground-muted" />
                            </div>
                            <p className="mt-3 text-sm font-medium text-foreground">No notifications</p>
                            <p className="mt-1 text-xs text-foreground-muted">You're all caught up!</p>
                        </div>
                    ) : (
                        <div className="space-y-0.5">
                            {notifications.map((notification) => (
                                <NotificationItemCompact
                                    key={notification.id}
                                    notification={notification}
                                    onMarkAsRead={handleMarkAsRead}
                                />
                            ))}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <DropdownDivider className="my-0" />
                <Link
                    href="/notifications"
                    className="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-foreground-muted transition-colors hover:bg-white/[0.04] hover:text-foreground"
                >
                    View all notifications
                    <ChevronRight className="h-4 w-4" />
                </Link>
            </DropdownContent>
        </Dropdown>
    );
}
