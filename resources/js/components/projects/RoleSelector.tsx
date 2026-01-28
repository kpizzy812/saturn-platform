import { Select } from '@/components/ui';
import type { ProjectRole } from '@/types/models';

interface RoleSelectorProps {
    value: ProjectRole;
    onChange: (role: ProjectRole) => void;
    disabled?: boolean;
    excludeOwner?: boolean;
}

const ROLE_OPTIONS: { value: ProjectRole; label: string; description: string }[] = [
    { value: 'owner', label: 'Owner', description: 'Full control, can delete project' },
    { value: 'admin', label: 'Admin', description: 'Manage members, deploy to all environments' },
    { value: 'developer', label: 'Developer', description: 'Deploy to dev/uat, needs approval for prod' },
    { value: 'member', label: 'Member', description: 'Limited deployment access' },
    { value: 'viewer', label: 'Viewer', description: 'Read-only access' },
];

export function RoleSelector({ value, onChange, disabled, excludeOwner }: RoleSelectorProps) {
    const options = ROLE_OPTIONS
        .filter(opt => !excludeOwner || opt.value !== 'owner')
        .map(opt => ({
            value: opt.value,
            label: opt.label,
        }));

    return (
        <Select
            value={value}
            onChange={(e) => onChange(e.target.value as ProjectRole)}
            options={options}
            disabled={disabled}
        />
    );
}

export function getRoleLabel(role: ProjectRole): string {
    return ROLE_OPTIONS.find(opt => opt.value === role)?.label || role;
}

export function getRoleDescription(role: ProjectRole): string {
    return ROLE_OPTIONS.find(opt => opt.value === role)?.description || '';
}

export function getRoleBadgeVariant(role: ProjectRole): 'primary' | 'success' | 'warning' | 'secondary' | 'info' {
    switch (role) {
        case 'owner':
            return 'primary';
        case 'admin':
            return 'success';
        case 'developer':
            return 'info';
        case 'member':
            return 'secondary';
        case 'viewer':
            return 'warning';
        default:
            return 'secondary';
    }
}
