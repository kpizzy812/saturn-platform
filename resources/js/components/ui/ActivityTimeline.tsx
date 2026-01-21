import * as React from 'react';
import { Link } from '@inertiajs/react';
import { cn, formatRelativeTime } from '@/lib/utils';
import type { ActivityLog, ActivityAction } from '@/types';
import {
    Rocket,
    Settings,
    Users,
    Database,
    Server,
    GitBranch,
    Trash2,
    Play,
    Pause,
    RefreshCw,
    UserPlus,
    UserMinus,
    Key,
} from 'lucide-react';
import { Button } from './Button';

interface ActivityTimelineProps {
    activities: ActivityLog[];
    showDateSeparators?: boolean;
    onLoadMore?: () => void;
    hasMore?: boolean;
    loading?: boolean;
}

const actionIcons: Record<ActivityAction, React.ReactNode> = {
    deployment_started: <Rocket className="h-4 w-4 text-info" />,
    deployment_completed: <Rocket className="h-4 w-4 text-primary" />,
    deployment_failed: <Rocket className="h-4 w-4 text-danger" />,
    settings_updated: <Settings className="h-4 w-4 text-foreground-muted" />,
    team_member_added: <UserPlus className="h-4 w-4 text-primary" />,
    team_member_removed: <UserMinus className="h-4 w-4 text-danger" />,
    database_created: <Database className="h-4 w-4 text-primary" />,
    database_deleted: <Trash2 className="h-4 w-4 text-danger" />,
    server_connected: <Server className="h-4 w-4 text-primary" />,
    server_disconnected: <Server className="h-4 w-4 text-danger" />,
    application_started: <Play className="h-4 w-4 text-primary" />,
    application_stopped: <Pause className="h-4 w-4 text-warning" />,
    application_restarted: <RefreshCw className="h-4 w-4 text-info" />,
    environment_variable_updated: <Key className="h-4 w-4 text-foreground-muted" />,
};

const actionBadgeColor: Record<ActivityAction, string> = {
    deployment_started: 'bg-info/10 text-info',
    deployment_completed: 'bg-primary/10 text-primary',
    deployment_failed: 'bg-danger/10 text-danger',
    settings_updated: 'bg-foreground-muted/10 text-foreground-muted',
    team_member_added: 'bg-primary/10 text-primary',
    team_member_removed: 'bg-danger/10 text-danger',
    database_created: 'bg-primary/10 text-primary',
    database_deleted: 'bg-danger/10 text-danger',
    server_connected: 'bg-primary/10 text-primary',
    server_disconnected: 'bg-danger/10 text-danger',
    application_started: 'bg-primary/10 text-primary',
    application_stopped: 'bg-warning/10 text-warning',
    application_restarted: 'bg-info/10 text-info',
    environment_variable_updated: 'bg-foreground-muted/10 text-foreground-muted',
};

function getResourceIcon(type: ActivityLog['resource']['type']) {
    switch (type) {
        case 'project':
            return <GitBranch className="h-3.5 w-3.5" />;
        case 'application':
            return <Rocket className="h-3.5 w-3.5" />;
        case 'database':
            return <Database className="h-3.5 w-3.5" />;
        case 'server':
            return <Server className="h-3.5 w-3.5" />;
        case 'team':
            return <Users className="h-3.5 w-3.5" />;
        default:
            return null;
    }
}

function groupActivitiesByDate(activities: ActivityLog[]) {
    const groups: Record<string, ActivityLog[]> = {
        Today: [],
        Yesterday: [],
        'This Week': [],
        Earlier: [],
    };

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

    activities.forEach((activity) => {
        const date = new Date(activity.timestamp);
        if (date >= today) {
            groups.Today.push(activity);
        } else if (date >= yesterday) {
            groups.Yesterday.push(activity);
        } else if (date >= weekAgo) {
            groups['This Week'].push(activity);
        } else {
            groups.Earlier.push(activity);
        }
    });

    return Object.entries(groups).filter(([_, items]) => items.length > 0);
}

export const ActivityTimeline = React.forwardRef<HTMLDivElement, ActivityTimelineProps>(
    ({ activities, showDateSeparators = false, onLoadMore, hasMore = false, loading = false }, ref) => {
        const groupedActivities = showDateSeparators
            ? groupActivitiesByDate(activities)
            : [['All', activities]];

        return (
            <div ref={ref} className="space-y-6">
                {groupedActivities.map(([group, items]) => (
                    <div key={group}>
                        {showDateSeparators && (
                            <div className="sticky top-0 z-10 mb-4 bg-background py-2">
                                <h2 className="text-sm font-semibold text-foreground-muted">{group}</h2>
                            </div>
                        )}
                        <div className="space-y-0">
                            {items.map((activity, index) => (
                                <ActivityTimelineItem
                                    key={activity.id}
                                    activity={activity}
                                    isLast={index === items.length - 1 && !hasMore}
                                />
                            ))}
                        </div>
                    </div>
                ))}

                {hasMore && (
                    <div className="flex justify-center pt-4">
                        <Button
                            variant="secondary"
                            onClick={onLoadMore}
                            disabled={loading}
                        >
                            {loading ? 'Loading...' : 'Load More'}
                        </Button>
                    </div>
                )}
            </div>
        );
    }
);
ActivityTimeline.displayName = 'ActivityTimeline';

interface ActivityTimelineItemProps {
    activity: ActivityLog;
    isLast: boolean;
}

function ActivityTimelineItem({ activity, isLast }: ActivityTimelineItemProps) {
    const initials = activity.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <Link
            href={`/activity/${activity.id}`}
            className="group relative flex gap-4 p-4 transition-colors hover:bg-background-tertiary"
        >
            {/* Timeline line */}
            {!isLast && (
                <div className="absolute left-8 top-14 h-full w-px bg-border" />
            )}

            {/* Timeline dot with user avatar */}
            <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white shadow-sm ring-4 ring-background">
                {activity.user.avatar ? (
                    <img
                        src={activity.user.avatar}
                        alt={activity.user.name}
                        className="h-full w-full rounded-full object-cover"
                    />
                ) : (
                    initials
                )}
            </div>

            {/* Content */}
            <div className="flex-1 space-y-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-foreground group-hover:text-primary transition-colors">
                                {activity.user.name}
                            </span>
                            <div className="flex items-center gap-1.5">
                                <div className={cn(
                                    'flex items-center gap-1.5 rounded-md px-2 py-0.5',
                                    actionBadgeColor[activity.action]
                                )}>
                                    {actionIcons[activity.action]}
                                    <span className="text-sm">
                                        {activity.description}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Resource Link */}
                        {activity.resource && (
                            <div
                                className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-background-secondary px-2 py-1 text-sm text-foreground-muted hover:border-primary hover:text-primary transition-colors"
                                onClick={(e) => e.stopPropagation()}
                            >
                                {getResourceIcon(activity.resource.type)}
                                <span>{activity.resource.name}</span>
                            </div>
                        )}
                    </div>

                    {/* Timestamp */}
                    <span className="text-xs text-foreground-subtle">
                        {formatRelativeTime(activity.timestamp)}
                    </span>
                </div>
            </div>
        </Link>
    );
}
