import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    LogIn,
    ShieldAlert,
    Users,
    Globe,
    AlertTriangle,
    Clock,
    User,
    Monitor,
} from 'lucide-react';

interface LoginHistoryEntry {
    id: number;
    user_id: number | null;
    user_name: string | null;
    user_email: string | null;
    ip_address: string;
    user_agent: string;
    status: string;
    location: string | null;
    failure_reason: string | null;
    logged_at: string;
}

interface Props {
    entries: {
        data: LoginHistoryEntry[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        totalToday: number;
        failedToday: number;
        uniqueIpsToday: number;
        suspiciousCount: number;
    };
    filters: {
        user_id?: string;
        status?: string;
        ip?: string;
        days?: string;
    };
}

function StatCard({ title, value, icon: Icon, variant }: {
    title: string;
    value: number | string;
    icon: React.ComponentType<{ className?: string }>;
    variant?: 'default' | 'danger';
}) {
    return (
        <Card variant="glass" hover>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-sm text-foreground-muted">{title}</p>
                        <p className="mt-2 text-3xl font-bold text-foreground">{value}</p>
                    </div>
                    <div className={`rounded-lg p-3 ${variant === 'danger' ? 'bg-danger/10' : 'bg-primary/10'}`}>
                        <Icon className={`h-6 w-6 ${variant === 'danger' ? 'text-danger' : 'text-primary'}`} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
}

function truncateUserAgent(userAgent: string, maxLength: number = 50): string {
    if (userAgent.length <= maxLength) return userAgent;
    return userAgent.substring(0, maxLength) + '...';
}

function LoginRow({ entry }: { entry: LoginHistoryEntry }) {
    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-cyan-500 text-sm font-medium text-white">
                            {entry.user_name ? entry.user_name.charAt(0).toUpperCase() : '?'}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {entry.user_name || 'Unknown User'}
                                </span>
                                <Badge
                                    variant={entry.status === 'success' ? 'success' : 'danger'}
                                    size="sm"
                                >
                                    {entry.status}
                                </Badge>
                            </div>
                            {entry.user_email && (
                                <p className="text-sm text-foreground-muted">{entry.user_email}</p>
                            )}
                            <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-foreground-subtle">
                                <span className="flex items-center gap-1">
                                    <Globe className="h-3 w-3" />
                                    {entry.ip_address}
                                    {entry.location && ` (${entry.location})`}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Monitor className="h-3 w-3" />
                                    {truncateUserAgent(entry.user_agent)}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {formatRelativeTime(entry.logged_at)}
                                </span>
                            </div>
                            {entry.failure_reason && (
                                <div className="mt-2 flex items-center gap-1 text-xs text-danger">
                                    <AlertTriangle className="h-3 w-3" />
                                    <span>{entry.failure_reason}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminLoginHistoryIndex({ entries, stats, filters }: Props) {
    const [ipQuery, setIpQuery] = React.useState(filters.ip ?? '');
    const [selectedStatus, setSelectedStatus] = React.useState(filters.status ?? '');
    const [selectedDays, setSelectedDays] = React.useState(filters.days ?? '');

    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (ipQuery !== (filters.ip ?? '')) {
                applyFilters({ ip: ipQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [ipQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            status: filters.status,
            ip: filters.ip,
            days: filters.days,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        router.get(`/admin/login-history?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleFilterChange = (key: string, value: string) => {
        switch (key) {
            case 'status':
                setSelectedStatus(value);
                break;
            case 'days':
                setSelectedDays(value);
                break;
        }
        applyFilters({ [key]: value || undefined });
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', page.toString());
        router.get(`/admin/login-history?${params.toString()}`);
    };

    return (
        <AdminLayout
            title="Login History"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Login History' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Login History</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Security audit: login attempts across the platform
                    </p>
                </div>

                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <StatCard
                        title="Total Today"
                        value={stats.totalToday}
                        icon={LogIn}
                    />
                    <StatCard
                        title="Failed Today"
                        value={stats.failedToday}
                        icon={ShieldAlert}
                        variant={stats.failedToday > 0 ? 'danger' : 'default'}
                    />
                    <StatCard
                        title="Unique IPs"
                        value={stats.uniqueIpsToday}
                        icon={Globe}
                    />
                    <StatCard
                        title="Suspicious Users"
                        value={stats.suspiciousCount}
                        icon={AlertTriangle}
                        variant={stats.suspiciousCount > 0 ? 'danger' : 'default'}
                    />
                </div>

                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-foreground-subtle">
                                    IP Address
                                </label>
                                <Input
                                    placeholder="Search by IP..."
                                    value={ipQuery}
                                    onChange={(e) => setIpQuery(e.target.value)}
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-foreground-subtle">
                                    Status
                                </label>
                                <select
                                    value={selectedStatus}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-foreground-subtle">
                                    Time Range
                                </label>
                                <select
                                    value={selectedDays}
                                    onChange={(e) => handleFilterChange('days', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Time</option>
                                    <option value="1">Last 24 Hours</option>
                                    <option value="7">Last 7 Days</option>
                                    <option value="30">Last 30 Days</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card variant="glass">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Login Attempts</CardTitle>
                                <CardDescription>
                                    Showing {entries.data.length} of {entries.total} entries
                                </CardDescription>
                            </div>
                            {entries.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={entries.current_page === 1}
                                        onClick={() => handlePageChange(entries.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-sm text-foreground-muted">
                                        Page {entries.current_page} of {entries.last_page}
                                    </span>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={entries.current_page === entries.last_page}
                                        onClick={() => handlePageChange(entries.current_page + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {entries.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <LogIn className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No login attempts found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {entries.data.map((entry) => (
                                    <LoginRow key={entry.id} entry={entry} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
