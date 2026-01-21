import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import AdminDashboard from '@/pages/Admin/Index';
import AdminUsersIndex from '@/pages/Admin/Users/Index';
import AdminUserShow from '@/pages/Admin/Users/Show';
import AdminServersIndex from '@/pages/Admin/Servers/Index';
import AdminTeamsIndex from '@/pages/Admin/Teams/Index';
import AdminSettingsIndex from '@/pages/Admin/Settings/Index';
import AdminLogsIndex from '@/pages/Admin/Logs/Index';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: {
        visit: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    usePage: () => ({
        url: '/admin',
        props: {
            auth: {
                user: {
                    name: 'Admin User',
                    email: 'admin@saturn.io',
                    is_root_user: true,
                },
            },
        },
    }),
}));

// Mock AdminLayout
vi.mock('@/layouts/AdminLayout', () => ({
    AdminLayout: ({ children, title }: any) => (
        <div data-testid="admin-layout" data-title={title}>
            {children}
        </div>
    ),
}));

// Mock UI components
vi.mock('@/components/ui/Card', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h3>{children}</h3>,
    CardDescription: ({ children }: any) => <p>{children}</p>,
}));

vi.mock('@/components/ui/Badge', () => ({
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
}));

vi.mock('@/components/ui/Button', () => ({
    Button: ({ children, onClick, disabled, variant }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant}>
            {children}
        </button>
    ),
}));

vi.mock('@/components/ui/Input', () => ({
    Input: ({ value, onChange, placeholder }: any) => (
        <input value={value} onChange={onChange} placeholder={placeholder} />
    ),
}));

vi.mock('@/components/ui/Dropdown', () => ({
    Dropdown: ({ children }: any) => <div>{children}</div>,
    DropdownTrigger: ({ children }: any) => <div>{children}</div>,
    DropdownContent: ({ children }: any) => <div>{children}</div>,
    DropdownItem: ({ children, onClick }: any) => (
        <button onClick={onClick}>{children}</button>
    ),
    DropdownDivider: () => <hr />,
}));

vi.mock('@/components/ui/Tabs', () => ({
    Tabs: ({ children, defaultValue }: any) => <div data-default-tab={defaultValue}>{children}</div>,
    TabsRoot: ({ children, defaultIndex }: any) => <div data-default-index={defaultIndex}>{children}</div>,
    TabsList: ({ children }: any) => <div>{children}</div>,
    TabsTrigger: ({ children, value }: any) => <button data-tab={value}>{children}</button>,
    TabsContent: ({ children, value }: any) => <div data-tab-content={value}>{children}</div>,
    TabsPanels: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Checkbox', () => ({
    Checkbox: ({ checked, onCheckedChange }: any) => (
        <input
            type="checkbox"
            checked={checked}
            onChange={(e) => onCheckedChange(e.target.checked)}
        />
    ),
}));

vi.mock('@/components/ui/Select', () => ({
    Select: ({ value, onChange, children }: any) => (
        <select value={value} onChange={onChange}>
            {children}
        </select>
    ),
}));

describe('Admin Dashboard', () => {
    it('renders admin dashboard with stats', () => {
        render(<AdminDashboard />);
        expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Total Users')).toBeInTheDocument();
        expect(screen.getByText('Servers')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
    });

    it('displays system health checks', () => {
        render(<AdminDashboard />);
        expect(screen.getByText('System Health')).toBeInTheDocument();
        expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
        expect(screen.getByText('Redis')).toBeInTheDocument();
        expect(screen.getByText('Soketi')).toBeInTheDocument();
    });

    it('displays recent activity', () => {
        render(<AdminDashboard />);
        expect(screen.getByText('Recent Activity')).toBeInTheDocument();
    });
});

describe('Admin Users', () => {
    it('renders user management page', () => {
        render(<AdminUsersIndex />);
        expect(screen.getByText('User Management')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search users by name or email...')).toBeInTheDocument();
    });

    it('filters users by status', async () => {
        render(<AdminUsersIndex />);

        const activeButton = screen.getByRole('button', { name: 'Active' });
        fireEvent.click(activeButton);

        await waitFor(() => {
            expect(screen.getByText('John Doe')).toBeInTheDocument();
        });
    });

    it('searches users by name', async () => {
        render(<AdminUsersIndex />);

        const searchInput = screen.getByPlaceholderText('Search users by name or email...');
        fireEvent.change(searchInput, { target: { value: 'john' } });

        await waitFor(() => {
            expect(screen.getByText('John Doe')).toBeInTheDocument();
        });
    });

    it('displays user details page', () => {
        render(<AdminUserShow />);
        expect(screen.getByText('John Doe')).toBeInTheDocument();
        // Email might appear multiple times, use getAllByText
        expect(screen.getAllByText('john.doe@example.com').length).toBeGreaterThan(0);
    });

    it('shows user tabs', () => {
        render(<AdminUserShow />);
        // Tab labels might appear multiple times, use getAllByText
        expect(screen.getAllByText(/Teams/).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Servers/).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Applications/).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Activity/).length).toBeGreaterThan(0);
    });
});

describe('Admin Servers', () => {
    it('renders server management page', () => {
        render(<AdminServersIndex />);
        expect(screen.getByText('Server Management')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search servers by name, IP, user, or team...')).toBeInTheDocument();
    });

    it('displays server stats', () => {
        render(<AdminServersIndex />);
        // Status labels might appear multiple times, use getAllByText
        expect(screen.getAllByText('Online').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Offline').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Errors').length).toBeGreaterThan(0);
    });

    it('filters servers by status', async () => {
        render(<AdminServersIndex />);

        const onlineButton = screen.getByRole('button', { name: 'Online' });
        fireEvent.click(onlineButton);

        await waitFor(() => {
            expect(screen.getByText('production-1')).toBeInTheDocument();
        });
    });
});

describe('Admin Teams', () => {
    it('renders team management page', () => {
        render(<AdminTeamsIndex />);
        expect(screen.getByText('Team Management')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search teams by name or owner...')).toBeInTheDocument();
    });

    it('displays team stats', () => {
        render(<AdminTeamsIndex />);
        expect(screen.getByText('Active Teams')).toBeInTheDocument();
        expect(screen.getByText('Trial Teams')).toBeInTheDocument();
        expect(screen.getByText('Monthly Revenue')).toBeInTheDocument();
    });

    it('filters teams by subscription status', async () => {
        render(<AdminTeamsIndex />);

        const activeButton = screen.getByRole('button', { name: 'Active' });
        fireEvent.click(activeButton);

        await waitFor(() => {
            expect(screen.getByText('Acme Corporation')).toBeInTheDocument();
        });
    });
});

describe('Admin Settings', () => {
    it('renders system settings page', () => {
        render(<AdminSettingsIndex />);
        expect(screen.getByText('System Settings')).toBeInTheDocument();
        expect(screen.getByText('General Settings')).toBeInTheDocument();
        expect(screen.getByText('Security Settings')).toBeInTheDocument();
    });

    it('updates site name', async () => {
        render(<AdminSettingsIndex />);

        const siteNameInput = screen.getAllByDisplayValue('Saturn Platform Cloud')[0];
        fireEvent.change(siteNameInput, { target: { value: 'New Site Name' } });

        await waitFor(() => {
            expect(siteNameInput).toHaveValue('New Site Name');
        });
    });

    it('toggles maintenance mode', async () => {
        render(<AdminSettingsIndex />);

        const checkboxes = screen.getAllByRole('checkbox');
        const maintenanceCheckbox = checkboxes.find(
            (cb) => cb.parentElement?.textContent?.includes('Maintenance Mode')
        );

        if (maintenanceCheckbox) {
            fireEvent.click(maintenanceCheckbox);

            await waitFor(() => {
                expect(maintenanceCheckbox).toBeChecked();
            });
        }
    });

    it('displays feature flags', () => {
        render(<AdminSettingsIndex />);
        expect(screen.getByText('Feature Flags')).toBeInTheDocument();
        expect(screen.getByText('OAuth Integration')).toBeInTheDocument();
        expect(screen.getByText('API Access')).toBeInTheDocument();
    });
});

describe('Admin Logs', () => {
    it('renders system logs page', () => {
        render(<AdminLogsIndex />);
        expect(screen.getByText('System Logs')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search logs by message, user, or IP address...')).toBeInTheDocument();
    });

    it('filters logs by level', async () => {
        render(<AdminLogsIndex />);

        const errorButton = screen.getByRole('button', { name: 'Error' });
        fireEvent.click(errorButton);

        await waitFor(() => {
            expect(screen.getByText(/Deployment failed/)).toBeInTheDocument();
        });
    });

    it('filters logs by category', async () => {
        render(<AdminLogsIndex />);

        const authButton = screen.getByRole('button', { name: 'Auth' });
        fireEvent.click(authButton);

        await waitFor(() => {
            expect(screen.getByText(/User login successful/)).toBeInTheDocument();
        });
    });

    it('searches logs', async () => {
        render(<AdminLogsIndex />);

        const searchInput = screen.getByPlaceholderText('Search logs by message, user, or IP address...');
        fireEvent.change(searchInput, { target: { value: 'deployment' } });

        await waitFor(() => {
            expect(screen.getByText(/Deployment failed/)).toBeInTheDocument();
        });
    });

    it('displays export button', () => {
        render(<AdminLogsIndex />);
        expect(screen.getByText('Export Logs')).toBeInTheDocument();
    });
});
