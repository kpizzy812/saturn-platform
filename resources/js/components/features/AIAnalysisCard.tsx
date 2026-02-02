import * as React from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { cn } from '@/lib/utils';
import { useDeploymentAnalysis, useAIServiceStatus } from '@/hooks/useDeploymentAnalysis';
import {
    Brain,
    AlertCircle,
    CheckCircle2,
    Lightbulb,
    Shield,
    Loader2,
    RefreshCw,
    Sparkles,
    ChevronDown,
    ChevronUp,
    Zap,
    Target,
} from 'lucide-react';
import type { DeploymentLogAnalysis, AISeverity, AIErrorCategory } from '@/types';

interface AIAnalysisCardProps {
    deploymentUuid: string;
    deploymentStatus?: string;
    className?: string;
}

const severityConfig: Record<AISeverity, { color: string; bgColor: string; icon: React.ReactNode }> = {
    critical: {
        color: 'text-red-400',
        bgColor: 'bg-red-500/15 border-red-500/30',
        icon: <AlertCircle className="h-4 w-4" />,
    },
    high: {
        color: 'text-orange-400',
        bgColor: 'bg-orange-500/15 border-orange-500/30',
        icon: <AlertCircle className="h-4 w-4" />,
    },
    medium: {
        color: 'text-yellow-400',
        bgColor: 'bg-yellow-500/15 border-yellow-500/30',
        icon: <AlertCircle className="h-4 w-4" />,
    },
    low: {
        color: 'text-green-400',
        bgColor: 'bg-green-500/15 border-green-500/30',
        icon: <CheckCircle2 className="h-4 w-4" />,
    },
};

const categoryConfig: Record<AIErrorCategory, { label: string; icon: React.ReactNode }> = {
    dockerfile: { label: 'Dockerfile Issue', icon: <Target className="h-4 w-4" /> },
    dependency: { label: 'Dependency Error', icon: <Zap className="h-4 w-4" /> },
    build: { label: 'Build Failure', icon: <Zap className="h-4 w-4" /> },
    runtime: { label: 'Runtime Error', icon: <AlertCircle className="h-4 w-4" /> },
    network: { label: 'Network Issue', icon: <AlertCircle className="h-4 w-4" /> },
    resource: { label: 'Resource Limit', icon: <AlertCircle className="h-4 w-4" /> },
    config: { label: 'Configuration Error', icon: <AlertCircle className="h-4 w-4" /> },
    unknown: { label: 'Unknown', icon: <AlertCircle className="h-4 w-4" /> },
};

function AnalysisContent({ analysis }: { analysis: DeploymentLogAnalysis }) {
    const [expanded, setExpanded] = React.useState(true);
    const severity = severityConfig[analysis.severity];
    const category = categoryConfig[analysis.error_category];

    return (
        <div className="space-y-4">
            {/* Header with severity and category */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Badge
                        className={cn(
                            'border px-2.5 py-0.5 text-xs font-medium',
                            severity.bgColor,
                            severity.color
                        )}
                    >
                        {severity.icon}
                        <span className="ml-1.5 capitalize">{analysis.severity}</span>
                    </Badge>
                    <Badge variant="secondary" className="text-xs">
                        {category.icon}
                        <span className="ml-1.5">{category.label}</span>
                    </Badge>
                </div>
                <div className="flex items-center gap-2 text-xs text-foreground-muted">
                    <span>Confidence: {analysis.confidence_percent}%</span>
                    <div className="w-16 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div
                            className="h-full bg-primary rounded-full"
                            style={{ width: `${analysis.confidence_percent}%` }}
                        />
                    </div>
                </div>
            </div>

            {/* Root Cause */}
            <div className={cn('rounded-lg p-4 border', severity.bgColor)}>
                <div className="flex items-start gap-3">
                    <div className={cn('mt-0.5', severity.color)}>
                        <Target className="h-5 w-5" />
                    </div>
                    <div className="flex-1 min-w-0">
                        <h4 className="font-medium text-foreground mb-1">Root Cause</h4>
                        <p className={cn('text-sm', severity.color)}>{analysis.root_cause}</p>
                        {analysis.root_cause_details && (
                            <p className="text-sm text-foreground-muted mt-2">{analysis.root_cause_details}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Expandable details */}
            <button
                onClick={() => setExpanded(!expanded)}
                className="flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground transition-colors w-full"
            >
                {expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                <span>{expanded ? 'Hide' : 'Show'} solution and prevention tips</span>
            </button>

            {expanded && (
                <>
                    {/* Solution Steps */}
                    {analysis.solution && analysis.solution.length > 0 && (
                        <div className="rounded-lg p-4 bg-blue-500/10 border border-blue-500/20">
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5 text-blue-400">
                                    <Lightbulb className="h-5 w-5" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h4 className="font-medium text-foreground mb-2">Solution</h4>
                                    <ol className="space-y-2">
                                        {analysis.solution.map((step, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm text-foreground-secondary">
                                                <span className="flex-shrink-0 w-5 h-5 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs font-medium">
                                                    {index + 1}
                                                </span>
                                                <span>{step}</span>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Prevention Tips */}
                    {analysis.prevention && analysis.prevention.length > 0 && (
                        <div className="rounded-lg p-4 bg-green-500/10 border border-green-500/20">
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5 text-green-400">
                                    <Shield className="h-5 w-5" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h4 className="font-medium text-foreground mb-2">Prevention</h4>
                                    <ul className="space-y-1.5">
                                        {analysis.prevention.map((tip, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm text-foreground-secondary">
                                                <CheckCircle2 className="h-4 w-4 text-green-400 flex-shrink-0 mt-0.5" />
                                                <span>{tip}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}

            {/* Footer with provider info */}
            <div className="flex items-center justify-between text-xs text-foreground-muted pt-2 border-t border-white/5">
                <span>
                    Analyzed by {analysis.provider} ({analysis.model})
                </span>
                {analysis.tokens_used && (
                    <span>{analysis.tokens_used.toLocaleString()} tokens used</span>
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
                    <Brain className="h-8 w-8 text-primary animate-pulse" />
                </div>
            </div>
            <h4 className="text-lg font-medium text-foreground mb-2">Analyzing Deployment Logs</h4>
            <p className="text-sm text-foreground-muted max-w-sm">
                AI is analyzing the deployment logs to identify the root cause and suggest solutions...
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
    aiAvailable,
}: {
    onAnalyze: () => void;
    isLoading: boolean;
    aiAvailable: boolean;
}) {
    if (!aiAvailable) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-center">
                <div className="rounded-full bg-yellow-500/10 p-4 mb-4">
                    <AlertCircle className="h-8 w-8 text-yellow-400" />
                </div>
                <h4 className="text-lg font-medium text-foreground mb-2">AI Analysis Unavailable</h4>
                <p className="text-sm text-foreground-muted max-w-sm">
                    No AI provider is configured. Configure ANTHROPIC_API_KEY, OPENAI_API_KEY, or Ollama to enable AI analysis.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="rounded-full bg-primary/10 p-4 mb-4">
                <Sparkles className="h-8 w-8 text-primary" />
            </div>
            <h4 className="text-lg font-medium text-foreground mb-2">AI Analysis Available</h4>
            <p className="text-sm text-foreground-muted max-w-sm mb-4">
                Let AI analyze the deployment logs to identify the root cause and suggest solutions.
            </p>
            <Button onClick={onAnalyze} disabled={isLoading}>
                {isLoading ? (
                    <>
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        Starting Analysis...
                    </>
                ) : (
                    <>
                        <Brain className="h-4 w-4 mr-2" />
                        Analyze with AI
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
            <h4 className="text-lg font-medium text-foreground mb-2">Analysis Failed</h4>
            <p className="text-sm text-foreground-muted max-w-sm mb-4">
                {errorMessage || 'An error occurred while analyzing the deployment logs.'}
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
                        Retry Analysis
                    </>
                )}
            </Button>
        </div>
    );
}

export function AIAnalysisCard({ deploymentUuid, deploymentStatus, className }: AIAnalysisCardProps) {
    const {
        analysis,
        isLoading,
        isAnalyzing,
        error,
        triggerAnalysis,
        refetch,
    } = useDeploymentAnalysis({
        deploymentUuid,
        autoRefresh: true, // Will auto-refresh when status is 'analyzing'
    });

    const { status: aiStatus } = useAIServiceStatus();

    // Only show for failed deployments
    if (deploymentStatus !== 'failed') {
        return null;
    }

    // Don't show if AI is disabled
    if (aiStatus && !aiStatus.enabled) {
        return null;
    }

    const renderContent = () => {
        if (isLoading && !analysis) {
            return (
                <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                </div>
            );
        }

        if (analysis?.status === 'analyzing' || isAnalyzing) {
            return <AnalyzingState />;
        }

        if (analysis?.status === 'completed') {
            return <AnalysisContent analysis={analysis} />;
        }

        if (analysis?.status === 'failed') {
            return (
                <FailedState
                    errorMessage={analysis.error_message}
                    onRetry={triggerAnalysis}
                    isLoading={isLoading}
                />
            );
        }

        return (
            <EmptyState
                onAnalyze={triggerAnalysis}
                isLoading={isLoading}
                aiAvailable={aiStatus?.available ?? false}
            />
        );
    };

    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2">
                            <Brain className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <CardTitle className="text-base">AI Error Analysis</CardTitle>
                            <CardDescription>Automated root cause analysis</CardDescription>
                        </div>
                    </div>
                    {analysis?.status === 'completed' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={triggerAnalysis}
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

export default AIAnalysisCard;
