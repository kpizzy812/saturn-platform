import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Users,
    Server,
} from 'lucide-react';

interface TeamInfo {
    id: number;
    name: string;
    description?: string;
    personal_team: boolean;
    members_count: number;
    servers_count: number;
    created_at: string;
    updated_at?: string;
}

interface Props {
    teams: {
        data: TeamInfo[];
        total: number;
    };
}

function TeamRow({ team }: { team: TeamInfo }) {
    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 text-sm font-medium text-white">
                            {team.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/teams/${team.id}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {team.name}
                                </Link>
                                {team.personal_team && (
                                    <Badge variant="default" size="sm">
                                        Personal
                                    </Badge>
                                )}
                            </div>
                            {team.description && (
                                <p className="text-sm text-foreground-muted">{team.description}</p>
                            )}
                            <div className="mt-2 flex items-center gap-4 text-xs text-foreground-subtle">
                                <div className="flex items-center gap-1">
                                    <Users className="h-3 w-3" />
                                    <span>{team.members_count} members</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Server className="h-3 w-3" />
                                    <span>{team.servers_count} servers</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <span className="text-xs text-foreground-subtle">
                        Since {new Date(team.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminTeamsIndex({ teams: teamsData }: Props) {
    const items = teamsData?.data ?? [];
    const total = teamsData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');

    const filteredTeams = items.filter((team) => {
        return team.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (team.description || '').toLowerCase().includes(searchQuery.toLowerCase());
    });

    const totalMembers = items.reduce((sum, t) => sum + t.members_count, 0);
    const totalServers = items.reduce((sum, t) => sum + t.servers_count, 0);

    return (
        <AdminLayout
            title="Teams"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Teams' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Team Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all teams and their subscriptions
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Teams</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <Users className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Members</p>
                                    <p className="text-2xl font-bold text-success">{totalMembers}</p>
                                </div>
                                <Users className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Servers</p>
                                    <p className="text-2xl font-bold text-foreground">{totalServers}</p>
                                </div>
                                <Server className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                placeholder="Search teams by name..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Teams List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredTeams.length} of {total} teams
                            </p>
                        </div>

                        {filteredTeams.length === 0 ? (
                            <div className="py-12 text-center">
                                <Users className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No teams found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredTeams.map((team) => (
                                    <TeamRow key={team.id} team={team} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
