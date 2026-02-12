import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerMetricsIndex from '@/pages/Servers/Metrics/Index';
import type { Server } from '@/types';

// Mock the useSentinelMetrics hook
vi.mock('@/hooks/useSentinelMetrics', () => ({
    useSentinelMetrics: vi.fn(() => ({
        metrics: {
            cpu: {
                current: '45%',
                percentage: 45,
                trend: [30, 35, 40, 45, 50],
            },
            memory: {
                current: '8 GB',
                percentage: 60,
                trend: [55, 58, 60, 62, 60],
            },
            disk: {
                current: '120 GB',
                percentage: 75,
                trend: [70, 72, 73, 74, 75],
            },
        },
        isLoading: false,
        error: null,
        refetch: vi.fn(),
    })),
}));

const mockServer: Server = {
    id: 1,
    uuid: 'server-uuid-1',
    name: 'production-server',
    description: 'Main production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    is_reachable: true,
    is_usable: true,
    settings: {
        id: 1,
        server_id: 1,
        is_build_server: false,
        concurrent_builds: 2,
    },
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

describe('Server Metrics Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('Server Metrics')).toBeInTheDocument();
            expect(screen.getByText(/Real-time performance monitoring for production-server/)).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });
    });

    describe('time range selector', () => {
        it('should render all time range options', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('1h')).toBeInTheDocument();
            expect(screen.getByText('6h')).toBeInTheDocument();
            expect(screen.getByText('24h')).toBeInTheDocument();
            expect(screen.getByText('7d')).toBeInTheDocument();
        });

        it('should highlight default time range (1h)', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            const oneHourButton = screen.getByText('1h').closest('button');
            expect(oneHourButton).toHaveClass('bg-foreground');
        });

        it('should change time range when clicked', async () => {
            const { user } = render(<ServerMetricsIndex server={mockServer} />);

            const sixHourButton = screen.getByText('6h').closest('button');
            if (sixHourButton) {
                await user.click(sixHourButton);
                expect(sixHourButton).toHaveClass('bg-foreground');
            }
        });
    });

    describe('auto-refresh controls', () => {
        it('should render auto-refresh button', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            const autoRefreshButton = screen.getByRole('button', { name: /auto-refresh/i });
            expect(autoRefreshButton).toBeInTheDocument();
        });

        it('should render refresh button', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            const refreshButton = screen.getByRole('button', { name: /^refresh$/i });
            expect(refreshButton).toBeInTheDocument();
        });

        it('should toggle auto-refresh when clicked', async () => {
            const { user } = render(<ServerMetricsIndex server={mockServer} />);

            const autoRefreshButton = screen.getByRole('button', { name: /auto-refresh/i });
            await user.click(autoRefreshButton);

            // Auto-refresh should toggle off (no longer have primary background)
            expect(autoRefreshButton).not.toHaveClass('bg-primary/10');
        });
    });

    describe('metric cards', () => {
        it('should render CPU usage metric card', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('CPU Usage')).toBeInTheDocument();
            const percentages = screen.getAllByText('45%');
            expect(percentages.length).toBeGreaterThan(0);
        });

        it('should render memory usage metric card', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('Memory Usage')).toBeInTheDocument();
            expect(screen.getByText('8 GB')).toBeInTheDocument();
            expect(screen.getByText('60%')).toBeInTheDocument();
        });

        it('should render disk usage metric card', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('Disk Usage')).toBeInTheDocument();
            expect(screen.getByText('120 GB')).toBeInTheDocument();
            expect(screen.getByText('75%')).toBeInTheDocument();
        });

        it('should display all metric percentages', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            // CPU 45%, Memory 60%, Disk 75%
            const percentageBadges = screen.getAllByText(/\d+%/);
            expect(percentageBadges.length).toBeGreaterThan(0);
        });
    });

    describe('detailed charts', () => {
        it('should render CPU history chart section', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('CPU History')).toBeInTheDocument();
        });

        it('should render memory history chart section', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('Memory History')).toBeInTheDocument();
        });
    });

    describe('about metrics section', () => {
        it('should render about metrics section', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('About Metrics')).toBeInTheDocument();
        });

        it('should display metrics description', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText(/Server metrics are collected in real-time/)).toBeInTheDocument();
        });

        it('should display metric explanations', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText(/CPU usage shows the current processor utilization/)).toBeInTheDocument();
            expect(screen.getByText(/Memory usage displays RAM consumption/)).toBeInTheDocument();
            expect(screen.getByText(/Disk usage shows total disk space/)).toBeInTheDocument();
        });
    });

    describe('data states', () => {
        it('should render Server Metrics title in all states', () => {
            render(<ServerMetricsIndex server={mockServer} />);
            expect(screen.getByText('Server Metrics')).toBeInTheDocument();
        });

        it('should render all metric sections', () => {
            render(<ServerMetricsIndex server={mockServer} />);

            expect(screen.getByText('CPU Usage')).toBeInTheDocument();
            expect(screen.getByText('Memory Usage')).toBeInTheDocument();
            expect(screen.getByText('Disk Usage')).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerMetricsIndex server={differentServer} />);

            expect(screen.getByText(/Real-time performance monitoring for staging-server/)).toBeInTheDocument();
        });

        it('should render with minimal server data', () => {
            const minimalServer: Server = {
                id: 2,
                uuid: 'minimal-uuid',
                name: 'minimal-server',
                description: null,
                ip: '127.0.0.1',
                port: 22,
                user: 'root',
                is_reachable: true,
                is_usable: true,
                settings: null,
                created_at: '2024-01-01T00:00:00Z',
                updated_at: '2024-01-01T00:00:00Z',
            };

            render(<ServerMetricsIndex server={minimalServer} />);

            expect(screen.getByText('Server Metrics')).toBeInTheDocument();
        });

        it('should render with different server names', () => {
            const customServer = {
                ...mockServer,
                name: 'custom-server',
            };

            render(<ServerMetricsIndex server={customServer} />);

            expect(screen.getByText(/Real-time performance monitoring for custom-server/)).toBeInTheDocument();
        });
    });
});
