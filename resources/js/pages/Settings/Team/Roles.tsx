import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Link } from '@inertiajs/react';
import {
    Crown,
    Shield,
    User as UserIcon,
    ArrowLeft,
    Check,
    X,
    Code2,
    Eye
} from 'lucide-react';

type Permission = {
    id: string;
    name: string;
    description: string;
    category: 'resources' | 'team' | 'settings';
};

type Role = {
    id: string;
    name: string;
    description: string;
    isBuiltIn: boolean;
    icon: React.ReactNode;
    permissions: string[];
    color: string;
};

const permissions: Permission[] = [
    // Resources
    { id: 'view_resources', name: 'View Resources', description: 'View applications, databases, and services', category: 'resources' },
    { id: 'deploy_resources', name: 'Deploy Resources', description: 'Deploy and restart applications', category: 'resources' },
    { id: 'edit_resources', name: 'Edit Resources', description: 'Modify resource configurations', category: 'resources' },
    { id: 'delete_resources', name: 'Delete Resources', description: 'Delete applications, databases, and services', category: 'resources' },
    { id: 'manage_env_vars', name: 'Manage Environment Variables', description: 'View and edit environment variables', category: 'resources' },
    { id: 'view_logs', name: 'View Logs', description: 'Access application and deployment logs', category: 'resources' },

    // Team
    { id: 'view_team', name: 'View Team', description: 'See team members and their roles', category: 'team' },
    { id: 'invite_members', name: 'Invite Members', description: 'Send team invitations', category: 'team' },
    { id: 'manage_members', name: 'Manage Members', description: 'Change roles and remove members', category: 'team' },
    { id: 'view_activity', name: 'View Activity', description: 'Access team activity logs', category: 'team' },

    // Settings
    { id: 'view_settings', name: 'View Settings', description: 'View team and project settings', category: 'settings' },
    { id: 'edit_settings', name: 'Edit Settings', description: 'Modify team and project settings', category: 'settings' },
    { id: 'manage_integrations', name: 'Manage Integrations', description: 'Connect and configure integrations', category: 'settings' },
    { id: 'manage_tokens', name: 'Manage API Tokens', description: 'Create and revoke API tokens', category: 'settings' },
];

const defaultRoles: Role[] = [
    {
        id: 'owner',
        name: 'Owner',
        description: 'Full control of the team and all resources',
        isBuiltIn: true,
        icon: <Crown className="h-4 w-4" />,
        color: 'text-warning',
        permissions: permissions.map(p => p.id),
    },
    {
        id: 'admin',
        name: 'Admin',
        description: 'Manage team members and settings',
        isBuiltIn: true,
        icon: <Shield className="h-4 w-4" />,
        color: 'text-primary',
        permissions: [
            'view_resources', 'deploy_resources', 'edit_resources', 'delete_resources',
            'manage_env_vars', 'view_logs', 'view_team', 'invite_members',
            'manage_members', 'view_activity', 'view_settings', 'edit_settings',
            'manage_integrations', 'manage_tokens'
        ],
    },
    {
        id: 'developer',
        name: 'Developer',
        description: 'Deploy applications and manage resources',
        isBuiltIn: true,
        icon: <Code2 className="h-4 w-4" />,
        color: 'text-success',
        permissions: [
            'view_resources', 'deploy_resources', 'edit_resources',
            'manage_env_vars', 'view_logs', 'view_team', 'view_activity',
            'view_settings'
        ],
    },
    {
        id: 'member',
        name: 'Member',
        description: 'View resources and basic operations',
        isBuiltIn: true,
        icon: <UserIcon className="h-4 w-4" />,
        color: 'text-foreground-muted',
        permissions: [
            'view_resources', 'view_logs', 'view_team', 'view_activity',
            'view_settings'
        ],
    },
    {
        id: 'viewer',
        name: 'Viewer',
        description: 'Read-only access to resources',
        isBuiltIn: true,
        icon: <Eye className="h-4 w-4" />,
        color: 'text-info',
        permissions: [
            'view_resources', 'view_logs', 'view_team', 'view_activity', 'view_settings'
        ],
    },
];

export default function TeamRoles() {
    const [roles] = React.useState<Role[]>(defaultRoles);
    const [expandedRole, setExpandedRole] = React.useState<string | null>(null);

    const groupedPermissions = permissions.reduce((acc, permission) => {
        if (!acc[permission.category]) {
            acc[permission.category] = [];
        }
        acc[permission.category].push(permission);
        return acc;
    }, {} as Record<string, Permission[]>);

    const categoryLabels: Record<string, string> = {
        resources: 'Resources',
        team: 'Team Management',
        settings: 'Settings',
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/settings/team">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-semibold text-foreground">Roles & Permissions</h2>
                            <p className="text-sm text-foreground-muted">
                                Manage team roles and their permissions
                            </p>
                        </div>
                    </div>
                </div>

                {/* Roles List */}
                <div className="space-y-4">
                    {roles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className={`flex h-10 w-10 items-center justify-center rounded-full bg-background-tertiary ${role.color}`}>
                                            {role.icon}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle>{role.name}</CardTitle>
                                                {role.isBuiltIn && (
                                                    <Badge variant="default">Built-in</Badge>
                                                )}
                                            </div>
                                            <CardDescription>{role.description}</CardDescription>
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setExpandedRole(expandedRole === role.id ? null : role.id)}
                                    >
                                        {expandedRole === role.id ? 'Hide' : 'Show'} Permissions
                                    </Button>
                                </div>
                            </CardHeader>
                            {expandedRole === role.id && (
                                <CardContent>
                                    <div className="space-y-6">
                                        {Object.entries(groupedPermissions).map(([category, perms]) => (
                                            <div key={category}>
                                                <h4 className="mb-3 text-sm font-semibold text-foreground">
                                                    {categoryLabels[category]}
                                                </h4>
                                                <div className="grid gap-2">
                                                    {perms.map((permission) => {
                                                        const hasPermission = role.permissions.includes(permission.id);
                                                        return (
                                                            <div
                                                                key={permission.id}
                                                                className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                                            >
                                                                <div className="flex-1">
                                                                    <p className="text-sm font-medium text-foreground">
                                                                        {permission.name}
                                                                    </p>
                                                                    <p className="text-xs text-foreground-muted">
                                                                        {permission.description}
                                                                    </p>
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    {hasPermission ? (
                                                                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/20">
                                                                            <Check className="h-4 w-4 text-primary" />
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
                            )}
                        </Card>
                    ))}
                </div>

                {/* Permission Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle>Permission Matrix</CardTitle>
                        <CardDescription>
                            Quick overview of permissions across all roles
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-border">
                                        <th className="pb-3 pr-4 text-left text-sm font-semibold text-foreground">
                                            Permission
                                        </th>
                                        {roles.map((role) => (
                                            <th key={role.id} className="pb-3 px-4 text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <span className={role.color}>{role.icon}</span>
                                                    <span className="text-sm font-semibold text-foreground">
                                                        {role.name}
                                                    </span>
                                                </div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(groupedPermissions).map(([category, perms]) => (
                                        <React.Fragment key={category}>
                                            <tr>
                                                <td colSpan={roles.length + 1} className="pt-4 pb-2">
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-foreground-subtle">
                                                        {categoryLabels[category]}
                                                    </p>
                                                </td>
                                            </tr>
                                            {perms.map((permission) => (
                                                <tr key={permission.id} className="border-b border-border/50">
                                                    <td className="py-3 pr-4">
                                                        <p className="text-sm text-foreground">{permission.name}</p>
                                                        <p className="text-xs text-foreground-muted">
                                                            {permission.description}
                                                        </p>
                                                    </td>
                                                    {roles.map((role) => (
                                                        <td key={role.id} className="py-3 px-4 text-center">
                                                            {role.permissions.includes(permission.id) ? (
                                                                <div className="flex justify-center">
                                                                    <div className="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20">
                                                                        <Check className="h-3 w-3 text-primary" />
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <div className="flex justify-center">
                                                                    <div className="flex h-5 w-5 items-center justify-center rounded-full bg-foreground-muted/10">
                                                                        <X className="h-3 w-3 text-foreground-muted" />
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </React.Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
