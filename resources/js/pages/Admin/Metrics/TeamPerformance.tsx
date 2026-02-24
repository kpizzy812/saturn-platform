import * as React from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Users, Server, Box, Database, Rocket, TrendingUp } from 'lucide-react';

interface TeamData {
    id: number;
    name: string;
    members_count: number;
    servers_count: number;
    projects_count: number;
    applications: number;
    databases: number;
    deployments_30d: number;
    success_rate: number;
    quotas: {
        max_servers: number | null;
        max_applications: number | null;
        max_databases: number | null;
        max_projects: number | null;
    };
}

interface Props {
    data: TeamData[];
}

function QuotaIndicator({ current, limit, label }: { current: number; limit: number | null; label: string }) {
    if (limit === null) {
        return (
            <span className="text-xs text-foreground-subtle" title={`${label}: ${current} (unlimited)`}>
                {current}
            </span>
        );
    }

    const pct = limit > 0 ? (current / limit) * 100 : 0;
    const variant = pct >= 90 ? 'danger' : pct >= 75 ? 'warning' : 'success';

    return (
        <span title={`${label}: ${current}/${limit}`}>
            <Badge variant={variant} size="sm">{current}/{limit}</Badge>
        </span>
    );
}

export function TeamPerformance({ data }: Props) {
    if (!data?.length) {
        return (
            <Card variant="glass">
                <CardContent className="p-8 text-center text-foreground-muted">
                    No team data available.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card variant="glass">
            <CardHeader>
                <CardTitle>Team Performance</CardTitle>
                <CardDescription>Resource usage and deployment statistics per team (last 30 days)</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border/50">
                                <th className="pb-3 text-left font-medium text-foreground-muted">Team</th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <Users className="mx-auto h-4 w-4" />
                                </th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <Server className="mx-auto h-4 w-4" />
                                </th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <Box className="mx-auto h-4 w-4" />
                                </th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <Database className="mx-auto h-4 w-4" />
                                </th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <Rocket className="mx-auto h-4 w-4" />
                                </th>
                                <th className="pb-3 text-center font-medium text-foreground-muted">
                                    <TrendingUp className="mx-auto h-4 w-4" />
                                </th>
                            </tr>
                            <tr className="border-b border-border/30">
                                <th className="pb-2 text-left text-xs text-foreground-subtle">Name</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">Members</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">Servers</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">Apps</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">DBs</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">Deploys</th>
                                <th className="pb-2 text-center text-xs text-foreground-subtle">Success</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.map((team) => (
                                <tr key={team.id} className="border-b border-border/20 hover:bg-foreground/[0.02]">
                                    <td className="py-3 font-medium text-foreground">{team.name}</td>
                                    <td className="py-3 text-center text-foreground-muted">{team.members_count}</td>
                                    <td className="py-3 text-center">
                                        <QuotaIndicator current={team.servers_count} limit={team.quotas.max_servers} label="Servers" />
                                    </td>
                                    <td className="py-3 text-center">
                                        <QuotaIndicator current={team.applications} limit={team.quotas.max_applications} label="Applications" />
                                    </td>
                                    <td className="py-3 text-center">
                                        <QuotaIndicator current={team.databases} limit={team.quotas.max_databases} label="Databases" />
                                    </td>
                                    <td className="py-3 text-center text-foreground-muted">{team.deployments_30d}</td>
                                    <td className="py-3 text-center">
                                        <Badge
                                            variant={team.success_rate >= 90 ? 'success' : team.success_rate >= 70 ? 'warning' : 'danger'}
                                            size="sm"
                                        >
                                            {team.success_rate}%
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
