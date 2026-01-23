import * as React from 'react';
import { cn } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/utils';
import {
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Info,
    Users,
    CreditCard,
    Shield,
    X,
} from 'lucide-react';
import type { Notification, NotificationType } from '@/types';

interface NotificationItemProps {
    notification: Notification;
    onMarkAsRead?: (id: string) => void;
    onDelete?: (id: string) => void;
}

const iconMap: Record<NotificationType, React.ReactNode> = {
    deployment_success: <CheckCircle2 className="h-5 w-5 text-primary" />,
    deployment_failure: <XCircle className="h-5 w-5 text-danger" />,
    team_invite: <Users className="h-5 w-5 text-info" />,
    billing_alert: <CreditCard className="h-5 w-5 text-warning" />,
    security_alert: <Shield className="h-5 w-5 text-danger" />,
    info: <Info className="h-5 w-5 text-foreground-muted" />,
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
    deployment_success: 'border-l-primary',
    deployment_failure: 'border-l-danger',
    team_invite: 'border-l-info',
    billing_alert: 'border-l-warning',
    security_alert: 'border-l-danger',
    info: 'border-l-border',
};

export const NotificationItem = React.forwardRef<HTMLDivElement, NotificationItemProps>(
    ({ notification, onMarkAsRead, onDelete }, ref) => {
        const [isDeleting, setIsDeleting] = React.useState(false);

        const handleDelete = (e: React.MouseEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDeleting(true);
            setTimeout(() => {
                onDelete?.(notification.id);
            }, 150);
        };

        const handleMarkAsRead = (e: React.SyntheticEvent) => {
            e.preventDefault();
            e.stopPropagation();
            if (!notification.isRead) {
                onMarkAsRead?.(notification.id);
            }
        };

        return (
            <div
                ref={ref}
                className={cn(
                    'group relative flex gap-4 rounded-lg border border-border bg-background-secondary p-4 transition-all duration-200',
                    !notification.isRead && 'border-l-4',
                    !notification.isRead && borderColorMap[notification.type],
                    isDeleting && 'translate-x-full opacity-0',
                    'hover:border-border/80 hover:bg-background-tertiary'
                )}
                onClick={handleMarkAsRead}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        handleMarkAsRead(e);
                    }
                }}
            >
                {/* Icon */}
                <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', bgColorMap[notification.type])}>
                    {iconMap[notification.type]}
                </div>

                {/* Content */}
                <div className="flex-1 space-y-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1">
                            <h4 className={cn('text-sm font-medium', notification.isRead ? 'text-foreground-muted' : 'text-foreground')}>
                                {notification.title}
                            </h4>
                            <p className="mt-1 text-sm text-foreground-muted">{notification.description}</p>
                        </div>

                        {/* Delete button */}
                        <button
                            onClick={handleDelete}
                            className="opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            aria-label="Delete notification"
                        >
                            <X className="h-4 w-4 text-foreground-muted hover:text-foreground" />
                        </button>
                    </div>

                    {/* Timestamp */}
                    <p className="text-xs text-foreground-subtle">{formatRelativeTime(notification.timestamp)}</p>

                    {/* Unread indicator */}
                    {!notification.isRead && (
                        <div className="absolute left-2 top-4 h-2 w-2 rounded-full bg-primary" />
                    )}
                </div>
            </div>
        );
    }
);
NotificationItem.displayName = 'NotificationItem';
