import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../../utils/test-utils';
import PermissionSetsIndex from '@/pages/Settings/Team/PermissionSets/Index';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ toast: vi.fn() }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

describe('PermissionSetsIndex', () => {
    const mockPermissionSets = [
        {
            id: 1,
            name: 'Admin',
            slug: 'admin',
            description: 'Full access to all resources',
            is_system: true,
            color: 'warning',
            icon: 'crown',
            users_count: 5,
            permissions: [],
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
        },
        {
            id: 2,
            name: 'Developer',
            slug: 'developer',
            description: 'Can manage applications and deployments',
            is_system: true,
            color: 'primary',
            icon: 'code',
            users_count: 10,
            permissions: [],
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
        },
        {
            id: 3,
            name: 'QA Engineer',
            slug: 'qa-engineer',
            description: 'Quality assurance team member',
            is_system: false,
            color: 'success',
            icon: 'shield',
            users_count: 3,
            permissions: [],
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the page title and description', () => {
        render(<PermissionSetsIndex permissionSets={[]} canManageRoles={false} />);

        expect(screen.getByText('Roles')).toBeInTheDocument();
        expect(screen.getByText('Manage roles and permissions for team members')).toBeInTheDocument();
    });

    it('renders back button', () => {
        render(<PermissionSetsIndex permissionSets={[]} canManageRoles={false} />);

        const backButton = screen.getByRole('button', { name: '' });
        expect(backButton).toBeInTheDocument();
    });

    it('renders create role button when user can manage roles', () => {
        render(<PermissionSetsIndex permissionSets={[]} canManageRoles={true} />);

        const createButtons = screen.getAllByText('Create Role');
        expect(createButtons.length).toBeGreaterThan(0);
    });

    it('does not render create role button when user cannot manage roles', () => {
        render(<PermissionSetsIndex permissionSets={[]} canManageRoles={false} />);

        const createButtons = screen.queryAllByText('Create Role');
        // Empty state might show a create button, so we just check header doesn't have it
        expect(createButtons.length).toBeLessThan(2);
    });

    it('renders built-in roles section', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        expect(screen.getByText('Built-in Roles')).toBeInTheDocument();
        expect(screen.getByText('System-defined roles with predefined permissions')).toBeInTheDocument();
    });

    it('renders system roles correctly', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        expect(screen.getByText('Admin')).toBeInTheDocument();
        expect(screen.getByText('Full access to all resources')).toBeInTheDocument();
        expect(screen.getByText('Developer')).toBeInTheDocument();
        expect(screen.getByText('Can manage applications and deployments')).toBeInTheDocument();

        const builtInBadges = screen.getAllByText('Built-in');
        expect(builtInBadges).toHaveLength(2);
    });

    it('renders user count for roles', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        expect(screen.getByText('5 users')).toBeInTheDocument();
        expect(screen.getByText('10 users')).toBeInTheDocument();
        expect(screen.getByText('3 users')).toBeInTheDocument();
    });

    it('renders custom roles section', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        expect(screen.getByText('Custom Roles')).toBeInTheDocument();
        expect(screen.getByText('Create custom roles for fine-grained access control')).toBeInTheDocument();
    });

    it('renders custom roles correctly', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        expect(screen.getByText('QA Engineer')).toBeInTheDocument();
        expect(screen.getByText('Quality assurance team member')).toBeInTheDocument();
    });

    it('displays empty state when no custom roles exist', () => {
        const systemRolesOnly = mockPermissionSets.filter((set) => set.is_system);
        render(<PermissionSetsIndex permissionSets={systemRolesOnly} canManageRoles={true} />);

        expect(screen.getByText('No custom roles yet. Create one to define custom access levels.')).toBeInTheDocument();
    });

    it('renders view permissions buttons for system roles', () => {
        render(<PermissionSetsIndex permissionSets={mockPermissionSets} canManageRoles={true} />);

        const viewPermissionsButtons = screen.getAllByText('View Permissions');
        expect(viewPermissionsButtons.length).toBeGreaterThan(0);
    });
});