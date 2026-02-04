import React, { useState } from 'react';
import {
    AlertTriangle,
    CheckCircle,
    XCircle,
    AlertCircle,
    ChevronDown,
    ChevronUp,
    Activity,
    FileCode,
    Gauge,
    Stethoscope,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    IssueSeverity,
    HealthStatus,
    AnalysisIssue,
} from '@/types/ai-chat';

interface AnalysisResultCardProps {
    intent: string;
    data: Record<string, unknown>;
}

/**
 * Get severity icon and color.
 */
function getSeverityStyle(severity: IssueSeverity) {
    return {
        critical: {
            icon: XCircle,
            color: 'text-red-500',
            bgColor: 'bg-red-500/10',
            borderColor: 'border-red-500/30',
        },
        high: {
            icon: AlertTriangle,
            color: 'text-orange-500',
            bgColor: 'bg-orange-500/10',
            borderColor: 'border-orange-500/30',
        },
        medium: {
            icon: AlertCircle,
            color: 'text-yellow-500',
            bgColor: 'bg-yellow-500/10',
            borderColor: 'border-yellow-500/30',
        },
        low: {
            icon: CheckCircle,
            color: 'text-green-500',
            bgColor: 'bg-green-500/10',
            borderColor: 'border-green-500/30',
        },
    }[severity] || {
        icon: AlertCircle,
        color: 'text-gray-500',
        bgColor: 'bg-gray-500/10',
        borderColor: 'border-gray-500/30',
    };
}

/**
 * Get health status icon and color.
 */
function getHealthStyle(health: HealthStatus) {
    return {
        healthy: {
            icon: CheckCircle,
            color: 'text-green-500',
            label: 'Healthy',
        },
        unhealthy: {
            icon: XCircle,
            color: 'text-red-500',
            label: 'Unhealthy',
        },
        degraded: {
            icon: AlertTriangle,
            color: 'text-yellow-500',
            label: 'Degraded',
        },
        unknown: {
            icon: AlertCircle,
            color: 'text-gray-500',
            label: 'Unknown',
        },
    }[health];
}

/**
 * Issue item component.
 */
function IssueItem({ issue }: { issue: AnalysisIssue }) {
    const style = getSeverityStyle(issue.severity);
    const Icon = style.icon;

    return (
        <div
            className={cn(
                'rounded-lg border p-3',
                style.bgColor,
                style.borderColor
            )}
        >
            <div className="flex items-start gap-2">
                <Icon className={cn('h-4 w-4 mt-0.5 flex-shrink-0', style.color)} />
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <span
                            className={cn(
                                'text-xs font-medium uppercase',
                                style.color
                            )}
                        >
                            {issue.severity}
                        </span>
                        {issue.line_number && (
                            <span className="text-xs text-foreground-muted">
                                Line {issue.line_number}
                            </span>
                        )}
                    </div>
                    <p className="text-sm text-foreground mt-1 break-words">
                        {issue.message}
                    </p>
                    {issue.suggestion && (
                        <p className="text-xs text-foreground-muted mt-1 italic">
                            {issue.suggestion}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Collapsible section component.
 */
function CollapsibleSection({
    title,
    icon: Icon,
    children,
    defaultOpen = false,
    badge,
}: {
    title: string;
    icon: React.ElementType;
    children: React.ReactNode;
    defaultOpen?: boolean;
    badge?: React.ReactNode;
}) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="border border-white/10 rounded-lg overflow-hidden">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex items-center justify-between p-3 bg-background-secondary/50 hover:bg-background-secondary transition-colors"
            >
                <div className="flex items-center gap-2">
                    <Icon className="h-4 w-4 text-foreground-muted" />
                    <span className="text-sm font-medium">{title}</span>
                    {badge}
                </div>
                {isOpen ? (
                    <ChevronUp className="h-4 w-4 text-foreground-muted" />
                ) : (
                    <ChevronDown className="h-4 w-4 text-foreground-muted" />
                )}
            </button>
            {isOpen && (
                <div className="p-3 border-t border-white/10">{children}</div>
            )}
        </div>
    );
}

/**
 * Error Analysis Card.
 */
function ErrorAnalysisCard({ data }: { data: Record<string, unknown> }) {
    const issues = (data.issues as AnalysisIssue[]) || [];
    const solutions = (data.solutions as string[]) || [];
    const errorsFound = (data.errors_found as number) || 0;

    const criticalCount = issues.filter((i) => i.severity === 'critical').length;
    const highCount = issues.filter((i) => i.severity === 'high').length;

    return (
        <div className="space-y-3">
            {/* Summary */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-primary" />
                    <span className="font-medium">Error Analysis</span>
                </div>
                <div className="flex items-center gap-2">
                    {criticalCount > 0 && (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-400">
                            {criticalCount} critical
                        </span>
                    )}
                    {highCount > 0 && (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-orange-500/20 text-orange-400">
                            {highCount} high
                        </span>
                    )}
                    {errorsFound === 0 && (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">
                            No errors
                        </span>
                    )}
                </div>
            </div>

            {/* Issues */}
            {issues.length > 0 && (
                <CollapsibleSection
                    title="Issues Found"
                    icon={AlertTriangle}
                    defaultOpen={true}
                    badge={
                        <span className="px-1.5 py-0.5 text-xs rounded bg-white/10">
                            {issues.length}
                        </span>
                    }
                >
                    <div className="space-y-2">
                        {issues.slice(0, 5).map((issue, i) => (
                            <IssueItem key={i} issue={issue} />
                        ))}
                        {issues.length > 5 && (
                            <p className="text-xs text-foreground-muted text-center">
                                +{issues.length - 5} more issues
                            </p>
                        )}
                    </div>
                </CollapsibleSection>
            )}

            {/* Solutions */}
            {solutions.length > 0 && (
                <CollapsibleSection
                    title="Recommended Actions"
                    icon={CheckCircle}
                    defaultOpen={issues.length > 0}
                >
                    <ol className="list-decimal list-inside space-y-1 text-sm">
                        {solutions.map((solution, i) => (
                            <li key={i} className="text-foreground-muted">
                                {solution}
                            </li>
                        ))}
                    </ol>
                </CollapsibleSection>
            )}
        </div>
    );
}

/**
 * Health Check Card.
 */
function HealthCheckCard({ data }: { data: Record<string, unknown> }) {
    const statuses = data.statuses as {
        healthy: number;
        unhealthy: number;
        degraded: number;
        unknown: number;
    };
    const resources = data.resources as Array<{
        name: string;
        type: string;
        status: string;
        health: HealthStatus;
        project?: string;
    }>;
    const healthyPercent = (data.healthy_percent as number) || 0;

    const unhealthyResources = resources?.filter((r) => r.health === 'unhealthy') || [];
    const degradedResources = resources?.filter((r) => r.health === 'degraded') || [];

    return (
        <div className="space-y-3">
            {/* Summary */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Stethoscope className="h-5 w-5 text-primary" />
                    <span className="font-medium">Health Check</span>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={cn(
                            'px-2 py-0.5 text-xs rounded-full',
                            healthyPercent >= 90
                                ? 'bg-green-500/20 text-green-400'
                                : healthyPercent >= 70
                                  ? 'bg-yellow-500/20 text-yellow-400'
                                  : 'bg-red-500/20 text-red-400'
                        )}
                    >
                        {healthyPercent}% healthy
                    </span>
                </div>
            </div>

            {/* Status Grid */}
            <div className="grid grid-cols-4 gap-2">
                {[
                    { label: 'Healthy', count: statuses?.healthy || 0, color: 'text-green-500' },
                    { label: 'Degraded', count: statuses?.degraded || 0, color: 'text-yellow-500' },
                    { label: 'Unhealthy', count: statuses?.unhealthy || 0, color: 'text-red-500' },
                    { label: 'Unknown', count: statuses?.unknown || 0, color: 'text-gray-500' },
                ].map((stat) => (
                    <div
                        key={stat.label}
                        className="text-center p-2 rounded bg-background-secondary/50"
                    >
                        <div className={cn('text-lg font-bold', stat.color)}>
                            {stat.count}
                        </div>
                        <div className="text-xs text-foreground-muted">{stat.label}</div>
                    </div>
                ))}
            </div>

            {/* Unhealthy Resources */}
            {unhealthyResources.length > 0 && (
                <CollapsibleSection
                    title="Unhealthy Resources"
                    icon={XCircle}
                    defaultOpen={true}
                    badge={
                        <span className="px-1.5 py-0.5 text-xs rounded bg-red-500/20 text-red-400">
                            {unhealthyResources.length}
                        </span>
                    }
                >
                    <div className="space-y-1">
                        {unhealthyResources.map((r, i) => (
                            <div
                                key={i}
                                className="flex items-center justify-between text-sm p-2 rounded bg-red-500/10"
                            >
                                <span className="font-medium">{r.name}</span>
                                <span className="text-xs text-foreground-muted">
                                    {r.type} - {r.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </CollapsibleSection>
            )}

            {/* Degraded Resources */}
            {degradedResources.length > 0 && (
                <CollapsibleSection
                    title="Degraded Resources"
                    icon={AlertTriangle}
                    badge={
                        <span className="px-1.5 py-0.5 text-xs rounded bg-yellow-500/20 text-yellow-400">
                            {degradedResources.length}
                        </span>
                    }
                >
                    <div className="space-y-1">
                        {degradedResources.map((r, i) => (
                            <div
                                key={i}
                                className="flex items-center justify-between text-sm p-2 rounded bg-yellow-500/10"
                            >
                                <span className="font-medium">{r.name}</span>
                                <span className="text-xs text-foreground-muted">
                                    {r.type} - {r.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </CollapsibleSection>
            )}
        </div>
    );
}

/**
 * Metrics Card.
 */
function MetricsCard({ data }: { data: Record<string, unknown> }) {
    const periodDays = (data.period_days as number) || 7;
    const totalDeployments = (data.total_deployments as number) || 0;
    const successfulDeployments = (data.successful_deployments as number) || 0;
    const failedDeployments = (data.failed_deployments as number) || 0;
    const successRate = (data.success_rate as number) || 0;

    return (
        <div className="space-y-3">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Gauge className="h-5 w-5 text-primary" />
                    <span className="font-medium">Deployment Metrics</span>
                </div>
                <span className="text-xs text-foreground-muted">
                    Last {periodDays} days
                </span>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-2">
                <div className="p-3 rounded-lg bg-background-secondary/50 text-center">
                    <div className="text-2xl font-bold text-foreground">
                        {totalDeployments}
                    </div>
                    <div className="text-xs text-foreground-muted">Total Deployments</div>
                </div>
                <div className="p-3 rounded-lg bg-background-secondary/50 text-center">
                    <div
                        className={cn(
                            'text-2xl font-bold',
                            successRate >= 90
                                ? 'text-green-500'
                                : successRate >= 70
                                  ? 'text-yellow-500'
                                  : 'text-red-500'
                        )}
                    >
                        {successRate}%
                    </div>
                    <div className="text-xs text-foreground-muted">Success Rate</div>
                </div>
                <div className="p-3 rounded-lg bg-green-500/10 text-center">
                    <div className="text-xl font-bold text-green-500">
                        {successfulDeployments}
                    </div>
                    <div className="text-xs text-foreground-muted">Successful</div>
                </div>
                <div className="p-3 rounded-lg bg-red-500/10 text-center">
                    <div className="text-xl font-bold text-red-500">
                        {failedDeployments}
                    </div>
                    <div className="text-xs text-foreground-muted">Failed</div>
                </div>
            </div>

            {/* Resource Counts */}
            <div className="flex items-center justify-around text-sm text-foreground-muted">
                <span>Apps: {data.app_count || 0}</span>
                <span>Services: {data.service_count || 0}</span>
                <span>Servers: {data.server_count || 0}</span>
            </div>
        </div>
    );
}

/**
 * Code Review Card.
 */
function CodeReviewCard({ data }: { data: Record<string, unknown> }) {
    const violationsCount = (data.violations_count as number) || 0;
    const criticalCount = (data.critical_count as number) || 0;

    return (
        <div className="space-y-3">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <FileCode className="h-5 w-5 text-primary" />
                    <span className="font-medium">Code Review</span>
                </div>
                <div className="flex items-center gap-2">
                    {violationsCount === 0 ? (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">
                            Passed
                        </span>
                    ) : criticalCount > 0 ? (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-400">
                            {criticalCount} critical
                        </span>
                    ) : (
                        <span className="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">
                            {violationsCount} issues
                        </span>
                    )}
                </div>
            </div>

            {/* Stats */}
            <div className="flex items-center justify-around p-3 rounded-lg bg-background-secondary/50">
                <div className="text-center">
                    <div className="text-xl font-bold">{violationsCount}</div>
                    <div className="text-xs text-foreground-muted">Total Issues</div>
                </div>
                <div className="text-center">
                    <div className="text-xl font-bold text-red-500">{criticalCount}</div>
                    <div className="text-xs text-foreground-muted">Critical</div>
                </div>
            </div>
        </div>
    );
}

/**
 * Main AnalysisResultCard component.
 * Renders appropriate card based on intent type.
 */
export function AnalysisResultCard({ intent, data }: AnalysisResultCardProps) {
    // Determine which card to render based on intent
    switch (intent) {
        case 'analyze_errors':
            return (
                <div className="mt-3 p-3 rounded-lg bg-background-tertiary/50 border border-white/10">
                    <ErrorAnalysisCard data={data} />
                </div>
            );

        case 'health_check':
            return (
                <div className="mt-3 p-3 rounded-lg bg-background-tertiary/50 border border-white/10">
                    <HealthCheckCard data={data} />
                </div>
            );

        case 'metrics':
            return (
                <div className="mt-3 p-3 rounded-lg bg-background-tertiary/50 border border-white/10">
                    <MetricsCard data={data} />
                </div>
            );

        case 'code_review':
            return (
                <div className="mt-3 p-3 rounded-lg bg-background-tertiary/50 border border-white/10">
                    <CodeReviewCard data={data} />
                </div>
            );

        default:
            // No special rendering for other intents
            return null;
    }
}
