import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import AdminMetricsIndex from '../Index';

// Mock Inertia router
const { mockGet } = vi.hoisted(() => ({ mockGet: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    router: { get: mockGet },
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
}));

// Mock AdminLayout
vi.mock('@/layouts/AdminLayout', () => ({
    AdminLayout: ({ children, title }: any) => (
        <div data-testid="admin-layout" data-title={title}>{children}</div>
    ),
}));

const defaultMetrics = {
    totalResources: 42,
    activeResources: 15,
    totalDeployments: 100,
    successfulDeployments: 85,
    failedDeployments: 15,
    averageDeploymentTime: 120,
    deploymentsLast24h: 5,
    deploymentsLast7d: 30,
    successRate: 85.0,
};

describe('AdminMetricsIndex', () => {
    it('renders overview tab by default', () => {
        render(<AdminMetricsIndex metrics={defaultMetrics} />);

        expect(screen.getByText('System Metrics')).toBeInTheDocument();
        expect(screen.getByText('42')).toBeInTheDocument(); // totalResources
        expect(screen.getByText('85.0%')).toBeInTheDocument(); // successRate
    });

    it('renders tab buttons', () => {
        render(<AdminMetricsIndex metrics={defaultMetrics} />);

        expect(screen.getByText('Overview')).toBeInTheDocument();
        expect(screen.getByText('Resource Usage')).toBeInTheDocument();
        expect(screen.getByText('Team Performance')).toBeInTheDocument();
        expect(screen.getByText('Cost Analytics')).toBeInTheDocument();
    });

    it('switches tab on click', () => {
        render(<AdminMetricsIndex metrics={defaultMetrics} />);

        fireEvent.click(screen.getByText('Resource Usage'));

        expect(mockGet).toHaveBeenCalledWith(
            '/admin/metrics',
            expect.objectContaining({ tab: 'resource-usage' }),
            expect.anything()
        );
    });

    it('renders resource usage tab when active', () => {
        const resourceUsage = {
            servers: [
                {
                    server_id: 1,
                    server_name: 'Test Server',
                    server_ip: '1.2.3.4',
                    checks: [],
                    latest: { cpu: 45, memory: 60, disk: 30 },
                },
            ],
            period: '24h',
        };

        render(
            <AdminMetricsIndex
                metrics={defaultMetrics}
                activeTab="resource-usage"
                resourceUsage={resourceUsage}
            />
        );

        expect(screen.getByText('Test Server')).toBeInTheDocument();
        expect(screen.getByText('45%')).toBeInTheDocument();
    });

    it('renders team performance tab when active', () => {
        const teamPerformance = [
            {
                id: 1,
                name: 'Core Team',
                members_count: 5,
                servers_count: 3,
                projects_count: 8,
                applications: 12,
                databases: 4,
                deployments_30d: 50,
                success_rate: 92.5,
                quotas: { max_servers: null, max_applications: null, max_databases: null, max_projects: null },
            },
        ];

        render(
            <AdminMetricsIndex
                metrics={defaultMetrics}
                activeTab="team-performance"
                teamPerformance={teamPerformance}
            />
        );

        expect(screen.getByText('Core Team')).toBeInTheDocument();
        expect(screen.getByText('92.5%')).toBeInTheDocument();
    });

    it('renders cost analytics tab when active', () => {
        const costAnalytics = {
            globalStats: {
                totalRequests: 500,
                successfulRequests: 480,
                failedRequests: 20,
                totalTokens: 1500000,
                totalCostUsd: 12.50,
                avgResponseTimeMs: 850,
            },
            teamCosts: [],
            modelCosts: [],
            dailyCosts: [],
        };

        render(
            <AdminMetricsIndex
                metrics={defaultMetrics}
                activeTab="cost-analytics"
                costAnalytics={costAnalytics}
            />
        );

        expect(screen.getByText('$12.50')).toBeInTheDocument();
        expect(screen.getByText('500')).toBeInTheDocument();
    });

    it('shows deployment stats correctly', () => {
        render(<AdminMetricsIndex metrics={defaultMetrics} />);

        expect(screen.getByText('Successful')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });
});
