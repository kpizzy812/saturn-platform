import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../../utils/test-utils';
import { userEvent } from '@testing-library/user-event';
import PermissionSetEdit from '@/pages/Settings/Team/PermissionSets/Edit';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ toast: vi.fn() }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

describe('PermissionSetEdit', () => {
    const mockPermissionSet = {
        id: 1,
        name: 'QA Engineer',
        slug: 'qa-engineer',
        description: 'Quality assurance team member',
        is_system: false,
        color: 'success',
        icon: 'shield',
        users_count: 5,
        permissions: [
            {
                id: 1,
                key: 'applications.view',
                name: 'View Applications',
                description: 'Can view all applications',
                category: 'resources',
                is_sensitive: false,
                environment_restrictions: null,
            },
        ],
        parent: null,
    };

    const mockSystemPermissionSet = {
        ...mockPermissionSet,
        id: 2,
        name: 'Admin',
        slug: 'admin',
        is_system: true,
    };

    const mockAllPermissions = {
        resources: [
            {
                id: 1,
                key: 'applications.view',
                name: 'View Applications',
                description: 'Can view all applications',
                resource: 'applications',
                action: 'view',
                is_sensitive: false,
            },
            {
                id: 2,
                key: 'applications.deploy',
                name: 'Deploy Applications',
                description: 'Can deploy applications',
                resource: 'applications',
                action: 'deploy',
                is_sensitive: true,
            },
        ],
        team: [
            {
                id: 3,
                key: 'team.manage_members',
                name: 'Manage Members',
                description: 'Can add and remove team members',
                resource: 'team',
                action: 'manage_members',
                is_sensitive: false,
            },
        ],
    };

    const mockEnvironments = [
        { id: 1, name: 'production' },
        { id: 2, name: 'staging' },
    ];

    const mockParentSets = [
        { id: 1, name: 'Developer', slug: 'developer' },
        { id: 2, name: 'Viewer', slug: 'viewer' },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the page title and description', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Edit Role')).toBeInTheDocument();
        expect(screen.getByText('Modify the permissions for QA Engineer')).toBeInTheDocument();
    });

    it('renders back button', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const buttons = screen.getAllByRole('button');
        const backButton = buttons.find((btn) => btn.querySelector('svg'));
        expect(backButton).toBeInTheDocument();
    });

    it('renders delete button', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Delete')).toBeInTheDocument();
    });

    it('pre-fills name field with existing value', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const nameInput = screen.getByDisplayValue('QA Engineer');
        expect(nameInput).toBeInTheDocument();
    });

    it('pre-fills description field with existing value', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const descriptionInput = screen.getByDisplayValue('Quality assurance team member');
        expect(descriptionInput).toBeInTheDocument();
    });

    it('disables name field for system roles', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockSystemPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const nameInput = screen.getByDisplayValue('Admin');
        expect(nameInput).toBeDisabled();
        expect(screen.getByText('System roles cannot be renamed.')).toBeInTheDocument();
    });

    it('renders basic information section', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Basic Information')).toBeInTheDocument();
        expect(screen.getByText('Name and describe your role')).toBeInTheDocument();
    });

    it('renders permissions section', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Permissions')).toBeInTheDocument();
        expect(screen.getByText('Select the permissions to include in this role')).toBeInTheDocument();
    });

    it('shows selected permissions count', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('1 selected')).toBeInTheDocument();
    });

    it('renders all permission categories', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Resources')).toBeInTheDocument();
        expect(screen.getByText('Team Management')).toBeInTheDocument();
    });

    it('renders cancel and save buttons', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /save changes/i })).toBeInTheDocument();
    });

    it('shows sensitive badge for sensitive permissions', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Sensitive')).toBeInTheDocument();
    });

    it('allows editing the name field', async () => {
        const user = userEvent.setup();
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const nameInput = screen.getByDisplayValue('QA Engineer');
        await user.clear(nameInput);
        await user.type(nameInput, 'Updated Role');
        expect(nameInput).toHaveValue('Updated Role');
    });

    it('does not show inherit from select for system roles', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockSystemPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBe(2); // Only Color and Icon, no Inherit From
    });

    it('shows inherit from select for custom roles', () => {
        render(
            <PermissionSetEdit
                permissionSet={mockPermissionSet}
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBe(3); // Color, Icon, and Inherit From
    });
});