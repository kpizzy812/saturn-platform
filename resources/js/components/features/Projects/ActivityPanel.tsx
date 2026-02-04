import { useState } from 'react';
import { Activity, Play, X, ChevronDown, ChevronRight, AlertTriangle, Loader2, Settings, Server, Database, Users, Rocket, Square, RotateCcw, Key } from 'lucide-react';
import { useTeamActivity } from '@/hooks/useTeamActivity';
import type { ActivityLog, ActivityAction } from '@/types';

function getActionIcon(action: ActivityAction) {
    switch (action) {
        case 'deployment_started':
            return <Rocket className="h-3.5 w-3.5" />;
        case 'deployment_completed':
            return <Play className="h-3.5 w-3.5" />;
        case 'deployment_failed':
            return <X className="h-3.5 w-3.5" />;
        case 'settings_updated':
            return <Settings className="h-3.5 w-3.5" />;
        case 'team_member_added':
        case 'team_member_removed':
            return <Users className="h-3.5 w-3.5" />;
        case 'database_created':
        case 'database_deleted':
            return <Database className="h-3.5 w-3.5" />;
        case 'server_connected':
        case 'server_disconnected':
            return <Server className="h-3.5 w-3.5" />;
        case 'application_started':
            return <Play className="h-3.5 w-3.5" />;
        case 'application_stopped':
            return <Square className="h-3.5 w-3.5" />;
        case 'application_restarted':
            return <RotateCcw className="h-3.5 w-3.5" />;
        case 'environment_variable_updated':
            return <Key className="h-3.5 w-3.5" />;
        default:
            return <Activity className="h-3.5 w-3.5" />;
    }
}

function getActionColor(action: ActivityAction): string {
    if (action.includes('failed') || action.includes('deleted') || action.includes('stopped') || action.includes('disconnected') || action.includes('removed')) {
        return 'bg-red-500';
    }
    if (action.includes('started') || action.includes('completed') || action.includes('created') || action.includes('connected') || action.includes('added')) {
        return 'bg-emerald-500';
    }
    if (action.includes('restarted')) {
        return 'bg-yellow-500';
    }
    return 'bg-blue-500';
}

function formatRelativeTime(timestamp: string): string {
    const now = new Date();
    const date = new Date(timestamp);
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHours = Math.floor(diffMin / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}

export function ActivityPanel() {
    const [isExpanded, setIsExpanded] = useState(false);
    const { activities, loading, error, meta } = useTeamActivity({
        perPage: 10,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    const count = meta.total || activities.length;

    return (
        <div className="absolute bottom-4 right-20 z-10">
            <div className={`w-80 rounded-lg border border-border bg-background shadow-lg transition-all duration-200 ${isExpanded ? 'max-h-96' : 'max-h-[52px]'} overflow-hidden`}>
                {/* Header */}
                <button
                    onClick={() => setIsExpanded(!isExpanded)}
                    className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                >
                    <span className="flex items-center gap-2">
                        <Activity className="h-4 w-4" />
                        Activity
                        {count > 0 && (
                            <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-primary/20 px-1.5 text-xs font-medium text-primary">
                                {count > 99 ? '99+' : count}
                            </span>
                        )}
                    </span>
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`} />
                </button>

                {/* Activity List */}
                {isExpanded && (
                    <div className="max-h-72 overflow-y-auto border-t border-border">
                        {loading && activities.length === 0 ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-5 w-5 animate-spin text-foreground-muted" />
                            </div>
                        ) : error ? (
                            <div className="flex flex-col items-center justify-center py-8 text-foreground-muted">
                                <AlertTriangle className="h-5 w-5 mb-2 text-yellow-500" />
                                <p className="text-xs">Failed to load activity</p>
                            </div>
                        ) : activities.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-foreground-muted">
                                <Activity className="h-5 w-5 mb-2 opacity-50" />
                                <p className="text-xs">No recent activity</p>
                            </div>
                        ) : (
                            <div className="p-2">
                                {activities.map((activity: ActivityLog, index: number) => (
                                    <div
                                        key={activity.id}
                                        className="group relative flex gap-3 rounded-lg p-2 hover:bg-background-secondary transition-colors cursor-pointer"
                                    >
                                        {/* Timeline connector */}
                                        {index < activities.length - 1 && (
                                            <div className="absolute left-[19px] top-8 h-[calc(100%-8px)] w-px bg-border" />
                                        )}

                                        {/* Status indicator */}
                                        <div className={`relative z-10 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-white ${getActionColor(activity.action)}`}>
                                            {getActionIcon(activity.action)}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-medium text-foreground truncate">{activity.description}</p>
                                                <span className="text-xs text-foreground-muted whitespace-nowrap">{formatRelativeTime(activity.timestamp)}</span>
                                            </div>
                                            {activity.resource && (
                                                <p className="mt-0.5 text-xs text-foreground-muted truncate">{activity.resource.name}</p>
                                            )}
                                            <p className="mt-0.5 text-xs text-foreground-subtle truncate group-hover:text-foreground-muted transition-colors">
                                                {activity.user.name}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* View All Link */}
                        {activities.length > 0 && (
                            <div className="border-t border-border p-2">
                                <a
                                    href="/activity"
                                    className="flex w-full items-center justify-center gap-1 rounded-md py-2 text-sm text-foreground-muted hover:bg-background-secondary hover:text-foreground transition-colors"
                                >
                                    View all activity
                                    <ChevronRight className="h-3.5 w-3.5" />
                                </a>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
