import * as React from 'react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Checkbox } from '@/components/ui/Checkbox';
import { router } from '@inertiajs/react';
import {
    Mail,
    Crown,
    Shield,
    Code,
    User as UserIcon,
    Eye,
    ChevronDown,
    ChevronUp,
    Folder,
    Unlock,
    AlertTriangle,
    Check,
    Info,
} from 'lucide-react';

// --- Types ---

interface PermissionSetOption {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    color: string | null;
    icon: string | null;
    permissions_count: number;
}

interface PermissionItem {
    id: number;
    key: string;
    name: string;
    description?: string;
    resource: string;
    action: string;
    is_sensitive?: boolean;
}

interface EnvironmentItem {
    id: number;
    name: string;
}

interface Project {
    id: number;
    name: string;
}

interface InviteData {
    projects: Project[];
    permissionSets: PermissionSetOption[];
    allPermissions: Record<string, PermissionItem[]>;
    environments: EnvironmentItem[];
    rolePermissions: Record<string, number[]>;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: () => void;
}

type Role = 'owner' | 'admin' | 'developer' | 'member' | 'viewer';

const roles: { value: Role; label: string; description: string; icon: React.ReactNode }[] = [
    { value: 'admin', label: 'Admin', description: 'Manage team members and settings', icon: <Shield className="h-4 w-4" /> },
    { value: 'developer', label: 'Developer', description: 'Deploy and manage resources', icon: <Code className="h-4 w-4" /> },
    { value: 'member', label: 'Member', description: 'View resources and basic operations', icon: <UserIcon className="h-4 w-4" /> },
    { value: 'viewer', label: 'Viewer', description: 'Read-only access to resources', icon: <Eye className="h-4 w-4" /> },
];

const categoryLabels: Record<string, string> = {
    resources: 'Resources',
    team: 'Team Management',
    settings: 'Settings',
};

const setIconMap: Record<string, React.ReactNode> = {
    shield: <Shield className="h-4 w-4" />,
    user: <UserIcon className="h-4 w-4" />,
    code: <Code className="h-4 w-4" />,
    crown: <Crown className="h-4 w-4" />,
    eye: <Eye className="h-4 w-4" />,
};

export function InviteTeamMemberModal({ isOpen, onClose, onSuccess }: Props) {
    // Form state
    const [email, setEmail] = React.useState('');
    const [role, setRole] = React.useState<Role>('member');

    // Project access state
    const [grantAllProjects, setGrantAllProjects] = React.useState(true);
    const [selectedProjects, setSelectedProjects] = React.useState<number[]>([]);

    // Permission state
    const [permissionMode, setPermissionMode] = React.useState<'role_default' | 'preset' | 'custom'>('role_default');
    const [selectedSetId, setSelectedSetId] = React.useState<number | null>(null);
    const [selectedPermissions, setSelectedPermissions] = React.useState<Set<number>>(new Set());
    const [environmentRestrictions, setEnvironmentRestrictions] = React.useState<Record<number, Record<string, boolean>>>({});
    const [showEnvironmentOptions, setShowEnvironmentOptions] = React.useState<number | null>(null);

    // Collapsible sections
    const [projectsExpanded, setProjectsExpanded] = React.useState(false);
    const [permissionsExpanded, setPermissionsExpanded] = React.useState(false);

    // Loading states
    const [inviteData, setInviteData] = React.useState<InviteData | null>(null);
    const [isLoadingData, setIsLoadingData] = React.useState(false);
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    // Load invite data when modal opens
    React.useEffect(() => {
        if (isOpen) {
            setIsLoadingData(true);
            setError(null);
            fetch('/settings/team/invite/data', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            })
                .then(res => {
                    if (!res.ok) throw new Error('Failed to load invite data');
                    return res.json();
                })
                .then((data: InviteData) => {
                    setInviteData(data);
                    setSelectedProjects(data.projects.map(p => p.id));
                })
                .catch(err => setError(err.message))
                .finally(() => setIsLoadingData(false));
        }
    }, [isOpen]);

    // Reset form when modal closes
    const handleClose = () => {
        setEmail('');
        setRole('member');
        setGrantAllProjects(true);
        setSelectedProjects([]);
        setPermissionMode('role_default');
        setSelectedSetId(null);
        setSelectedPermissions(new Set());
        setEnvironmentRestrictions({});
        setShowEnvironmentOptions(null);
        setProjectsExpanded(false);
        setPermissionsExpanded(false);
        setError(null);
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setError(null);

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const payload: Record<string, any> = {
            email,
            role,
        };

        // Project access: null = all, array = specific
        if (!grantAllProjects) {
            payload.allowed_projects = selectedProjects;
        }

        // Permissions
        if (permissionMode === 'preset' && selectedSetId) {
            payload.permission_set_id = selectedSetId;
        } else if (permissionMode === 'custom' && selectedPermissions.size > 0) {
            payload.custom_permissions = Array.from(selectedPermissions).map(id => ({
                permission_id: id,
                environment_restrictions: environmentRestrictions[id] || {},
            }));
        }

        router.post('/settings/team/invite', payload, {
            onSuccess: () => {
                handleClose();
                onSuccess?.();
                router.reload();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                setError(typeof firstError === 'string' ? firstError : 'Failed to send invitation');
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    // Project helpers
    const toggleProject = (projectId: number) => {
        if (grantAllProjects) return;
        setSelectedProjects(prev =>
            prev.includes(projectId)
                ? prev.filter(id => id !== projectId)
                : [...prev, projectId]
        );
    };

    const handleGrantAllChange = (checked: boolean) => {
        setGrantAllProjects(checked);
        if (checked && inviteData) {
            setSelectedProjects(inviteData.projects.map(p => p.id));
        }
    };

    // Permission helpers
    const togglePermission = (permissionId: number) => {
        const newSelected = new Set(selectedPermissions);
        if (newSelected.has(permissionId)) {
            newSelected.delete(permissionId);
            const newRestrictions = { ...environmentRestrictions };
            delete newRestrictions[permissionId];
            setEnvironmentRestrictions(newRestrictions);
        } else {
            newSelected.add(permissionId);
        }
        setSelectedPermissions(newSelected);
    };

    const toggleEnvironmentRestriction = (permissionId: number, envName: string) => {
        const current = environmentRestrictions[permissionId] || {};
        const newValue = current[envName] === false ? true : (current[envName] === true ? undefined : false);

        const newRestrictions = { ...environmentRestrictions };
        if (newValue === undefined) {
            const { [envName]: _, ...rest } = current;
            if (Object.keys(rest).length === 0) {
                delete newRestrictions[permissionId];
            } else {
                newRestrictions[permissionId] = rest;
            }
        } else {
            newRestrictions[permissionId] = { ...current, [envName]: newValue };
        }
        setEnvironmentRestrictions(newRestrictions);
    };

    // Summary helpers
    const getProjectSummary = () => {
        if (grantAllProjects) return 'All Projects';
        if (selectedProjects.length === 0) return 'No Projects';
        return `${selectedProjects.length} Project${selectedProjects.length !== 1 ? 's' : ''}`;
    };

    const getPermissionSummary = () => {
        if (permissionMode === 'role_default') return 'Role Default';
        if (permissionMode === 'preset' && selectedSetId && inviteData) {
            const set = inviteData.permissionSets.find(s => s.id === selectedSetId);
            return set ? set.name : 'Preset';
        }
        if (permissionMode === 'custom') return `${selectedPermissions.size} Custom`;
        return 'Role Default';
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Invite Team Member"
            description="Send an invitation with role, project access, and permissions"
            size="lg"
        >
            {isLoadingData ? (
                <div className="flex items-center justify-center py-8">
                    <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : error && !inviteData ? (
                <div className="rounded-lg border border-danger/50 bg-danger/10 p-4 text-center text-danger">
                    {error}
                </div>
            ) : (
                <form onSubmit={handleSubmit}>
                    <div className="space-y-4">
                        {/* Section 1: Email & Role */}
                        <div className="space-y-4">
                            <Input
                                label="Email Address"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="colleague@example.com"
                                required
                            />

                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">Role</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {roles.map((r) => (
                                        <button
                                            key={r.value}
                                            type="button"
                                            onClick={() => setRole(r.value)}
                                            className={`flex items-center gap-2.5 rounded-lg border p-3 text-left transition-all ${
                                                role === r.value
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-border hover:border-border/80'
                                            }`}
                                        >
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary flex-shrink-0">
                                                {r.icon}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-foreground">{r.label}</p>
                                                <p className="text-[10px] text-foreground-muted truncate">{r.description}</p>
                                            </div>
                                            {role === r.value && (
                                                <div className="ml-auto flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white flex-shrink-0">
                                                    <Check className="h-3 w-3" />
                                                </div>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Section 2: Project Access (Collapsible) */}
                        {inviteData && inviteData.projects.length > 0 && (
                            <div className="rounded-lg border border-border">
                                <button
                                    type="button"
                                    onClick={() => setProjectsExpanded(!projectsExpanded)}
                                    className="flex w-full items-center justify-between p-3 text-left hover:bg-background-tertiary/50 transition-colors rounded-lg"
                                >
                                    <div className="flex items-center gap-2">
                                        <Folder className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm font-medium text-foreground">Project Access</span>
                                        <Badge variant={grantAllProjects ? 'success' : selectedProjects.length === 0 ? 'danger' : 'warning'} className="text-[10px]">
                                            {getProjectSummary()}
                                        </Badge>
                                    </div>
                                    {projectsExpanded ? (
                                        <ChevronUp className="h-4 w-4 text-foreground-muted" />
                                    ) : (
                                        <ChevronDown className="h-4 w-4 text-foreground-muted" />
                                    )}
                                </button>

                                {projectsExpanded && (
                                    <div className="border-t border-border p-3 space-y-3">
                                        {/* Grant All Toggle */}
                                        <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-2.5 transition-all hover:border-border/80 hover:bg-background-secondary">
                                            <Checkbox
                                                checked={grantAllProjects}
                                                onCheckedChange={(checked) => handleGrantAllChange(checked)}
                                            />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <Unlock className="h-4 w-4 text-success" />
                                                    <p className="text-sm font-medium text-foreground">Grant Access to All Projects</p>
                                                </div>
                                                <p className="text-[10px] text-foreground-muted">
                                                    Including future projects
                                                </p>
                                            </div>
                                        </label>

                                        {/* Project List */}
                                        {!grantAllProjects && (
                                            <>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs text-foreground-muted">
                                                        {selectedProjects.length} of {inviteData.projects.length} selected
                                                    </span>
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedProjects(inviteData.projects.map(p => p.id))}
                                                            className="text-xs text-primary hover:underline"
                                                        >
                                                            Select all
                                                        </button>
                                                        <span className="text-foreground-muted">|</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedProjects([])}
                                                            className="text-xs text-primary hover:underline"
                                                        >
                                                            Clear
                                                        </button>
                                                    </div>
                                                </div>
                                                <div className="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                                                    {inviteData.projects.map((project) => (
                                                        <label
                                                            key={project.id}
                                                            className="flex cursor-pointer items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-background-tertiary"
                                                        >
                                                            <Checkbox
                                                                checked={selectedProjects.includes(project.id)}
                                                                onCheckedChange={() => toggleProject(project.id)}
                                                            />
                                                            <Folder className="h-3.5 w-3.5 text-foreground-muted" />
                                                            <span className="text-sm text-foreground">{project.name}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                                {selectedProjects.length === 0 && (
                                                    <div className="flex items-center gap-2 text-xs text-warning">
                                                        <AlertTriangle className="h-3.5 w-3.5" />
                                                        <span>No access - member will not see any projects</span>
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Section 3: Custom Permissions (Collapsible) */}
                        {inviteData && (
                            <div className="rounded-lg border border-border">
                                <button
                                    type="button"
                                    onClick={() => setPermissionsExpanded(!permissionsExpanded)}
                                    className="flex w-full items-center justify-between p-3 text-left hover:bg-background-tertiary/50 transition-colors rounded-lg"
                                >
                                    <div className="flex items-center gap-2">
                                        <Shield className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm font-medium text-foreground">Permissions</span>
                                        <Badge variant="default" className="text-[10px]">
                                            {getPermissionSummary()}
                                        </Badge>
                                    </div>
                                    {permissionsExpanded ? (
                                        <ChevronUp className="h-4 w-4 text-foreground-muted" />
                                    ) : (
                                        <ChevronDown className="h-4 w-4 text-foreground-muted" />
                                    )}
                                </button>

                                {permissionsExpanded && (
                                    <div className="border-t border-border p-3 space-y-3">
                                        {/* Mode Switcher */}
                                        <div className="flex gap-1 rounded-lg border border-border bg-background-secondary p-1">
                                            <button
                                                type="button"
                                                onClick={() => setPermissionMode('role_default')}
                                                className={`flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-all ${
                                                    permissionMode === 'role_default'
                                                        ? 'bg-background text-foreground shadow-sm'
                                                        : 'text-foreground-muted hover:text-foreground'
                                                }`}
                                            >
                                                Role Default
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setPermissionMode('preset')}
                                                className={`flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-all ${
                                                    permissionMode === 'preset'
                                                        ? 'bg-background text-foreground shadow-sm'
                                                        : 'text-foreground-muted hover:text-foreground'
                                                }`}
                                            >
                                                Preset
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    // Pre-fill with role's default permissions when switching to custom
                                                    if (permissionMode !== 'custom' && inviteData?.rolePermissions?.[role]) {
                                                        setSelectedPermissions(new Set(inviteData.rolePermissions[role]));
                                                    }
                                                    setPermissionMode('custom');
                                                }}
                                                className={`flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-all ${
                                                    permissionMode === 'custom'
                                                        ? 'bg-background text-foreground shadow-sm'
                                                        : 'text-foreground-muted hover:text-foreground'
                                                }`}
                                            >
                                                Custom
                                            </button>
                                        </div>

                                        {/* Role Default */}
                                        {permissionMode === 'role_default' && (
                                            <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                                <UserIcon className="h-4 w-4 text-foreground-muted" />
                                                <p className="text-sm text-foreground-muted">
                                                    Member will inherit permissions from the <span className="font-medium text-foreground capitalize">{role}</span> role.
                                                </p>
                                            </div>
                                        )}

                                        {/* Preset Selection */}
                                        {permissionMode === 'preset' && (
                                            <div className="max-h-52 space-y-1.5 overflow-y-auto">
                                                {inviteData.permissionSets.length === 0 ? (
                                                    <p className="py-4 text-center text-sm text-foreground-muted">
                                                        No permission sets available
                                                    </p>
                                                ) : (
                                                    inviteData.permissionSets.map((set) => (
                                                        <button
                                                            key={set.id}
                                                            type="button"
                                                            onClick={() => setSelectedSetId(set.id)}
                                                            className={`flex w-full items-center gap-2.5 rounded-lg border p-2.5 transition-all ${
                                                                selectedSetId === set.id
                                                                    ? 'border-primary bg-primary/10'
                                                                    : 'border-border hover:border-border/80'
                                                            }`}
                                                        >
                                                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-background-tertiary flex-shrink-0">
                                                                {setIconMap[set.icon || 'shield'] || <Shield className="h-3.5 w-3.5" />}
                                                            </div>
                                                            <div className="flex-1 text-left min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <p className="text-sm font-medium text-foreground truncate">{set.name}</p>
                                                                    {set.is_system && (
                                                                        <Badge variant="default" className="text-[9px]">System</Badge>
                                                                    )}
                                                                    <Badge variant="info" className="text-[9px]">
                                                                        {set.permissions_count}
                                                                    </Badge>
                                                                </div>
                                                                {set.description && (
                                                                    <p className="text-[10px] text-foreground-muted truncate">{set.description}</p>
                                                                )}
                                                            </div>
                                                            {selectedSetId === set.id && (
                                                                <div className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white flex-shrink-0">
                                                                    <Check className="h-3 w-3" />
                                                                </div>
                                                            )}
                                                        </button>
                                                    ))
                                                )}
                                            </div>
                                        )}

                                        {/* Custom Permissions */}
                                        {permissionMode === 'custom' && (
                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between">
                                                    <Badge variant="default" className="text-[10px]">{selectedPermissions.size} selected</Badge>
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (!inviteData) return;
                                                                const allIds = new Set<number>();
                                                                Object.values(inviteData.allPermissions).forEach(perms =>
                                                                    perms.forEach(p => allIds.add(p.id))
                                                                );
                                                                setSelectedPermissions(allIds);
                                                            }}
                                                            className="text-xs text-primary hover:underline"
                                                        >
                                                            Select All
                                                        </button>
                                                        <span className="text-foreground-muted">|</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setSelectedPermissions(new Set());
                                                                setEnvironmentRestrictions({});
                                                            }}
                                                            className="text-xs text-primary hover:underline"
                                                        >
                                                            Clear
                                                        </button>
                                                    </div>
                                                </div>

                                                <div className="max-h-52 space-y-3 overflow-y-auto">
                                                    {Object.entries(inviteData.allPermissions).map(([category, permissions]) => (
                                                        <div key={category}>
                                                            <div className="flex items-center justify-between mb-1.5">
                                                                <h4 className="text-[10px] font-semibold uppercase tracking-wide text-foreground-muted">
                                                                    {categoryLabels[category] || category}
                                                                </h4>
                                                                <button
                                                                    type="button"
                                                                    className="text-[10px] text-primary hover:underline"
                                                                    onClick={() => {
                                                                        const ids = permissions.map(p => p.id);
                                                                        const allSelected = ids.every(id => selectedPermissions.has(id));
                                                                        const newSelected = new Set(selectedPermissions);
                                                                        ids.forEach(id => {
                                                                            if (allSelected) newSelected.delete(id);
                                                                            else newSelected.add(id);
                                                                        });
                                                                        setSelectedPermissions(newSelected);
                                                                    }}
                                                                >
                                                                    {permissions.every(p => selectedPermissions.has(p.id))
                                                                        ? 'Deselect'
                                                                        : 'Select All'}
                                                                </button>
                                                            </div>
                                                            <div className="space-y-1">
                                                                {permissions.map((permission) => {
                                                                    const isSelected = selectedPermissions.has(permission.id);
                                                                    const hasRestrictions = environmentRestrictions[permission.id] &&
                                                                        Object.keys(environmentRestrictions[permission.id]).length > 0;

                                                                    return (
                                                                        <div key={permission.id} className="space-y-1">
                                                                            <div
                                                                                className={`flex items-center justify-between rounded-lg border p-2 cursor-pointer transition-all ${
                                                                                    isSelected
                                                                                        ? 'border-primary bg-primary/5'
                                                                                        : 'border-border bg-background hover:border-border/80'
                                                                                }`}
                                                                                onClick={() => togglePermission(permission.id)}
                                                                            >
                                                                                <div className="flex-1 min-w-0">
                                                                                    <div className="flex items-center gap-1.5">
                                                                                        <p className="text-xs font-medium text-foreground truncate">
                                                                                            {permission.name}
                                                                                        </p>
                                                                                        {permission.is_sensitive && (
                                                                                            <Badge variant="warning" className="text-[9px] px-1 py-0">
                                                                                                Sensitive
                                                                                            </Badge>
                                                                                        )}
                                                                                        {hasRestrictions && (
                                                                                            <Badge variant="info" className="text-[9px] px-1 py-0">
                                                                                                Env
                                                                                            </Badge>
                                                                                        )}
                                                                                    </div>
                                                                                    {permission.description && (
                                                                                        <p className="text-[10px] text-foreground-muted truncate">
                                                                                            {permission.description}
                                                                                        </p>
                                                                                    )}
                                                                                </div>
                                                                                <div className="flex items-center gap-1 ml-2 flex-shrink-0">
                                                                                    {isSelected && inviteData.environments.length > 0 && (
                                                                                        <button
                                                                                            type="button"
                                                                                            className="p-1 hover:bg-background-tertiary rounded"
                                                                                            onClick={(e) => {
                                                                                                e.stopPropagation();
                                                                                                setShowEnvironmentOptions(
                                                                                                    showEnvironmentOptions === permission.id
                                                                                                        ? null
                                                                                                        : permission.id
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            <Info className="h-3 w-3 text-foreground-muted" />
                                                                                        </button>
                                                                                    )}
                                                                                    <div
                                                                                        className={`flex h-5 w-5 items-center justify-center rounded border transition-all ${
                                                                                            isSelected
                                                                                                ? 'border-primary bg-primary text-white'
                                                                                                : 'border-border'
                                                                                        }`}
                                                                                    >
                                                                                        {isSelected && <Check className="h-3 w-3" />}
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            {/* Environment Restrictions */}
                                                                            {isSelected && showEnvironmentOptions === permission.id && inviteData.environments.length > 0 && (
                                                                                <div className="ml-3 p-2 rounded-lg border border-border bg-background-secondary">
                                                                                    <p className="text-[10px] font-medium text-foreground mb-1.5">
                                                                                        Environment Restrictions
                                                                                    </p>
                                                                                    <div className="flex flex-wrap gap-1.5">
                                                                                        {inviteData.environments.map((env) => {
                                                                                            const restriction = environmentRestrictions[permission.id]?.[env.name];
                                                                                            return (
                                                                                                <button
                                                                                                    key={env.id}
                                                                                                    type="button"
                                                                                                    className={`px-2 py-0.5 rounded-full text-[10px] font-medium transition-all ${
                                                                                                        restriction === false
                                                                                                            ? 'bg-danger/10 text-danger border border-danger/30'
                                                                                                            : restriction === true
                                                                                                            ? 'bg-success/10 text-success border border-success/30'
                                                                                                            : 'bg-background border border-border text-foreground-muted'
                                                                                                    }`}
                                                                                                    onClick={() => toggleEnvironmentRestriction(permission.id, env.name)}
                                                                                                >
                                                                                                    {env.name}
                                                                                                    {restriction === false && ' (Blocked)'}
                                                                                                    {restriction === true && ' (Allowed)'}
                                                                                                </button>
                                                                                            );
                                                                                        })}
                                                                                    </div>
                                                                                    <p className="text-[10px] text-foreground-subtle mt-1">
                                                                                        Click to cycle: Default → Blocked → Allowed
                                                                                    </p>
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Error */}
                        {error && (
                            <div className="rounded-lg border border-danger/50 bg-danger/10 p-3 text-sm text-danger">
                                {error}
                            </div>
                        )}
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={handleClose} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isSubmitting} disabled={!email || isLoadingData}>
                            <Mail className="mr-2 h-4 w-4" />
                            Send Invitation
                        </Button>
                    </ModalFooter>
                </form>
            )}
        </Modal>
    );
}
