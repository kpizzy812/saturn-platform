import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Bell,
    BellOff,
    Users,
    TrendingUp,
    Search,
    ArrowLeft,
} from 'lucide-react';

interface ChannelInfo {
    enabled: boolean;
    events_enabled: number;
    events_total: number;
}

interface TeamInfo {
    id: number;
    name: string;
    channels: Record<string, ChannelInfo>;
    enabled_channels_count: number;
}

interface Stats {
    total_teams: number;
    configured_teams: number;
    unconfigured_teams: number;
    most_popular_channel: string | null;
    most_popular_count: number;
    channel_popularity: Record<string, number>;
}

interface Props {
    teams: TeamInfo[];
    stats: Stats;
}

const CHANNEL_NAMES = ['discord', 'slack', 'telegram', 'email', 'pushover', 'webhook'] as const;
type ChannelName = (typeof CHANNEL_NAMES)[number];

const CHANNEL_LABELS: Record<ChannelName, string> = {
    discord: 'Discord',
    slack: 'Slack',
    telegram: 'Telegram',
    email: 'Email',
    pushover: 'Pushover',
    webhook: 'Webhook',
};

function ChannelBadge({ name, info }: { name: ChannelName; info: ChannelInfo }) {
    if (!info.enabled) {
        return (
            <Badge variant="secondary" size="sm" className="opacity-50">
                {CHANNEL_LABELS[name]}
            </Badge>
        );
    }

    return (
        <Badge variant="success" size="sm">
            {CHANNEL_LABELS[name]} {info.events_enabled}/{info.events_total}
        </Badge>
    );
}

function TeamRow({ team }: { team: TeamInfo }) {
    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">{team.name}</span>
                                <Badge variant={team.enabled_channels_count > 0 ? 'primary' : 'secondary'} size="sm">
                                    {team.enabled_channels_count} channel{team.enabled_channels_count !== 1 ? 's' : ''}
                                </Badge>
                            </div>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {CHANNEL_NAMES.map((ch) => (
                                    <ChannelBadge key={ch} name={ch} info={team.channels[ch]} />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminNotificationsOverview({ teams, stats }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [channelFilter, setChannelFilter] = React.useState<string>('all');

    const filteredTeams = React.useMemo(() => {
        let result = teams;

        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            result = result.filter((t) => t.name.toLowerCase().includes(q));
        }

        if (channelFilter !== 'all') {
            result = result.filter((t) => t.channels[channelFilter]?.enabled);
        }

        return result;
    }, [teams, searchQuery, channelFilter]);

    const popularLabel = stats.most_popular_channel
        ? `${CHANNEL_LABELS[stats.most_popular_channel as ChannelName]} (${stats.most_popular_count} team${stats.most_popular_count !== 1 ? 's' : ''})`
        : 'None';

    return (
        <AdminLayout
            title="Notification Channels Overview"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Notifications', href: '/admin/notifications' },
                { label: 'Channel Overview' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Notification Channels Overview</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Overview of notification channels configured across all teams
                        </p>
                    </div>
                    <Link href="/admin/notifications">
                        <Button variant="secondary" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            System Notifications
                        </Button>
                    </Link>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Teams</p>
                                    <p className="text-2xl font-bold text-primary">{stats.total_teams}</p>
                                </div>
                                <Users className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Configured</p>
                                    <p className="text-2xl font-bold text-success">{stats.configured_teams}</p>
                                </div>
                                <Bell className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Unconfigured</p>
                                    <p className="text-2xl font-bold text-warning">{stats.unconfigured_teams}</p>
                                </div>
                                <BellOff className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Most Popular</p>
                                    <p className="text-lg font-bold text-foreground">{popularLabel}</p>
                                </div>
                                <TrendingUp className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search teams by name..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant={channelFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setChannelFilter('all')}
                                >
                                    All
                                </Button>
                                {CHANNEL_NAMES.map((ch) => (
                                    <Button
                                        key={ch}
                                        variant={channelFilter === ch ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => setChannelFilter(ch)}
                                    >
                                        {CHANNEL_LABELS[ch]}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Teams List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredTeams.length} of {teams.length} teams
                            </p>
                        </div>

                        {filteredTeams.length === 0 ? (
                            <div className="py-12 text-center">
                                <BellOff className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No teams found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filter
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
