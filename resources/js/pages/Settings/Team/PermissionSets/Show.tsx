import * as React from 'react';
import { SettingsLayout } from '../../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Link, router } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';
import {
    ArrowLeft,
    Check,
    X,
    Crown,
    Shield,
    User as UserIcon,
    Code2,
    Eye,
    Edit,
    Users,
    ShieldAlert,
} from 'lucide-react';

interface Permission {
    id: number;
    key: string;
    name: string;
    description?: string;
    category: string;
    is_sensitive?: boolean;
    environment_restrictions?: Record<string, boolean> | null;
}

interface User {
    id: number;
    name: string;
    email: string;
    environment_overrides?: Record<string, boolean> | null;
}

interface PermissionSet {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    color: string | null;
    icon: string | null;
    users_count: number;
    permissions: Permission[];
    users: User[];
    parent?: {
        id: number;
        name: string;
        slug: string;
    } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    permissionSet: PermissionSet;
    allPermissions: Record<string, Permission[]>;
}

const iconMap: Record<string, React.ReactNode> = {
    crown: <Crown className="h-5 w-5" />,
    shield: <Shield className="h-5 w-5" />,
    code: <Code2 className="h-5 w-5" />,
    user: <UserIcon className="h-5 w-5" />,
    eye: <Eye className="h-5 w-5" />,
};

const colorMap: Record<string, string> = {
    warning: 'text-warning',
    primary: 'text-primary',
    success: 'text-success',
    info: 'text-info',
    'foreground-muted': 'text-foreground-muted',
};

const categoryLabels: Record<string, string> = {
    resources: 'Resources',
    team: 'Team Management',
    settings: 'Settings',
};

export default function PermissionSetShow({ permissionSet, allPermissions }: Props) {
    const { toast } = useToast();

    const getIcon = () => {
        return iconMap[permissionSet.icon || 'user'] || <UserIcon className="h-5 w-5" />;
    };

    const getColorClass = () => {
        return colorMap[permissionSet.color || 'foreground-muted'] || 'text-foreground-muted';
    };

    const hasPermission = (permissionKey: string) => {
        return permissionSet.permissions.some((p) => p.key === permissionKey);
    };

    const getPermissionRestrictions = (permissionKey: string) => {
        const perm = permissionSet.permissions.find((p) => p.key === permissionKey);
        return perm?.environment_restrictions || null;
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/settings/team/permission-sets">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div className="flex items-center gap-4">
                            <div
                                className={`flex h-12 w-12 items-center justify-center rounded-full bg-background-tertiary ${getColorClass()}`}
                            >
                                {getIcon()}
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-2xl font-semibold text-foreground">{permissionSet.name}</h2>
                                    {permissionSet.is_system && <Badge variant="default">Built-in</Badge>}
                                </div>
                                <p className="text-sm text-foreground-muted">
                                    {permissionSet.description || 'No description'}
                                </p>
                            </div>
                        </div>
                    </div>
                    {!permissionSet.is_system && (
                        <Link href={`/settings/team/permission-sets/${permissionSet.id}/edit`}>
                            <Button>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Role
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Overview */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                    <Users className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold text-foreground">{permissionSet.users_count}</p>
                                    <p className="text-sm text-foreground-muted">Assigned Users</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-success/10">
                                    <Check className="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold text-foreground">
                                        {permissionSet.permissions.length}
                                    </p>
                                    <p className="text-sm text-foreground-muted">Permissions Granted</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-warning/10">
                                    <ShieldAlert className="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p className="text-2xl font-semibold text-foreground">
                                        {permissionSet.permissions.filter((p) => p.is_sensitive).length}
                                    </p>
                                    <p className="text-sm text-foreground-muted">Sensitive Permissions</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Permissions Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>
                            Permissions granted to users with this role
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {Object.entries(allPermissions).map(([category, permissions]) => (
                                <div key={category}>
                                    <h4 className="mb-3 text-sm font-semibold text-foreground uppercase tracking-wide">
                                        {categoryLabels[category] || category}
                                    </h4>
                                    <div className="grid gap-2">
                                        {permissions.map((permission) => {
                                            const granted = hasPermission(permission.key);
                                            const restrictions = getPermissionRestrictions(permission.key);

                                            return (
                                                <div
                                                    key={permission.id}
                                                    className={`flex items-center justify-between rounded-lg border p-3 ${
                                                        granted
                                                            ? 'border-success/30 bg-success/5'
                                                            : 'border-border bg-background'
                                                    }`}
                                                >
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <p className="text-sm font-medium text-foreground">
                                                                {permission.name}
                                                            </p>
                                                            {permission.is_sensitive && (
                                                                <Badge variant="warning" className="text-xs">
                                                                    Sensitive
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-xs text-foreground-muted">
                                                            {permission.description}
                                                        </p>
                                                        {restrictions && Object.keys(restrictions).length > 0 && (
                                                            <div className="mt-1 flex items-center gap-1">
                                                                <span className="text-xs text-foreground-subtle">
                                                                    Environment restrictions:
                                                                </span>
                                                                {Object.entries(restrictions).map(([env, allowed]) => (
                                                                    <Badge
                                                                        key={env}
                                                                        variant={allowed ? 'success' : 'danger'}
                                                                        className="text-xs"
                                                                    >
                                                                        {env}: {allowed ? 'Yes' : 'No'}
                                                                    </Badge>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {granted ? (
                                                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-success/20">
                                                                <Check className="h-4 w-4 text-success" />
                                                            </div>
                                                        ) : (
                                                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-foreground-muted/20">
                                                                <X className="h-4 w-4 text-foreground-muted" />
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Assigned Users */}
                <Card>
                    <CardHeader>
                        <CardTitle>Assigned Users</CardTitle>
                        <CardDescription>
                            Users who have this role assigned
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {permissionSet.users.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Users className="h-12 w-12 text-foreground-muted mb-4" />
                                <p className="text-foreground-muted">
                                    No users assigned to this role yet.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {permissionSet.users.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-xs font-semibold text-white">
                                                {user.name
                                                    .split(' ')
                                                    .map((n) => n[0])
                                                    .join('')
                                                    .toUpperCase()
                                                    .slice(0, 2)}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-foreground">{user.name}</p>
                                                <p className="text-xs text-foreground-muted">{user.email}</p>
                                            </div>
                                        </div>
                                        {user.environment_overrides && Object.keys(user.environment_overrides).length > 0 && (
                                            <div className="flex items-center gap-1">
                                                <span className="text-xs text-foreground-subtle">Overrides:</span>
                                                {Object.entries(user.environment_overrides).map(([env, allowed]) => (
                                                    <Badge
                                                        key={env}
                                                        variant={allowed ? 'success' : 'danger'}
                                                        className="text-xs"
                                                    >
                                                        {env}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
