import * as React from 'react';
import { SettingsLayout } from '../../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Link, router, useForm } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';
import {
    ArrowLeft,
    Check,
    Info,
    Trash2,
} from 'lucide-react';
import { Modal, ModalFooter } from '@/components/ui/Modal';

interface Permission {
    id: number;
    key: string;
    name: string;
    description?: string;
    resource: string;
    action: string;
    is_sensitive?: boolean;
}

interface Environment {
    id: number;
    name: string;
}

interface PermissionSetPermission {
    id: number;
    key: string;
    name: string;
    description?: string;
    category: string;
    is_sensitive?: boolean;
    environment_restrictions?: Record<string, boolean> | null;
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
    permissions: PermissionSetPermission[];
    parent?: {
        id: number;
        name: string;
        slug: string;
    } | null;
}

interface Props {
    permissionSet: PermissionSet;
    allPermissions: Record<string, Permission[]>;
    environments: Environment[];
    parentSets: Array<{ id: number; name: string; slug: string }>;
}

const categoryLabels: Record<string, string> = {
    resources: 'Resources',
    team: 'Team Management',
    settings: 'Settings',
};

interface PermissionSelection {
    permission_id: number;
    environment_restrictions: Record<string, boolean>;
}

export default function PermissionSetEdit({ permissionSet, allPermissions, environments, parentSets }: Props) {
    const { toast } = useToast();
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);

    // Initialize selected permissions from existing data
    const initialSelected = new Set(permissionSet.permissions.map((p) => p.id));
    const initialRestrictions: Record<number, Record<string, boolean>> = {};
    permissionSet.permissions.forEach((p) => {
        if (p.environment_restrictions && Object.keys(p.environment_restrictions).length > 0) {
            initialRestrictions[p.id] = p.environment_restrictions;
        }
    });

    const { data, setData, processing, errors } = useForm({
        name: permissionSet.name,
        description: permissionSet.description || '',
        color: permissionSet.color || 'primary',
        icon: permissionSet.icon || 'shield',
        parent_id: permissionSet.parent?.id || '' as string | number,
        permissions: [] as PermissionSelection[],
    });

    const [selectedPermissions, setSelectedPermissions] = React.useState<Set<number>>(initialSelected);
    const [environmentRestrictions, setEnvironmentRestrictions] = React.useState<Record<number, Record<string, boolean>>>(initialRestrictions);
    const [showEnvironmentOptions, setShowEnvironmentOptions] = React.useState<number | null>(null);

    const togglePermission = (permissionId: number) => {
        const newSelected = new Set(selectedPermissions);
        if (newSelected.has(permissionId)) {
            newSelected.delete(permissionId);
            // Clean up environment restrictions
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const permissions: PermissionSelection[] = Array.from(selectedPermissions).map((id) => ({
            permission_id: id,
            environment_restrictions: environmentRestrictions[id] || {},
        }));

        router.put(`/settings/team/permission-sets/${permissionSet.id}`, {
            name: data.name,
            description: data.description,
            color: data.color,
            icon: data.icon,
            parent_id: data.parent_id || null,
            permissions,
        }, {
            onSuccess: () => {
                toast({
                    title: 'Permission set updated',
                    description: `${data.name} has been updated successfully.`,
                });
            },
            onError: () => {
                toast({
                    title: 'Failed to update permission set',
                    description: 'Please check the form and try again.',
                    variant: 'error',
                });
            },
        });
    };

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(`/settings/team/permission-sets/${permissionSet.id}`, {
            onSuccess: () => {
                toast({
                    title: 'Permission set deleted',
                    description: `${permissionSet.name} has been deleted.`,
                });
            },
            onError: () => {
                toast({
                    title: 'Failed to delete',
                    description: 'Unable to delete permission set. It may be in use.',
                    variant: 'error',
                });
            },
            onFinish: () => {
                setIsDeleting(false);
                setShowDeleteModal(false);
            },
        });
    };

    const colorOptions = [
        { value: 'primary', label: 'Blue' },
        { value: 'success', label: 'Green' },
        { value: 'warning', label: 'Yellow' },
        { value: 'info', label: 'Cyan' },
        { value: 'foreground-muted', label: 'Gray' },
    ];

    const iconOptions = [
        { value: 'shield', label: 'Shield' },
        { value: 'user', label: 'User' },
        { value: 'code', label: 'Code' },
        { value: 'eye', label: 'Eye' },
        { value: 'crown', label: 'Crown' },
    ];

    return (
        <SettingsLayout activeSection="team">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={`/settings/team/permission-sets/${permissionSet.id}`}>
                            <Button variant="ghost" size="icon" type="button">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-semibold text-foreground">Edit Permission Set</h2>
                            <p className="text-sm text-foreground-muted">
                                Modify the permissions for {permissionSet.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="danger"
                            onClick={() => setShowDeleteModal(true)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                        <Link href={`/settings/team/permission-sets/${permissionSet.id}`}>
                            <Button variant="secondary" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" loading={processing} disabled={!data.name || selectedPermissions.size === 0}>
                            Save Changes
                        </Button>
                    </div>
                </div>

                {/* Basic Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                        <CardDescription>
                            Name and describe your permission set
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Name</label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., QA Engineer"
                                    required
                                    disabled={permissionSet.is_system}
                                />
                                {errors.name && <p className="text-sm text-danger">{errors.name}</p>}
                                {permissionSet.is_system && (
                                    <p className="text-xs text-foreground-muted">
                                        System permission sets cannot be renamed.
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Description</label>
                                <Input
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="e.g., Quality assurance team member"
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Color</label>
                                <select
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                >
                                    {colorOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Icon</label>
                                <select
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    value={data.icon}
                                    onChange={(e) => setData('icon', e.target.value)}
                                >
                                    {iconOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {!permissionSet.is_system && (
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Inherit From (Optional)</label>
                                    <select
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        value={data.parent_id}
                                        onChange={(e) => setData('parent_id', e.target.value ? Number(e.target.value) : '')}
                                    >
                                        <option value="">None</option>
                                        {parentSets.map((set) => (
                                            <option key={set.id} value={set.id}>
                                                {set.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Permissions */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Permissions</CardTitle>
                                <CardDescription>
                                    Select the permissions to include in this set
                                </CardDescription>
                            </div>
                            <Badge variant="default">{selectedPermissions.size} selected</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {Object.entries(allPermissions).map(([category, permissions]) => (
                                <div key={category}>
                                    <div className="flex items-center justify-between mb-3">
                                        <h4 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                            {categoryLabels[category] || category}
                                        </h4>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                const categoryIds = permissions.map((p) => p.id);
                                                const allSelected = categoryIds.every((id) => selectedPermissions.has(id));
                                                const newSelected = new Set(selectedPermissions);
                                                categoryIds.forEach((id) => {
                                                    if (allSelected) {
                                                        newSelected.delete(id);
                                                    } else {
                                                        newSelected.add(id);
                                                    }
                                                });
                                                setSelectedPermissions(newSelected);
                                            }}
                                        >
                                            {permissions.every((p) => selectedPermissions.has(p.id))
                                                ? 'Deselect All'
                                                : 'Select All'}
                                        </Button>
                                    </div>
                                    <div className="grid gap-2">
                                        {permissions.map((permission) => {
                                            const isSelected = selectedPermissions.has(permission.id);
                                            const hasRestrictions = environmentRestrictions[permission.id] &&
                                                Object.keys(environmentRestrictions[permission.id]).length > 0;

                                            return (
                                                <div key={permission.id} className="space-y-2">
                                                    <div
                                                        className={`flex items-center justify-between rounded-lg border p-3 cursor-pointer transition-all ${
                                                            isSelected
                                                                ? 'border-primary bg-primary/5'
                                                                : 'border-border bg-background hover:border-border/80'
                                                        }`}
                                                        onClick={() => togglePermission(permission.id)}
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
                                                                {hasRestrictions && (
                                                                    <Badge variant="info" className="text-xs">
                                                                        Env restricted
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <p className="text-xs text-foreground-muted">
                                                                {permission.description}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            {isSelected && environments.length > 0 && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        setShowEnvironmentOptions(
                                                                            showEnvironmentOptions === permission.id
                                                                                ? null
                                                                                : permission.id
                                                                        );
                                                                    }}
                                                                >
                                                                    <Info className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            <div
                                                                className={`flex h-6 w-6 items-center justify-center rounded border transition-all ${
                                                                    isSelected
                                                                        ? 'border-primary bg-primary text-white'
                                                                        : 'border-border'
                                                                }`}
                                                            >
                                                                {isSelected && <Check className="h-4 w-4" />}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Environment Restrictions */}
                                                    {isSelected && showEnvironmentOptions === permission.id && environments.length > 0 && (
                                                        <div className="ml-4 p-3 rounded-lg border border-border bg-background-secondary">
                                                            <p className="text-xs font-medium text-foreground mb-2">
                                                                Environment Restrictions
                                                            </p>
                                                            <div className="flex flex-wrap gap-2">
                                                                {environments.map((env) => {
                                                                    const restriction = environmentRestrictions[permission.id]?.[env.name];
                                                                    return (
                                                                        <button
                                                                            key={env.id}
                                                                            type="button"
                                                                            className={`px-3 py-1 rounded-full text-xs font-medium transition-all ${
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
                                                            <p className="text-xs text-foreground-subtle mt-2">
                                                                Click to toggle: No restriction → Blocked → Allowed
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
                    </CardContent>
                </Card>
            </form>

            {/* Delete Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Permission Set"
                description={`Are you sure you want to delete "${permissionSet.name}"? Users assigned to this set will need to be reassigned.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDeleteModal(false)} disabled={isDeleting}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDelete} loading={isDeleting}>
                        Delete
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
