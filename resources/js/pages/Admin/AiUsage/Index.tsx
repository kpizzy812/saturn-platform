import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import {
    Brain,
    DollarSign,
    Zap,
    TrendingUp,
    TrendingDown,
    Clock,
    MessageSquare,
    AlertTriangle,
    FileCode,
    BarChart3,
    Users,
    Calendar,
} from 'lucide-react';

interface UsageStats {
    totalRequests: number;
    successfulRequests: number;
    failedRequests: number;
    totalTokens: number;
    totalCostUsd: number;
    avgResponseTimeMs: number;
}

interface ByProviderStats {
    [provider: string]: {
        count: number;
        total_cost: number;
    };
}

interface ByOperationStats {
    [operation: string]: {
        count: number;
        total_cost: number;
        total_tokens: number;
    };
}

interface DailyUsage {
    date: string;
    requests: number;
    cost: number;
    tokens: number;
}

interface TopTeam {
    team_id: number;
    team_name: string;
    total_requests: number;
    total_cost: number;
}

interface ModelPricing {
    provider: string;
    model_id: string;
    model_name: string;
    input_price_per_1m: number;
    output_price_per_1m: number;
    context_window: number | null;
}

interface Props {
    stats7d: UsageStats;
    stats30d: UsageStats;
    stats90d: UsageStats;
    byProvider: ByProviderStats;
    byOperation: ByOperationStats;
    dailyUsage: DailyUsage[];
    topTeams: TopTeam[];
    modelPricing: { [provider: string]: ModelPricing[] };
    period: '7d' | '30d' | '90d';
}

const defaultStats: UsageStats = {
    totalRequests: 0,
    successfulRequests: 0,
    failedRequests: 0,
    totalTokens: 0,
    totalCostUsd: 0,
    avgResponseTimeMs: 0,
};

function StatCard({ title, value, subtitle, icon: Icon, trend, trendValue }: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
}) {
    return (
        <Card variant="glass" hover>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-sm text-foreground-muted">{title}</p>
                        <p className="mt-2 text-3xl font-bold text-foreground">{value}</p>
                        {subtitle && (
                            <p className="mt-1 text-xs text-foreground-subtle">{subtitle}</p>
                        )}
                    </div>
                    <div className="rounded-lg bg-primary/10 p-3">
                        <Icon className="h-6 w-6 text-primary" />
                    </div>
                </div>
                {trend && trendValue && (
                    <div className="mt-4 flex items-center gap-1">
                        {trend === 'up' ? (
                            <TrendingUp className="h-4 w-4 text-success" />
                        ) : trend === 'down' ? (
                            <TrendingDown className="h-4 w-4 text-danger" />
                        ) : null}
                        <span className={`text-xs ${
                            trend === 'up' ? 'text-success' :
                            trend === 'down' ? 'text-danger' :
                            'text-foreground-muted'
                        }`}>
                            {trendValue}
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function formatCost(cost: number): string {
    if (cost < 0.01) {
        return `$${cost.toFixed(4)}`;
    }
    if (cost < 1) {
        return `$${cost.toFixed(3)}`;
    }
    return `$${cost.toFixed(2)}`;
}

function formatNumber(num: number): string {
    if (num >= 1_000_000) {
        return `${(num / 1_000_000).toFixed(1)}M`;
    }
    if (num >= 1_000) {
        return `${(num / 1_000).toFixed(1)}K`;
    }
    return num.toString();
}

function getOperationLabel(operation: string): string {
    switch (operation) {
        case 'chat': return 'AI Chat';
        case 'deployment_analysis': return 'Error Analysis';
        case 'code_review': return 'Code Review';
        case 'command_parse': return 'Command Parse';
        default: return operation;
    }
}

function getOperationIcon(operation: string) {
    switch (operation) {
        case 'chat': return MessageSquare;
        case 'deployment_analysis': return AlertTriangle;
        case 'code_review': return FileCode;
        default: return Brain;
    }
}

function getProviderColor(provider: string): string {
    switch (provider.toLowerCase()) {
        case 'anthropic':
        case 'claude':
            return 'bg-orange-500/20 text-orange-400 border-orange-500/30';
        case 'openai':
            return 'bg-green-500/20 text-green-400 border-green-500/30';
        case 'ollama':
            return 'bg-purple-500/20 text-purple-400 border-purple-500/30';
        default:
            return 'bg-gray-500/20 text-gray-400 border-gray-500/30';
    }
}

export default function AiUsageIndex({
    stats7d = defaultStats,
    stats30d = defaultStats,
    stats90d = defaultStats,
    byProvider = {},
    byOperation = {},
    dailyUsage = [],
    topTeams = [],
    modelPricing = {},
    period = '30d',
}: Props) {
    const [selectedPeriod, setSelectedPeriod] = React.useState<'7d' | '30d' | '90d'>(period);

    const stats = selectedPeriod === '7d' ? stats7d : selectedPeriod === '30d' ? stats30d : stats90d;

    const successRate = stats.totalRequests > 0
        ? ((stats.successfulRequests / stats.totalRequests) * 100).toFixed(1)
        : '0';

    // Calculate max for bar chart
    const maxDailyCost = Math.max(...dailyUsage.map(d => d.cost), 0.01);

    return (
        <AdminLayout title="AI Usage Statistics">
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground flex items-center gap-3">
                            <Brain className="h-7 w-7 text-primary" />
                            AI Usage Statistics
                        </h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Monitor AI token usage and costs across all features
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {(['7d', '30d', '90d'] as const).map((p) => (
                            <Button
                                key={p}
                                variant={selectedPeriod === p ? 'primary' : 'outline'}
                                size="sm"
                                onClick={() => setSelectedPeriod(p)}
                            >
                                {p === '7d' ? '7 Days' : p === '30d' ? '30 Days' : '90 Days'}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Main Stats Grid */}
                <div className="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Cost"
                        value={formatCost(stats.totalCostUsd)}
                        subtitle={`${selectedPeriod} period`}
                        icon={DollarSign}
                    />
                    <StatCard
                        title="Total Tokens"
                        value={formatNumber(stats.totalTokens)}
                        subtitle={`${stats.totalRequests} requests`}
                        icon={Zap}
                    />
                    <StatCard
                        title="Success Rate"
                        value={`${successRate}%`}
                        subtitle={`${stats.failedRequests} failed`}
                        icon={BarChart3}
                        trend={parseFloat(successRate) >= 95 ? 'up' : parseFloat(successRate) < 80 ? 'down' : 'neutral'}
                    />
                    <StatCard
                        title="Avg Response"
                        value={`${Math.round(stats.avgResponseTimeMs)}ms`}
                        subtitle="Response time"
                        icon={Clock}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* By Provider */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Usage by Provider</CardTitle>
                            <CardDescription>Cost and requests breakdown</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {Object.keys(byProvider).length > 0 ? (
                                <div className="space-y-4">
                                    {Object.entries(byProvider).map(([provider, data]) => (
                                        <div key={provider} className="flex items-center justify-between p-3 rounded-lg bg-background-tertiary/50">
                                            <div className="flex items-center gap-3">
                                                <Badge className={getProviderColor(provider)}>
                                                    {provider}
                                                </Badge>
                                                <span className="text-sm text-foreground-muted">
                                                    {data.count} requests
                                                </span>
                                            </div>
                                            <span className="text-sm font-semibold text-foreground">
                                                {formatCost(data.total_cost)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Brain className="h-8 w-8 text-foreground-muted mb-2" />
                                    <p className="text-sm text-foreground-muted">No usage data</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* By Operation */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Usage by Feature</CardTitle>
                            <CardDescription>AI Chat, Error Analysis, Code Review</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {Object.keys(byOperation).length > 0 ? (
                                <div className="space-y-4">
                                    {Object.entries(byOperation).map(([operation, data]) => {
                                        const Icon = getOperationIcon(operation);
                                        return (
                                            <div key={operation} className="flex items-center justify-between p-3 rounded-lg bg-background-tertiary/50">
                                                <div className="flex items-center gap-3">
                                                    <div className="rounded-lg bg-primary/10 p-2">
                                                        <Icon className="h-4 w-4 text-primary" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-foreground">
                                                            {getOperationLabel(operation)}
                                                        </p>
                                                        <p className="text-xs text-foreground-muted">
                                                            {data.count} requests • {formatNumber(data.total_tokens)} tokens
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-sm font-semibold text-foreground">
                                                    {formatCost(data.total_cost)}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Brain className="h-8 w-8 text-foreground-muted mb-2" />
                                    <p className="text-sm text-foreground-muted">No usage data</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Daily Usage Chart */}
                <Card variant="glass" className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5 text-primary" />
                            Daily Usage
                        </CardTitle>
                        <CardDescription>Cost per day over the selected period</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {dailyUsage.length > 0 ? (
                            <div className="flex items-end gap-1 h-40">
                                {dailyUsage.slice(-30).map((day, index) => {
                                    const height = (day.cost / maxDailyCost) * 100;
                                    return (
                                        <div
                                            key={day.date}
                                            className="flex-1 group relative"
                                            title={`${day.date}: ${formatCost(day.cost)}`}
                                        >
                                            <div
                                                className="w-full bg-primary/60 hover:bg-primary transition-colors rounded-t"
                                                style={{ height: `${Math.max(height, 2)}%` }}
                                            />
                                            <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:block bg-background-secondary border border-border rounded px-2 py-1 text-xs whitespace-nowrap z-10">
                                                <div className="font-medium">{day.date}</div>
                                                <div className="text-foreground-muted">{formatCost(day.cost)}</div>
                                                <div className="text-foreground-muted">{day.requests} req</div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <BarChart3 className="h-8 w-8 text-foreground-muted mb-2" />
                                <p className="text-sm text-foreground-muted">No daily data available</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2 mt-6">
                    {/* Top Teams */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-primary" />
                                Top Teams by Usage
                            </CardTitle>
                            <CardDescription>Teams with highest AI costs</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topTeams.length > 0 ? (
                                <div className="space-y-3">
                                    {topTeams.map((team, index) => (
                                        <div key={team.team_id} className="flex items-center justify-between p-3 rounded-lg bg-background-tertiary/50">
                                            <div className="flex items-center gap-3">
                                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/20 text-xs font-bold text-primary">
                                                    {index + 1}
                                                </span>
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">
                                                        {team.team_name}
                                                    </p>
                                                    <p className="text-xs text-foreground-muted">
                                                        {team.total_requests} requests
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-sm font-semibold text-foreground">
                                                {formatCost(team.total_cost)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Users className="h-8 w-8 text-foreground-muted mb-2" />
                                    <p className="text-sm text-foreground-muted">No team data</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Model Pricing Reference */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-primary" />
                                Model Pricing
                            </CardTitle>
                            <CardDescription>Current pricing per 1M tokens</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4 max-h-80 overflow-y-auto">
                                {Object.entries(modelPricing).map(([provider, models]) => (
                                    <div key={provider}>
                                        <div className="flex items-center gap-2 mb-2">
                                            <Badge className={getProviderColor(provider)} size="sm">
                                                {provider}
                                            </Badge>
                                        </div>
                                        <div className="space-y-2 pl-2">
                                            {models.slice(0, 4).map((model) => (
                                                <div key={model.model_id} className="flex items-center justify-between text-sm">
                                                    <span className="text-foreground-muted truncate max-w-[150px]" title={model.model_name}>
                                                        {model.model_name}
                                                    </span>
                                                    <div className="flex gap-3 text-xs">
                                                        <span className="text-success" title="Input price">
                                                            ${model.input_price_per_1m}
                                                        </span>
                                                        <span className="text-warning" title="Output price">
                                                            ${model.output_price_per_1m}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {Object.keys(modelPricing).length === 0 && (
                                    <div className="flex flex-col items-center justify-center py-8 text-center">
                                        <DollarSign className="h-8 w-8 text-foreground-muted mb-2" />
                                        <p className="text-sm text-foreground-muted">Run migrations to load pricing</p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
