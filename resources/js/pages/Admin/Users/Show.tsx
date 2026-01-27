import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';

import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Mail,
    Calendar,
    Shield,
    Users,
    Activity,
    UserCheck,
    Ban,
    Trash2,
} from 'lucide-react';

interface UserDetails {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_superadmin: boolean;
    force_password_reset?: boolean;
    created_at: string;
    updated_at: string;
    teams: {
        id: number;
        name: string;
        personal_team: boolean;
        role: string;
    }[];
}

interface Props {
    user: UserDetails;
}

export default function AdminUserShow({
    user,
}: Props) {
    const teams = user?.teams ?? [];
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
        const confirmed = await confirm({
            title: 'Toggle User Status',
            description: `Change status for ${user.name}?`,
            confirmText: 'Confirm',
            variant: 'warning',
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

    const isVerified = !!user.email_verified_at;
    const config = isVerified
        ? { variant: 'success' as const, label: 'Verified' }
        : { variant: 'warning' as const, label: 'Unverified' };

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
                                    {user.is_superadmin && (
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
                                {<>
                                    <Ban className="h-4 w-4" />
                                    Toggle Status
                                </>}
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
                                    <p className="text-xs text-foreground-subtle">Updated</p>
                                    <p className="text-sm text-foreground">
                                        {new Date(user.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Shield className="mt-1 h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Role</p>
                                    <p className="text-sm text-foreground">
                                        {user.is_superadmin ? 'Super Admin' : 'User'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                {/* Teams */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Teams ({teams.length})</CardTitle>
                        <CardDescription>Teams this user belongs to</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {teams.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No teams</p>
                        ) : (
                            teams.map((team) => (
                                <div
                                    key={team.id}
                                    className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                >
                                    <div className="flex items-center gap-3">
                                        <Users className="h-5 w-5 text-foreground-muted" />
                                        <div>
                                            <p className="font-medium text-foreground">{team.name}</p>
                                            {team.personal_team && (
                                                <p className="text-sm text-foreground-muted">Personal team</p>
                                            )}
                                        </div>
                                    </div>
                                    <Badge variant="primary" size="sm">
                                        {team.role}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
