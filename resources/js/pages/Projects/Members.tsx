import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Badge, Select } from '@/components/ui';
import { ArrowLeft, UserPlus, Trash2, Shield, Users, Crown, Code, Eye } from 'lucide-react';
import { RoleSelector, getRoleLabel, getRoleBadgeVariant } from '@/components/projects/RoleSelector';
import type { ProjectRole } from '@/types/models';

interface ProjectMember {
    id: number;
    name: string;
    email: string;
    role: ProjectRole;
    access_type: 'project' | 'team';
    has_team_access?: boolean;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
}

interface Props {
    project: {
        id: number;
        uuid: string;
        name: string;
    };
    members: ProjectMember[];
    availableTeamMembers: TeamMember[];
    currentUserRole: ProjectRole | null;
    currentUserId: number;
}

export default function ProjectMembers({
    project,
    members: initialMembers,
    availableTeamMembers,
    currentUserRole,
    currentUserId,
}: Props) {
    const [members, setMembers] = useState<ProjectMember[]>(initialMembers);
    const [showAddMember, setShowAddMember] = useState(false);
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [selectedRole, setSelectedRole] = useState<ProjectRole>('developer');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const canManageMembers = currentUserRole === 'owner' || currentUserRole === 'admin';

    const getRoleIcon = (role: ProjectRole) => {
        switch (role) {
            case 'owner':
                return <Crown className="h-4 w-4" />;
            case 'admin':
                return <Shield className="h-4 w-4" />;
            case 'developer':
                return <Code className="h-4 w-4" />;
            case 'viewer':
                return <Eye className="h-4 w-4" />;
            default:
                return <Users className="h-4 w-4" />;
        }
    };

    const handleAddMember = async () => {
        if (!selectedUserId) return;
        setLoading(true);
        setError('');

        try {
            const res = await fetch(`/projects/${project.uuid}/members`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    user_id: parseInt(selectedUserId),
                    role: selectedRole,
                }),
            });

            if (!res.ok) {
                const err = await res.json();
                setError(err.message || 'Failed to add member');
                return;
            }

            const member = await res.json();
            // Add to members list with project access type
            setMembers([...members, { ...member, access_type: 'project' as const }]);
            setShowAddMember(false);
            setSelectedUserId('');
            setSelectedRole('developer');
        } catch {
            setError('Failed to add member');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateRole = async (memberId: number, newRole: ProjectRole) => {
        setError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/members/${memberId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ role: newRole }),
            });

            if (!res.ok) {
                const err = await res.json();
                setError(err.message || 'Failed to update role');
                return;
            }

            setMembers(members.map(m => m.id === memberId ? { ...m, role: newRole } : m));
        } catch {
            setError('Failed to update role');
        }
    };

    const handleRemoveMember = async (memberId: number) => {
        if (!confirm('Remove this member from the project?')) return;
        setError('');

        try {
            const res = await fetch(`/projects/${project.uuid}/members/${memberId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!res.ok) {
                const err = await res.json();
                setError(err.message || 'Failed to remove member');
                return;
            }

            setMembers(members.filter(m => m.id !== memberId));
        } catch {
            setError('Failed to remove member');
        }
    };

    // Split members into project members and team members
    const projectMembers = members.filter(m => m.access_type === 'project');
    const teamMembers = members.filter(m => m.access_type === 'team');

    // Filter out users who are already project members
    const projectMemberIds = new Set(projectMembers.map(m => m.id));
    const availableUsers = availableTeamMembers.filter(u => !projectMemberIds.has(u.id));

    return (
        <AppLayout
            title={`${project.name} - Members`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Members' },
            ]}
        >
            <div className="mx-auto max-w-3xl">
                <Link
                    href={`/projects/${project.uuid}/settings`}
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Settings
                </Link>

                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-foreground">Project Members</h1>
                    <p className="mt-1 text-foreground-muted">
                        Manage who has access to this project and their roles
                    </p>
                </div>

                {error && (
                    <div className="mb-6 rounded-lg border border-danger/50 bg-danger/5 p-4 text-sm text-danger">
                        {error}
                    </div>
                )}

                {/* Role Legend */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="text-base">Role Permissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-start gap-2">
                                <Badge variant="primary" size="sm">Owner</Badge>
                                <span className="text-xs text-foreground-muted">Full control, delete project</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <Badge variant="success" size="sm">Admin</Badge>
                                <span className="text-xs text-foreground-muted">Manage members, deploy all</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <Badge variant="info" size="sm">Developer</Badge>
                                <span className="text-xs text-foreground-muted">Deploy dev/uat, approval for prod</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <Badge variant="secondary" size="sm">Member</Badge>
                                <span className="text-xs text-foreground-muted">Limited deployment</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <Badge variant="warning" size="sm">Viewer</Badge>
                                <span className="text-xs text-foreground-muted">Read-only access</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Project Members - explicitly added to project */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Project Members ({projectMembers.length})
                            </CardTitle>
                            <CardDescription>
                                Users explicitly assigned to this project with specific roles
                            </CardDescription>
                        </div>
                        {canManageMembers && availableUsers.length > 0 && (
                            <Button onClick={() => setShowAddMember(true)} size="sm">
                                <UserPlus className="mr-2 h-4 w-4" />
                                Add Member
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {/* Add member form */}
                        {showAddMember && (
                            <div className="mb-4 rounded-lg border border-border bg-background-secondary p-4">
                                <h4 className="mb-3 text-sm font-medium text-foreground">Add Team Member to Project</h4>
                                <div className="flex flex-wrap items-end gap-3">
                                    <div className="min-w-[200px] flex-1">
                                        <label className="mb-1 block text-xs text-foreground-muted">User</label>
                                        <Select
                                            value={selectedUserId}
                                            onChange={(e) => setSelectedUserId(e.target.value)}
                                            options={[
                                                { value: '', label: 'Select a user...' },
                                                ...availableUsers.map(u => ({
                                                    value: u.id.toString(),
                                                    label: `${u.name} (${u.email})`,
                                                })),
                                            ]}
                                        />
                                    </div>
                                    <div className="min-w-[150px]">
                                        <label className="mb-1 block text-xs text-foreground-muted">Role</label>
                                        <RoleSelector
                                            value={selectedRole}
                                            onChange={setSelectedRole}
                                            excludeOwner={currentUserRole !== 'owner'}
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            onClick={handleAddMember}
                                            disabled={!selectedUserId || loading}
                                            size="sm"
                                        >
                                            {loading ? 'Adding...' : 'Add'}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setShowAddMember(false);
                                                setSelectedUserId('');
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Project members table */}
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {projectMembers.length === 0 ? (
                                <div className="p-8 text-center text-sm text-foreground-muted">
                                    No project-specific members. Add team members to give them specific roles in this project.
                                </div>
                            ) : (
                                projectMembers.map((member) => (
                                    <div key={member.id} className="flex items-center justify-between px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                {getRoleIcon(member.role)}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">{member.name}</span>
                                                    {member.id === currentUserId && (
                                                        <Badge variant="secondary" size="sm">You</Badge>
                                                    )}
                                                    <Badge variant="info" size="sm">Project Member</Badge>
                                                </div>
                                                <span className="text-sm text-foreground-muted">{member.email}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {canManageMembers && member.id !== currentUserId ? (
                                                <>
                                                    <RoleSelector
                                                        value={member.role}
                                                        onChange={(role) => handleUpdateRole(member.id, role)}
                                                        excludeOwner={currentUserRole !== 'owner'}
                                                    />
                                                    <button
                                                        onClick={() => handleRemoveMember(member.id)}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-danger/10 hover:text-danger"
                                                        title="Remove from project"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </>
                                            ) : (
                                                <Badge variant={getRoleBadgeVariant(member.role)} size="sm">
                                                    {getRoleLabel(member.role)}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Team Access - team members with team-level access */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Team Access ({teamMembers.length})
                        </CardTitle>
                        <CardDescription>
                            Team members with access configured at team level. Manage access in Team Settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {teamMembers.length === 0 ? (
                                <div className="p-8 text-center text-sm text-foreground-muted">
                                    All team members are added as project members.
                                </div>
                            ) : (
                                teamMembers.map((member) => (
                                    <div key={member.id} className="flex items-center justify-between px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-secondary/10 text-secondary">
                                                {getRoleIcon(member.role)}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-foreground">{member.name}</span>
                                                    {member.id === currentUserId && (
                                                        <Badge variant="secondary" size="sm">You</Badge>
                                                    )}
                                                    <Badge variant="secondary" size="sm">Team Role</Badge>
                                                </div>
                                                <span className="text-sm text-foreground-muted">{member.email}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Badge variant={getRoleBadgeVariant(member.role)} size="sm">
                                                {getRoleLabel(member.role)}
                                            </Badge>
                                            {member.has_team_access ? (
                                                <Badge variant="success" size="sm">Has Access</Badge>
                                            ) : (
                                                <Badge variant="danger" size="sm">No Access</Badge>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                        {teamMembers.length > 0 && (
                            <p className="mt-3 text-xs text-foreground-muted">
                                To modify team-level access, go to Team Settings → Configure Projects for each member.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
