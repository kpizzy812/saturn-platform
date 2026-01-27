import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import { formatRelativeTime } from '@/lib/utils';
import type { ActivityLog, ActivityAction } from '@/types';
import {
    Activity,
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
    ArrowLeft,
    Clock,
    User,
    ChevronRight,
} from 'lucide-react';

interface Props {
    activity?: ActivityLog;
    relatedActivities?: ActivityLog[];
}


const actionIcons: Record<ActivityAction, React.ReactNode> = {
    deployment_started: <Rocket className="h-5 w-5 text-info" />,
    deployment_completed: <Rocket className="h-5 w-5 text-primary" />,
    deployment_failed: <Rocket className="h-5 w-5 text-danger" />,
    settings_updated: <Settings className="h-5 w-5 text-foreground-muted" />,
    team_member_added: <UserPlus className="h-5 w-5 text-primary" />,
    team_member_removed: <UserMinus className="h-5 w-5 text-danger" />,
    database_created: <Database className="h-5 w-5 text-primary" />,
    database_deleted: <Trash2 className="h-5 w-5 text-danger" />,
    server_connected: <Server className="h-5 w-5 text-primary" />,
    server_disconnected: <Server className="h-5 w-5 text-danger" />,
    application_started: <Play className="h-5 w-5 text-primary" />,
    application_stopped: <Pause className="h-5 w-5 text-warning" />,
    application_restarted: <RefreshCw className="h-5 w-5 text-info" />,
    environment_variable_updated: <Key className="h-5 w-5 text-foreground-muted" />,
};

function getResourceIcon(type: ActivityLog['resource']['type']) {
    switch (type) {
        case 'project':
            return <GitBranch className="h-4 w-4" />;
        case 'application':
            return <Rocket className="h-4 w-4" />;
        case 'database':
            return <Database className="h-4 w-4" />;
        case 'server':
            return <Server className="h-4 w-4" />;
        case 'team':
            return <Users className="h-4 w-4" />;
        default:
            return null;
    }
}

export default function ActivityShow({ activity: propActivity, relatedActivities: propRelated }: Props) {
    const activity = propActivity;
    const relatedActivities = propRelated || [];

    if (!activity) {
        return (
            <AppLayout title="Activity Details" breadcrumbs={[{ label: 'Activity', href: '/activity' }, { label: 'Details' }]}>
                <div className="flex flex-col items-center justify-center py-16">
                    <Activity className="h-12 w-12 text-foreground-muted" />
                    <h3 className="mt-4 text-lg font-medium text-foreground">Activity not found</h3>
                    <Link href="/activity">
                        <Button variant="secondary" size="sm" className="mt-4">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Activity
                        </Button>
                    </Link>
                </div>
            </AppLayout>
        );
    }

    const initials = activity.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <AppLayout
            title="Activity Details"
            breadcrumbs={[
                { label: 'Activity', href: '/activity' },
                { label: 'Details' },
            ]}
        >
            {/* Back Button */}
            <div className="mb-6">
                <Link href="/activity">
                    <Button variant="secondary" size="sm">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Activity
                    </Button>
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Content */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Activity Header */}
                    <Card>
                        <CardContent>
                            <div className="flex gap-4">
                                {/* User Avatar */}
                                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-lg font-medium text-white shadow-lg">
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
                                <div className="flex-1 space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="rounded-lg bg-background-secondary p-2">
                                            {actionIcons[activity.action]}
                                        </div>
                                        <div className="flex-1">
                                            <h1 className="text-xl font-bold text-foreground">
                                                {activity.description}
                                            </h1>
                                            <div className="mt-1 flex items-center gap-2 text-sm text-foreground-muted">
                                                <User className="h-4 w-4" />
                                                <span>{activity.user.name}</span>
                                                <span>({activity.user.email})</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Timestamp */}
                                    <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                        <Clock className="h-4 w-4" />
                                        <span>{formatRelativeTime(activity.timestamp)}</span>
                                        <span className="text-foreground-subtle">
                                            ({new Date(activity.timestamp).toLocaleString()})
                                        </span>
                                    </div>

                                    {/* Resource Link */}
                                    {activity.resource && (
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-foreground-muted">Resource:</span>
                                            <Link
                                                href={`/${activity.resource.type}s/${activity.resource.id}`}
                                                className="inline-flex items-center gap-2 rounded-md border border-border bg-background-secondary px-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-primary hover:text-primary"
                                            >
                                                {getResourceIcon(activity.resource.type)}
                                                {activity.resource.name}
                                                <ChevronRight className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Action Badge */}
                    <Card>
                        <CardContent>
                            <h3 className="mb-3 text-sm font-semibold text-foreground-muted">Action Type</h3>
                            <Badge variant="default" className="text-sm">
                                {activity.action.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                            </Badge>
                        </CardContent>
                    </Card>

                    {/* Related Activities */}
                    {relatedActivities.length > 0 && (
                        <Card>
                            <CardContent>
                                <h3 className="mb-4 text-sm font-semibold text-foreground">
                                    Related Activities
                                </h3>
                                <ActivityTimeline
                                    activities={relatedActivities}
                                    showDateSeparators={false}
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
