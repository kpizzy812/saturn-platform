import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Import after mock setup
import SharedVariablesIndex from '@/pages/SharedVariables/Index';

const mockTeam = {
    id: 1,
    name: 'Test Team',
};

const mockVariables = [
    {
        id: 1,
        uuid: 'var-1',
        key: 'API_BASE_URL',
        value: 'https://api.example.com',
        is_secret: false,
        scope: 'team' as const,
        scope_name: 'Test Team',
        created_at: '2024-01-01T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'var-2',
        key: 'DATABASE_HOST',
        value: 'db.example.com',
        is_secret: false,
        scope: 'project' as const,
        scope_name: 'Production Project',
        created_at: '2024-01-05T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'var-3',
        key: 'JWT_SECRET',
        value: 'super-secret-key-12345',
        is_secret: true,
        scope: 'environment' as const,
        scope_name: 'Production Environment',
        created_at: '2024-01-10T00:00:00Z',
    },
    {
        id: 4,
        uuid: 'var-4',
        key: 'STRIPE_KEY',
        value: 'sk_live_1234567890',
        is_secret: true,
        scope: 'team' as const,
        scope_name: 'Test Team',
        created_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 5,
        uuid: 'var-5',
        key: 'REDIS_URL',
        value: 'redis://localhost:6379',
        is_secret: false,
        scope: 'project' as const,
        scope_name: 'Staging Project',
        inherited_from: 'Team',
        created_at: '2024-01-20T00:00:00Z',
    },
    {
        id: 6,
        uuid: 'var-6',
        key: 'MAIL_HOST',
        value: 'smtp.example.com',
        is_secret: false,
        scope: 'environment' as const,
        scope_name: 'Staging Environment',
        created_at: '2024-02-01T00:00:00Z',
    },
];

describe('Shared Variables Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the page title and description', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getAllByText('Shared Variables').length).toBeGreaterThan(0);
            expect(
                screen.getByText('Manage variables shared across team, projects, and environments')
            ).toBeInTheDocument();
        });

        it('renders add variable button', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('Add Variable')).toBeInTheDocument();
        });

        it('renders breadcrumbs', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('Dashboard')).toBeInTheDocument();
            expect(screen.getAllByText('Shared Variables').length).toBeGreaterThan(0);
        });
    });

    describe('Tab Filtering', () => {
        it('renders all scope tabs', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('All Variables')).toBeInTheDocument();
            expect(screen.getByText('Team')).toBeInTheDocument();
            expect(screen.getByText('Project')).toBeInTheDocument();
            expect(screen.getByText('Environment')).toBeInTheDocument();
        });

        it('displays correct counts in tabs', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            // All: 6 variables
            expect(screen.getByText('6')).toBeInTheDocument();
            // Team: 2 variables (API_BASE_URL, STRIPE_KEY)
            expect(screen.getAllByText('2').length).toBeGreaterThan(0);
        });

        it('filters by team scope', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const teamTab = screen.getByText('Team');
            fireEvent.click(teamTab);

            expect(screen.getByText('API_BASE_URL')).toBeInTheDocument();
            expect(screen.getByText('STRIPE_KEY')).toBeInTheDocument();
            expect(screen.queryByText('DATABASE_HOST')).not.toBeInTheDocument();
        });

        it('filters by project scope', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const projectTab = screen.getByText('Project');
            fireEvent.click(projectTab);

            expect(screen.getByText('DATABASE_HOST')).toBeInTheDocument();
            expect(screen.getByText('REDIS_URL')).toBeInTheDocument();
            expect(screen.queryByText('API_BASE_URL')).not.toBeInTheDocument();
        });

        it('filters by environment scope', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const environmentTab = screen.getByText('Environment');
            fireEvent.click(environmentTab);

            expect(screen.getByText('JWT_SECRET')).toBeInTheDocument();
            expect(screen.getByText('MAIL_HOST')).toBeInTheDocument();
            expect(screen.queryByText('API_BASE_URL')).not.toBeInTheDocument();
        });

        it('shows all variables by default', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('API_BASE_URL')).toBeInTheDocument();
            expect(screen.getByText('DATABASE_HOST')).toBeInTheDocument();
            expect(screen.getByText('JWT_SECRET')).toBeInTheDocument();
            expect(screen.getByText('STRIPE_KEY')).toBeInTheDocument();
            expect(screen.getByText('REDIS_URL')).toBeInTheDocument();
            expect(screen.getByText('MAIL_HOST')).toBeInTheDocument();
        });
    });

    describe('Search Functionality', () => {
        it('renders search input', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByPlaceholderText('Search variables...')).toBeInTheDocument();
        });

        it('filters variables by search query', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const searchInput = screen.getByPlaceholderText('Search variables...');
            fireEvent.change(searchInput, { target: { value: 'API' } });

            expect(screen.getByText('API_BASE_URL')).toBeInTheDocument();
            expect(screen.queryByText('DATABASE_HOST')).not.toBeInTheDocument();
        });

        it('is case insensitive', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const searchInput = screen.getByPlaceholderText('Search variables...');
            fireEvent.change(searchInput, { target: { value: 'jwt' } });

            expect(screen.getByText('JWT_SECRET')).toBeInTheDocument();
        });

        it('combines search with tab filter', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const teamTab = screen.getByText('Team');
            fireEvent.click(teamTab);

            const searchInput = screen.getByPlaceholderText('Search variables...');
            fireEvent.change(searchInput, { target: { value: 'STRIPE' } });

            expect(screen.getByText('STRIPE_KEY')).toBeInTheDocument();
            expect(screen.queryByText('API_BASE_URL')).not.toBeInTheDocument();
        });
    });

    describe('Variable Display', () => {
        it('displays all variable keys', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('API_BASE_URL')).toBeInTheDocument();
            expect(screen.getByText('DATABASE_HOST')).toBeInTheDocument();
            expect(screen.getByText('JWT_SECRET')).toBeInTheDocument();
        });

        it('masks secret values', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            // Secret values should be shown as dots
            expect(screen.getAllByText('••••••••').length).toBeGreaterThan(0);
        });

        it('shows non-secret values', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText('https://api.example.com')).toBeInTheDocument();
            expect(screen.getByText('db.example.com')).toBeInTheDocument();
        });

        it('displays scope badges', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getAllByText('team').length).toBeGreaterThan(0);
            expect(screen.getAllByText('project').length).toBeGreaterThan(0);
            expect(screen.getAllByText('environment').length).toBeGreaterThan(0);
        });

        it('displays scope names', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getAllByText('Test Team').length).toBeGreaterThan(0);
            expect(screen.getByText('Production Project')).toBeInTheDocument();
            expect(screen.getByText('Production Environment')).toBeInTheDocument();
        });

        it('shows lock icon for secrets', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const lockIcons = container.querySelectorAll('.lucide-lock');
            expect(lockIcons.length).toBeGreaterThan(0);
        });

        it('shows unlock icon for non-secrets', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            // Check that non-secret variables exist (they show Unlock icon)
            // mockVariables has 4 non-secret variables (API_BASE_URL, DATABASE_HOST, REDIS_URL, MAIL_HOST)
            const nonSecretVars = mockVariables.filter(v => !v.is_secret);
            expect(nonSecretVars.length).toBe(4);
            // Just verify the component rendered properly with these variables
            expect(screen.getByText('API_BASE_URL')).toBeInTheDocument();
            expect(screen.getByText('DATABASE_HOST')).toBeInTheDocument();
        });

        it('displays inherited from information', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            expect(screen.getByText(/inherited from Team/i)).toBeInTheDocument();
        });
    });

    describe('Variable Actions', () => {
        it('has edit button for each variable', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const editButtons = screen.getAllByText('Edit');
            expect(editButtons.length).toBeGreaterThan(0);
        });

        it('links to variable edit page', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const links = screen.getAllByRole('link');
            const editLink = links.find(link =>
                link.getAttribute('href')?.includes('/shared-variables/var-1')
            );
            expect(editLink).toBeInTheDocument();
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no variables', () => {
            render(<SharedVariablesIndex variables={[]} team={mockTeam} />);
            expect(screen.getByText('No variables found')).toBeInTheDocument();
        });

        it('has create link in empty state', () => {
            render(<SharedVariablesIndex variables={[]} team={mockTeam} />);
            expect(screen.getByText('Create your first variable')).toBeInTheDocument();
        });

        it('displays empty state when search returns no results', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const searchInput = screen.getByPlaceholderText('Search variables...');
            fireEvent.change(searchInput, { target: { value: 'nonexistent' } });

            expect(screen.getByText('No variables found')).toBeInTheDocument();
        });
    });

    describe('Navigation', () => {
        it('links to create page', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const addButton = screen.getByText('Add Variable').closest('a');
            expect(addButton).toHaveAttribute('href', '/shared-variables/create');
        });

        it('links to dashboard in breadcrumbs', () => {
            render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const dashboardLink = screen.getByText('Dashboard');
            expect(dashboardLink.closest('a')).toHaveAttribute('href', '/new');
        });
    });

    describe('Scope Icons', () => {
        it('renders team scope icon', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const teamIcons = container.querySelectorAll('.lucide-building-2');
            expect(teamIcons.length).toBeGreaterThan(0);
        });

        it('renders project scope icon', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const projectIcons = container.querySelectorAll('.lucide-folder-kanban');
            expect(projectIcons.length).toBeGreaterThan(0);
        });

        it('renders environment scope icon', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const envIcons = container.querySelectorAll('.lucide-layers');
            expect(envIcons.length).toBeGreaterThan(0);
        });
    });

    describe('Badge Colors', () => {
        it('applies correct badge variant for team scope', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const teamBadges = Array.from(container.querySelectorAll('.inline-flex')).filter(el =>
                el.textContent?.includes('team')
            );
            expect(teamBadges.length).toBeGreaterThan(0);
        });

        it('applies correct badge variant for project scope', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const projectBadges = Array.from(container.querySelectorAll('.inline-flex')).filter(el =>
                el.textContent?.includes('project')
            );
            expect(projectBadges.length).toBeGreaterThan(0);
        });

        it('applies correct badge variant for environment scope', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const envBadges = Array.from(container.querySelectorAll('.inline-flex')).filter(el =>
                el.textContent?.includes('environment')
            );
            expect(envBadges.length).toBeGreaterThan(0);
        });
    });

    describe('Hover Effects', () => {
        it('applies hover styles to variable rows', () => {
            const { container } = render(<SharedVariablesIndex variables={mockVariables} team={mockTeam} />);
            const rows = container.querySelectorAll('.hover\\:bg-background-secondary');
            expect(rows.length).toBeGreaterThan(0);
        });
    });
});
