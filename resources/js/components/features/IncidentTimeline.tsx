import * as React from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import {
    AlertTriangle,
    AlertCircle,
    CheckCircle2,
    Info,
    Rocket,
    RotateCcw,
    Bell,
    Activity,
    Clock,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    Lightbulb,
    TrendingUp,
    Zap,
    RefreshCw,
} from 'lucide-react';

// Types
interface TimelineEvent {
    id: string;
    type: 'status_change' | 'deployment' | 'alert' | 'rollback' | 'action' | 'metric';
    severity: 'critical' | 'warning' | 'info' | 'success';
    timestamp: string;
    title: string;
    description: string;
    metadata: Record<string, unknown>;
    actions?: Array<{
        label: string;
        action: string;
        params?: Record<string, unknown>;
    }>;
}

interface Incident {
    id: string;
    started_at: string;
    ended_at: string;
    events: string[];
    severity: 'critical' | 'warning';
    duration_seconds: number;
}

interface RootCause {
    primary: {
        type: string;
        confidence: number;
        description: string;
        suggestion: string;
        related_event: string;
    };
    contributing?: Array<{
        type: string;
        confidence: number;
        description: string;
        suggestion: string;
        related_event: string;
    }>;
}

interface TimelineSummary {
    total_events: number;
    critical_events: number;
    warning_events: number;
    incidents_count: number;
    deployments: {
        total: number;
        failed: number;
    };
    health_status: 'healthy' | 'warning' | 'degraded' | 'critical';
}

interface IncidentTimelineData {
    events: TimelineEvent[];
    incidents: Incident[];
    root_cause: RootCause | null;
    summary: TimelineSummary;
    period: {
        from: string;
        to: string;
    };
}

interface IncidentTimelineProps {
    applicationUuid: string;
    className?: string;
    compact?: boolean;
    onEventClick?: (event: TimelineEvent) => void;
}

// Severity config
const severityConfig = {
    critical: {
        icon: AlertCircle,
        color: 'text-danger',
        bgColor: 'bg-danger/10',
        borderColor: 'border-danger/30',
        label: 'Critical',
    },
    warning: {
        icon: AlertTriangle,
        color: 'text-warning',
        bgColor: 'bg-warning/10',
        borderColor: 'border-warning/30',
        label: 'Warning',
    },
    info: {
        icon: Info,
        color: 'text-info',
        bgColor: 'bg-info/10',
        borderColor: 'border-info/30',
        label: 'Info',
    },
    success: {
        icon: CheckCircle2,
        color: 'text-success',
        bgColor: 'bg-success/10',
        borderColor: 'border-success/30',
        label: 'Success',
    },
};

// Type icons
const typeIcons: Record<TimelineEvent['type'], React.ComponentType<{ className?: string }>> = {
    status_change: Activity,
    deployment: Rocket,
    alert: Bell,
    rollback: RotateCcw,
    action: Zap,
    metric: TrendingUp,
};

// Health status config
const healthStatusConfig = {
    healthy: { color: 'text-success', bgColor: 'bg-success/10', label: 'Healthy' },
    warning: { color: 'text-warning', bgColor: 'bg-warning/10', label: 'Warning' },
    degraded: { color: 'text-warning', bgColor: 'bg-warning/10', label: 'Degraded' },
    critical: { color: 'text-danger', bgColor: 'bg-danger/10', label: 'Critical' },
};

// Format duration
function formatDuration(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${mins}m`;
}

// Format relative time
function formatRelativeTime(timestamp: string): string {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);

    if (seconds < 60) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return date.toLocaleDateString();
}

// Timeline Event Component
function TimelineEventItem({
    event,
    isInIncident,
    onClick,
}: {
    event: TimelineEvent;
    isInIncident: boolean;
    onClick?: () => void;
}) {
    const [expanded, setExpanded] = React.useState(false);
    const config = severityConfig[event.severity];
    const TypeIcon = typeIcons[event.type] || Activity;
    const SeverityIcon = config.icon;

    return (
        <div
            className={cn(
                'relative flex gap-4 pb-6 group',
                isInIncident && 'bg-danger/5 -mx-4 px-4 rounded-lg'
            )}
        >
            {/* Timeline line */}
            <div className="absolute left-[19px] top-10 bottom-0 w-0.5 bg-border group-last:hidden" />

            {/* Icon */}
            <div
                className={cn(
                    'relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2',
                    config.bgColor,
                    config.borderColor
                )}
            >
                <TypeIcon className={cn('h-4 w-4', config.color)} />
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div
                    className="flex items-start justify-between gap-2 cursor-pointer"
                    onClick={() => setExpanded(!expanded)}
                >
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-medium text-foreground">{event.title}</span>
                            <Badge
                                variant="outline"
                                className={cn('text-xs', config.color, config.bgColor)}
                            >
                                {config.label}
                            </Badge>
                            {isInIncident && (
                                <Badge variant="outline" className="text-xs text-danger bg-danger/10">
                                    In Incident
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-foreground-muted mt-1 line-clamp-1">
                            {event.description}
                        </p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <span className="text-xs text-foreground-muted">
                            {formatRelativeTime(event.timestamp)}
                        </span>
                        {expanded ? (
                            <ChevronDown className="h-4 w-4 text-foreground-muted" />
                        ) : (
                            <ChevronRight className="h-4 w-4 text-foreground-muted" />
                        )}
                    </div>
                </div>

                {/* Expanded details */}
                {expanded && (
                    <div className="mt-3 p-3 rounded-lg bg-background-secondary border border-border">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-foreground-muted" />
                                <span className="text-foreground-muted">
                                    {new Date(event.timestamp).toLocaleString()}
                                </span>
                            </div>
                            <p className="text-foreground-muted">{event.description}</p>

                            {/* Metadata */}
                            {Object.keys(event.metadata).length > 0 && (
                                <div className="mt-2 pt-2 border-t border-border">
                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        {Object.entries(event.metadata).map(([key, value]) => (
                                            <div key={key}>
                                                <span className="text-foreground-muted">{key}: </span>
                                                <span className="text-foreground">
                                                    {typeof value === 'object' ? JSON.stringify(value) : String(value)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Actions */}
                            {event.actions && event.actions.length > 0 && (
                                <div className="mt-3 flex gap-2">
                                    {event.actions.map((action, idx) => (
                                        <Button
                                            key={idx}
                                            variant="outline"
                                            size="sm"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onClick?.();
                                            }}
                                        >
                                            {action.label}
                                            <ExternalLink className="ml-1 h-3 w-3" />
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

// Root Cause Analysis Component
function RootCauseAnalysis({ rootCause }: { rootCause: RootCause }) {
    return (
        <Card className="border-warning/30 bg-warning/5">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Lightbulb className="h-5 w-5 text-warning" />
                    Root Cause Analysis
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Primary cause */}
                <div className="p-3 rounded-lg bg-background border border-border">
                    <div className="flex items-start justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {rootCause.primary.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                </span>
                                <Badge variant="outline" className="text-xs">
                                    {Math.round(rootCause.primary.confidence * 100)}% confidence
                                </Badge>
                            </div>
                            <p className="text-sm text-foreground-muted mt-1">
                                {rootCause.primary.description}
                            </p>
                        </div>
                    </div>
                    <div className="mt-3 p-2 rounded bg-primary/10 border border-primary/20">
                        <div className="flex items-start gap-2">
                            <Lightbulb className="h-4 w-4 text-primary mt-0.5 shrink-0" />
                            <span className="text-sm text-foreground">
                                <strong>Suggestion:</strong> {rootCause.primary.suggestion}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Contributing causes */}
                {rootCause.contributing && rootCause.contributing.length > 0 && (
                    <div>
                        <h4 className="text-sm font-medium text-foreground-muted mb-2">
                            Contributing Factors
                        </h4>
                        <div className="space-y-2">
                            {rootCause.contributing.map((cause, idx) => (
                                <div
                                    key={idx}
                                    className="p-2 rounded bg-background-secondary border border-border text-sm"
                                >
                                    <span className="font-medium">{cause.type.replace(/_/g, ' ')}</span>
                                    <span className="text-foreground-muted ml-2">
                                        ({Math.round(cause.confidence * 100)}%)
                                    </span>
                                    <p className="text-foreground-muted text-xs mt-1">{cause.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// Summary Stats Component
function SummaryStats({ summary }: { summary: TimelineSummary }) {
    const healthConfig = healthStatusConfig[summary.health_status];

    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {/* Health Status */}
            <div className={cn('p-4 rounded-lg border', healthConfig.bgColor, 'border-border')}>
                <div className="flex items-center gap-2">
                    <Activity className={cn('h-5 w-5', healthConfig.color)} />
                    <span className="text-sm text-foreground-muted">Health</span>
                </div>
                <p className={cn('text-lg font-semibold mt-1', healthConfig.color)}>
                    {healthConfig.label}
                </p>
            </div>

            {/* Incidents */}
            <div className="p-4 rounded-lg border border-border bg-background-secondary">
                <div className="flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5 text-warning" />
                    <span className="text-sm text-foreground-muted">Incidents</span>
                </div>
                <p className="text-lg font-semibold mt-1 text-foreground">
                    {summary.incidents_count}
                </p>
            </div>

            {/* Critical Events */}
            <div className="p-4 rounded-lg border border-border bg-background-secondary">
                <div className="flex items-center gap-2">
                    <AlertCircle className="h-5 w-5 text-danger" />
                    <span className="text-sm text-foreground-muted">Critical</span>
                </div>
                <p className="text-lg font-semibold mt-1 text-foreground">
                    {summary.critical_events}
                </p>
            </div>

            {/* Deployments */}
            <div className="p-4 rounded-lg border border-border bg-background-secondary">
                <div className="flex items-center gap-2">
                    <Rocket className="h-5 w-5 text-primary" />
                    <span className="text-sm text-foreground-muted">Deploys</span>
                </div>
                <p className="text-lg font-semibold mt-1 text-foreground">
                    {summary.deployments.total}
                    {summary.deployments.failed > 0 && (
                        <span className="text-sm text-danger ml-1">
                            ({summary.deployments.failed} failed)
                        </span>
                    )}
                </p>
            </div>
        </div>
    );
}

// Main Component
export function IncidentTimeline({
    applicationUuid,
    className,
    compact = false,
    onEventClick,
}: IncidentTimelineProps) {
    const [data, setData] = React.useState<IncidentTimelineData | null>(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);
    const [timeRange, setTimeRange] = React.useState(24); // hours

    // Fetch timeline data
    const fetchTimeline = React.useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/applications/${applicationUuid}/incidents?hours=${timeRange}&limit=100`,
                { headers: { Accept: 'application/json' } }
            );

            if (!response.ok) {
                throw new Error('Failed to fetch incident timeline');
            }

            const result = await response.json();
            setData(result);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setLoading(false);
        }
    }, [applicationUuid, timeRange]);

    React.useEffect(() => {
        fetchTimeline();
    }, [fetchTimeline]);

    // Get incident event IDs for highlighting
    const incidentEventIds = React.useMemo(() => {
        if (!data?.incidents) return new Set<string>();
        return new Set(data.incidents.flatMap((i) => i.events));
    }, [data?.incidents]);

    if (loading) {
        return (
            <div className={cn('flex items-center justify-center py-12', className)}>
                <Spinner size="lg" />
            </div>
        );
    }

    if (error) {
        return (
            <Card className={cn('border-danger/30', className)}>
                <CardContent className="py-8 text-center">
                    <AlertCircle className="h-8 w-8 text-danger mx-auto mb-3" />
                    <p className="text-foreground-muted">{error}</p>
                    <Button variant="outline" size="sm" className="mt-4" onClick={fetchTimeline}>
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Retry
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (!data) return null;

    return (
        <div className={cn('space-y-6', className)}>
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-foreground">Incident Timeline</h2>
                    <p className="text-sm text-foreground-muted">
                        {data.summary.total_events} events in the last {timeRange} hours
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={timeRange}
                        onChange={(e) => setTimeRange(Number(e.target.value))}
                        className="px-3 py-1.5 text-sm rounded-lg border border-border bg-background-secondary text-foreground"
                    >
                        <option value={1}>Last 1 hour</option>
                        <option value={6}>Last 6 hours</option>
                        <option value={24}>Last 24 hours</option>
                        <option value={72}>Last 3 days</option>
                        <option value={168}>Last 7 days</option>
                    </select>
                    <Button variant="outline" size="sm" onClick={fetchTimeline}>
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Summary Stats */}
            {!compact && <SummaryStats summary={data.summary} />}

            {/* Root Cause Analysis */}
            {data.root_cause && !compact && <RootCauseAnalysis rootCause={data.root_cause} />}

            {/* Active Incidents */}
            {data.incidents.length > 0 && (
                <Card className="border-danger/30">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-danger">
                            <AlertTriangle className="h-5 w-5" />
                            Active Incidents ({data.incidents.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {data.incidents.map((incident) => (
                                <div
                                    key={incident.id}
                                    className="p-3 rounded-lg bg-danger/10 border border-danger/20"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    incident.severity === 'critical'
                                                        ? 'text-danger bg-danger/10'
                                                        : 'text-warning bg-warning/10'
                                                )}
                                            >
                                                {incident.severity}
                                            </Badge>
                                            <span className="text-sm text-foreground">
                                                {incident.events.length} related events
                                            </span>
                                        </div>
                                        <span className="text-xs text-foreground-muted">
                                            Duration: {formatDuration(incident.duration_seconds)}
                                        </span>
                                    </div>
                                    <div className="mt-2 text-xs text-foreground-muted">
                                        Started: {new Date(incident.started_at).toLocaleString()}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Timeline Events */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base">Event Timeline</CardTitle>
                </CardHeader>
                <CardContent>
                    {data.events.length === 0 ? (
                        <div className="py-8 text-center">
                            <CheckCircle2 className="h-8 w-8 text-success mx-auto mb-3" />
                            <p className="text-foreground-muted">No events in this time period</p>
                            <p className="text-sm text-foreground-muted mt-1">
                                Your application is running smoothly
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-0">
                            {data.events.map((event) => (
                                <TimelineEventItem
                                    key={event.id}
                                    event={event}
                                    isInIncident={incidentEventIds.has(event.id)}
                                    onClick={() => onEventClick?.(event)}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default IncidentTimeline;
