import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { TabsRoot, TabsList, TabsTrigger, TabsContent, TabsPanels } from '@/components/ui/Tabs';
import { useConfirm } from '@/components/ui';
import {
    User,
    Mail,
    Calendar,
    Shield,
    Server,
    Users,
    Activity,
    CreditCard,
    UserCheck,
    Ban,
    Trash2,
} from 'lucide-react';

interface UserDetails {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'suspended' | 'pending';
    is_root_user: boolean;
    created_at: string;
    updated_at: string;
    last_login_at?: string;
    email_verified_at?: string;
}

interface Team {
    id: number;
    name: string;
    role: string;
    members_count: number;
}

interface Server {
    id: number;
    name: string;
    ip: string;
    status: 'online' | 'offline';
    team: string;
}

interface Application {
    id: number;
    name: string;
    status: 'running' | 'stopped';
    team: string;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string;
    timestamp: string;
    ip_address?: string;
}

interface BillingInfo {
    plan: string;
    status: 'active' | 'cancelled';
    current_period_end: string;
    monthly_cost: number;
}

interface Props {
    user: UserDetails;
    teams: Team[];
    servers: Server[];
    applications: Application[];
    activityLogs: ActivityLog[];
    billing?: BillingInfo;
}

const defaultUser: UserDetails = {
    id: 1,
    name: 'John Doe',
    email: 'john.doe@example.com',
    status: 'active',
    is_root_user: false,
    created_at: '2024-01-15T10:30:00Z',
    updated_at: '2024-03-10T14:20:00Z',
    last_login_at: '2024-03-10T14:20:00Z',
    email_verified_at: '2024-01-15T11:00:00Z',
};

const defaultTeams: Team[] = [
    { id: 1, name: 'Personal', role: 'owner', members_count: 1 },
    { id: 2, name: 'Production Team', role: 'admin', members_count: 5 },
    { id: 3, name: 'Staging Team', role: 'member', members_count: 3 },
];

const defaultServers: Server[] = [
    { id: 1, name: 'production-1', ip: '192.168.1.100', status: 'online', team: 'Production Team' },
    { id: 2, name: 'staging-1', ip: '192.168.1.101', status: 'online', team: 'Staging Team' },
];

const defaultApplications: Application[] = [
    { id: 1, name: 'api-service', status: 'running', team: 'Production Team' },
    { id: 2, name: 'web-app', status: 'running', team: 'Production Team' },
];

const defaultActivityLogs: ActivityLog[] = [
    {
        id: 1,
        action: 'Login',
        description: 'User logged in',
        timestamp: '2024-03-10T14:20:00Z',
        ip_address: '192.168.1.50',
    },
    {
        id: 2,
        action: 'Deployment',
        description: 'Deployed api-service to production',
        timestamp: '2024-03-10T12:15:00Z',
        ip_address: '192.168.1.50',
    },
    {
        id: 3,
        action: 'Server Added',
        description: 'Added server production-1',
        timestamp: '2024-03-09T09:30:00Z',
        ip_address: '192.168.1.50',
    },
];

export default function AdminUserShow({
    user = defaultUser,
    teams = defaultTeams,
    servers = defaultServers,
    applications = defaultApplications,
    activityLogs = defaultActivityLogs,
    billing,
}: Props) {
    const confirm = useConfirm();

    const handleImpersonate = async () => {
        const confirmed = await confirm({
            title: 'Impersonate User',
            description: `Impersonate ${user.name}? You will be logged in as this user.`,
            confirmText: 'Impersonate',
            variant: 'warning',
        });
        if (confirmed) {
            router.post(`/admin/users/${user.id}/impersonate`);
        }
    };

    const handleToggleSuspension = async () => {
        const isSuspended = user.status === 'suspended';
        const confirmed = await confirm({
            title: isSuspended ? 'Activate User' : 'Suspend User',
            description: `${isSuspended ? 'Activate' : 'Suspend'} ${user.name}?`,
            confirmText: isSuspended ? 'Activate' : 'Suspend',
            variant: isSuspended ? 'warning' : 'danger',
        });
        if (confirmed) {
            router.post(`/admin/users/${user.id}/toggle-suspension`);
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete User',
            description: `Are you sure you want to delete ${user.name}? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/users/${user.id}`);
        }
    };

    const statusConfig = {
        active: { variant: 'success' as const, label: 'Active' },
        suspended: { variant: 'danger' as const, label: 'Suspended' },
        pending: { variant: 'warning' as const, label: 'Pending' },
    };

    const config = statusConfig[user.status];

    return (
        <AdminLayout
            title={user.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Users', href: '/admin/users' },
                { label: user.name },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-2xl font-medium text-white">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{user.name}</h1>
                                    {user.is_root_user && (
                                        <Badge variant="primary" icon={<Shield className="h-3 w-3" />}>
                                            Admin
                                        </Badge>
                                    )}
                                    <Badge variant={config.variant}>{config.label}</Badge>
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">{user.email}</p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={handleImpersonate}>
                                <UserCheck className="h-4 w-4" />
                                Impersonate
                            </Button>
                            <Button
                                variant="secondary"
                                onClick={handleToggleSuspension}
                            >
                                {user.status === 'suspended' ? (
                                    <>
                                        <UserCheck className="h-4 w-4" />
                                        Activate
                                    </>
                                ) : (
                                    <>
                                        <Ban className="h-4 w-4" />
                                        Suspend
                                    </>
                                )}
                            </Button>
                            <Button variant="danger" onClick={handleDelete}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                {/* User Info */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-6">
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="flex items-start gap-3">
                                <Mail className="mt-1 h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Email</p>
                                    <p className="text-sm text-foreground">{user.email}</p>
                                    {user.email_verified_at && (
                                        <Badge variant="success" size="sm" className="mt-1">
                                            Verified
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Calendar className="mt-1 h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Joined</p>
                                    <p className="text-sm text-foreground">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Activity className="mt-1 h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Last Login</p>
                                    <p className="text-sm text-foreground">
                                        {user.last_login_at
                                            ? new Date(user.last_login_at).toLocaleDateString()
                                            : 'Never'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Shield className="mt-1 h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Role</p>
                                    <p className="text-sm text-foreground">
                                        {user.is_root_user ? 'Super Admin' : 'User'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                <TabsRoot defaultIndex={0}>
                    <TabsList>
                        <TabsTrigger>Teams ({teams.length})</TabsTrigger>
                        <TabsTrigger>Servers ({servers.length})</TabsTrigger>
                        <TabsTrigger>Applications ({applications.length})</TabsTrigger>
                        <TabsTrigger>Activity</TabsTrigger>
                        {billing && <TabsTrigger>Billing</TabsTrigger>}
                    </TabsList>

                    <TabsPanels>
                    <TabsContent className="mt-6">
                        <Card variant="glass">
                            <CardHeader>
                                <CardTitle>Teams</CardTitle>
                                <CardDescription>Teams this user belongs to</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {teams.map((team) => (
                                    <div
                                        key={team.id}
                                        className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 text-foreground-muted" />
                                            <div>
                                                <p className="font-medium text-foreground">{team.name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    {team.members_count} members
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant="primary" size="sm">
                                            {team.role}
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent className="mt-6">
                        <Card variant="glass">
                            <CardHeader>
                                <CardTitle>Servers</CardTitle>
                                <CardDescription>Servers managed by this user</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {servers.map((server) => (
                                    <div
                                        key={server.id}
                                        className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Server className="h-5 w-5 text-foreground-muted" />
                                            <div>
                                                <p className="font-medium text-foreground">{server.name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    {server.ip} · {server.team}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge
                                            variant={server.status === 'online' ? 'success' : 'danger'}
                                            size="sm"
                                        >
                                            {server.status}
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent className="mt-6">
                        <Card variant="glass">
                            <CardHeader>
                                <CardTitle>Applications</CardTitle>
                                <CardDescription>Applications deployed by this user</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {applications.map((app) => (
                                    <div
                                        key={app.id}
                                        className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div>
                                            <p className="font-medium text-foreground">{app.name}</p>
                                            <p className="text-sm text-foreground-muted">{app.team}</p>
                                        </div>
                                        <Badge
                                            variant={app.status === 'running' ? 'success' : 'danger'}
                                            size="sm"
                                        >
                                            {app.status}
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent className="mt-6">
                        <Card variant="glass">
                            <CardHeader>
                                <CardTitle>Activity Log</CardTitle>
                                <CardDescription>Recent user activity</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {activityLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <p className="font-medium text-foreground">{log.action}</p>
                                                <p className="text-sm text-foreground-muted">{log.description}</p>
                                                {log.ip_address && (
                                                    <p className="text-xs text-foreground-subtle">
                                                        IP: {log.ip_address}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="text-xs text-foreground-subtle">
                                                {new Date(log.timestamp).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {billing && (
                        <TabsContent className="mt-6">
                            <Card variant="glass">
                                <CardHeader>
                                    <CardTitle>Billing Information</CardTitle>
                                    <CardDescription>Subscription and payment details</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm text-foreground-subtle">Current Plan</p>
                                                <p className="font-medium text-foreground">{billing.plan}</p>
                                            </div>
                                            <Badge
                                                variant={billing.status === 'active' ? 'success' : 'danger'}
                                            >
                                                {billing.status}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-sm text-foreground-subtle">Monthly Cost</p>
                                            <p className="font-medium text-foreground">
                                                ${billing.monthly_cost.toFixed(2)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-foreground-subtle">Current Period Ends</p>
                                            <p className="font-medium text-foreground">
                                                {new Date(billing.current_period_end).toLocaleDateString()}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    )}
                    </TabsPanels>
                </TabsRoot>
            </div>
        </AdminLayout>
    );
}
