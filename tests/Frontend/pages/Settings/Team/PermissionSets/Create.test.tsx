import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../../utils/test-utils';
import { userEvent } from '@testing-library/user-event';
import PermissionSetCreate from '@/pages/Settings/Team/PermissionSets/Create';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ toast: vi.fn() }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

describe('PermissionSetCreate', () => {
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
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const createRoleElements = screen.getAllByText('Create Role');
        expect(createRoleElements.length).toBeGreaterThan(0);
        expect(screen.getByText('Define a custom role with specific permissions for team members')).toBeInTheDocument();
    });

    it('renders back button', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const buttons = screen.getAllByRole('button');
        const backButton = buttons.find((btn) => btn.querySelector('svg'));
        expect(backButton).toBeInTheDocument();
    });

    it('renders basic information section', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Basic Information')).toBeInTheDocument();
        expect(screen.getByText('Name and describe your role')).toBeInTheDocument();
    });

    it('renders name and description fields', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByPlaceholderText('e.g., QA Engineer')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('e.g., Quality assurance team member')).toBeInTheDocument();
    });

    it('renders color, icon, and parent selects', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBe(3); // Color, Icon, Inherit From
    });

    it('renders permissions section with categories', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Permissions')).toBeInTheDocument();
        expect(screen.getByText('Select the permissions to include in this role')).toBeInTheDocument();
        expect(screen.getByText('Resources')).toBeInTheDocument();
        expect(screen.getByText('Team Management')).toBeInTheDocument();
    });

    it('renders all permissions from the mock data', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('View Applications')).toBeInTheDocument();
        expect(screen.getByText('Deploy Applications')).toBeInTheDocument();
        expect(screen.getByText('Manage Members')).toBeInTheDocument();
    });

    it('shows sensitive badge for sensitive permissions', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Sensitive')).toBeInTheDocument();
    });

    it('displays selected permissions count badge', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('0 selected')).toBeInTheDocument();
    });

    it('renders select all/deselect all buttons for each category', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const selectButtons = screen.getAllByText('Select All');
        expect(selectButtons.length).toBe(2); // One for each category
    });

    it('renders cancel and create buttons', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /create role/i })).toBeInTheDocument();
    });

    it('disables create button when name is empty', () => {
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const createButton = screen.getByRole('button', { name: /create role/i });
        expect(createButton).toBeDisabled();
    });

    it('allows typing in the name field', async () => {
        const user = userEvent.setup();
        render(
            <PermissionSetCreate
                allPermissions={mockAllPermissions}
                environments={mockEnvironments}
                parentSets={mockParentSets}
            />,
        );

        const nameInput = screen.getByPlaceholderText('e.g., QA Engineer');
        await user.type(nameInput, 'Test Role');
        expect(nameInput).toHaveValue('Test Role');
    });
});