import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ObservabilityTraces from '../Traces';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { visit: vi.fn(), post: vi.fn() },
    usePage: () => ({
        props: {
            auth: { name: 'Test User', email: 'test@test.com' },
            notifications: { unreadCount: 0, recent: [] },
        },
    }),
}));

vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children, className }: any) => <h3 className={className}>{children}</h3>,
    CardContent: ({ children }: any) => <div>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
}));

vi.mock('@/lib/utils', () => ({
    formatRelativeTime: () => '5 minutes ago',
}));

vi.mock('@/components/features/DeploymentGraph', () => ({
    DeploymentGraph: ({ stages, compact }: any) => (
        <div data-testid="deployment-graph" data-compact={compact}>
            {stages.map((s: any) => (
                <span key={s.id} data-stage-id={s.id}>{s.name}</span>
            ))}
        </div>
    ),
}));

const sampleOperations = [
    {
        id: 'deploy-1',
        type: 'deployment' as const,
        name: 'Deployed my-app',
        status: 'success' as const,
        duration: 45,
        timestamp: '2026-02-23T12:00:00Z',
        resource: { type: 'application', name: 'my-app', id: 'uuid-1' },
        user: { name: 'Admin', email: 'admin@saturn.ac' },
        commit: 'abc1234',
        triggeredBy: 'manual',
        stages: [
            { id: 'prepare', name: 'Prepare', status: 'completed', duration: 3 },
            { id: 'clone', name: 'Clone', status: 'completed', duration: 5 },
            { id: 'build', name: 'Build', status: 'completed', duration: 25 },
            { id: 'deploy', name: 'Deploy', status: 'completed', duration: 12 },
        ],
        changes: null,
    },
    {
        id: 'deploy-2',
        type: 'deployment' as const,
        name: 'Deployed api-server',
        status: 'error' as const,
        duration: 120,
        timestamp: '2026-02-23T11:00:00Z',
        resource: { type: 'application', name: 'api-server', id: 'uuid-2' },
        user: { name: 'Developer', email: 'dev@saturn.ac' },
        commit: 'def5678',
        triggeredBy: 'webhook',
        stages: [
            { id: 'prepare', name: 'Prepare', status: 'completed', duration: 2 },
            { id: 'clone', name: 'Clone', status: 'completed', duration: 4 },
            { id: 'build', name: 'Build', status: 'failed', duration: 114 },
        ],
        changes: null,
    },
    {
        id: 'activity-10',
        type: 'config_change' as const,
        name: 'Updated my-app',
        status: 'success' as const,
        duration: null,
        timestamp: '2026-02-23T10:00:00Z',
        resource: { type: 'application', name: 'my-app', id: 'uuid-1' },
        user: { name: 'Admin', email: 'admin@saturn.ac' },
        commit: null,
        triggeredBy: null,
        stages: null,
        changes: {
            old: { ports_exposes: '3000' },
            attributes: { ports_exposes: '3000,8080' },
        },
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

describe('ObservabilityTraces (Operations)', () => {
    it('renders page title and description', () => {
        render(<ObservabilityTraces />);
        expect(screen.getByText('Operations')).toBeInTheDocument();
        expect(screen.getByText('Deployment history and resource changes')).toBeInTheDocument();
    });

    it('renders operation list when operations provided', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('Deployed my-app')).toBeInTheDocument();
        expect(screen.getByText('Deployed api-server')).toBeInTheDocument();
        expect(screen.getByText('Updated my-app')).toBeInTheDocument();
    });

    it('renders status badges for each operation', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        // Badge text includes "Success" in list items + dropdown option
        const successBadges = screen.getAllByText('Success');
        const errorBadges = screen.getAllByText('Error');
        expect(successBadges.length).toBe(3); // deploy-1 badge + activity-10 badge + dropdown option
        expect(errorBadges.length).toBe(2); // deploy-2 badge + dropdown option
    });

    it('shows duration for deployments', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('45s')).toBeInTheDocument();
        expect(screen.getByText('2m')).toBeInTheDocument();
    });

    it('shows commit hash for deployments', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('abc1234')).toBeInTheDocument();
        expect(screen.getByText('def5678')).toBeInTheDocument();
    });

    it('shows user name for each operation', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getAllByText('Admin').length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('Developer')).toBeInTheDocument();
    });

    it('shows resource name chips', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const myAppChips = screen.getAllByText('my-app');
        expect(myAppChips.length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('api-server')).toBeInTheDocument();
    });

    it('shows "No operation selected" initially', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('No operation selected')).toBeInTheDocument();
        expect(screen.getByText('Select an operation from the list to view details')).toBeInTheDocument();
    });

    it('shows deployment detail with DeploymentGraph when deployment clicked', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        fireEvent.click(screen.getByText('Deployed my-app'));
        expect(screen.getByTestId('deployment-graph')).toBeInTheDocument();
        expect(screen.getByText('Deployment Stages')).toBeInTheDocument();
        expect(screen.getByText('Duration')).toBeInTheDocument();
        expect(screen.getByText('manual')).toBeInTheDocument();
    });

    it('shows config change detail with diff when config change clicked', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        fireEvent.click(screen.getByText('Updated my-app'));
        expect(screen.getByText('Config Change')).toBeInTheDocument();
        expect(screen.getByText('Changes')).toBeInTheDocument();
        expect(screen.getByText('ports_exposes')).toBeInTheDocument();
        expect(screen.getByText('3000')).toBeInTheDocument();
        expect(screen.getByText('3000,8080')).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByPlaceholderText('Search operations...')).toBeInTheDocument();
    });

    it('filters operations by search query', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const searchInput = screen.getByPlaceholderText('Search operations...');
        fireEvent.change(searchInput, { target: { value: 'api-server' } });
        expect(screen.getByText('Deployed api-server')).toBeInTheDocument();
        expect(screen.queryByText('Deployed my-app')).not.toBeInTheDocument();
    });

    it('renders type filter dropdown', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('All Types')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
        expect(screen.getByText('Config Changes')).toBeInTheDocument();
    });

    it('filters operations by type - deployments only', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const typeSelect = screen.getByDisplayValue('All Types');
        fireEvent.change(typeSelect, { target: { value: 'deployment' } });
        expect(screen.getByText('Deployed my-app')).toBeInTheDocument();
        expect(screen.getByText('Deployed api-server')).toBeInTheDocument();
        expect(screen.queryByText('Updated my-app')).not.toBeInTheDocument();
    });

    it('filters operations by type - config changes only', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const typeSelect = screen.getByDisplayValue('All Types');
        fireEvent.change(typeSelect, { target: { value: 'config_change' } });
        expect(screen.getByText('Updated my-app')).toBeInTheDocument();
        expect(screen.queryByText('Deployed my-app')).not.toBeInTheDocument();
    });

    it('renders status filter dropdown', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('All Statuses')).toBeInTheDocument();
    });

    it('filters operations by status - error only', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const statusSelect = screen.getByDisplayValue('All Statuses');
        fireEvent.change(statusSelect, { target: { value: 'error' } });
        expect(screen.getByText('Deployed api-server')).toBeInTheDocument();
        expect(screen.queryByText('Deployed my-app')).not.toBeInTheDocument();
    });

    it('renders empty state when no operations', () => {
        render(<ObservabilityTraces operations={[]} />);
        expect(screen.getByText('Recent Operations')).toBeInTheDocument();
        expect(screen.getByText('No operations found')).toBeInTheDocument();
    });

    it('renders empty state when no operations provided (undefined)', () => {
        render(<ObservabilityTraces />);
        expect(screen.getByText('Recent Operations')).toBeInTheDocument();
    });

    it('shows stages in deployment graph', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        fireEvent.click(screen.getByText('Deployed my-app'));
        const graph = screen.getByTestId('deployment-graph');
        expect(graph).toBeInTheDocument();
        expect(screen.getByText('Prepare')).toBeInTheDocument();
        expect(screen.getByText('Clone')).toBeInTheDocument();
        expect(screen.getByText('Build')).toBeInTheDocument();
        expect(screen.getByText('Deploy')).toBeInTheDocument();
    });

    it('highlights selected operation', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        const firstOp = screen.getByText('Deployed my-app').closest('[class*="cursor-pointer"]');
        expect(firstOp).not.toBeNull();
        fireEvent.click(firstOp!);
        // After click, the border-primary class should be applied
        expect(firstOp?.className).toContain('border-primary');
    });

    it('shows operation count in header', () => {
        render(<ObservabilityTraces operations={sampleOperations} />);
        expect(screen.getByText('(3)')).toBeInTheDocument();
    });
});
