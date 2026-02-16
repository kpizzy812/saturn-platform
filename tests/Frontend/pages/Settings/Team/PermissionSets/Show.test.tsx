import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../../utils/test-utils';
import PermissionSetShow from '@/pages/Settings/Team/PermissionSets/Show';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ toast: vi.fn() }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

describe('PermissionSetShow', () => {
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
            {
                id: 2,
                key: 'applications.deploy',
                name: 'Deploy Applications',
                description: 'Can deploy applications',
                category: 'resources',
                is_sensitive: true,
                environment_restrictions: { production: false, staging: true },
            },
        ],
        users: [
            {
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
                environment_overrides: null,
            },
            {
                id: 2,
                name: 'Jane Smith',
                email: 'jane@example.com',
                environment_overrides: { production: true },
            },
        ],
        parent: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    };

    const mockSystemPermissionSet = {
        ...mockPermissionSet,
        id: 2,
        name: 'Admin',
        slug: 'admin',
        is_system: true,
        description: 'Full access to all resources',
    };

    const mockAllPermissions = {
        resources: [
            {
                id: 1,
                key: 'applications.view',
                name: 'View Applications',
                description: 'Can view all applications',
                category: 'resources',
                is_sensitive: false,
            },
            {
                id: 2,
                key: 'applications.deploy',
                name: 'Deploy Applications',
                description: 'Can deploy applications',
                category: 'resources',
                is_sensitive: true,
            },
            {
                id: 3,
                key: 'applications.delete',
                name: 'Delete Applications',
                description: 'Can delete applications',
                category: 'resources',
                is_sensitive: true,
            },
        ],
        team: [
            {
                id: 4,
                key: 'team.manage_members',
                name: 'Manage Members',
                description: 'Can add and remove team members',
                category: 'team',
                is_sensitive: false,
            },
        ],
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the role name', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('QA Engineer')).toBeInTheDocument();
    });

    it('renders the role description', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Quality assurance team member')).toBeInTheDocument();
    });

    it('shows built-in badge for system roles', () => {
        render(<PermissionSetShow permissionSet={mockSystemPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Built-in')).toBeInTheDocument();
    });

    it('does not show built-in badge for custom roles', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.queryByText('Built-in')).not.toBeInTheDocument();
    });

    it('shows edit button for custom roles', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Edit Role')).toBeInTheDocument();
    });

    it('does not show edit button for system roles', () => {
        render(<PermissionSetShow permissionSet={mockSystemPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.queryByText('Edit Role')).not.toBeInTheDocument();
    });

    it('displays assigned users count', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        const assignedUsersLabels = screen.getAllByText('Assigned Users');
        expect(assignedUsersLabels.length).toBeGreaterThan(0);
    });

    it('displays permissions granted count', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('2')).toBeInTheDocument();
        expect(screen.getByText('Permissions Granted')).toBeInTheDocument();
    });

    it('displays sensitive permissions count', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('1')).toBeInTheDocument();
        expect(screen.getByText('Sensitive Permissions')).toBeInTheDocument();
    });

    it('renders permissions section', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Permissions')).toBeInTheDocument();
        expect(screen.getByText('Permissions granted to users with this role')).toBeInTheDocument();
    });

    it('renders permission categories', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Resources')).toBeInTheDocument();
        expect(screen.getByText('Team Management')).toBeInTheDocument();
    });

    it('shows sensitive badge for sensitive permissions', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        const sensitiveBadges = screen.getAllByText('Sensitive');
        expect(sensitiveBadges.length).toBeGreaterThan(0);
    });

    it('displays environment restrictions when present', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Environment restrictions:')).toBeInTheDocument();
    });

    it('renders assigned users section', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        const assignedUsersLabels = screen.getAllByText('Assigned Users');
        expect(assignedUsersLabels.length).toBeGreaterThan(0);
        expect(screen.getByText('Users who have this role assigned')).toBeInTheDocument();
    });

    it('displays user information', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('john@example.com')).toBeInTheDocument();
        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
        expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    });

    it('shows empty state when no users assigned', () => {
        const noUsersSet = { ...mockPermissionSet, users: [], users_count: 0 };
        render(<PermissionSetShow permissionSet={noUsersSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('No users assigned to this role yet.')).toBeInTheDocument();
    });

    it('displays user environment overrides when present', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        expect(screen.getByText('Overrides:')).toBeInTheDocument();
    });

    it('renders back button', () => {
        render(<PermissionSetShow permissionSet={mockPermissionSet} allPermissions={mockAllPermissions} />);

        const buttons = screen.getAllByRole('button');
        const backButton = buttons.find((btn) => btn.querySelector('svg'));
        expect(backButton).toBeInTheDocument();
    });
});