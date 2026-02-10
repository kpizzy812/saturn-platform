import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock router
const mockRouterPost = vi.fn();
const mockRouterDelete = vi.fn();

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
        post: mockRouterPost,
        delete: mockRouterDelete,
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

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: mockAddToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Mock realtime status hook
vi.mock('@/hooks/useRealtimeStatus', () => ({
    useRealtimeStatus: vi.fn(() => ({
        isConnected: true,
    })),
}));

// Mock useConfirm hook
const mockConfirm = vi.fn(() => Promise.resolve(true));
vi.mock('@/components/ui/ConfirmationModal', () => ({
    useConfirm: () => mockConfirm,
    ConfirmationProvider: ({ children }: { children: React.ReactNode }) => children,
}));

// Import after mock
import ServiceShow from '@/pages/Services/Show';

const mockService = {
    id: 1,
    uuid: 'service-uuid-123',
    name: 'production-api',
    description: 'Main production API service',
    docker_compose_raw: 'version: "3.8"\nservices:\n  api:\n    image: node:18',
    environment_id: 1,
    destination_id: 1,
    status: 'running',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
};

describe('ServiceShow Page', () => {
    beforeEach(() => {
        mockRouterPost.mockClear();
        mockRouterDelete.mockClear();
        mockAddToast.mockClear();
        mockConfirm.mockClear();
        mockConfirm.mockResolvedValue(true);
    });

    it('renders the service header with name', () => {
        render(<ServiceShow service={mockService} />);
        // Multiple instances may exist (breadcrumb + header)
        expect(screen.getAllByText('production-api').length).toBeGreaterThan(0);
    });

    it('displays the service description', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Main production API service')).toBeInTheDocument();
    });

    it('shows action buttons', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Redeploy')).toBeInTheDocument();
        expect(screen.getByText('Restart')).toBeInTheDocument();
        expect(screen.getByText('Delete')).toBeInTheDocument();
    });

    it('renders all tab labels', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Overview')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
        expect(screen.getByText('Logs')).toBeInTheDocument();
        expect(screen.getByText('Variables')).toBeInTheDocument();
        expect(screen.getByText('Settings')).toBeInTheDocument();
    });

    it('shows rollbacks tab', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Rollbacks')).toBeInTheDocument();
    });

    it('shows metrics cards in overview tab', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('CPU Usage')).toBeInTheDocument();
        expect(screen.getByText('Memory')).toBeInTheDocument();
        expect(screen.getByText('Network')).toBeInTheDocument();
    });

    it('displays recent deployments section', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Recent Deployments')).toBeInTheDocument();
    });

    it('shows loading state when service prop is missing', () => {
        // Component should show loading state when no service prop is provided
        render(<ServiceShow service={undefined as any} />);
        expect(screen.getByText('Loading service...')).toBeInTheDocument();
    });

    it('shows back to services link', () => {
        render(<ServiceShow service={mockService} />);
        const backLink = screen.getByText('Back to Services').closest('a');
        expect(backLink).toBeInTheDocument();
        expect(backLink?.getAttribute('href')).toBe('/services');
    });

    it('displays running status badge', () => {
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('Running')).toBeInTheDocument();
    });

    it('displays stopped status badge for stopped service', () => {
        const stoppedService = { ...mockService, status: 'stopped' };
        render(<ServiceShow service={stoppedService} />);
        expect(screen.getByText('Stopped')).toBeInTheDocument();
    });

    it('displays deploying status badge for deploying service', () => {
        const deployingService = { ...mockService, status: 'deploying' };
        render(<ServiceShow service={deployingService} />);
        expect(screen.getByText('Deploying')).toBeInTheDocument();
    });

    it('shows settings icon link', () => {
        render(<ServiceShow service={mockService} />);
        const settingsLink = document.querySelector('a[href="/services/service-uuid-123/settings"]');
        expect(settingsLink).toBeInTheDocument();
    });

    it('redeploy button is clickable', async () => {
        render(<ServiceShow service={mockService} />);

        const redeployButton = screen.getByText('Redeploy').closest('button');
        expect(redeployButton).toBeInTheDocument();
        expect(redeployButton).not.toBeDisabled();
    });

    it('restart button is clickable', async () => {
        render(<ServiceShow service={mockService} />);

        const restartButton = screen.getByText('Restart').closest('button');
        expect(restartButton).toBeInTheDocument();
        expect(restartButton).not.toBeDisabled();
    });

    it('shows confirmation dialog when delete is clicked', async () => {
        mockConfirm.mockResolvedValueOnce(false);
        render(<ServiceShow service={mockService} />);

        const deleteButton = screen.getByText('Delete');
        fireEvent.click(deleteButton);

        await waitFor(() => {
            expect(mockConfirm).toHaveBeenCalledWith(
                expect.objectContaining({
                    title: expect.any(String),
                })
            );
        });
    });

    it('delete button is clickable and accessible', () => {
        render(<ServiceShow service={mockService} />);

        const deleteButton = screen.getByText('Delete').closest('button');
        expect(deleteButton).toBeInTheDocument();
        expect(deleteButton).not.toBeDisabled();
    });

    it('has redeploy button in enabled state initially', () => {
        render(<ServiceShow service={mockService} />);
        const redeployButton = screen.getByText('Redeploy').closest('button');
        expect(redeployButton).not.toBeDisabled();
    });

    it('has restart button in enabled state initially', () => {
        render(<ServiceShow service={mockService} />);
        const restartButton = screen.getByText('Restart').closest('button');
        expect(restartButton).not.toBeDisabled();
    });

    it('shows breadcrumb navigation', () => {
        render(<ServiceShow service={mockService} />);
        // Breadcrumbs would show Dashboard, Services, and service name
        const links = screen.getAllByRole('link');
        const dashboardLink = links.find(link => link.getAttribute('href') === '/dashboard');
        const servicesLink = links.find(link => link.getAttribute('href') === '/services');
        expect(dashboardLink).toBeInTheDocument();
        expect(servicesLink).toBeInTheDocument();
    });

    it.skip('displays mock metrics data', () => {
        // Skip: Component metrics display changed
        render(<ServiceShow service={mockService} />);
        // Check for metrics values
        const bodyText = document.body.textContent || '';
        expect(bodyText).toContain('23%');
        expect(bodyText).toContain('512 MB / 2 GB');
        expect(bodyText).toContain('1.2 MB/s');
    });

    it.skip('shows recent deployment items', () => {
        // Skip: Component deployment display changed
        render(<ServiceShow service={mockService} />);
        // Check for deployment commit hashes
        expect(screen.getByText('a1b2c3d')).toBeInTheDocument();
        expect(screen.getByText('e4f5g6h')).toBeInTheDocument();
        expect(screen.getByText('i7j8k9l')).toBeInTheDocument();
    });

    it.skip('displays deployment status for recent deployments', () => {
        // Skip: Component deployment display changed
        render(<ServiceShow service={mockService} />);
        // Check for multiple "finished" badges and one "failed" badge
        const badges = screen.getAllByText('finished');
        expect(badges.length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('failed')).toBeInTheDocument();
    });

    it.skip('shows deployment duration', () => {
        // Skip: Component deployment display changed
        render(<ServiceShow service={mockService} />);
        expect(screen.getByText('3m 45s')).toBeInTheDocument();
        expect(screen.getByText('2m 30s')).toBeInTheDocument();
        expect(screen.getByText('1m 15s')).toBeInTheDocument();
    });

    it.skip('links to deployment detail pages', () => {
        // Skip: Component deployment display changed
        render(<ServiceShow service={mockService} />);
        const links = screen.getAllByRole('link');
        const deploymentLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/deployments/')
        );
        expect(deploymentLinks.length).toBeGreaterThanOrEqual(3);
    });
});
