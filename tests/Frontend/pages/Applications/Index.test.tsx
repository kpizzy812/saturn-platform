import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        patch: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Mock hooks
vi.mock('@/hooks/useRealtimeStatus', () => ({
    useRealtimeStatus: vi.fn(() => ({})),
}));

// Import after mocks
import ApplicationsIndex from '@/pages/Applications/Index';
import type { Application } from '@/types';

const mockApplications: Application[] = [
    {
        id: 1,
        uuid: 'app-uuid-1',
        name: 'production-api',
        description: 'Main production API',
        fqdn: 'api.example.com',
        repository_project_id: null,
        git_repository: 'https://github.com/user/api',
        git_branch: 'main',
        build_pack: 'nixpacks',
        status: 'running',
        environment_id: 1,
        destination_id: 1,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'app-uuid-2',
        name: 'staging-frontend',
        description: 'Staging frontend app',
        fqdn: 'staging.example.com',
        repository_project_id: null,
        git_repository: 'https://github.com/user/frontend',
        git_branch: 'develop',
        build_pack: 'dockerfile',
        status: 'stopped',
        environment_id: 1,
        destination_id: 1,
        created_at: '2024-01-02T00:00:00Z',
        updated_at: '2024-01-14T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'app-uuid-3',
        name: 'dev-backend',
        description: null,
        fqdn: null,
        repository_project_id: null,
        git_repository: 'https://github.com/user/backend',
        git_branch: 'dev',
        build_pack: 'dockercompose',
        status: 'deploying',
        environment_id: 2,
        destination_id: 1,
        created_at: '2024-01-03T00:00:00Z',
        updated_at: '2024-01-13T00:00:00Z',
    },
];

describe('Applications Index Page', () => {
    it('renders the page header', () => {
        render(<ApplicationsIndex applications={[]} />);
        const heading = screen.getByRole('heading', { name: 'Applications' });
        expect(heading).toBeInTheDocument();
        expect(screen.getByText('Manage your deployed applications')).toBeInTheDocument();
    });

    it('shows New Application button', () => {
        render(<ApplicationsIndex applications={[]} />);
        const newAppButtons = screen.getAllByText('New Application');
        expect(newAppButtons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no applications exist', () => {
        render(<ApplicationsIndex applications={[]} />);
        expect(screen.getByText('No applications yet')).toBeInTheDocument();
        expect(screen.getByText('Deploy your first application from Git or Docker image.')).toBeInTheDocument();
        expect(screen.getByText('Create Application')).toBeInTheDocument();
    });

    it('shows application cards when applications exist', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.getByText('staging-frontend')).toBeInTheDocument();
        expect(screen.getByText('dev-backend')).toBeInTheDocument();
    });

    it('displays application status badges', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        // StatusBadge component renders the status - checking for multiple badges
        const statusBadges = document.querySelectorAll('[class*="rounded-full"]');
        expect(statusBadges.length).toBeGreaterThan(0);
        // Verify status text appears in the document (case-insensitive search)
        const bodyText = document.body.textContent || '';
        expect(bodyText.toLowerCase()).toContain('running');
        expect(bodyText.toLowerCase()).toContain('stopped');
        expect(bodyText.toLowerCase()).toContain('deploying');
    });

    it('shows application repository information', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('https://github.com/user/api')).toBeInTheDocument();
        expect(screen.getByText('https://github.com/user/frontend')).toBeInTheDocument();
    });

    it('shows application branches', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('main')).toBeInTheDocument();
        expect(screen.getByText('develop')).toBeInTheDocument();
        expect(screen.getByText('dev')).toBeInTheDocument();
    });

    it('shows application build packs', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('nixpacks')).toBeInTheDocument();
        expect(screen.getByText('dockerfile')).toBeInTheDocument();
        expect(screen.getByText('dockercompose')).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        const searchInput = screen.getByPlaceholderText('Search applications...');
        expect(searchInput).toBeInTheDocument();
    });

    it('filters applications by search query', () => {
        const { user } = render(<ApplicationsIndex applications={mockApplications} />);
        const searchInput = screen.getByPlaceholderText('Search applications...');

        fireEvent.change(searchInput, { target: { value: 'production' } });

        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.queryByText('staging-frontend')).not.toBeInTheDocument();
        expect(screen.queryByText('dev-backend')).not.toBeInTheDocument();
    });

    it('shows project and environment filters', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('All Projects')).toBeInTheDocument();
        expect(screen.getByText('All Status')).toBeInTheDocument();
    });

    it('shows domain links for applications with FQDN', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByText('api.example.com')).toBeInTheDocument();
        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
    });

    it('shows quick action dropdown for each application', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        const dropdownButtons = screen.getAllByRole('button');
        // Should have dropdowns for actions on each card
        expect(dropdownButtons.length).toBeGreaterThan(0);
    });

    it('links to application detail pages', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        const links = screen.getAllByRole('link');
        const appLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/applications/app-uuid-')
        );
        // Should have at least 3 application card links
        expect(appLinks.length).toBeGreaterThanOrEqual(3);
    });

    it('shows no results message when filter returns no results', () => {
        const { user } = render(<ApplicationsIndex applications={mockApplications} />);
        const searchInput = screen.getByPlaceholderText('Search applications...');

        fireEvent.change(searchInput, { target: { value: 'nonexistent-app' } });

        expect(screen.getByText('No applications found')).toBeInTheDocument();
        expect(screen.getByText('Try adjusting your filters or search query.')).toBeInTheDocument();
    });

    it('displays last updated date for applications', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        // Check for "Updated" text (part of the date display)
        const updatedTexts = screen.getAllByText(/Updated/);
        expect(updatedTexts.length).toBeGreaterThan(0);
    });

    it('optimistically removes application from list on delete', async () => {
        // Mock Dropdown to render items directly (HeadlessUI transitions don't work in jsdom)
        vi.mock('@/components/ui/Dropdown', () => ({
            Dropdown: ({ children }: any) => <div>{children}</div>,
            DropdownTrigger: ({ children }: any) => <div>{children}</div>,
            DropdownContent: ({ children }: any) => <div>{children}</div>,
            DropdownItem: ({ children, onClick, danger }: any) => (
                <button onClick={onClick} data-danger={danger}>{children}</button>
            ),
            DropdownDivider: () => <hr />,
            useDropdown: () => ({ isOpen: false }),
        }));

        const { router } = await import('@inertiajs/react');
        vi.mocked(router.delete).mockImplementation((_url: string, options: any) => {
            options.onSuccess?.();
        });

        const { user } = render(<ApplicationsIndex applications={mockApplications} />);

        // All 3 apps visible initially
        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.getByText('staging-frontend')).toBeInTheDocument();
        expect(screen.getByText('dev-backend')).toBeInTheDocument();

        // Click Delete button (now directly visible due to mocked Dropdown)
        const deleteButtons = screen.getAllByText('Delete');
        await user.click(deleteButtons[0]);

        // Confirm the deletion in the confirmation dialog
        const confirmBtn = await screen.findByRole('button', { name: /delete/i });
        await user.click(confirmBtn);

        // The app should be removed immediately (optimistic update)
        expect(screen.queryByText('production-api')).not.toBeInTheDocument();

        // Other apps should still be visible
        expect(screen.getByText('staging-frontend')).toBeInTheDocument();
        expect(screen.getByText('dev-backend')).toBeInTheDocument();

        // Verify router.delete was called with preserveState: true
        expect(router.delete).toHaveBeenCalledWith(
            '/applications/app-uuid-1',
            expect.objectContaining({ preserveState: true })
        );
    });
});
