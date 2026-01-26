import { useState } from 'react';
import { Activity, Play, X, Variable, ChevronDown, ChevronRight } from 'lucide-react';

export function ActivityPanel() {
    const [isExpanded, setIsExpanded] = useState(false);

    const activities = [
        {
            id: 1,
            type: 'deployment',
            service: 'api-server',
            status: 'active',
            message: 'Deployment succeeded',
            time: '2 min ago',
            user: 'John Doe',
            details: 'Build completed in 45s, deployed to us-east4',
        },
        {
            id: 2,
            type: 'config',
            service: 'api-server',
            status: 'info',
            message: 'Variable updated',
            time: '15 min ago',
            user: 'John Doe',
            details: 'DATABASE_URL was modified',
        },
        {
            id: 3,
            type: 'deployment',
            service: 'postgres',
            status: 'active',
            message: 'Database restarted',
            time: '1 hour ago',
            user: 'System',
            details: 'Automatic restart after configuration change',
        },
        {
            id: 4,
            type: 'deployment',
            service: 'redis',
            status: 'warning',
            message: 'High memory usage',
            time: '3 hours ago',
            user: 'System',
            details: 'Memory usage exceeded 80% threshold',
        },
        {
            id: 5,
            type: 'deployment',
            service: 'api-server',
            status: 'error',
            message: 'Deployment failed',
            time: '1 day ago',
            user: 'Jane Smith',
            details: 'Build failed: npm install exited with code 1',
        },
    ];

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active':
                return 'bg-emerald-500';
            case 'warning':
                return 'bg-yellow-500';
            case 'error':
                return 'bg-red-500';
            default:
                return 'bg-blue-500';
        }
    };

    const getStatusIcon = (type: string, status: string) => {
        if (type === 'config') {
            return <Variable className="h-3.5 w-3.5" />;
        }
        switch (status) {
            case 'active':
                return <Play className="h-3.5 w-3.5" />;
            case 'error':
                return <X className="h-3.5 w-3.5" />;
            case 'warning':
                return <Activity className="h-3.5 w-3.5" />;
            default:
                return <Activity className="h-3.5 w-3.5" />;
        }
    };

    return (
        <div className="absolute bottom-4 right-4 z-10">
            <div className={`w-80 rounded-lg border border-border bg-background shadow-lg transition-all duration-200 ${isExpanded ? 'max-h-96' : 'max-h-[52px]'} overflow-hidden`}>
                {/* Header */}
                <button
                    onClick={() => setIsExpanded(!isExpanded)}
                    className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                >
                    <span className="flex items-center gap-2">
                        <Activity className="h-4 w-4" />
                        Activity
                        <span className="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-primary/20 px-1.5 text-xs font-medium text-primary">
                            {activities.length}
                        </span>
                    </span>
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`} />
                </button>

                {/* Activity List */}
                {isExpanded && (
                    <div className="max-h-72 overflow-y-auto border-t border-border">
                        <div className="p-2">
                            {activities.map((activity, index) => (
                                <div
                                    key={activity.id}
                                    className="group relative flex gap-3 rounded-lg p-2 hover:bg-background-secondary transition-colors cursor-pointer"
                                >
                                    {/* Timeline connector */}
                                    {index < activities.length - 1 && (
                                        <div className="absolute left-[19px] top-8 h-[calc(100%-8px)] w-px bg-border" />
                                    )}

                                    {/* Status indicator */}
                                    <div className={`relative z-10 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full ${getStatusColor(activity.status)}`}>
                                        {getStatusIcon(activity.type, activity.status)}
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium text-foreground truncate">{activity.message}</p>
                                            <span className="text-xs text-foreground-muted whitespace-nowrap">{activity.time}</span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-foreground-muted truncate">{activity.service}</p>
                                        <p className="mt-1 text-xs text-foreground-subtle line-clamp-2 group-hover:text-foreground-muted transition-colors">
                                            {activity.details}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* View All Link */}
                        <div className="border-t border-border p-2">
                            <button className="flex w-full items-center justify-center gap-1 rounded-md py-2 text-sm text-foreground-muted hover:bg-background-secondary hover:text-foreground transition-colors">
                                View all activity
                                <ChevronRight className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
