import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import Dashboard from '../../Dashboard';
import ProjectsIndex from '../Index';
import ApplicationsIndex from '../../Applications/Index';
import DatabasesIndex from '../../Databases/Index';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
    router: { delete: vi.fn(), post: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock useRealtimeStatus
vi.mock('@/hooks/useRealtimeStatus', () => ({
    useRealtimeStatus: () => ({ isConnected: false }),
}));

// Mock usePermissions
vi.mock('@/hooks/usePermissions', () => ({
    usePermissions: () => ({ can: () => true }),
}));

// Mock animation components
vi.mock('@/components/animation', () => ({
    FadeIn: ({ children }: any) => <div>{children}</div>,
    StaggerList: ({ children, ...props }: any) => <div {...props}>{children}</div>,
    StaggerItem: ({ children }: any) => <div>{children}</div>,
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Badge: ({ children, ...props }: any) => <span data-testid="badge" {...props}>{children}</span>,
    Button: ({ children, ...props }: any) => <button {...props}>{children}</button>,
    useConfirm: () => vi.fn(),
    useToast: () => ({ toast: vi.fn() }),
    Input: (props: any) => <input {...props} />,
    Select: ({ children, ...props }: any) => <select {...props}>{children}</select>,
    StatusBadge: ({ status }: any) => <span>{status}</span>,
}));

// Mock Dropdown components
vi.mock('@/components/ui/Dropdown', () => ({
    Dropdown: ({ children }: any) => <div>{children}</div>,
    DropdownTrigger: ({ children }: any) => <div>{children}</div>,
    DropdownContent: ({ children }: any) => <div>{children}</div>,
    DropdownItem: ({ children }: any) => <div>{children}</div>,
    DropdownDivider: () => <hr />,
}));

// Mock DatabaseCard
vi.mock('@/components/features/DatabaseCard', () => ({
    DatabaseCard: ({ database }: any) => <div data-testid={`db-card-${database.uuid}`}>{database.name}</div>,
}));

// Shared test data
const mockProject = {
    id: 1,
    uuid: 'proj-uuid-1',
    name: 'My Project',
    environments: [
        {
            id: 1,
            uuid: 'env-1',
            name: 'production',
            applications: [{ id: 1 }],
            databases: [{ id: 1 }],
            services: [],
        },
        {
            id: 2,
            uuid: 'env-2',
            name: 'staging',
            applications: [],
            databases: [],
            services: [],
        },
    ],
    updated_at: '2026-02-24T12:00:00Z',
    team_id: 1,
    description: null,
    created_at: '2026-02-24T12:00:00Z',
};

const mockApplications = [
    {
        id: 1,
        uuid: 'app-uuid-1',
        name: 'My App',
        description: null,
        fqdn: null,
        git_repository: 'https://github.com/example/app',
        git_branch: 'main',
        build_pack: 'nixpacks' as const,
        status: { state: 'running', health: 'healthy' },
        project_name: 'My Project',
        environment_name: 'production',
        environment_type: 'production' as any,
        created_at: '2026-02-24T12:00:00Z',
        updated_at: '2026-02-24T12:00:00Z',
    },
    {
        id: 2,
        uuid: 'app-uuid-2',
        name: 'Other App',
        description: null,
        fqdn: null,
        git_repository: null,
        git_branch: 'dev',
        build_pack: 'dockerfile' as const,
        status: { state: 'stopped', health: 'unknown' },
        project_name: 'Other Project',
        environment_name: 'staging',
        environment_type: 'uat' as any,
        created_at: '2026-02-24T12:00:00Z',
        updated_at: '2026-02-24T12:00:00Z',
    },
];

const mockDatabases = [
    {
        id: 1,
        uuid: 'db-uuid-1',
        name: 'My Postgres',
        description: null,
        database_type: 'postgresql' as any,
        status: { state: 'running', health: 'healthy' },
        environment_id: 1,
        project_name: 'My Project',
        environment_name: 'production',
        environment_type: 'production' as any,
    },
    {
        id: 2,
        uuid: 'db-uuid-2',
        name: 'Other Redis',
        description: null,
        database_type: 'redis' as any,
        status: { state: 'stopped', health: 'unknown' },
        environment_id: 2,
        project_name: 'Other Project',
        environment_name: 'staging',
        environment_type: 'uat' as any,
    },
];

// Reset URL before each test
beforeEach(() => {
    Object.defineProperty(window, 'location', {
        value: { search: '', href: 'http://localhost/' },
        writable: true,
    });
});

// ============================================================
// Dashboard tests
// ============================================================
describe('Dashboard - interactive navigation', () => {
    it('renders project cards with environment badges as links', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        // Both environment badges should be rendered
        expect(screen.getByText('production')).toBeInTheDocument();
        expect(screen.getByText('staging')).toBeInTheDocument();
    });

    it('environment badges link to /projects/{uuid}?env={envName}', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        const productionLink = screen.getByText('production').closest('a');
        expect(productionLink).not.toBeNull();
        expect(productionLink?.getAttribute('href')).toBe(
            `/projects/${mockProject.uuid}?env=production`
        );

        const stagingLink = screen.getByText('staging').closest('a');
        expect(stagingLink).not.toBeNull();
        expect(stagingLink?.getAttribute('href')).toBe(
            `/projects/${mockProject.uuid}?env=staging`
        );
    });

    it('apps resource count links to /applications?project={projectName}', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        // The project has 1 app, so "1 app" should appear as a link
        const appsText = screen.getByText('1 app');
        const appsLink = appsText.closest('a');
        expect(appsLink).not.toBeNull();
        expect(appsLink?.getAttribute('href')).toBe(
            `/applications?project=${encodeURIComponent(mockProject.name)}`
        );
    });

    it('dbs resource count links to /databases?project={projectName}', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        // The project has 1 db, so "1 db" should appear as a link
        const dbsText = screen.getByText('1 db');
        const dbsLink = dbsText.closest('a');
        expect(dbsLink).not.toBeNull();
        expect(dbsLink?.getAttribute('href')).toBe(
            `/databases?project=${encodeURIComponent(mockProject.name)}`
        );
    });

    it('services resource count does not have its own dedicated link element', () => {
        const projectWithServices = {
            ...mockProject,
            environments: [
                {
                    ...mockProject.environments[0],
                    services: [{ id: 1 }],
                    applications: [],
                    databases: [],
                },
            ],
        };

        render(<Dashboard projects={[projectWithServices as any]} />);

        // Services count appears but the span itself should NOT have an <a> as immediate parent
        // (unlike apps/dbs which are wrapped in their own dedicated <Link> elements)
        const svcsText = screen.getByText('1 svc');
        // The immediate parent of the text node's element should be a <span> inside a <div>, not an <a>
        expect(svcsText.tagName.toLowerCase()).toBe('span');
        expect(svcsText.parentElement?.tagName.toLowerCase()).not.toBe('a');
    });

    it('shows empty state when no projects are provided', () => {
        render(<Dashboard projects={[]} />);
        expect(screen.getByText('No projects yet')).toBeInTheDocument();
    });

    it('renders main project card link pointing to /projects/{uuid}', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        const links = screen.getAllByRole('link');
        const projectLinks = links.filter(l =>
            l.getAttribute('href') === `/projects/${mockProject.uuid}`
        );
        // The main card is a link to the project
        expect(projectLinks.length).toBeGreaterThanOrEqual(1);
    });

    it('renders New Project card as link to /projects/create', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        const links = screen.getAllByRole('link');
        const createLinks = links.filter(l => l.getAttribute('href') === '/projects/create');
        expect(createLinks.length).toBeGreaterThanOrEqual(1);
    });

    it('shows project resource summary in card header', () => {
        render(<Dashboard projects={[mockProject as any]} />);

        // The project has 2 total resources (1 app + 1 db)
        expect(screen.getByText('2 resources')).toBeInTheDocument();
    });

    it('handles multiple environment badges up to 4', () => {
        const projectWith5Envs = {
            ...mockProject,
            environments: [
                { id: 1, uuid: 'e1', name: 'production', applications: [], databases: [], services: [] },
                { id: 2, uuid: 'e2', name: 'staging', applications: [], databases: [], services: [] },
                { id: 3, uuid: 'e3', name: 'dev', applications: [], databases: [], services: [] },
                { id: 4, uuid: 'e4', name: 'uat', applications: [], databases: [], services: [] },
                { id: 5, uuid: 'e5', name: 'qa', applications: [], databases: [], services: [] },
            ],
        };

        render(<Dashboard projects={[projectWith5Envs as any]} />);

        // Only first 4 environments should be shown as badge links
        expect(screen.getByText('production')).toBeInTheDocument();
        expect(screen.getByText('staging')).toBeInTheDocument();
        expect(screen.getByText('dev')).toBeInTheDocument();
        expect(screen.getByText('uat')).toBeInTheDocument();
        // 5th env should be replaced by +1 overflow badge
        expect(screen.queryByText('qa')).not.toBeInTheDocument();
        expect(screen.getByText('+1')).toBeInTheDocument();
    });
});

// ============================================================
// Projects/Index tests
// ============================================================
describe('Projects/Index - interactive navigation', () => {
    it('renders environment badges as links pointing to /projects/{uuid}?env={envName}', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);

        const productionLink = screen.getByText('production').closest('a');
        expect(productionLink).not.toBeNull();
        expect(productionLink?.getAttribute('href')).toBe(
            `/projects/${mockProject.uuid}?env=production`
        );

        const stagingLink = screen.getByText('staging').closest('a');
        expect(stagingLink).not.toBeNull();
        expect(stagingLink?.getAttribute('href')).toBe(
            `/projects/${mockProject.uuid}?env=staging`
        );
    });

    it('apps resource count links to /applications?project={projectName}', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);

        const appsText = screen.getByText('1 app');
        const appsLink = appsText.closest('a');
        expect(appsLink).not.toBeNull();
        expect(appsLink?.getAttribute('href')).toBe(
            `/applications?project=${encodeURIComponent(mockProject.name)}`
        );
    });

    it('dbs resource count links to /databases?project={projectName}', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);

        const dbsText = screen.getByText('1 db');
        const dbsLink = dbsText.closest('a');
        expect(dbsLink).not.toBeNull();
        expect(dbsLink?.getAttribute('href')).toBe(
            `/databases?project=${encodeURIComponent(mockProject.name)}`
        );
    });

    it('services count does not have its own dedicated link element', () => {
        const projectWithServices = {
            ...mockProject,
            environments: [
                {
                    ...mockProject.environments[0],
                    services: [{ id: 10 }, { id: 11 }, { id: 12 }],
                    applications: [],
                    databases: [],
                },
            ],
        };

        render(<ProjectsIndex projects={[projectWithServices as any]} />);

        // Services count text should be in a <span> whose immediate parent is NOT an <a>
        const svcsText = screen.getByText('3 svcs');
        expect(svcsText.tagName.toLowerCase()).toBe('span');
        expect(svcsText.parentElement?.tagName.toLowerCase()).not.toBe('a');
    });

    it('shows empty state with create project button when no projects', () => {
        render(<ProjectsIndex projects={[]} />);
        expect(screen.getByText('No projects yet')).toBeInTheDocument();
        const createLink = screen.getAllByRole('link').find(l => l.getAttribute('href') === '/projects/create');
        expect(createLink).toBeDefined();
    });

    it('main card is a link to the project page', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);

        const links = screen.getAllByRole('link');
        const projectCardLinks = links.filter(l =>
            l.getAttribute('href') === `/projects/${mockProject.uuid}`
        );
        expect(projectCardLinks.length).toBeGreaterThanOrEqual(1);
    });

    it('renders project name in card', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);
        expect(screen.getByText('My Project')).toBeInTheDocument();
    });

    it('shows resource count summary in card subtitle', () => {
        render(<ProjectsIndex projects={[mockProject as any]} />);
        // 1 app + 1 db = 2 total resources
        expect(screen.getByText('2 resources')).toBeInTheDocument();
    });

    it('displays overflow badge when more than 4 environments', () => {
        const projectWithManyEnvs = {
            ...mockProject,
            environments: [
                { id: 1, uuid: 'e1', name: 'production', applications: [], databases: [], services: [] },
                { id: 2, uuid: 'e2', name: 'staging', applications: [], databases: [], services: [] },
                { id: 3, uuid: 'e3', name: 'dev', applications: [], databases: [], services: [] },
                { id: 4, uuid: 'e4', name: 'uat', applications: [], databases: [], services: [] },
                { id: 5, uuid: 'e5', name: 'hotfix', applications: [], databases: [], services: [] },
                { id: 6, uuid: 'e6', name: 'canary', applications: [], databases: [], services: [] },
            ],
        };

        render(<ProjectsIndex projects={[projectWithManyEnvs as any]} />);

        // Only 4 rendered as links, 2 collapsed into +2 badge
        expect(screen.getByText('+2')).toBeInTheDocument();
        expect(screen.queryByText('hotfix')).not.toBeInTheDocument();
        expect(screen.queryByText('canary')).not.toBeInTheDocument();
    });

    it('renders multiple project cards when multiple projects are provided', () => {
        const secondProject = {
            ...mockProject,
            id: 2,
            uuid: 'proj-uuid-2',
            name: 'Second Project',
            environments: [],
        };

        render(<ProjectsIndex projects={[mockProject as any, secondProject as any]} />);

        expect(screen.getByText('My Project')).toBeInTheDocument();
        expect(screen.getByText('Second Project')).toBeInTheDocument();
    });
});

// ============================================================
// Applications/Index tests
// ============================================================
describe('Applications/Index - query param pre-selection', () => {
    it('initializes filterProject to "all" when no ?project= param is present', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        const selects = screen.getAllByRole('combobox');
        // First select is the project filter
        const projectSelect = selects[0] as HTMLSelectElement;
        expect(projectSelect.value).toBe('all');
    });

    it('pre-selects project filter from ?project= query param', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=My%20Project', href: 'http://localhost/?project=My%20Project' },
            writable: true,
        });

        render(<ApplicationsIndex applications={mockApplications} />);

        const selects = screen.getAllByRole('combobox');
        const projectSelect = selects[0] as HTMLSelectElement;
        expect(projectSelect.value).toBe('My Project');
    });

    it('shows only matching applications when project filter is pre-selected via URL', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=My%20Project', href: 'http://localhost/?project=My%20Project' },
            writable: true,
        });

        render(<ApplicationsIndex applications={mockApplications} />);

        // "My App" belongs to "My Project", should be visible
        expect(screen.getByText('My App')).toBeInTheDocument();
        // "Other App" belongs to "Other Project", should be hidden
        expect(screen.queryByText('Other App')).not.toBeInTheDocument();
    });

    it('shows all applications when no project filter is set', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        expect(screen.getByText('My App')).toBeInTheDocument();
        expect(screen.getByText('Other App')).toBeInTheDocument();
    });

    it('renders project options in the filter select', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        expect(screen.getByText('All Projects')).toBeInTheDocument();
        // "My Project" and "Other Project" may appear in both option elements and card subtitles
        const myProjectOptions = screen.getAllByText('My Project').filter(
            el => el.tagName.toLowerCase() === 'option'
        );
        expect(myProjectOptions.length).toBeGreaterThanOrEqual(1);
        const otherProjectOptions = screen.getAllByText('Other Project').filter(
            el => el.tagName.toLowerCase() === 'option'
        );
        expect(otherProjectOptions.length).toBeGreaterThanOrEqual(1);
    });

    it('renders environment filter select with options derived from applications', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        expect(screen.getByText('All Environments')).toBeInTheDocument();
        // environment names appear both in <option> and in badge <span>, use getAllByText
        const productionOptions = screen.getAllByText('production').filter(
            el => el.tagName.toLowerCase() === 'option'
        );
        expect(productionOptions.length).toBeGreaterThanOrEqual(1);
        const stagingOptions = screen.getAllByText('staging').filter(
            el => el.tagName.toLowerCase() === 'option'
        );
        expect(stagingOptions.length).toBeGreaterThanOrEqual(1);
    });

    it('renders status filter select with all status options', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        expect(screen.getByText('All Status')).toBeInTheDocument();
        expect(screen.getByText('Running')).toBeInTheDocument();
        expect(screen.getByText('Stopped')).toBeInTheDocument();
        expect(screen.getByText('Deploying')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });

    it('shows empty state with create button when applications list is empty', () => {
        render(<ApplicationsIndex applications={[]} />);
        expect(screen.getByText('No applications yet')).toBeInTheDocument();
        const createLink = screen.getAllByRole('link').find(l =>
            l.getAttribute('href') === '/applications/create'
        );
        expect(createLink).toBeDefined();
    });

    it('shows no-results state when filter matches nothing', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=NonExistent', href: 'http://localhost/?project=NonExistent' },
            writable: true,
        });

        render(<ApplicationsIndex applications={mockApplications} />);

        expect(screen.getByText('No applications found')).toBeInTheDocument();
    });

    it('renders search input for filtering by name', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        expect(screen.getByPlaceholderText('Search applications...')).toBeInTheDocument();
    });

    it('renders New Application button link to /applications/create', () => {
        render(<ApplicationsIndex applications={mockApplications} />);
        const createLink = screen.getAllByRole('link').find(l =>
            l.getAttribute('href') === '/applications/create'
        );
        expect(createLink).toBeDefined();
    });

    it('each application card links to its detail page', () => {
        render(<ApplicationsIndex applications={mockApplications} />);

        const appLink = screen.getAllByRole('link').find(l =>
            l.getAttribute('href') === `/applications/${mockApplications[0].uuid}`
        );
        expect(appLink).toBeDefined();
    });
});

// ============================================================
// Databases/Index tests
// ============================================================
describe('Databases/Index - query param pre-selection', () => {
    it('initializes filterProject to "all" when no ?project= param is present', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        const selects = screen.getAllByRole('combobox');
        const projectSelect = selects[0] as HTMLSelectElement;
        expect(projectSelect.value).toBe('all');
    });

    it('pre-selects project filter from ?project= query param', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=My%20Project', href: 'http://localhost/?project=My%20Project' },
            writable: true,
        });

        render(<DatabasesIndex databases={mockDatabases as any} />);

        const selects = screen.getAllByRole('combobox');
        const projectSelect = selects[0] as HTMLSelectElement;
        expect(projectSelect.value).toBe('My Project');
    });

    it('shows only matching databases when project filter is pre-selected via URL', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=My%20Project', href: 'http://localhost/?project=My%20Project' },
            writable: true,
        });

        render(<DatabasesIndex databases={mockDatabases as any} />);

        // "My Postgres" belongs to "My Project" — DatabaseCard renders it with the name
        expect(screen.getByText('My Postgres')).toBeInTheDocument();
        // "Other Redis" belongs to "Other Project" — should be hidden
        expect(screen.queryByText('Other Redis')).not.toBeInTheDocument();
    });

    it('shows all databases when no project filter is set', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByText('My Postgres')).toBeInTheDocument();
        expect(screen.getByText('Other Redis')).toBeInTheDocument();
    });

    it('renders filter UI (search, project, type, status) when databases exist', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByPlaceholderText('Search databases...')).toBeInTheDocument();
        expect(screen.getByText('All Projects')).toBeInTheDocument();
        expect(screen.getByText('All Types')).toBeInTheDocument();
        expect(screen.getByText('All Status')).toBeInTheDocument();
    });

    it('does NOT render filter UI when databases list is empty', () => {
        render(<DatabasesIndex databases={[]} />);

        expect(screen.queryByPlaceholderText('Search databases...')).not.toBeInTheDocument();
        expect(screen.queryByText('All Projects')).not.toBeInTheDocument();
    });

    it('renders project options in the filter select', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByText('My Project')).toBeInTheDocument();
        expect(screen.getByText('Other Project')).toBeInTheDocument();
    });

    it('renders database type options formatted nicely', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
        expect(screen.getByText('Redis')).toBeInTheDocument();
    });

    it('renders status filter options', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByText('Running')).toBeInTheDocument();
        expect(screen.getByText('Stopped')).toBeInTheDocument();
        expect(screen.getByText('Exited')).toBeInTheDocument();
    });

    it('shows empty state when databases list is empty', () => {
        render(<DatabasesIndex databases={[]} />);

        expect(screen.getByText('No databases yet')).toBeInTheDocument();
        const createLink = screen.getAllByRole('link').find(l =>
            l.getAttribute('href') === '/databases/create'
        );
        expect(createLink).toBeDefined();
    });

    it('shows no-results state when filter matches nothing', () => {
        Object.defineProperty(window, 'location', {
            value: { search: '?project=NonExistent', href: 'http://localhost/?project=NonExistent' },
            writable: true,
        });

        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByText('No databases found')).toBeInTheDocument();
    });

    it('renders DatabaseCard for each filtered database', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        expect(screen.getByTestId(`db-card-${mockDatabases[0].uuid}`)).toBeInTheDocument();
        expect(screen.getByTestId(`db-card-${mockDatabases[1].uuid}`)).toBeInTheDocument();
    });

    it('renders New Database button link to /databases/create', () => {
        render(<DatabasesIndex databases={mockDatabases as any} />);

        const createLink = screen.getAllByRole('link').find(l =>
            l.getAttribute('href') === '/databases/create'
        );
        expect(createLink).toBeDefined();
    });

    it('URL-encoded project names with spaces are decoded correctly', () => {
        Object.defineProperty(window, 'location', {
            value: {
                search: '?project=My+Project',
                href: 'http://localhost/?project=My+Project',
            },
            writable: true,
        });

        render(<DatabasesIndex databases={mockDatabases as any} />);

        // URLSearchParams decodes + as space, so "My Project" should match
        const selects = screen.getAllByRole('combobox');
        const projectSelect = selects[0] as HTMLSelectElement;
        expect(projectSelect.value).toBe('My Project');
    });
});
