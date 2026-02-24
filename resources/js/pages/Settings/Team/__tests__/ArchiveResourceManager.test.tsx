import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ArchiveResourceManager from '../ArchiveResourceManager';
import type { FullResource, EnvironmentOption } from '../ArchiveResourceManager';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: {
        reload: vi.fn(),
    },
}));

vi.mock('@/components/ui/Card', () => ({
    Card: ({ children }: any) => <div data-testid="card">{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h3>{children}</h3>,
    CardDescription: ({ children }: any) => <p>{children}</p>,
    CardContent: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Button', () => ({
    Button: ({ children, onClick, disabled, loading, variant, size }: any) => (
        <button onClick={onClick} disabled={disabled || loading} data-variant={variant} data-size={size}>
            {children}
        </button>
    ),
}));

vi.mock('@/components/ui/Badge', () => ({
    Badge: ({ children, variant, size, dot }: any) => (
        <span data-variant={variant} data-size={size} data-dot={dot}>{children}</span>
    ),
}));

vi.mock('@/components/ui/Select', () => ({
    Select: ({ children, value, onChange, options, className }: any) => (
        <select value={value} onChange={onChange} className={className}>
            {options ? options.map((opt: any) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
            )) : children}
        </select>
    ),
}));

vi.mock('@/components/ui/ConfirmationModal', () => ({
    useConfirmation: (_opts: any) => ({
        open: vi.fn(),
        ConfirmationDialog: () => null,
    }),
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: vi.fn(),
    }),
}));

vi.mock('lucide-react', () => ({
    ExternalLink: () => <span data-testid="icon-external-link" />,
    Trash2: () => <span data-testid="icon-trash" />,
    ArrowRightLeft: () => <span data-testid="icon-move" />,
    Search: () => <span data-testid="icon-search" />,
    Package: () => <span data-testid="icon-package" />,
    ArrowUpDown: () => <span data-testid="icon-arrow-up-down" />,
    Server: () => <span data-testid="icon-server" />,
    ChevronDown: () => <span data-testid="icon-chevron-down" />,
}));

const makeResource = (overrides: Partial<FullResource> = {}): FullResource => ({
    id: 1,
    uuid: 'abc-123',
    type: 'App',
    full_type: 'App\\Models\\Application',
    name: 'my-frontend',
    status: 'running',
    project_name: 'ProjectA',
    project_id: 10,
    environment_name: 'production',
    environment_id: 100,
    server_name: null,
    action_count: 5,
    url: '/applications/abc-123',
    interaction: 'created',
    ...overrides,
});

const makeEnvironment = (overrides: Partial<EnvironmentOption> = {}): EnvironmentOption => ({
    id: 100,
    name: 'production',
    project_name: 'ProjectA',
    project_id: 10,
    ...overrides,
});

describe('ArchiveResourceManager', () => {
    const defaultResources: FullResource[] = [
        makeResource({ id: 1, uuid: 'abc-1', name: 'my-frontend', type: 'App', status: 'running', project_name: 'ProjectA', project_id: 10, url: '/applications/abc-1' }),
        makeResource({ id: 2, uuid: 'abc-2', name: 'main-db', type: 'PostgreSQL', full_type: 'App\\Models\\StandalonePostgresql', status: 'running', project_name: 'ProjectA', project_id: 10, url: '/databases/abc-2' }),
        makeResource({ id: 3, uuid: 'abc-3', name: 'api-service', type: 'App', status: 'stopped', project_name: 'ProjectB', project_id: 20, url: '/applications/abc-3' }),
        makeResource({ id: 4, uuid: 'abc-4', name: 'cache', type: 'Redis', full_type: 'App\\Models\\StandaloneRedis', status: 'running', project_name: 'ProjectA', project_id: 10, url: '/databases/abc-4' }),
    ];

    const defaultEnvironments: EnvironmentOption[] = [
        makeEnvironment({ id: 100, name: 'production', project_name: 'ProjectA', project_id: 10 }),
        makeEnvironment({ id: 101, name: 'staging', project_name: 'ProjectA', project_id: 10 }),
        makeEnvironment({ id: 200, name: 'dev', project_name: 'ProjectB', project_id: 20 }),
    ];

    it('renders resource list with correct names', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getByText('my-frontend')).toBeInTheDocument();
        expect(screen.getByText('main-db')).toBeInTheDocument();
        expect(screen.getByText('api-service')).toBeInTheDocument();
        expect(screen.getByText('cache')).toBeInTheDocument();
    });

    it('renders type badges', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // Type labels appear in both badges and filter dropdown
        expect(screen.getAllByText('App').length).toBeGreaterThanOrEqual(2);
        expect(screen.getAllByText('PostgreSQL').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Redis').length).toBeGreaterThanOrEqual(1);
    });

    it('renders status badges', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // Status labels appear in both badges and filter dropdown
        expect(screen.getAllByText('running').length).toBeGreaterThanOrEqual(3);
        expect(screen.getAllByText('stopped').length).toBeGreaterThanOrEqual(1);
    });

    it('renders resource count', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getByText('4 resources associated with this member')).toBeInTheDocument();
    });

    it('resource names are clickable links', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        const link = screen.getByText('my-frontend').closest('a');
        expect(link).toHaveAttribute('href', '/applications/abc-1');
    });

    it('renders nothing when resources array is empty', () => {
        const { container } = render(
            <ArchiveResourceManager
                archiveId={1}
                resources={[]}
                environments={defaultEnvironments}
            />
        );

        expect(container.innerHTML).toBe('');
    });

    it('filters by type when type filter is changed', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // Find the type filter select (first select)
        const selects = screen.getAllByRole('combobox');
        const typeSelect = selects[0];

        // Change to PostgreSQL
        fireEvent.change(typeSelect, { target: { value: 'PostgreSQL' } });

        expect(screen.getByText('main-db')).toBeInTheDocument();
        expect(screen.queryByText('my-frontend')).not.toBeInTheDocument();
        expect(screen.queryByText('api-service')).not.toBeInTheDocument();
    });

    it('filters by search query', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        const searchInput = screen.getByPlaceholderText('Search by name...');
        fireEvent.change(searchInput, { target: { value: 'api' } });

        expect(screen.getByText('api-service')).toBeInTheDocument();
        expect(screen.queryByText('my-frontend')).not.toBeInTheDocument();
        expect(screen.queryByText('main-db')).not.toBeInTheDocument();
    });

    it('shows project/environment info for each resource', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getAllByText('ProjectA / production').length).toBeGreaterThanOrEqual(1);
    });

    it('shows action count for each resource', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getAllByText('5 actions').length).toBe(4);
    });

    it('checkbox selection toggles individual resources', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // Get all checkboxes (first is select-all, then one per resource)
        const checkboxes = screen.getAllByRole('checkbox');
        expect(checkboxes.length).toBe(5); // 1 select-all + 4 resources

        // Click first resource checkbox
        fireEvent.click(checkboxes[1]);

        // Bulk toolbar should appear
        expect(screen.getByText('1 selected')).toBeInTheDocument();
    });

    it('select all checkbox selects all filtered resources', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        const checkboxes = screen.getAllByRole('checkbox');
        const selectAll = checkboxes[0];

        fireEvent.click(selectAll);

        expect(screen.getByText('4 selected')).toBeInTheDocument();
    });

    it('clear button deselects all', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // Select all
        const checkboxes = screen.getAllByRole('checkbox');
        fireEvent.click(checkboxes[0]);
        expect(screen.getByText('4 selected')).toBeInTheDocument();

        // Click clear
        fireEvent.click(screen.getByText('Clear'));
        expect(screen.queryByText('4 selected')).not.toBeInTheDocument();
    });

    it('bulk action bar only appears when items are selected', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        // No selection initially
        expect(screen.queryByText(/selected/)).not.toBeInTheDocument();

        // Select one resource
        const checkboxes = screen.getAllByRole('checkbox');
        fireEvent.click(checkboxes[1]);

        expect(screen.getByText('1 selected')).toBeInTheDocument();
    });

    it('renders interaction badges for resources', () => {
        const resources = [
            makeResource({ id: 1, uuid: 'c-1', name: 'created-app', interaction: 'created', url: '/applications/c-1' }),
            makeResource({ id: 2, uuid: 'm-1', name: 'modified-app', interaction: 'modified', url: '/applications/m-1' }),
        ];

        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={resources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getByText('created')).toBeInTheDocument();
        expect(screen.getByText('modified')).toBeInTheDocument();
    });

    it('renders sort buttons', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Type')).toBeInTheDocument();
        expect(screen.getByText('Status')).toBeInTheDocument();
        expect(screen.getByText('Project')).toBeInTheDocument();
        expect(screen.getByText('Actions')).toBeInTheDocument();
    });

    it('renders server name when present', () => {
        const resources = [
            makeResource({ id: 1, uuid: 's-1', name: 'app-with-server', server_name: 'prod-server-1', url: '/applications/s-1' }),
            makeResource({ id: 2, uuid: 's-2', name: 'app-no-server', server_name: null, url: '/applications/s-2' }),
        ];

        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={resources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.getByText('prod-server-1')).toBeInTheDocument();
    });

    it('shows "Show more" button when resources exceed page size', () => {
        // Create 25 resources (PAGE_SIZE is 20)
        const manyResources = Array.from({ length: 25 }, (_, i) =>
            makeResource({
                id: i + 1,
                uuid: `r-${i + 1}`,
                name: `resource-${i + 1}`,
                url: `/applications/r-${i + 1}`,
            })
        );

        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={manyResources}
                environments={defaultEnvironments}
            />
        );

        // Should show "Show more" with remaining count
        expect(screen.getByText(/Show more/)).toBeInTheDocument();
        expect(screen.getByText(/5 remaining/)).toBeInTheDocument();

        // First 20 visible, 21st not
        expect(screen.getByText('resource-1')).toBeInTheDocument();
        expect(screen.getByText('resource-20')).toBeInTheDocument();
    });

    it('clicking "Show more" loads next page', () => {
        const manyResources = Array.from({ length: 25 }, (_, i) =>
            makeResource({
                id: i + 1,
                uuid: `r-${i + 1}`,
                name: `resource-${i + 1}`,
                url: `/applications/r-${i + 1}`,
            })
        );

        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={manyResources}
                environments={defaultEnvironments}
            />
        );

        // Click show more
        fireEvent.click(screen.getByText(/Show more/));

        // Now all 25 should be visible
        expect(screen.getByText('resource-25')).toBeInTheDocument();
        // Show more should be gone (25 < 40)
        expect(screen.queryByText(/Show more/)).not.toBeInTheDocument();
    });

    it('does not show "Show more" when resources fit on one page', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        expect(screen.queryByText(/Show more/)).not.toBeInTheDocument();
    });

    it('filters by status when status filter is changed', () => {
        render(
            <ArchiveResourceManager
                archiveId={1}
                resources={defaultResources}
                environments={defaultEnvironments}
            />
        );

        const selects = screen.getAllByRole('combobox');
        const statusSelect = selects[1];

        fireEvent.change(statusSelect, { target: { value: 'stopped' } });

        expect(screen.getByText('api-service')).toBeInTheDocument();
        expect(screen.queryByText('my-frontend')).not.toBeInTheDocument();
        expect(screen.queryByText('main-db')).not.toBeInTheDocument();
        expect(screen.queryByText('cache')).not.toBeInTheDocument();
    });
});
