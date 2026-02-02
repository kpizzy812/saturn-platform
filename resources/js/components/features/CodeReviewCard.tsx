import * as React from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { cn } from '@/lib/utils';
import { useCodeReview, useCodeReviewStatus } from '@/hooks/useCodeReview';
import type { CodeReview, CodeReviewViolation, ViolationSeverity } from '@/hooks/useCodeReview';
import {
    Shield,
    ShieldAlert,
    ShieldCheck,
    AlertTriangle,
    AlertCircle,
    CheckCircle2,
    Loader2,
    RefreshCw,
    FileCode,
    ChevronDown,
    ChevronUp,
    Code,
    ExternalLink,
    Eye,
    EyeOff,
    Sparkles,
    Clock,
} from 'lucide-react';

interface CodeReviewCardProps {
    deploymentUuid: string;
    commitSha?: string;
    className?: string;
}

const severityConfig: Record<ViolationSeverity, { color: string; bgColor: string; icon: React.ReactNode; label: string }> = {
    critical: {
        color: 'text-red-400',
        bgColor: 'bg-red-500/15 border-red-500/30',
        icon: <ShieldAlert className="h-4 w-4" />,
        label: 'Critical',
    },
    high: {
        color: 'text-orange-400',
        bgColor: 'bg-orange-500/15 border-orange-500/30',
        icon: <AlertTriangle className="h-4 w-4" />,
        label: 'High',
    },
    medium: {
        color: 'text-yellow-400',
        bgColor: 'bg-yellow-500/15 border-yellow-500/30',
        icon: <AlertCircle className="h-4 w-4" />,
        label: 'Medium',
    },
    low: {
        color: 'text-green-400',
        bgColor: 'bg-green-500/15 border-green-500/30',
        icon: <CheckCircle2 className="h-4 w-4" />,
        label: 'Low',
    },
};

function ViolationItem({ violation }: { violation: CodeReviewViolation }) {
    const [expanded, setExpanded] = React.useState(false);
    const severity = severityConfig[violation.severity];

    return (
        <div className={cn('rounded-lg border p-3', severity.bgColor)}>
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full text-left"
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-start gap-2 flex-1 min-w-0">
                        <div className={cn('mt-0.5 flex-shrink-0', severity.color)}>
                            {severity.icon}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <Badge variant="secondary" className="text-xs font-mono">
                                    {violation.rule_id}
                                </Badge>
                                <span className="text-xs text-foreground-muted">
                                    {violation.rule_category}
                                </span>
                                {violation.contains_secret && (
                                    <Badge className="bg-purple-500/20 text-purple-400 border-purple-500/30 text-xs">
                                        <EyeOff className="h-3 w-3 mr-1" />
                                        Secret
                                    </Badge>
                                )}
                            </div>
                            <p className={cn('text-sm mt-1', severity.color)}>
                                {violation.message}
                            </p>
                            <div className="flex items-center gap-2 mt-1 text-xs text-foreground-muted">
                                <FileCode className="h-3 w-3" />
                                <span className="font-mono truncate">{violation.location}</span>
                            </div>
                        </div>
                    </div>
                    <div className="flex-shrink-0">
                        {expanded ? (
                            <ChevronUp className="h-4 w-4 text-foreground-muted" />
                        ) : (
                            <ChevronDown className="h-4 w-4 text-foreground-muted" />
                        )}
                    </div>
                </div>
            </button>

            {expanded && (
                <div className="mt-3 pt-3 border-t border-white/10 space-y-3">
                    {violation.snippet && (
                        <div>
                            <h5 className="text-xs font-medium text-foreground-muted mb-1.5">Code Snippet</h5>
                            <pre className="text-xs bg-black/30 rounded p-2 overflow-x-auto font-mono">
                                <code>{violation.snippet}</code>
                            </pre>
                        </div>
                    )}

                    {violation.suggestion && (
                        <div>
                            <h5 className="text-xs font-medium text-foreground-muted mb-1.5 flex items-center gap-1">
                                <Sparkles className="h-3 w-3" />
                                AI Suggestion
                            </h5>
                            <p className="text-sm text-foreground-secondary">{violation.suggestion}</p>
                        </div>
                    )}

                    <div className="flex items-center gap-3 text-xs text-foreground-muted">
                        <span>Confidence: {Math.round(violation.confidence * 100)}%</span>
                        <span>Source: {violation.source}</span>
                        {violation.is_deterministic && (
                            <Badge variant="secondary" className="text-xs">
                                Deterministic
                            </Badge>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function ReviewContent({ review }: { review: CodeReview }) {
    const [showAllViolations, setShowAllViolations] = React.useState(false);
    const violations = review.violations || [];
    const displayViolations = showAllViolations ? violations : violations.slice(0, 5);
    const hasMoreViolations = violations.length > 5;

    const getStatusIcon = () => {
        if (review.has_critical) {
            return <ShieldAlert className="h-5 w-5 text-red-400" />;
        }
        if (review.has_violations) {
            return <AlertTriangle className="h-5 w-5 text-yellow-400" />;
        }
        return <ShieldCheck className="h-5 w-5 text-green-400" />;
    };

    return (
        <div className="space-y-4">
            {/* Summary */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    {getStatusIcon()}
                    <div>
                        <span className="font-medium">{review.status_label}</span>
                        <div className="flex items-center gap-2 text-xs text-foreground-muted mt-0.5">
                            <span>{review.files_count} files analyzed</span>
                            {review.duration_ms && (
                                <>
                                    <span>•</span>
                                    <span>{(review.duration_ms / 1000).toFixed(1)}s</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {review.violations_count > 0 && (
                    <div className="flex items-center gap-2">
                        {review.critical_count > 0 && (
                            <Badge className="bg-red-500/20 text-red-400 border-red-500/30">
                                {review.critical_count} Critical
                            </Badge>
                        )}
                        {(review.violations_by_severity['high'] || 0) > 0 && (
                            <Badge className="bg-orange-500/20 text-orange-400 border-orange-500/30">
                                {review.violations_by_severity['high']} High
                            </Badge>
                        )}
                        {(review.violations_by_severity['medium'] || 0) > 0 && (
                            <Badge className="bg-yellow-500/20 text-yellow-400 border-yellow-500/30">
                                {review.violations_by_severity['medium']} Medium
                            </Badge>
                        )}
                    </div>
                )}
            </div>

            {/* Summary */}
            {review.summary && (
                <div className="rounded-lg p-4 bg-blue-500/10 border border-blue-500/20">
                    <div className="flex items-start gap-3">
                        <Sparkles className="h-5 w-5 text-blue-400 mt-0.5 flex-shrink-0" />
                        <div>
                            <h4 className="font-medium text-blue-400 text-sm">AI Summary</h4>
                            <p className="text-sm text-foreground-secondary mt-1">
                                {review.summary}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* No violations */}
            {!review.has_violations && (
                <div className="rounded-lg p-4 bg-green-500/10 border border-green-500/20">
                    <div className="flex items-center gap-3">
                        <ShieldCheck className="h-6 w-6 text-green-400" />
                        <div>
                            <h4 className="font-medium text-green-400">No Security Issues Found</h4>
                            <p className="text-sm text-foreground-muted">
                                No hardcoded secrets or dangerous patterns detected in this commit.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Violations list */}
            {violations.length > 0 && (
                <div className="space-y-2">
                    <h4 className="text-sm font-medium flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 text-yellow-400" />
                        Violations ({violations.length})
                    </h4>
                    <div className="space-y-2">
                        {displayViolations.map((violation) => (
                            <ViolationItem key={violation.id} violation={violation} />
                        ))}
                    </div>
                    {hasMoreViolations && (
                        <button
                            onClick={() => setShowAllViolations(!showAllViolations)}
                            className="text-sm text-primary hover:text-primary/80 flex items-center gap-1"
                        >
                            {showAllViolations ? (
                                <>
                                    <ChevronUp className="h-4 w-4" />
                                    Show Less
                                </>
                            ) : (
                                <>
                                    <ChevronDown className="h-4 w-4" />
                                    Show {violations.length - 5} More
                                </>
                            )}
                        </button>
                    )}
                </div>
            )}

            {/* Footer */}
            <div className="flex items-center justify-between text-xs text-foreground-muted pt-2 border-t border-white/5">
                <div className="flex items-center gap-2">
                    <Code className="h-3 w-3" />
                    <span className="font-mono">{review.commit_sha.substring(0, 7)}</span>
                </div>
                {review.llm_provider && (
                    <span>Enhanced by {review.llm_provider}</span>
                )}
                {review.llm_failed && (
                    <span className="text-yellow-400">LLM enrichment failed</span>
                )}
            </div>
        </div>
    );
}

function AnalyzingState() {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="relative mb-4">
                <div className="absolute inset-0 rounded-full bg-primary/20 animate-ping" />
                <div className="relative rounded-full bg-primary/10 p-4">
                    <Shield className="h-8 w-8 text-primary animate-pulse" />
                </div>
            </div>
            <h4 className="text-lg font-medium text-foreground mb-2">Analyzing Code Changes</h4>
            <p className="text-sm text-foreground-muted max-w-sm">
                Scanning for hardcoded secrets and security vulnerabilities...
            </p>
            <div className="flex items-center gap-2 mt-4 text-xs text-foreground-muted">
                <Loader2 className="h-3 w-3 animate-spin" />
                <span>This may take a few moments</span>
            </div>
        </div>
    );
}

function EmptyState({
    onAnalyze,
    isLoading,
    isEnabled,
}: {
    onAnalyze: () => void;
    isLoading: boolean;
    isEnabled: boolean;
}) {
    if (!isEnabled) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-center">
                <div className="rounded-full bg-gray-500/10 p-4 mb-4">
                    <Shield className="h-8 w-8 text-gray-400" />
                </div>
                <h4 className="text-lg font-medium text-foreground mb-2">Code Review Disabled</h4>
                <p className="text-sm text-foreground-muted max-w-sm">
                    Enable code review by setting AI_CODE_REVIEW_ENABLED=true in your environment.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="rounded-full bg-primary/10 p-4 mb-4">
                <Shield className="h-8 w-8 text-primary" />
            </div>
            <h4 className="text-lg font-medium text-foreground mb-2">Security Code Review</h4>
            <p className="text-sm text-foreground-muted max-w-sm mb-4">
                Scan code changes for hardcoded secrets, API keys, and security vulnerabilities.
            </p>
            <Button onClick={onAnalyze} disabled={isLoading}>
                {isLoading ? (
                    <>
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        Starting Review...
                    </>
                ) : (
                    <>
                        <Shield className="h-4 w-4 mr-2" />
                        Run Code Review
                    </>
                )}
            </Button>
        </div>
    );
}

function FailedState({
    errorMessage,
    onRetry,
    isLoading,
}: {
    errorMessage: string | null;
    onRetry: () => void;
    isLoading: boolean;
}) {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="rounded-full bg-red-500/10 p-4 mb-4">
                <AlertCircle className="h-8 w-8 text-red-400" />
            </div>
            <h4 className="text-lg font-medium text-foreground mb-2">Review Failed</h4>
            <p className="text-sm text-foreground-muted max-w-sm mb-4">
                {errorMessage || 'An error occurred while analyzing the code changes.'}
            </p>
            <Button variant="secondary" onClick={onRetry} disabled={isLoading}>
                {isLoading ? (
                    <>
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        Retrying...
                    </>
                ) : (
                    <>
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Retry Review
                    </>
                )}
            </Button>
        </div>
    );
}

export function CodeReviewCard({ deploymentUuid, commitSha, className }: CodeReviewCardProps) {
    const {
        review,
        isLoading,
        isAnalyzing,
        error,
        triggerReview,
        refetch,
    } = useCodeReview({
        deploymentUuid,
        autoRefresh: true, // Hook handles auto-refresh internally based on isAnalyzing
    });

    const { status: serviceStatus } = useCodeReviewStatus();

    // Don't show if code review is disabled
    if (serviceStatus && !serviceStatus.enabled) {
        return null;
    }

    // Don't show if no commit (e.g., non-git deployment)
    if (!commitSha) {
        return null;
    }

    const renderContent = () => {
        if (isLoading && !review) {
            return (
                <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                </div>
            );
        }

        if (review?.status === 'analyzing' || isAnalyzing) {
            return <AnalyzingState />;
        }

        if (review?.status === 'completed') {
            return <ReviewContent review={review} />;
        }

        if (review?.status === 'failed') {
            return (
                <FailedState
                    errorMessage={review.error_message}
                    onRetry={triggerReview}
                    isLoading={isLoading}
                />
            );
        }

        return (
            <EmptyState
                onAnalyze={triggerReview}
                isLoading={isLoading}
                isEnabled={serviceStatus?.enabled ?? false}
            />
        );
    };

    const getHeaderBadge = () => {
        if (!review) return null;

        if (review.status === 'completed') {
            if (review.has_critical) {
                return (
                    <Badge className="bg-red-500/20 text-red-400 border-red-500/30">
                        {review.critical_count} Critical
                    </Badge>
                );
            }
            if (review.has_violations) {
                return (
                    <Badge className="bg-yellow-500/20 text-yellow-400 border-yellow-500/30">
                        {review.violations_count} Issues
                    </Badge>
                );
            }
            return (
                <Badge className="bg-green-500/20 text-green-400 border-green-500/30">
                    Passed
                </Badge>
            );
        }

        if (review.status === 'analyzing') {
            return (
                <Badge variant="secondary">
                    <Loader2 className="h-3 w-3 animate-spin mr-1" />
                    Analyzing
                </Badge>
            );
        }

        return null;
    };

    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2">
                            <Shield className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                Code Review
                                {getHeaderBadge()}
                            </CardTitle>
                            <CardDescription>Security vulnerability scanner</CardDescription>
                        </div>
                    </div>
                    {review?.status === 'completed' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={triggerReview}
                            disabled={isLoading || isAnalyzing}
                        >
                            <RefreshCw className={cn('h-4 w-4', (isLoading || isAnalyzing) && 'animate-spin')} />
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent>{renderContent()}</CardContent>
        </Card>
    );
}

export default CodeReviewCard;
