/**
 * AI Chat Types
 */

/**
 * Available AI chat intents/actions.
 */
export type AiChatIntent =
    | 'deploy'
    | 'restart'
    | 'stop'
    | 'start'
    | 'logs'
    | 'status'
    | 'delete'
    | 'analyze_errors'
    | 'analyze_deployment'
    | 'code_review'
    | 'health_check'
    | 'metrics'
    | 'help'
    | 'none';

/**
 * Severity levels for issues.
 */
export type IssueSeverity = 'critical' | 'high' | 'medium' | 'low';

/**
 * Health status for resources.
 */
export type HealthStatus = 'healthy' | 'unhealthy' | 'degraded' | 'unknown';

/**
 * Analysis result issue.
 */
export interface AnalysisIssue {
    severity: IssueSeverity;
    message: string;
    suggestion?: string;
    line_number?: number;
}

/**
 * Error analysis result from analyze_errors command.
 */
export interface ErrorAnalysisResult {
    type: 'error_analysis';
    resource_name: string;
    resource_type: string;
    errors_found: number;
    issues: AnalysisIssue[];
    summary?: string;
    solutions: string[];
}

/**
 * Deployment analysis result from analyze_deployment command.
 */
export interface DeploymentAnalysisResult {
    type: 'deployment_analysis';
    deployment_uuid: string;
    app_name: string;
    severity: IssueSeverity;
    category: string;
    confidence: number;
    root_cause?: string;
    root_cause_details?: string;
    solution: string[];
    prevention: string[];
}

/**
 * Code review result.
 */
export interface CodeReviewResult {
    type: 'code_review';
    code_review_id: number;
    app_name: string;
    commit_sha: string;
    status: 'pending' | 'analyzing' | 'completed' | 'failed';
    violations_count: number;
    critical_count: number;
    summary?: string;
    violations: Array<{
        severity: IssueSeverity;
        rule_id: string;
        message: string;
        file_path?: string;
        line_number?: number;
    }>;
}

/**
 * Health check result.
 */
export interface HealthCheckResult {
    type: 'health_check';
    total: number;
    healthy_percent: number;
    statuses: {
        healthy: number;
        unhealthy: number;
        degraded: number;
        unknown: number;
    };
    resources: Array<{
        name: string;
        type: string;
        status: string;
        health: HealthStatus;
        project?: string;
    }>;
}

/**
 * Metrics result.
 */
export interface MetricsResult {
    type: 'metrics';
    period_days: number;
    total_deployments: number;
    successful_deployments: number;
    failed_deployments: number;
    success_rate: number;
    app_count: number;
    service_count: number;
    server_count: number;
}

/**
 * Union type for all analysis results.
 */
export type AnalysisResult =
    | ErrorAnalysisResult
    | DeploymentAnalysisResult
    | CodeReviewResult
    | HealthCheckResult
    | MetricsResult;

export interface AiChatSession {
    uuid: string;
    title: string | null;
    context_type: string | null;
    context_id: number | null;
    context_name: string | null;
    last_message?: string | null;
    created_at: string;
    updated_at: string;
}

export interface AiChatMessage {
    uuid: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    intent: string | null;
    intent_label: string | null;
    intent_params?: Record<string, unknown> | null;
    command_status: 'pending' | 'executing' | 'completed' | 'failed' | null;
    command_result: string | null;
    rating: number | null;
    created_at: string;
}

export interface ChatContext {
    type: string;
    id: number;
    name: string;
    uuid?: string;
}

export interface AiChatStatus {
    enabled: boolean;
    available: boolean;
    provider: string | null;
    model: string | null;
}

export interface AiUsageStats {
    total_requests: number;
    successful_requests: number;
    failed_requests: number;
    total_tokens: number;
    total_cost_usd: number;
    avg_response_time_ms: number;
    by_provider: Record<string, { count: number; total_cost: number }>;
}

export interface AiCommandStats {
    intent: string;
    count: number;
}

export interface AiRatingStats {
    ratings: Record<string, number>;
    total: number;
    average: number;
}

export interface AiDailyStats {
    date: string;
    requests: number;
    tokens: number;
    cost: number;
}

export interface SendMessageOptions {
    execute_commands?: boolean;
}

export interface ConfirmCommandOptions {
    intent: string;
    params: Record<string, unknown>;
}
