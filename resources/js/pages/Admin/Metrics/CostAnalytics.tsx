import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { DollarSign, Zap, Brain, TrendingUp } from 'lucide-react';

interface GlobalStats {
    totalRequests: number;
    successfulRequests: number;
    failedRequests: number;
    totalTokens: number;
    totalCostUsd: number;
    avgResponseTimeMs: number;
}

interface TeamCost {
    team_id: number;
    team_name: string;
    total_cost: number;
    request_count: number;
    total_tokens: number;
}

interface ModelCost {
    model: string;
    total_cost: number;
    request_count: number;
}

interface DailyCost {
    date: string;
    cost: number;
    requests: number;
}

interface Props {
    data: {
        globalStats: GlobalStats;
        teamCosts: TeamCost[];
        modelCosts: ModelCost[];
        dailyCosts: DailyCost[];
    };
}

function formatCost(value: number): string {
    if (value >= 1) return `$${value.toFixed(2)}`;
    if (value >= 0.01) return `$${value.toFixed(3)}`;
    return `$${value.toFixed(4)}`;
}

function formatTokens(value: number): string {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)}K`;
    return String(value);
}

export function CostAnalytics({ data }: Props) {
    if (!data?.globalStats) {
        return (
            <Card variant="glass">
                <CardContent className="p-8 text-center text-foreground-muted">
                    No AI usage data available.
                </CardContent>
            </Card>
        );
    }

    const { globalStats, teamCosts, modelCosts, dailyCosts } = data;
    const maxDailyCost = Math.max(...dailyCosts.map((d) => d.cost), 0.001);

    return (
        <div className="space-y-6">
            {/* Global Stats */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card variant="glass">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-primary/10 p-2">
                                <DollarSign className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-xs text-foreground-muted">Total Cost (30d)</p>
                                <p className="text-xl font-bold text-foreground">
                                    {formatCost(globalStats.totalCostUsd)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card variant="glass">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-info/10 p-2">
                                <Zap className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-xs text-foreground-muted">Requests (30d)</p>
                                <p className="text-xl font-bold text-foreground">
                                    {globalStats.totalRequests.toLocaleString()}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card variant="glass">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-success/10 p-2">
                                <Brain className="h-5 w-5 text-success" />
                            </div>
                            <div>
                                <p className="text-xs text-foreground-muted">Tokens Used</p>
                                <p className="text-xl font-bold text-foreground">
                                    {formatTokens(globalStats.totalTokens)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card variant="glass">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-warning/10 p-2">
                                <TrendingUp className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-xs text-foreground-muted">Avg Response</p>
                                <p className="text-xl font-bold text-foreground">
                                    {Math.round(globalStats.avgResponseTimeMs)}ms
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Daily Cost Chart */}
            {dailyCosts.length > 0 && (
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Daily Cost Trend</CardTitle>
                        <CardDescription>AI usage cost over the last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex h-32 items-end gap-1">
                            {dailyCosts.map((day) => (
                                <div
                                    key={day.date}
                                    className="group relative flex-1"
                                    title={`${day.date}: ${formatCost(day.cost)} (${day.requests} requests)`}
                                >
                                    <div
                                        className="w-full rounded-t bg-primary/70 transition-colors group-hover:bg-primary"
                                        style={{ height: `${Math.max((day.cost / maxDailyCost) * 100, 2)}%` }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-xs text-foreground-subtle">
                            <span>{dailyCosts[0]?.date}</span>
                            <span>{dailyCosts[dailyCosts.length - 1]?.date}</span>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Per-Model Costs */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Cost by Model</CardTitle>
                        <CardDescription>AI model usage breakdown</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {modelCosts.length === 0 ? (
                            <p className="text-sm text-foreground-muted">No model data</p>
                        ) : (
                            <div className="space-y-3">
                                {modelCosts.map((model) => (
                                    <div key={model.model} className="flex items-center justify-between border-b border-border/30 pb-2">
                                        <div>
                                            <p className="text-sm font-medium text-foreground">{model.model}</p>
                                            <p className="text-xs text-foreground-subtle">
                                                {model.request_count.toLocaleString()} requests
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold text-foreground">
                                            {formatCost(model.total_cost)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Per-Team Costs */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Cost by Team</CardTitle>
                        <CardDescription>AI usage per team</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {teamCosts.length === 0 ? (
                            <p className="text-sm text-foreground-muted">No team cost data</p>
                        ) : (
                            <div className="space-y-3">
                                {teamCosts.map((team) => (
                                    <div key={team.team_id} className="flex items-center justify-between border-b border-border/30 pb-2">
                                        <div>
                                            <p className="text-sm font-medium text-foreground">{team.team_name}</p>
                                            <p className="text-xs text-foreground-subtle">
                                                {team.request_count.toLocaleString()} requests · {formatTokens(team.total_tokens)} tokens
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold text-foreground">
                                            {formatCost(team.total_cost)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
