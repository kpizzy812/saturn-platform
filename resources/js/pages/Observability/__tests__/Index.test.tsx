import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import ObservabilityIndex from '../Index';

// Mock Inertia
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

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, variant, size }: any) => (
        <button onClick={onClick} data-variant={variant} data-size={size}>{children}</button>
    ),
}));

// Mock Chart/Sparkline
vi.mock('@/components/ui/Chart', () => ({
    Sparkline: ({ data, color }: any) => (
        <div data-testid="sparkline" data-color={color} data-points={data?.length ?? 0}>sparkline</div>
    ),
}));

describe('ObservabilityIndex', () => {
    it('renders empty state when no metrics provided', () => {
        render(<ObservabilityIndex />);
        expect(screen.getByText('No metrics available')).toBeInTheDocument();
    });

    it('renders metric cards with sparkline when data present', () => {
        const metrics = [
            { label: 'Avg CPU', value: '45.2%', change: '+2.1%', trend: 'up' as const, data: [10, 20, 30, 40, 50] },
            { label: 'Avg Memory', value: '62.0%', change: '-1.5%', trend: 'down' as const, data: [60, 55, 50, 45, 40] },
            { label: 'Avg Disk', value: '30.0%', change: '', trend: 'neutral' as const, data: [30, 30, 30, 30, 30] },
            { label: 'Active Deployments', value: '2', change: '', trend: 'neutral' as const, data: [] },
        ];

        render(<ObservabilityIndex metricsOverview={metrics} />);

        expect(screen.getByText('Avg CPU')).toBeInTheDocument();
        expect(screen.getByText('45.2%')).toBeInTheDocument();
        expect(screen.getByText('+2.1%')).toBeInTheDocument();

        expect(screen.getByText('Avg Memory')).toBeInTheDocument();
        expect(screen.getByText('Avg Disk')).toBeInTheDocument();

        // Sparklines should be rendered for metrics with data
        const sparklines = screen.getAllByTestId('sparkline');
        expect(sparklines.length).toBe(3); // 3 metrics have data, Active Deployments has no data
    });

    it('renders deployment stats section', () => {
        const deploymentStats = {
            today: 5,
            week: 23,
            successRate: 95.5,
            avgDuration: 185,
            success: 22,
            failed: 1,
        };

        render(<ObservabilityIndex deploymentStats={deploymentStats} />);

        expect(screen.getByText('Deployment Stats')).toBeInTheDocument();
        expect(screen.getByText('Success Rate')).toBeInTheDocument();
        expect(screen.getByText('95.5%')).toBeInTheDocument();
        expect(screen.getByText('Avg Duration')).toBeInTheDocument();
        expect(screen.getByText('3m 5s')).toBeInTheDocument();
        expect(screen.getByText('22')).toBeInTheDocument();
        expect(screen.getByText('1')).toBeInTheDocument();
    });

    it('does not render deployment stats when not provided', () => {
        render(<ObservabilityIndex />);
        expect(screen.queryByText('Deployment Stats')).not.toBeInTheDocument();
    });

    it('renders server health cards', () => {
        const services = [
            { id: '1', name: 'Production Server', status: 'healthy' as const, uptime: 99.8, responseTime: 42, errorRate: 0.2 },
            { id: '2', name: 'Staging Server', status: 'degraded' as const, uptime: 95.0, responseTime: 120, errorRate: 5.0 },
        ];

        render(<ObservabilityIndex services={services} />);

        expect(screen.getByText('Production Server')).toBeInTheDocument();
        expect(screen.getByText('healthy')).toBeInTheDocument();
        expect(screen.getByText('99.8%')).toBeInTheDocument();
        expect(screen.getByText('42ms')).toBeInTheDocument();

        expect(screen.getByText('Staging Server')).toBeInTheDocument();
        expect(screen.getByText('degraded')).toBeInTheDocument();
    });

    it('renders empty server state', () => {
        render(<ObservabilityIndex services={[]} />);
        expect(screen.getByText('No servers monitored')).toBeInTheDocument();
    });

    it('renders recent alerts', () => {
        const alerts = [
            { id: '1', severity: 'critical' as const, service: 'web-app', message: 'Deployment failed for web-app', time: '5 minutes ago' },
            { id: '2', severity: 'warning' as const, service: 'CPU Alert', message: 'Alert triggered: CPU Alert', time: '10 minutes ago' },
        ];

        render(<ObservabilityIndex recentAlerts={alerts} />);

        expect(screen.getByText('Deployment failed for web-app')).toBeInTheDocument();
        expect(screen.getByText('Alert triggered: CPU Alert')).toBeInTheDocument();
        expect(screen.getByText('critical')).toBeInTheDocument();
        expect(screen.getByText('warning')).toBeInTheDocument();
    });

    it('renders no alerts message when empty', () => {
        render(<ObservabilityIndex recentAlerts={[]} />);
        expect(screen.getByText('No recent alerts')).toBeInTheDocument();
    });

    it('renders quick access links', () => {
        render(<ObservabilityIndex />);
        expect(screen.getByText('Quick Access')).toBeInTheDocument();
        expect(screen.getByText('Logs')).toBeInTheDocument();
        expect(screen.getByText('Traces')).toBeInTheDocument();
        expect(screen.getByText('Metrics')).toBeInTheDocument();
    });

    it('formats duration correctly for deployment stats', () => {
        // Test seconds only
        const statsShort = { today: 1, week: 1, successRate: 100, avgDuration: 45, success: 1, failed: 0 };
        const { unmount } = render(<ObservabilityIndex deploymentStats={statsShort} />);
        expect(screen.getByText('45s')).toBeInTheDocument();
        unmount();

        // Test minutes only (exact)
        const statsMinutes = { today: 1, week: 1, successRate: 100, avgDuration: 120, success: 1, failed: 0 };
        render(<ObservabilityIndex deploymentStats={statsMinutes} />);
        expect(screen.getByText('2m')).toBeInTheDocument();
    });
});
