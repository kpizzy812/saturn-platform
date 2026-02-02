import * as React from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
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
    ChevronDown,
    ChevronRight,
    Zap,
    Target,
    Info,
} from 'lucide-react';
import type { DeploymentLogAnalysis, AISeverity, AIErrorCategory } from '@/types';

interface AIAnalysisCardProps {
    deploymentUuid: string;
    deploymentStatus?: string;
    className?: string;
}

const severityConfig: Record<AISeverity, { color: string; bg: string; border: string }> = {
    critical: { color: 'text-red-400', bg: 'bg-red-500/10', border: 'border-red-500/20' },
    high: { color: 'text-orange-400', bg: 'bg-orange-500/10', border: 'border-orange-500/20' },
    medium: { color: 'text-yellow-400', bg: 'bg-yellow-500/10', border: 'border-yellow-500/20' },
    low: { color: 'text-green-400', bg: 'bg-green-500/10', border: 'border-green-500/20' },
};

const categoryLabels: Record<AIErrorCategory, string> = {
    dockerfile: 'Dockerfile',
    dependency: 'Dependency',
    build: 'Build',
    runtime: 'Runtime',
    network: 'Network',
    resource: 'Resource',
    config: 'Config',
    unknown: 'Unknown',
};

function AnalysisContent({ analysis }: { analysis: DeploymentLogAnalysis }) {
    const [showSolution, setShowSolution] = React.useState(false);
    const [showPrevention, setShowPrevention] = React.useState(false);
    const severity = severityConfig[analysis.severity];

    return (
        <div className="space-y-3">
            {/* Root Cause - always visible */}
            <div className={cn('rounded-md p-3 border', severity.bg, severity.border)}>
                <div className="flex items-start gap-2">
                    <Target className={cn('h-4 w-4 mt-0.5 flex-shrink-0', severity.color)} />
                    <div className="flex-1 min-w-0">
                        <p className={cn('text-sm font-medium', severity.color)}>{analysis.root_cause}</p>
                        {analysis.root_cause_details && (
                            <p className="text-xs text-foreground-muted mt-1">{analysis.root_cause_details}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Solution - collapsible */}
            {analysis.solution && analysis.solution.length > 0 && (
                <div className="border border-white/5 rounded-md overflow-hidden">
                    <button
                        onClick={() => setShowSolution(!showSolution)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-left hover:bg-white/5 transition-colors"
                    >
                        {showSolution ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        <Lightbulb className="h-4 w-4 text-blue-400" />
                        <span className="font-medium">Solution</span>
                        <Badge variant="secondary" className="ml-auto text-xs">
                            {analysis.solution.length} step{analysis.solution.length > 1 ? 's' : ''}
                        </Badge>
                    </button>
                    {showSolution && (
                        <div className="px-3 pb-3 pt-1 border-t border-white/5">
                            <ol className="space-y-1.5">
                                {analysis.solution.map((step, i) => (
                                    <li key={i} className="flex items-start gap-2 text-xs text-foreground-secondary">
                                        <span className="flex-shrink-0 w-4 h-4 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-[10px] font-medium mt-0.5">
                                            {i + 1}
                                        </span>
                                        <span>{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                </div>
            )}

            {/* Prevention - collapsible */}
            {analysis.prevention && analysis.prevention.length > 0 && (
                <div className="border border-white/5 rounded-md overflow-hidden">
                    <button
                        onClick={() => setShowPrevention(!showPrevention)}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-left hover:bg-white/5 transition-colors"
                    >
                        {showPrevention ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        <Shield className="h-4 w-4 text-green-400" />
                        <span className="font-medium">Prevention</span>
                        <Badge variant="secondary" className="ml-auto text-xs">
                            {analysis.prevention.length} tip{analysis.prevention.length > 1 ? 's' : ''}
                        </Badge>
                    </button>
                    {showPrevention && (
                        <div className="px-3 pb-3 pt-1 border-t border-white/5">
                            <ul className="space-y-1">
                                {analysis.prevention.map((tip, i) => (
                                    <li key={i} className="flex items-start gap-2 text-xs text-foreground-secondary">
                                        <CheckCircle2 className="h-3 w-3 text-green-400 flex-shrink-0 mt-0.5" />
                                        <span>{tip}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {/* Footer */}
            <div className="flex items-center justify-between text-[10px] text-foreground-muted pt-1">
                <span>{analysis.provider} &middot; {analysis.model}</span>
                <span>{analysis.confidence_percent}% confidence</span>
            </div>
        </div>
    );
}

export function AIAnalysisCard({ deploymentUuid, deploymentStatus, className }: AIAnalysisCardProps) {
    const [isExpanded, setIsExpanded] = React.useState(true);

    // Only fetch for failed deployments to avoid unnecessary requests
    // Check both raw status ('failed') and display status ('crashed') for compatibility
    const isFailed = deploymentStatus === 'failed' || deploymentStatus === 'crashed';

    const {
        analysis,
        isLoading,
        isAnalyzing,
        triggerAnalysis,
    } = useDeploymentAnalysis({
        deploymentUuid,
        autoRefresh: isFailed, // Only auto-refresh for failed deployments
        enabled: isFailed, // Only fetch when deployment is failed
    });

    const { status: aiStatus, isLoading: aiStatusLoading } = useAIServiceStatus();

    // Only show for failed deployments
    if (!isFailed) {
        return null;
    }

    // Loading AI status or analysis
    if ((aiStatusLoading || isLoading) && !analysis) {
        return (
            <div className={cn('flex items-center gap-2 px-3 py-2 rounded-md bg-background-secondary border border-white/5 text-xs text-foreground-muted', className)}>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                <span>Checking AI analysis...</span>
            </div>
        );
    }

    // AI disabled globally
    if (aiStatus && !aiStatus.enabled) {
        return null;
    }

    // AI not configured - show minimal hint
    if (!aiStatus?.available && !analysis) {
        return (
            <div className={cn('flex items-center gap-2 px-3 py-2 rounded-md bg-yellow-500/5 border border-yellow-500/10 text-xs text-yellow-400/80', className)}>
                <Info className="h-3.5 w-3.5 flex-shrink-0" />
                <span>AI analysis available with ANTHROPIC_API_KEY or OPENAI_API_KEY</span>
            </div>
        );
    }

    // Currently analyzing
    if (analysis?.status === 'analyzing' || isAnalyzing) {
        return (
            <div className={cn('flex items-center gap-2 px-3 py-2 rounded-md bg-primary/5 border border-primary/10 text-xs', className)}>
                <Brain className="h-3.5 w-3.5 text-primary animate-pulse" />
                <span className="text-primary">Analyzing deployment logs...</span>
                <Loader2 className="h-3 w-3 animate-spin text-primary ml-auto" />
            </div>
        );
    }

    // Analysis failed
    if (analysis?.status === 'failed') {
        return (
            <div className={cn('flex items-center gap-2 px-3 py-2 rounded-md bg-red-500/5 border border-red-500/10 text-xs', className)}>
                <AlertCircle className="h-3.5 w-3.5 text-red-400 flex-shrink-0" />
                <span className="text-red-400/80 truncate">{analysis.error_message || 'Analysis failed'}</span>
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto h-6 px-2 text-xs"
                    onClick={triggerAnalysis}
                    disabled={isLoading}
                >
                    <RefreshCw className={cn('h-3 w-3', isLoading && 'animate-spin')} />
                </Button>
            </div>
        );
    }

    // No analysis yet - offer to analyze
    if (!analysis || analysis.status === 'pending') {
        return (
            <div className={cn('flex items-center gap-2 px-3 py-2 rounded-md bg-background-secondary border border-white/5 text-xs', className)}>
                <Brain className="h-3.5 w-3.5 text-foreground-muted" />
                <span className="text-foreground-muted">AI can analyze this failure</span>
                <Button
                    variant="secondary"
                    size="sm"
                    className="ml-auto h-6 px-2 text-xs"
                    onClick={triggerAnalysis}
                    disabled={isLoading}
                >
                    {isLoading ? (
                        <Loader2 className="h-3 w-3 animate-spin" />
                    ) : (
                        <>
                            <Zap className="h-3 w-3 mr-1" />
                            Analyze
                        </>
                    )}
                </Button>
            </div>
        );
    }

    // Analysis completed - show full card
    const severity = severityConfig[analysis.severity];

    return (
        <div className={cn('rounded-lg border overflow-hidden', severity.border, severity.bg, className)}>
            {/* Header - clickable */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex items-center gap-3 w-full px-4 py-3 text-left hover:bg-white/5 transition-colors"
            >
                <div className={cn('rounded-md p-1.5', severity.bg)}>
                    <Brain className={cn('h-4 w-4', severity.color)} />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-foreground">AI Analysis</span>
                        <Badge className={cn('text-[10px] px-1.5 py-0', severity.bg, severity.color, 'border', severity.border)}>
                            {analysis.severity}
                        </Badge>
                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                            {categoryLabels[analysis.error_category]}
                        </Badge>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                        onClick={(e) => {
                            e.stopPropagation();
                            triggerAnalysis();
                        }}
                        disabled={isLoading || isAnalyzing}
                    >
                        <RefreshCw className={cn('h-3.5 w-3.5', (isLoading || isAnalyzing) && 'animate-spin')} />
                    </Button>
                    {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                </div>
            </button>

            {/* Content */}
            {isExpanded && (
                <div className="px-4 pb-4 border-t border-white/5">
                    <div className="pt-3">
                        <AnalysisContent analysis={analysis} />
                    </div>
                </div>
            )}
        </div>
    );
}

export default AIAnalysisCard;
