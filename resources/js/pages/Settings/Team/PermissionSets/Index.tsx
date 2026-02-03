import * as React from 'react';
import { SettingsLayout } from '../../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Link, router } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import {
    ArrowLeft,
    Plus,
    Crown,
    Shield,
    User as UserIcon,
    Code2,
    Eye,
    MoreVertical,
    Edit,
    Trash2,
    Users,
    Lock,
} from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

interface Permission {
    id: number;
    key: string;
    name: string;
    category: string;
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
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
}

interface Props {
    permissionSets: PermissionSet[];
}

const iconMap: Record<string, React.ReactNode> = {
    crown: <Crown className="h-4 w-4" />,
    shield: <Shield className="h-4 w-4" />,
    code: <Code2 className="h-4 w-4" />,
    user: <UserIcon className="h-4 w-4" />,
    eye: <Eye className="h-4 w-4" />,
};

const colorMap: Record<string, string> = {
    warning: 'text-warning',
    primary: 'text-primary',
    success: 'text-success',
    info: 'text-info',
    'foreground-muted': 'text-foreground-muted',
};

export default function PermissionSetsIndex({ permissionSets }: Props) {
    const { toast } = useToast();
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [selectedSet, setSelectedSet] = React.useState<PermissionSet | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const getIcon = (set: PermissionSet) => {
        return iconMap[set.icon || 'user'] || <UserIcon className="h-4 w-4" />;
    };

    const getColorClass = (set: PermissionSet) => {
        return colorMap[set.color || 'foreground-muted'] || 'text-foreground-muted';
    };

    const handleDelete = () => {
        if (!selectedSet) return;

        setIsDeleting(true);
        router.delete(`/settings/team/permission-sets/${selectedSet.id}`, {
            onSuccess: () => {
                toast({
                    title: 'Permission set deleted',
                    description: `${selectedSet.name} has been deleted.`,
                });
                setShowDeleteModal(false);
                setSelectedSet(null);
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
            },
        });
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
                            <h2 className="text-2xl font-semibold text-foreground">Permission Sets</h2>
                            <p className="text-sm text-foreground-muted">
                                Manage roles and permissions for team members
                            </p>
                        </div>
                    </div>
                    <Link href="/settings/team/permission-sets/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Permission Set
                        </Button>
                    </Link>
                </div>

                {/* System Permission Sets */}
                <Card>
                    <CardHeader>
                        <CardTitle>Built-in Roles</CardTitle>
                        <CardDescription>
                            System-defined permission sets with predefined permissions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {permissionSets
                                .filter((set) => set.is_system)
                                .map((set) => (
                                    <div
                                        key={set.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div
                                                className={`flex h-10 w-10 items-center justify-center rounded-full bg-background-tertiary ${getColorClass(set)}`}
                                            >
                                                {getIcon(set)}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{set.name}</p>
                                                    <Badge variant="default">Built-in</Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    {set.description}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                                <Users className="h-4 w-4" />
                                                {set.users_count} user{set.users_count !== 1 ? 's' : ''}
                                            </div>
                                            <Link href={`/settings/team/permission-sets/${set.id}`}>
                                                <Button variant="ghost" size="sm">
                                                    View Permissions
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Custom Permission Sets */}
                <Card>
                    <CardHeader>
                        <CardTitle>Custom Permission Sets</CardTitle>
                        <CardDescription>
                            Create custom permission sets for fine-grained access control
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {permissionSets.filter((set) => !set.is_system).length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Lock className="h-12 w-12 text-foreground-muted mb-4" />
                                <p className="text-foreground-muted mb-4">
                                    No custom permission sets yet. Create one to define custom access levels.
                                </p>
                                <Link href="/settings/team/permission-sets/create">
                                    <Button variant="secondary">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Permission Set
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {permissionSets
                                    .filter((set) => !set.is_system)
                                    .map((set) => (
                                        <div
                                            key={set.id}
                                            className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div
                                                    className={`flex h-10 w-10 items-center justify-center rounded-full bg-background-tertiary ${getColorClass(set)}`}
                                                >
                                                    {getIcon(set)}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-foreground">{set.name}</p>
                                                    <p className="text-sm text-foreground-muted">
                                                        {set.description || 'No description'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                                    <Users className="h-4 w-4" />
                                                    {set.users_count} user{set.users_count !== 1 ? 's' : ''}
                                                </div>
                                                <Dropdown>
                                                    <DropdownTrigger>
                                                        <Button variant="ghost" size="icon">
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownTrigger>
                                                    <DropdownContent>
                                                        <Link href={`/settings/team/permission-sets/${set.id}`}>
                                                            <DropdownItem>
                                                                <Eye className="h-4 w-4" />
                                                                View Details
                                                            </DropdownItem>
                                                        </Link>
                                                        <Link href={`/settings/team/permission-sets/${set.id}/edit`}>
                                                            <DropdownItem>
                                                                <Edit className="h-4 w-4" />
                                                                Edit
                                                            </DropdownItem>
                                                        </Link>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            danger
                                                            onClick={() => {
                                                                setSelectedSet(set);
                                                                setShowDeleteModal(true);
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                            Delete
                                                        </DropdownItem>
                                                    </DropdownContent>
                                                </Dropdown>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Delete Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Permission Set"
                description={`Are you sure you want to delete "${selectedSet?.name}"? Users assigned to this set will need to be reassigned.`}
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
