import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import { Link } from '@inertiajs/react';
import type { ActivityLog, Project } from '@/types';
import {
    ArrowLeft,
    Mail,
    Calendar,
    Clock,
    Crown,
    Shield,
    User as UserIcon,
    Lock,
    UserX,
    UserCog,
    GitBranch,
    Activity
} from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    joinedAt: string;
    lastActive: string;
}

interface MemberProject {
    id: number;
    name: string;
    role: string;
    lastAccessed: string;
}

interface Props {
    member?: TeamMember;
    projects?: MemberProject[];
    activities?: ActivityLog[];
}

const mockMember: TeamMember = {
    id: 2,
    name: 'Jane Smith',
    email: 'jane@acme.com',
    role: 'admin',
    joinedAt: '2024-02-20',
    lastActive: '2024-03-28T12:15:00Z'
};

const mockProjects: MemberProject[] = [
    { id: 1, name: 'Production', role: 'admin', lastAccessed: '2024-03-28T12:15:00Z' },
    { id: 2, name: 'Staging', role: 'admin', lastAccessed: '2024-03-27T14:30:00Z' },
    { id: 3, name: 'Development', role: 'member', lastAccessed: '2024-03-26T09:20:00Z' },
];

const mockActivities: ActivityLog[] = [
    {
        id: '1',
        action: 'deployment_completed',
        description: 'deployed',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'application',
            name: 'api-service',
            id: '12'
        },
        timestamp: '2024-03-28T12:15:00Z'
    },
    {
        id: '2',
        action: 'server_connected',
        description: 'connected',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'server',
            name: 'prod-server-01',
            id: '3'
        },
        timestamp: '2024-03-26T15:45:00Z'
    },
    {
        id: '3',
        action: 'application_restarted',
        description: 'restarted',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'application',
            name: 'worker-service',
            id: '15'
        },
        timestamp: '2024-03-25T18:20:00Z'
    },
    {
        id: '4',
        action: 'settings_updated',
        description: 'updated settings',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'application',
            name: 'web-app',
            id: '8'
        },
        timestamp: '2024-03-24T10:30:00Z'
    },
];

export default function MemberShow({ member: propMember, projects: propProjects, activities: propActivities }: Props) {
    const member = propMember || mockMember;
    const projects = propProjects || mockProjects;
    const activities = propActivities || mockActivities;

    const [showRemoveModal, setShowRemoveModal] = React.useState(false);
    const [showRoleModal, setShowRoleModal] = React.useState(false);
    const [selectedRole, setSelectedRole] = React.useState(member.role);

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner':
                return <Crown className="h-4 w-4" />;
            case 'admin':
                return <Shield className="h-4 w-4" />;
            case 'viewer':
                return <Lock className="h-4 w-4" />;
            default:
                return <UserIcon className="h-4 w-4" />;
        }
    };

    const getRoleBadgeVariant = (role: string): 'default' | 'success' | 'warning' | 'info' => {
        switch (role) {
            case 'owner':
                return 'warning';
            case 'admin':
                return 'success';
            case 'viewer':
                return 'info';
            default:
                return 'default';
        }
    };

    const formatLastActive = (timestamp: string) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 5) return 'Just now';
        if (diffMins < 60) return `${diffMins} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        return date.toLocaleDateString();
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const handleRemoveMember = () => {
        console.log('Remove member:', member.id);
        setShowRemoveModal(false);
        // Redirect to team page
    };

    const handleChangeRole = () => {
        console.log('Change role to:', selectedRole);
        setShowRoleModal(false);
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/settings/team">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div className="flex-1">
                        <h2 className="text-2xl font-semibold text-foreground">Team Member</h2>
                        <p className="text-sm text-foreground-muted">
                            View and manage member details
                        </p>
                    </div>
                </div>

                {/* Member Profile */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-6">
                                <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-3xl font-semibold text-white">
                                    {member.avatar ? (
                                        <img src={member.avatar} alt={member.name} className="h-full w-full rounded-full object-cover" />
                                    ) : (
                                        getInitials(member.name)
                                    )}
                                </div>
                                <div>
                                    <div className="flex items-center gap-3">
                                        <h3 className="text-2xl font-semibold text-foreground">{member.name}</h3>
                                        <Badge variant={getRoleBadgeVariant(member.role)}>
                                            <span className="mr-1">{getRoleIcon(member.role)}</span>
                                            {member.role}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 flex items-center gap-4 text-sm text-foreground-muted">
                                        <div className="flex items-center gap-1.5">
                                            <Mail className="h-4 w-4" />
                                            <span>{member.email}</span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Calendar className="h-4 w-4" />
                                            <span>Joined {new Date(member.joinedAt).toLocaleDateString()}</span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Clock className="h-4 w-4" />
                                            <span>Active {formatLastActive(member.lastActive)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {member.role !== 'owner' && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            setSelectedRole(member.role);
                                            setShowRoleModal(true);
                                        }}
                                    >
                                        <UserCog className="mr-2 h-4 w-4" />
                                        Change Role
                                    </Button>
                                    <Button
                                        variant="danger"
                                        onClick={() => setShowRemoveModal(true)}
                                    >
                                        <UserX className="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Access Permissions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Access Permissions</CardTitle>
                        <CardDescription>
                            What this member can do based on their role
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3">
                            {member.role === 'owner' && (
                                <>
                                    <PermissionItem
                                        granted
                                        title="Full Team Control"
                                        description="Complete access to all team resources and settings"
                                    />
                                    <PermissionItem
                                        granted
                                        title="Billing Management"
                                        description="Manage subscriptions and payment methods"
                                    />
                                </>
                            )}
                            {(member.role === 'owner' || member.role === 'admin') && (
                                <>
                                    <PermissionItem
                                        granted
                                        title="Team Management"
                                        description="Invite, remove, and manage team members"
                                    />
                                    <PermissionItem
                                        granted
                                        title="Settings Management"
                                        description="Configure team and project settings"
                                    />
                                </>
                            )}
                            <PermissionItem
                                granted={member.role !== 'viewer'}
                                title="Resource Deployment"
                                description="Deploy and manage applications and services"
                            />
                            <PermissionItem
                                granted={member.role !== 'viewer'}
                                title="Environment Variables"
                                description="View and edit environment variables"
                            />
                            <PermissionItem
                                granted
                                title="View Resources"
                                description="Access to view applications, databases, and services"
                            />
                            <PermissionItem
                                granted
                                title="View Logs"
                                description="Access to application and deployment logs"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Project Access */}
                <Card>
                    <CardHeader>
                        <CardTitle>Project Access</CardTitle>
                        <CardDescription>
                            Projects this member has access to
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {projects.map((project) => (
                                <div
                                    key={project.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-500">
                                            <GitBranch className="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">{project.name}</p>
                                            <p className="text-sm text-foreground-muted">
                                                Last accessed {formatLastActive(project.lastAccessed)}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge variant="default">{project.role}</Badge>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Member Activity */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Recent Activity</CardTitle>
                                <CardDescription>
                                    Actions performed by this member
                                </CardDescription>
                            </div>
                            <Link href={`/settings/team/activity?member=${member.email}`}>
                                <Button variant="secondary" size="sm">
                                    <Activity className="mr-2 h-4 w-4" />
                                    View All
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {activities.length > 0 ? (
                            <ActivityTimeline activities={activities} />
                        ) : (
                            <div className="py-8 text-center">
                                <p className="text-sm text-foreground-muted">No recent activity</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Change Role Modal */}
            <Modal
                isOpen={showRoleModal}
                onClose={() => setShowRoleModal(false)}
                title="Change Member Role"
                description={`Update the role for ${member.name}`}
            >
                <div className="space-y-2">
                    {(['admin', 'member', 'viewer'] as const).map((role) => (
                        <button
                            key={role}
                            onClick={() => setSelectedRole(role)}
                            className={`flex w-full items-center gap-3 rounded-lg border p-3 transition-all ${
                                selectedRole === role
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border hover:border-border/80'
                            }`}
                        >
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary">
                                {getRoleIcon(role)}
                            </div>
                            <div className="flex-1 text-left">
                                <p className="font-medium text-foreground capitalize">{role}</p>
                                <p className="text-xs text-foreground-muted">
                                    {role === 'admin' && 'Manage team members and settings'}
                                    {role === 'member' && 'Deploy and manage resources'}
                                    {role === 'viewer' && 'Read-only access to resources'}
                                </p>
                            </div>
                            {member.role === role && role !== selectedRole && (
                                <Badge variant="default">Current</Badge>
                            )}
                        </button>
                    ))}
                </div>

                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRoleModal(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleChangeRole} disabled={selectedRole === member.role}>
                        Update Role
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Remove Member Modal */}
            <Modal
                isOpen={showRemoveModal}
                onClose={() => setShowRemoveModal(false)}
                title="Remove Team Member"
                description={`Are you sure you want to remove ${member.name} from the team? They will lose access to all team resources.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRemoveModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRemoveMember}>
                        Remove Member
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}

function PermissionItem({ granted, title, description }: { granted: boolean; title: string; description: string }) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3">
            <div className="flex-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                <p className="text-xs text-foreground-muted">{description}</p>
            </div>
            <Badge variant={granted ? 'success' : 'default'}>
                {granted ? 'Granted' : 'Not Granted'}
            </Badge>
        </div>
    );
}
