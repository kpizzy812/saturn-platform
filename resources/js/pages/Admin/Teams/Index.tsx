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
    Database,
    CreditCard,
} from 'lucide-react';

interface TeamInfo {
    id: number;
    name: string;
    owner: string;
    members_count: number;
    servers_count: number;
    applications_count: number;
    databases_count: number;
    subscription_status: 'active' | 'cancelled' | 'trial' | 'expired';
    subscription_plan?: string;
    monthly_spend?: number;
    created_at: string;
}

interface Props {
    teams: TeamInfo[];
    total: number;
}

const defaultTeams: TeamInfo[] = [
    {
        id: 1,
        name: 'Acme Corporation',
        owner: 'john.doe@example.com',
        members_count: 12,
        servers_count: 8,
        applications_count: 24,
        databases_count: 15,
        subscription_status: 'active',
        subscription_plan: 'Pro',
        monthly_spend: 299,
        created_at: '2024-01-15',
    },
    {
        id: 2,
        name: 'StartupXYZ',
        owner: 'jane.smith@example.com',
        members_count: 5,
        servers_count: 3,
        applications_count: 10,
        databases_count: 6,
        subscription_status: 'trial',
        subscription_plan: 'Trial',
        monthly_spend: 0,
        created_at: '2024-03-01',
    },
    {
        id: 3,
        name: 'Dev Team Alpha',
        owner: 'bob.wilson@example.com',
        members_count: 8,
        servers_count: 5,
        applications_count: 18,
        databases_count: 10,
        subscription_status: 'active',
        subscription_plan: 'Business',
        monthly_spend: 599,
        created_at: '2023-12-10',
    },
    {
        id: 4,
        name: 'Legacy Systems Inc',
        owner: 'legacy@example.com',
        members_count: 2,
        servers_count: 1,
        applications_count: 3,
        databases_count: 2,
        subscription_status: 'cancelled',
        subscription_plan: 'Free',
        monthly_spend: 0,
        created_at: '2023-10-05',
    },
];

function TeamRow({ team }: { team: TeamInfo }) {
    const statusConfig = {
        active: { variant: 'success' as const, label: 'Active' },
        cancelled: { variant: 'danger' as const, label: 'Cancelled' },
        trial: { variant: 'warning' as const, label: 'Trial' },
        expired: { variant: 'danger' as const, label: 'Expired' },
    };

    const config = statusConfig[team.subscription_status];

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
                                {team.subscription_plan && (
                                    <Badge variant="primary" size="sm">
                                        {team.subscription_plan}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-sm text-foreground-muted">Owner: {team.owner}</p>
                            <div className="mt-2 flex items-center gap-4 text-xs text-foreground-subtle">
                                <div className="flex items-center gap-1">
                                    <Users className="h-3 w-3" />
                                    <span>{team.members_count} members</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Server className="h-3 w-3" />
                                    <span>{team.servers_count} servers</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Database className="h-3 w-3" />
                                    <span>{team.applications_count} apps</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Database className="h-3 w-3" />
                                    <span>{team.databases_count} databases</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <Badge variant={config.variant} size="sm">
                        {config.label}
                    </Badge>
                    {team.monthly_spend !== undefined && team.monthly_spend > 0 && (
                        <div className="flex items-center gap-1 text-xs text-foreground-muted">
                            <CreditCard className="h-3 w-3" />
                            <span>${team.monthly_spend}/mo</span>
                        </div>
                    )}
                    <span className="text-xs text-foreground-subtle">
                        Since {new Date(team.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminTeamsIndex({ teams = defaultTeams, total = 4 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'active' | 'trial' | 'cancelled' | 'expired'>('all');

    const filteredTeams = teams.filter((team) => {
        const matchesSearch =
            team.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            team.owner.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || team.subscription_status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const activeCount = teams.filter((t) => t.subscription_status === 'active').length;
    const trialCount = teams.filter((t) => t.subscription_status === 'trial').length;
    const totalRevenue = teams.reduce((sum, t) => sum + (t.monthly_spend || 0), 0);

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
                                    <p className="text-sm text-foreground-subtle">Active Teams</p>
                                    <p className="text-2xl font-bold text-success">{activeCount}</p>
                                </div>
                                <Users className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Trial Teams</p>
                                    <p className="text-2xl font-bold text-warning">{trialCount}</p>
                                </div>
                                <Users className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Monthly Revenue</p>
                                    <p className="text-2xl font-bold text-primary">${totalRevenue}</p>
                                </div>
                                <CreditCard className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search teams by name or owner..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'active' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('active')}
                                >
                                    Active
                                </Button>
                                <Button
                                    variant={statusFilter === 'trial' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('trial')}
                                >
                                    Trial
                                </Button>
                                <Button
                                    variant={statusFilter === 'cancelled' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('cancelled')}
                                >
                                    Cancelled
                                </Button>
                            </div>
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
