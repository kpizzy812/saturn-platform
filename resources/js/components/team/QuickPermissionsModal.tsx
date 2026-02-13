import * as React from 'react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { router } from '@inertiajs/react';
import { Shield, User as UserIcon, Code, Crown, Eye, Check, Info, ChevronLeft } from 'lucide-react';

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

interface PermissionSelection {
    permission_id: number;
    environment_restrictions: Record<string, boolean>;
}

interface Member {
    id: number;
    name: string;
    role: string;
}

interface PermissionsData {
    permissionSets: PermissionSetOption[];
    allPermissions: Record<string, PermissionItem[]>;
    currentPermissionSetId: number | null;
    currentPermissions: PermissionSelection[];
    isPersonalSet: boolean;
    personalSetId: number | null;
    environments: EnvironmentItem[];
    member: Member;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    member: { id: number; name: string; email: string; role: string } | null;
    onSuccess?: () => void;
}

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

export function QuickPermissionsModal({ isOpen, onClose, member, onSuccess }: Props) {
    const [data, setData] = React.useState<PermissionsData | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [isSaving, setIsSaving] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    // Mode: 'quick' = select preset, 'custom' = checkboxes
    const [mode, setMode] = React.useState<'quick' | 'custom'>('quick');

    // Quick mode state
    const [selectedSetId, setSelectedSetId] = React.useState<number | null>(null);

    // Custom mode state
    const [selectedPermissions, setSelectedPermissions] = React.useState<Set<number>>(new Set());
    const [environmentRestrictions, setEnvironmentRestrictions] = React.useState<Record<number, Record<string, boolean>>>({});
    const [showEnvironmentOptions, setShowEnvironmentOptions] = React.useState<number | null>(null);

    // Load data when modal opens
    React.useEffect(() => {
        if (isOpen && member) {
            setIsLoading(true);
            setError(null);
            fetch(`/settings/team/members/${member.id}/permissions`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(d => { throw new Error(d.message || 'Failed to load permissions'); });
                    }
                    return res.json();
                })
                .then((responseData: PermissionsData) => {
                    setData(responseData);

                    // Determine initial mode
                    if (responseData.isPersonalSet) {
                        setMode('custom');
                        // Populate custom selections from current permissions
                        const permIds = new Set(responseData.currentPermissions.map(p => p.permission_id));
                        setSelectedPermissions(permIds);
                        const envRestrictions: Record<number, Record<string, boolean>> = {};
                        responseData.currentPermissions.forEach(p => {
                            if (p.environment_restrictions && Object.keys(p.environment_restrictions).length > 0) {
                                envRestrictions[p.permission_id] = p.environment_restrictions;
                            }
                        });
                        setEnvironmentRestrictions(envRestrictions);
                    } else {
                        setMode('quick');
                        setSelectedSetId(responseData.currentPermissionSetId);
                        setSelectedPermissions(new Set());
                        setEnvironmentRestrictions({});
                    }
                })
                .catch(err => {
                    setError(err.message);
                })
                .finally(() => setIsLoading(false));
        }
    }, [isOpen, member]);

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

    const handleSave = () => {
        if (!member) return;
        setIsSaving(true);
        setError(null);

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

        if (mode === 'quick') {
            // Save preset permission set
            fetch(`/settings/team/members/${member.id}/permission-set`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ permission_set_id: selectedSetId }),
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(d => { throw new Error(d.message || 'Failed to update permission set'); });
                    }
                    return res.json();
                })
                .then(() => {
                    onSuccess?.();
                    onClose();
                    router.reload();
                })
                .catch(err => setError(err.message))
                .finally(() => setIsSaving(false));
        } else {
            // Save custom permissions
            const permissions: PermissionSelection[] = Array.from(selectedPermissions).map(id => ({
                permission_id: id,
                environment_restrictions: environmentRestrictions[id] || {},
            }));

            fetch(`/settings/team/members/${member.id}/permissions/custom`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ permissions }),
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(d => { throw new Error(d.message || 'Failed to save custom permissions'); });
                    }
                    return res.json();
                })
                .then(() => {
                    onSuccess?.();
                    onClose();
                    router.reload();
                })
                .catch(err => setError(err.message))
                .finally(() => setIsSaving(false));
        }
    };

    const handleClose = () => {
        setError(null);
        onClose();
    };

    const switchToCustom = () => {
        if (!data) return;
        // If switching from quick mode with a selected set, pre-populate from that set's permissions
        if (mode === 'quick' && selectedSetId && data.currentPermissionSetId === selectedSetId) {
            const permIds = new Set(data.currentPermissions.map(p => p.permission_id));
            setSelectedPermissions(permIds);
            const envRestrictions: Record<number, Record<string, boolean>> = {};
            data.currentPermissions.forEach(p => {
                if (p.environment_restrictions && Object.keys(p.environment_restrictions).length > 0) {
                    envRestrictions[p.permission_id] = p.environment_restrictions;
                }
            });
            setEnvironmentRestrictions(envRestrictions);
        }
        setMode('custom');
    };

    if (!member) return null;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Edit Permissions"
            description={`Configure permissions for ${member.name}`}
            size="lg"
        >
            {isLoading ? (
                <div className="flex items-center justify-center py-8">
                    <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : error ? (
                <div className="rounded-lg border border-danger/50 bg-danger/10 p-4 text-center text-danger">
                    {error}
                </div>
            ) : data ? (
                <div className="space-y-4">
                    {/* Mode Switcher */}
                    <div className="flex gap-2 rounded-lg border border-border bg-background-secondary p-1">
                        <button
                            type="button"
                            onClick={() => setMode('quick')}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all ${
                                mode === 'quick'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            Permission Sets
                        </button>
                        <button
                            type="button"
                            onClick={switchToCustom}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all ${
                                mode === 'custom'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            Custom Permissions
                        </button>
                    </div>

                    {mode === 'quick' ? (
                        /* Quick Mode - Permission Set Selection */
                        <div className="max-h-96 space-y-2 overflow-y-auto">
                            {/* None option */}
                            <button
                                type="button"
                                onClick={() => setSelectedSetId(null)}
                                className={`flex w-full items-center gap-3 rounded-lg border p-3 transition-all ${
                                    selectedSetId === null
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border hover:border-border/80'
                                }`}
                            >
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary">
                                    <UserIcon className="h-4 w-4 text-foreground-muted" />
                                </div>
                                <div className="flex-1 text-left">
                                    <p className="font-medium text-foreground">Role Default</p>
                                    <p className="text-xs text-foreground-muted">
                                        Use permissions from the member's role ({member.role})
                                    </p>
                                </div>
                                {selectedSetId === null && (
                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white">
                                        <Check className="h-4 w-4" />
                                    </div>
                                )}
                            </button>

                            {data.permissionSets.map((set) => (
                                <button
                                    key={set.id}
                                    type="button"
                                    onClick={() => setSelectedSetId(set.id)}
                                    className={`flex w-full items-center gap-3 rounded-lg border p-3 transition-all ${
                                        selectedSetId === set.id
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    }`}
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary">
                                        {setIconMap[set.icon || 'shield'] || <Shield className="h-4 w-4" />}
                                    </div>
                                    <div className="flex-1 text-left">
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-foreground">{set.name}</p>
                                            {set.is_system && (
                                                <Badge variant="default" className="text-[10px]">System</Badge>
                                            )}
                                            <Badge variant="info" className="text-[10px]">
                                                {set.permissions_count} permissions
                                            </Badge>
                                        </div>
                                        {set.description && (
                                            <p className="text-xs text-foreground-muted">{set.description}</p>
                                        )}
                                    </div>
                                    {selectedSetId === set.id && (
                                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white">
                                            <Check className="h-4 w-4" />
                                        </div>
                                    )}
                                    {data.currentPermissionSetId === set.id && selectedSetId !== set.id && (
                                        <Badge variant="default" className="text-[10px]">Current</Badge>
                                    )}
                                </button>
                            ))}
                        </div>
                    ) : (
                        /* Custom Mode - Permission Checkboxes */
                        <div className="space-y-4">
                            {mode === 'custom' && data.isPersonalSet && (
                                <button
                                    type="button"
                                    onClick={() => setMode('quick')}
                                    className="flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    <ChevronLeft className="h-3 w-3" />
                                    Switch to preset
                                </button>
                            )}

                            <div className="flex items-center justify-between">
                                <Badge variant="default">{selectedPermissions.size} selected</Badge>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (!data) return;
                                            const allIds = new Set<number>();
                                            Object.values(data.allPermissions).forEach(perms =>
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
                                        Deselect All
                                    </button>
                                </div>
                            </div>

                            <div className="max-h-80 space-y-4 overflow-y-auto">
                                {Object.entries(data.allPermissions).map(([category, permissions]) => (
                                    <div key={category}>
                                        <div className="flex items-center justify-between mb-2">
                                            <h4 className="text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                                {categoryLabels[category] || category}
                                            </h4>
                                            <button
                                                type="button"
                                                className="text-xs text-primary hover:underline"
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
                                                                {isSelected && data.environments.length > 0 && (
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
                                                        {isSelected && showEnvironmentOptions === permission.id && data.environments.length > 0 && (
                                                            <div className="ml-3 p-2 rounded-lg border border-border bg-background-secondary">
                                                                <p className="text-[10px] font-medium text-foreground mb-1.5">
                                                                    Environment Restrictions
                                                                </p>
                                                                <div className="flex flex-wrap gap-1.5">
                                                                    {data.environments.map((env) => {
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
            ) : null}

            <ModalFooter>
                <Button variant="secondary" onClick={handleClose} disabled={isSaving}>
                    Cancel
                </Button>
                <Button
                    onClick={handleSave}
                    loading={isSaving}
                    disabled={isLoading || !!error || (mode === 'custom' && selectedPermissions.size === 0)}
                >
                    Save Permissions
                </Button>
            </ModalFooter>
        </Modal>
    );
}
