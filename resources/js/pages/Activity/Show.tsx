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

// Mock data for demo
const MOCK_ACTIVITY: ActivityLog = {
    id: '1',
    action: 'settings_updated',
    description: 'Updated environment variables',
    user: {
        name: 'Jane Smith',
        email: 'jane@example.com',
        avatar: undefined
    },
    resource: {
        type: 'application',
        name: 'staging-frontend',
        id: 'app-2'
    },
    timestamp: new Date(Date.now() - 1000 * 60 * 60).toISOString(),
};

const MOCK_RELATED: ActivityLog[] = [
    {
        id: '2',
        action: 'deployment_started',
        description: 'Started deployment for staging-frontend',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 45).toISOString(),
    },
    {
        id: '3',
        action: 'deployment_completed',
        description: 'Deployment completed successfully',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 40).toISOString(),
    },
];

// Mock before/after data for settings changes
const MOCK_CHANGES = {
    environment_variables: {
        before: {
            NODE_ENV: 'development',
            API_URL: 'http://localhost:3000',
            DEBUG: 'true',
        },
        after: {
            NODE_ENV: 'production',
            API_URL: 'https://api.example.com',
            DEBUG: 'false',
            NEW_FEATURE_FLAG: 'enabled',
        },
    },
};

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
    const activity = propActivity || MOCK_ACTIVITY;
    const relatedActivities = propRelated || MOCK_RELATED;

    const initials = activity.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    // For settings changes, show before/after diff
    const showDiff = activity.action === 'settings_updated' || activity.action === 'environment_variable_updated';

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

                    {/* Changes Diff (for settings updates) */}
                    {showDiff && (
                        <Card>
                            <CardContent>
                                <h2 className="mb-4 text-lg font-semibold text-foreground">Changes</h2>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {/* Before */}
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary">Before</Badge>
                                        </div>
                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <pre className="text-sm text-foreground-muted">
                                                {JSON.stringify(MOCK_CHANGES.environment_variables.before, null, 2)}
                                            </pre>
                                        </div>
                                    </div>

                                    {/* After */}
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="default">After</Badge>
                                        </div>
                                        <div className="rounded-lg border border-primary bg-primary/5 p-4">
                                            <pre className="text-sm text-foreground">
                                                {JSON.stringify(MOCK_CHANGES.environment_variables.after, null, 2)}
                                            </pre>
                                        </div>
                                    </div>
                                </div>

                                {/* Change Summary */}
                                <div className="mt-4 space-y-2">
                                    <h3 className="text-sm font-semibold text-foreground">Summary</h3>
                                    <ul className="space-y-1 text-sm text-foreground-muted">
                                        <li className="flex items-center gap-2">
                                            <Badge variant="success" className="text-xs">Added</Badge>
                                            <code className="rounded bg-background-secondary px-1 py-0.5">NEW_FEATURE_FLAG</code>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Badge variant="warning" className="text-xs">Modified</Badge>
                                            <code className="rounded bg-background-secondary px-1 py-0.5">NODE_ENV</code>,
                                            <code className="rounded bg-background-secondary px-1 py-0.5">API_URL</code>,
                                            <code className="rounded bg-background-secondary px-1 py-0.5">DEBUG</code>
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    )}
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
