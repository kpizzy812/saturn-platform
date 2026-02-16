import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import { Select } from '@/components/ui/Select';
import { Checkbox } from '@/components/ui/Checkbox';
import { Link, router } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';
import { KickMemberModal } from '@/components/team/KickMemberModal';
import type { ActivityLog } from '@/types';
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
    Activity,
    Loader2,
    LogOut,
    Check,
    X,
} from 'lucide-react';

interface PermissionSetPermission {
    id: number;
    key: string;
    name: string;
    description: string | null;
    category: string;
    is_sensitive: boolean;
}

interface PermissionSetSummary {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    color: string | null;
    icon: string | null;
    permissions_count: number;
    permissions: PermissionSetPermission[];
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    permissionSetId: number | null;
    joinedAt: string;
    lastActive: string;
}

interface MemberProject {
    id: number;
    name: string;
    role: string;
    hasAccess: boolean;
    lastAccessed: string;
}

interface Props {
    member: TeamMember;
    projects: MemberProject[];
    activities: ActivityLog[];
    isCurrentUser: boolean;
    canManageTeam: boolean;
    canEditPermissions: boolean;
    permissionSets: PermissionSetSummary[];
    allowedProjects: number[] | null;
    hasFullProjectAccess: boolean;
    teamMembers: Array<{ id: number; name: string; email: string }>;
}

export default function MemberShow({
    member,
    projects,
    activities,
    isCurrentUser,
    canManageTeam,
    canEditPermissions,
    permissionSets,
    allowedProjects,
    hasFullProjectAccess,
    teamMembers,
}: Props) {
    const { toast } = useToast();
    const [showKickModal, setShowKickModal] = React.useState(false);
    const [showLeaveModal, setShowLeaveModal] = React.useState(false);
    const [showRoleModal, setShowRoleModal] = React.useState(false);
    const [selectedRole, setSelectedRole] = React.useState(member.role);
    const [isUpdatingRole, setIsUpdatingRole] = React.useState(false);
    const [isLeaving, setIsLeaving] = React.useState(false);

    // Permission set state
    const [selectedPermissionSetId, setSelectedPermissionSetId] = React.useState<string>(
        member.permissionSetId !== null ? String(member.permissionSetId) : '',
    );
    const [isUpdatingPermissionSet, setIsUpdatingPermissionSet] = React.useState(false);

    // Project access state
    const [fullAccess, setFullAccess] = React.useState(hasFullProjectAccess);
    const [selectedProjects, setSelectedProjects] = React.useState<number[]>(() => {
        if (hasFullProjectAccess) return projects.map((p) => p.id);
        if (allowedProjects && Array.isArray(allowedProjects)) return allowedProjects.map(Number);
        return [];
    });
    const [isUpdatingProjects, setIsUpdatingProjects] = React.useState(false);

    // Use server-computed permission that accounts for admin-vs-admin restrictions
    const canEditMember = canEditPermissions;

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
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const handleLeaveTeam = () => {
        setIsLeaving(true);
        router.delete(`/settings/team/members/${member.id}`, {
            onSuccess: () => {
                toast({
                    title: 'Left team',
                    description: 'You have left the team.',
                    variant: 'success',
                });
                setShowLeaveModal(false);
                router.visit('/dashboard');
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: Object.values(errors).flat().join(', ') || 'Failed to leave team',
                    variant: 'error',
                });
            },
            onFinish: () => {
                setIsLeaving(false);
            },
        });
    };

    const handleChangeRole = () => {
        if (selectedRole === member.role) {
            setShowRoleModal(false);
            return;
        }

        setIsUpdatingRole(true);
        router.post(
            `/settings/team/members/${member.id}/role`,
            {
                role: selectedRole,
            },
            {
                onSuccess: () => {
                    toast({
                        title: 'Role updated',
                        description: `${member.name}'s role has been changed to ${selectedRole}.`,
                        variant: 'success',
                    });
                    setShowRoleModal(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Error',
                        description: Object.values(errors).flat().join(', ') || 'Failed to update role',
                        variant: 'error',
                    });
                },
                onFinish: () => {
                    setIsUpdatingRole(false);
                },
            },
        );
    };

    const handlePermissionSetChange = (value: string) => {
        setSelectedPermissionSetId(value);
        setIsUpdatingPermissionSet(true);

        router.post(
            `/settings/team/members/${member.id}/permission-set`,
            {
                permission_set_id: value === '' ? null : Number(value),
            },
            {
                onSuccess: () => {
                    toast({
                        title: 'Role updated',
                        description: value === '' ? 'Switched to role-based permissions.' : 'Role assigned.',
                        variant: 'success',
                    });
                },
                onError: (errors) => {
                    toast({
                        title: 'Error',
                        description:
                            Object.values(errors).flat().join(', ') || 'Failed to update role',
                        variant: 'error',
                    });
                    setSelectedPermissionSetId(
                        member.permissionSetId !== null ? String(member.permissionSetId) : '',
                    );
                },
                onFinish: () => {
                    setIsUpdatingPermissionSet(false);
                },
            },
        );
    };

    const handleToggleFullAccess = (checked: boolean) => {
        setFullAccess(checked);
        if (checked) {
            setSelectedProjects(projects.map((p) => p.id));
        }
    };

    const handleToggleProject = (projectId: number, checked: boolean) => {
        setSelectedProjects((prev) => (checked ? [...prev, projectId] : prev.filter((id) => id !== projectId)));
    };

    const handleSaveProjectAccess = () => {
        setIsUpdatingProjects(true);

        const payload = fullAccess
            ? { grant_all: true, allowed_projects: [] as number[] }
            : { grant_all: false, allowed_projects: selectedProjects };

        router.post(`/settings/team/members/${member.id}/projects`, payload, {
            onSuccess: () => {
                toast({
                    title: 'Project access updated',
                    description: 'Member project access has been saved.',
                    variant: 'success',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: Object.values(errors).flat().join(', ') || 'Failed to update project access',
                    variant: 'error',
                });
            },
            onFinish: () => {
                setIsUpdatingProjects(false);
            },
        });
    };

    // Determine the active permission set for display
    const activePermissionSet =
        selectedPermissionSetId !== ''
            ? permissionSets.find((s) => s.id === Number(selectedPermissionSetId))
            : null;

    // Group permissions by category
    const groupedPermissions = React.useMemo(() => {
        if (!activePermissionSet) return {};
        const groups: Record<string, PermissionSetPermission[]> = {};
        for (const perm of activePermissionSet.permissions) {
            const cat = perm.category || 'General';
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(perm);
        }
        return groups;
    }, [activePermissionSet]);

    // Check if project access has changed
    const projectAccessChanged = React.useMemo(() => {
        if (fullAccess !== hasFullProjectAccess) return true;
        if (fullAccess) return false;
        const numSort = (a: number, b: number) => a - b;
        const original = allowedProjects ? allowedProjects.map(Number).sort(numSort) : [];
        const current = [...selectedProjects].sort(numSort);
        if (original.length !== current.length) return true;
        return original.some((v, i) => v !== current[i]);
    }, [fullAccess, hasFullProjectAccess, allowedProjects, selectedProjects]);

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
                        <p className="text-sm text-foreground-muted">View and manage member details</p>
                    </div>
                </div>

                {/* Member Profile */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-6">
                                <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-3xl font-semibold text-white">
                                    {member.avatar ? (
                                        <img
                                            src={member.avatar}
                                            alt={member.name}
                                            className="h-full w-full rounded-full object-cover"
                                        />
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
                                    {canManageTeam && !isCurrentUser && (
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
                                    )}
                                    {isCurrentUser ? (
                                        <Button variant="danger" onClick={() => setShowLeaveModal(true)}>
                                            <LogOut className="mr-2 h-4 w-4" />
                                            Leave Team
                                        </Button>
                                    ) : (
                                        canManageTeam && (
                                            <Button variant="danger" onClick={() => setShowKickModal(true)}>
                                                <UserX className="mr-2 h-4 w-4" />
                                                Remove
                                            </Button>
                                        )
                                    )}
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
                            {canEditMember
                                ? 'Assign a role or use role-based defaults'
                                : 'What this member can do based on their role'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Permission Set Selector */}
                        {canEditMember && permissionSets.length > 0 && (
                            <div className="mb-4">
                                <Select
                                    label="Role"
                                    value={selectedPermissionSetId}
                                    onChange={(e) => handlePermissionSetChange(e.target.value)}
                                    disabled={isUpdatingPermissionSet}
                                >
                                    <option value="">Role-based (default)</option>
                                    {permissionSets.map((set) => (
                                        <option key={set.id} value={String(set.id)}>
                                            {set.name}
                                            {set.is_system ? ' (System)' : ''} - {set.permissions_count} permissions
                                        </option>
                                    ))}
                                </Select>
                                {isUpdatingPermissionSet && (
                                    <div className="mt-2 flex items-center gap-2 text-sm text-foreground-muted">
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                        Saving...
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Show Permission Set Details if assigned */}
                        {activePermissionSet ? (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-border bg-background-secondary p-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-foreground">
                                                {activePermissionSet.name}
                                            </p>
                                            {activePermissionSet.description && (
                                                <p className="text-xs text-foreground-muted">
                                                    {activePermissionSet.description}
                                                </p>
                                            )}
                                        </div>
                                        <Badge variant={activePermissionSet.is_system ? 'info' : 'default'}>
                                            {activePermissionSet.is_system ? 'System' : 'Custom'}
                                        </Badge>
                                    </div>
                                </div>
                                {Object.entries(groupedPermissions).map(([category, perms]) => (
                                    <div key={category}>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                            {category}
                                        </p>
                                        <div className="grid gap-2">
                                            {perms.map((perm) => (
                                                <div
                                                    key={perm.id}
                                                    className="flex items-center gap-2 rounded-lg border border-border bg-background p-3"
                                                >
                                                    <Check className="h-4 w-4 shrink-0 text-primary" />
                                                    <div className="flex-1">
                                                        <p className="text-sm font-medium text-foreground">
                                                            {perm.name}
                                                        </p>
                                                        {perm.description && (
                                                            <p className="text-xs text-foreground-muted">
                                                                {perm.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                    {perm.is_sensitive && (
                                                        <Badge variant="warning">Sensitive</Badge>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            /* Fallback: Role-based permissions */
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
                        )}
                    </CardContent>
                </Card>

                {/* Project Access */}
                <Card>
                    <CardHeader>
                        <CardTitle>Project Access</CardTitle>
                        <CardDescription>
                            {canEditMember
                                ? 'Toggle which projects this member can access'
                                : 'Projects this member has access to'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {/* Full access toggle */}
                            {canEditMember && (
                                <div className="mb-4 rounded-lg border border-border bg-background-secondary p-4">
                                    <Checkbox
                                        label="All Projects"
                                        hint="Grant access to all current and future projects"
                                        checked={fullAccess}
                                        onCheckedChange={handleToggleFullAccess}
                                    />
                                </div>
                            )}

                            {projects.map((project) => (
                                <div
                                    key={project.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80"
                                >
                                    <div className="flex items-center gap-3">
                                        {canEditMember && (
                                            <Checkbox
                                                checked={fullAccess || selectedProjects.includes(project.id)}
                                                disabled={fullAccess}
                                                onCheckedChange={(checked) =>
                                                    handleToggleProject(project.id, checked)
                                                }
                                            />
                                        )}
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
                                    {!canEditMember && (
                                        <Badge variant={project.hasAccess ? 'success' : 'default'}>
                                            {project.hasAccess ? 'Access' : 'No Access'}
                                        </Badge>
                                    )}
                                </div>
                            ))}

                            {/* Save button */}
                            {canEditMember && projectAccessChanged && (
                                <div className="flex justify-end pt-2">
                                    <Button
                                        onClick={handleSaveProjectAccess}
                                        disabled={isUpdatingProjects}
                                    >
                                        {isUpdatingProjects ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Saving...
                                            </>
                                        ) : (
                                            'Save Project Access'
                                        )}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Member Activity */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Recent Activity</CardTitle>
                                <CardDescription>Actions performed by this member</CardDescription>
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
                                <p className="font-medium capitalize text-foreground">{role}</p>
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
                    <Button variant="secondary" onClick={() => setShowRoleModal(false)} disabled={isUpdatingRole}>
                        Cancel
                    </Button>
                    <Button onClick={handleChangeRole} disabled={selectedRole === member.role || isUpdatingRole}>
                        {isUpdatingRole ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Updating...
                            </>
                        ) : (
                            'Update Role'
                        )}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Kick Member Modal */}
            <KickMemberModal
                isOpen={showKickModal}
                onClose={() => setShowKickModal(false)}
                member={member}
                teamMembers={teamMembers}
            />

            {/* Leave Team Modal */}
            <Modal
                isOpen={showLeaveModal}
                onClose={() => !isLeaving && setShowLeaveModal(false)}
                title="Leave Team"
                description="Are you sure you want to leave this team? You will lose access to all team resources."
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowLeaveModal(false)} disabled={isLeaving}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleLeaveTeam} disabled={isLeaving}>
                        {isLeaving ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Leaving...
                            </>
                        ) : (
                            'Leave Team'
                        )}
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}

function PermissionItem({
    granted,
    title,
    description,
}: {
    granted: boolean;
    title: string;
    description: string;
}) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3">
            <div className="flex items-center gap-2">
                {granted ? (
                    <Check className="h-4 w-4 shrink-0 text-primary" />
                ) : (
                    <X className="h-4 w-4 shrink-0 text-foreground-muted" />
                )}
                <div className="flex-1">
                    <p className="text-sm font-medium text-foreground">{title}</p>
                    <p className="text-xs text-foreground-muted">{description}</p>
                </div>
            </div>
            <Badge variant={granted ? 'success' : 'default'}>{granted ? 'Granted' : 'Not Granted'}</Badge>
        </div>
    );
}
